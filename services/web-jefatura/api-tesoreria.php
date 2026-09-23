<?php
/**
 * API JSON de Tesorería.
 * Todas las peticiones necesitan sesión iniciada.
 * Las que modifican datos (POST) necesitan la cabecera X-CSRF-Token.
 *
 * GET  ?action=state&anio=2026&modo=natural|curso
 *                                     -> partidas, movimientos del periodo, balance, periodos
 * GET  ?action=export                 -> descarga tesoreria.json
 * POST ?action=movimiento.add         { fecha, tipo, partida, concepto, cantidad, precio }
 * POST ?action=movimiento.update      { id, fecha, tipo, partida, concepto, cantidad, precio }
 * POST ?action=movimiento.delete      { id }
 * POST ?action=comentario.save        { anio, modo, texto }
 * POST ?action=import                 (cuerpo = JSON completo a importar)
 *
 * Los POST aceptan también "anio" y "modo" para devolver el estado de ese periodo
 * (año natural, o curso escolar sep–ago que empieza en "anio").
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tesoreria.php';

exigir_login_api();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'export' && $method === 'GET') {
    $data = cargar_tesoreria();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="tesoreria-' . date('Ymd-His') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function responder(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function error(string $msg, int $code = 400): void
{
    responder(['ok' => false, 'error' => $msg], $code);
}

function anio_valido($v, string $modo = 'natural'): int
{
    $a = (int) $v;
    return ($a >= 2000 && $a <= 2100) ? $a : periodo_actual($modo);
}

function estado_tesoreria(int $anio, string $modo): array
{
    $data = cargar_tesoreria();
    return [
        'ok'          => true,
        'anio'        => $anio,
        'modo'        => $modo,
        'modos'       => MODOS_PERIODO,
        'anios'       => array_map(
            fn($a) => ['valor' => $a, 'etiqueta' => periodo_etiqueta($modo, $a)],
            tesoreria_anios($data, $modo)
        ),
        'partidas'    => PARTIDAS,
        'movimientos' => movimientos_periodo($data, $modo, $anio),
        'balance'     => calcular_balance($data, $anio, $modo),
        'comentario'  => (string) ($data['comentarios'][periodo_clave($modo, $anio)] ?? ''),
    ];
}

/** Acepta 12,5 / 12.5 / "1.234,56". Devuelve null si no es un número. */
function leer_numero($v): ?float
{
    if (is_int($v) || is_float($v)) return (float) $v;
    $s = str_replace([' ', '€'], '', trim((string) $v));
    if ($s === '') return null;
    if (strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

/** Valida y normaliza los campos de un movimiento. Lanza error() si algo falla. */
function validar_movimiento(array $b): array
{
    $tipo = ($b['tipo'] ?? '') === 'gasto' ? 'gasto' : (($b['tipo'] ?? '') === 'ingreso' ? 'ingreso' : '');
    if ($tipo === '') error('Indica si es un ingreso o un gasto');

    $partida = (string) ($b['partida'] ?? '');
    $info = partida_info($partida);
    if (!$info || $info[0] !== $tipo) error('Elige una partida válida para ' . ($tipo === 'ingreso' ? 'ingresos' : 'gastos'));

    $fecha = limpiar_texto($b['fecha'] ?? '', 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) error('La fecha no es válida');

    $concepto = limpiar_texto($b['concepto'] ?? '', 200);
    if ($concepto === '') error('El concepto no puede estar vacío');

    $cantidad = leer_numero($b['cantidad'] ?? 1);
    if ($cantidad === null || $cantidad <= 0) error('La cantidad debe ser un número mayor que 0');

    $precio = leer_numero($b['precio'] ?? null);
    if ($precio === null || $precio == 0) error('El precio debe ser un número distinto de 0');

    return [
        'fecha'    => $fecha,
        'tipo'     => $tipo,
        'partida'  => $partida,
        'concepto' => $concepto,
        'cantidad' => round($cantidad, 3),
        'precio'   => euros($precio),
        'importe'  => euros($cantidad * $precio),
    ];
}

if ($method === 'GET') {
    if ($action === 'state') {
        $modo = modo_valido($_GET['modo'] ?? 'natural');
        responder(estado_tesoreria(anio_valido($_GET['anio'] ?? 0, $modo), $modo));
    }
    error('Acción GET no reconocida: ' . $action, 404);
}

if ($method !== 'POST') {
    error('Método no permitido', 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
if (!csrf_valido($token)) {
    error('Token de seguridad no válido. Recarga la página.', 403);
}

$rawBody = file_get_contents('php://input');
$body = json_decode((string) $rawBody, true);
if (!is_array($body)) {
    error('No se pudieron leer los datos enviados (JSON no válido).');
}
$modo = modo_valido($body['modo'] ?? ($_GET['modo'] ?? 'natural'));

try {
    switch ($action) {

        case 'movimiento.add': {
            $mov = validar_movimiento($body);
            modificar_tesoreria(function (array $d) use ($mov) {
                $d['movimientos'][] = ['id' => nuevo_id('m', $d['movimientos'])] + $mov + ['creado' => date('c')];
                return $d;
            });
            responder(estado_tesoreria(periodo_de_fecha($modo, $mov['fecha']), $modo));
        }

        case 'movimiento.update': {
            $id = (string) ($body['id'] ?? '');
            $mov = validar_movimiento($body);
            modificar_tesoreria(function (array $d) use ($id, $mov) {
                foreach ($d['movimientos'] as &$m) {
                    if ($m['id'] === $id) {
                        $m = array_merge($m, $mov, ['modificado' => date('c')]);
                        return $d;
                    }
                }
                throw new RuntimeException('Movimiento no encontrado');
            });
            responder(estado_tesoreria(anio_valido($body['anio'] ?? 0, $modo), $modo));
        }

        case 'movimiento.delete': {
            $id = (string) ($body['id'] ?? '');
            modificar_tesoreria(function (array $d) use ($id) {
                $d['movimientos'] = array_values(array_filter($d['movimientos'], fn($m) => $m['id'] !== $id));
                return $d;
            });
            responder(estado_tesoreria(anio_valido($body['anio'] ?? 0, $modo), $modo));
        }

        case 'comentario.save': {
            $clave = periodo_clave($modo, anio_valido($body['anio'] ?? 0, $modo));
            $texto = trim((string) ($body['texto'] ?? ''));
            $texto = function_exists('mb_substr') ? mb_substr($texto, 0, 5000) : substr($texto, 0, 5000);
            modificar_tesoreria(function (array $d) use ($clave, $texto) {
                if ($texto === '') unset($d['comentarios'][$clave]);
                else $d['comentarios'][$clave] = $texto;
                return $d;
            });
            responder(['ok' => true]);
        }

        case 'import': {
            if (!isset($body['movimientos']) || !is_array($body['movimientos'])) {
                error('El fichero no tiene el formato esperado (falta "movimientos").');
            }
            $nuevo = tesoreria_vacia();
            foreach ($body['movimientos'] as $m) {
                if (!is_array($m) || !isset($m['id'])) continue;
                $info = partida_info((string) ($m['partida'] ?? ''));
                if (!$info) continue;
                $cantidad = (float) ($m['cantidad'] ?? 1);
                $precio = (float) ($m['precio'] ?? 0);
                $nuevo['movimientos'][] = [
                    'id'       => (string) $m['id'],
                    'fecha'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['fecha'] ?? '')) ? $m['fecha'] : date('Y-m-d'),
                    'tipo'     => $info[0],
                    'partida'  => (string) $m['partida'],
                    'concepto' => limpiar_texto($m['concepto'] ?? '', 200),
                    'cantidad' => $cantidad,
                    'precio'   => euros($precio),
                    'importe'  => euros($cantidad * $precio),
                    'creado'   => (string) ($m['creado'] ?? date('c')),
                ];
            }
            foreach ((array) ($body['comentarios'] ?? []) as $a => $t) {
                if (is_string($t) && preg_match('/^(curso-)?\d{4}$/', (string) $a)) $nuevo['comentarios'][(string) $a] = $t;
            }
            modificar_tesoreria(fn() => $nuevo);
            responder(estado_tesoreria(anio_valido($_GET['anio'] ?? 0, $modo), $modo));
        }

        default:
            error('Acción no reconocida: ' . $action, 404);
    }
} catch (Throwable $e) {
    error('Error: ' . $e->getMessage(), 500);
}
