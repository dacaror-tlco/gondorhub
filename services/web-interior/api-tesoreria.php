<?php
/**
 * API JSON de Tesorería (movimientos).
 * Todas las peticiones necesitan sesión iniciada.
 * Las que modifican datos (POST) necesitan la cabecera X-CSRF-Token.
 *
 * GET  ?action=state&anio=2026&modo=natural|curso
 *                                     -> partidas, movimientos del periodo, balance, periodos, actividades
 * GET  ?action=actividad&id=a001      -> movimientos vinculados a una actividad
 * POST ?action=movimiento.add         { fecha, tipo, partida, concepto, cantidad, precio, actividad, proveedor, cif, factura }
 * POST ?action=movimiento.update      { id, …lo mismo… }
 * POST ?action=movimiento.delete      { id }
 * POST ?action=comentario.save        { anio, modo, texto }
 *
 * Los POST aceptan también "anio" y "modo" para devolver el estado de ese periodo.
 * (La copia de seguridad de todo está en api.php?action=export / import.)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tesoreria.php';
require_once __DIR__ . '/includes/zona.php';

exigir_login_api();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

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

/** Actividades para el desplegable (más recientes primero). */
function lista_actividades(): array
{
    $l = array_map(fn($a) => ['id' => $a['id'], 'nombre' => $a['nombre'], 'fecha' => $a['fecha']], cargar_zona()['actividades']);
    usort($l, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    return $l;
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
        'actividades' => lista_actividades(),
        'movimientos' => movimientos_periodo($data, $modo, $anio),
        'balance'     => calcular_balance($data, $anio, $modo),
        'comentario'  => (string) ($data['comentarios'][periodo_clave($modo, $anio)] ?? ''),
    ];
}

/** Valida y normaliza los campos de un movimiento. Lanza error() si algo falla. */
function validar_movimiento(array $b): array
{
    $tipo = in_array($b['tipo'] ?? '', ['ingreso', 'gasto'], true) ? $b['tipo'] : '';
    if ($tipo === '') error('Indica si es un ingreso o un gasto');

    $partida = (string) ($b['partida'] ?? '');
    $info = partida_info($partida);
    if (!$info || $info[0] !== $tipo) error('Elige una partida válida para ' . ($tipo === 'ingreso' ? 'ingresos' : 'gastos'));

    $fecha = limpiar_fecha($b['fecha'] ?? '');
    if (!$fecha) error('La fecha no es válida');

    $concepto = limpiar_texto($b['concepto'] ?? '', 200);
    if ($concepto === '') error('El concepto no puede estar vacío');

    $cantidad = leer_numero($b['cantidad'] ?? 1);
    if ($cantidad === null || $cantidad <= 0) error('La cantidad debe ser un número mayor que 0');

    $precio = leer_numero($b['precio'] ?? null);
    if ($precio === null || $precio == 0) error('El precio debe ser un número distinto de 0');

    $actividad = limpiar_id($b['actividad'] ?? '') ?: null;
    if ($actividad && !actividad(cargar_zona(), $actividad)) $actividad = null;

    return [
        'fecha'     => $fecha,
        'tipo'      => $tipo,
        'partida'   => $partida,
        'concepto'  => $concepto,
        'cantidad'  => round($cantidad, 3),
        'precio'    => euros($precio),
        'importe'   => euros($cantidad * $precio),
        'actividad' => $actividad,
        'proveedor' => limpiar_texto($b['proveedor'] ?? '', 120),
        'cif'       => limpiar_texto($b['cif'] ?? '', 20),
        'factura'   => limpiar_texto($b['factura'] ?? '', 40),
    ];
}

if ($method === 'GET') {
    if ($action === 'state') {
        $modo = modo_valido($_GET['modo'] ?? 'natural');
        responder(estado_tesoreria(anio_valido($_GET['anio'] ?? 0, $modo), $modo));
    }
    if ($action === 'actividad') {
        $id = limpiar_id($_GET['id'] ?? '');
        responder(['ok' => true, 'partidas' => PARTIDAS, 'movimientos' => movimientos_actividad(cargar_tesoreria(), $id)]);
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

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    error('No se pudieron leer los datos enviados (JSON no válido).');
}
$modo = modo_valido($body['modo'] ?? ($_GET['modo'] ?? 'natural'));

try {
    switch ($action) {

        case 'movimiento.add': {
            $mov = validar_movimiento($body);
            modificar_tesoreria(function (array $d) use ($mov) {
                $d['movimientos'][] = ['id' => nuevo_id('m', $d['movimientos'])] + $mov + ['origen' => null, 'creado' => date('c')];
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
            borrar_movimiento((string) ($body['id'] ?? ''));
            responder(estado_tesoreria(anio_valido($body['anio'] ?? 0, $modo), $modo));
        }

        case 'comentario.save': {
            $clave = periodo_clave($modo, anio_valido($body['anio'] ?? 0, $modo));
            $texto = limpiar_parrafo($body['texto'] ?? '');
            modificar_tesoreria(function (array $d) use ($clave, $texto) {
                if ($texto === '') unset($d['comentarios'][$clave]);
                else $d['comentarios'][$clave] = $texto;
                return $d;
            });
            responder(['ok' => true]);
        }

        default:
            error('Acción no reconocida: ' . $action, 404);
    }
} catch (Throwable $e) {
    error('Error: ' . $e->getMessage(), 500);
}
