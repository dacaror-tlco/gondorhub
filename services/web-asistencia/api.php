<?php
/**
 * API JSON de la web de asistencia.
 * Todas las peticiones necesitan sesión iniciada.
 * Las que modifican datos (POST) necesitan la cabecera X-CSRF-Token.
 *
 * GET  ?action=state                -> estado completo + resumen
 * GET  ?action=export               -> descarga data.json
 * POST ?action=persona.add          { nombre, categoria }
 * POST ?action=persona.update       { id, nombre?, categoria?, activo? }
 * POST ?action=persona.delete       { id }
 * POST ?action=actividad.add        { nombre, fecha, ambito, descripcion? }
 * POST ?action=actividad.update     { id, nombre?, fecha?, ambito?, descripcion? }
 * POST ?action=actividad.delete     { id }
 * POST ?action=actividad.asistencia { id, asistentes: [ids] }
 * POST ?action=import               (cuerpo = JSON completo a importar)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

exigir_login_api();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/* ---- Export: respuesta especial (descarga de fichero) ---- */
if ($action === 'export' && $method === 'GET') {
    $data = cargar_datos();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="asistencia-' . date('Ymd-His') . '.json"');
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

/** Estado que devolvemos tras cualquier operación. */
function estado_completo(): array
{
    $data = cargar_datos();
    return [
        'ok'          => true,
        'meta'        => $data['meta'] ?? ['curso' => APP_CURSO],
        'personas'    => $data['personas'],
        'actividades' => $data['actividades'],
        'resumen'     => calcular_resumen($data),
        'ambitos'     => AMBITOS,
    ];
}

/* ---- GET: solo lectura ---- */
if ($method === 'GET') {
    if ($action === 'state') {
        responder(estado_completo());
    }
    error('Acción GET no reconocida: ' . $action, 404);
}

/* ---- POST: comprobación CSRF ---- */
if ($method !== 'POST') {
    error('Método no permitido', 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
if (!csrf_valido($token)) {
    error('Token de seguridad no válido. Recarga la página.', 403);
}

/* Cuerpo JSON. Si llega algo pero no es JSON válido, avisamos claramente
   en lugar de continuar con datos vacíos. */
$rawBody = file_get_contents('php://input');
if ($rawBody !== '' && $rawBody !== false) {
    $body = json_decode($rawBody, true);
    if (!is_array($body)) {
        error('No se pudieron leer los datos enviados (JSON no válido: '
            . json_last_error_msg() . '). Comprueba la codificación (debe ser UTF-8).');
    }
} else {
    $body = $_POST;
}

$LIMITE_TEXTO = 200;

function limpiar_texto($v, int $max): string
{
    $v = trim((string) $v);
    $v = preg_replace('/\s+/u', ' ', $v);
    if (function_exists('mb_substr')) {
        $v = mb_substr($v, 0, $max);
    } else {
        $v = substr($v, 0, $max);
    }
    return $v;
}

try {
    switch ($action) {

        case 'persona.add': {
            $nombre = limpiar_texto($body['nombre'] ?? '', $LIMITE_TEXTO);
            $cat = ($body['categoria'] ?? '') === 'colaborador' ? 'colaborador' : 'educador';
            if ($nombre === '') error('El nombre no puede estar vacío');
            modificar_datos(function (array $d) use ($nombre, $cat) {
                $d['personas'][] = [
                    'id'        => nuevo_id('p', $d['personas']),
                    'nombre'    => $nombre,
                    'categoria' => $cat,
                    'activo'    => true,
                ];
                return $d;
            });
            responder(estado_completo());
        }

        case 'persona.update': {
            $id = (string) ($body['id'] ?? '');
            modificar_datos(function (array $d) use ($id, $body, $LIMITE_TEXTO) {
                $ok = false;
                foreach ($d['personas'] as &$p) {
                    if ($p['id'] !== $id) continue;
                    if (isset($body['nombre'])) {
                        $n = limpiar_texto($body['nombre'], $LIMITE_TEXTO);
                        if ($n !== '') $p['nombre'] = $n;
                    }
                    if (isset($body['categoria'])) {
                        $p['categoria'] = $body['categoria'] === 'colaborador' ? 'colaborador' : 'educador';
                    }
                    if (isset($body['activo'])) {
                        $p['activo'] = (bool) $body['activo'];
                    }
                    $ok = true;
                    break;
                }
                unset($p);
                if (!$ok) throw new RuntimeException('Persona no encontrada');
                return $d;
            });
            responder(estado_completo());
        }

        case 'persona.delete': {
            $id = (string) ($body['id'] ?? '');
            modificar_datos(function (array $d) use ($id) {
                $d['personas'] = array_values(array_filter(
                    $d['personas'],
                    fn($p) => $p['id'] !== $id
                ));
                // También la quitamos de la lista de asistentes de cada actividad.
                foreach ($d['actividades'] as &$a) {
                    $a['asistentes'] = array_values(array_filter(
                        $a['asistentes'] ?? [],
                        fn($x) => $x !== $id
                    ));
                }
                unset($a);
                return $d;
            });
            responder(estado_completo());
        }

        case 'actividad.add': {
            $nombre = limpiar_texto($body['nombre'] ?? '', $LIMITE_TEXTO);
            $fecha = limpiar_texto($body['fecha'] ?? '', 10);
            $ambito = array_key_exists($body['ambito'] ?? '', AMBITOS) ? $body['ambito'] : 'ambos';
            $desc = limpiar_texto($body['descripcion'] ?? '', 500);
            if ($nombre === '') error('El nombre de la actividad no puede estar vacío');
            if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = '';
            modificar_datos(function (array $d) use ($nombre, $fecha, $ambito, $desc) {
                $d['actividades'][] = [
                    'id'          => nuevo_id('a', $d['actividades']),
                    'nombre'      => $nombre,
                    'fecha'       => $fecha,
                    'ambito'      => $ambito,
                    'descripcion' => $desc,
                    'asistentes'  => [],
                    'creada'      => date('c'),
                ];
                return $d;
            });
            responder(estado_completo());
        }

        case 'actividad.update': {
            $id = (string) ($body['id'] ?? '');
            modificar_datos(function (array $d) use ($id, $body, $LIMITE_TEXTO) {
                $ok = false;
                foreach ($d['actividades'] as &$a) {
                    if ($a['id'] !== $id) continue;
                    if (isset($body['nombre'])) {
                        $n = limpiar_texto($body['nombre'], $LIMITE_TEXTO);
                        if ($n !== '') $a['nombre'] = $n;
                    }
                    if (isset($body['fecha'])) {
                        $f = limpiar_texto($body['fecha'], 10);
                        $a['fecha'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : '';
                    }
                    if (isset($body['ambito']) && array_key_exists($body['ambito'], AMBITOS)) {
                        $a['ambito'] = $body['ambito'];
                    }
                    if (isset($body['descripcion'])) {
                        $a['descripcion'] = limpiar_texto($body['descripcion'], 500);
                    }
                    $ok = true;
                    break;
                }
                unset($a);
                if (!$ok) throw new RuntimeException('Actividad no encontrada');
                return $d;
            });
            responder(estado_completo());
        }

        case 'actividad.delete': {
            $id = (string) ($body['id'] ?? '');
            modificar_datos(function (array $d) use ($id) {
                $d['actividades'] = array_values(array_filter(
                    $d['actividades'],
                    fn($a) => $a['id'] !== $id
                ));
                return $d;
            });
            responder(estado_completo());
        }

        case 'actividad.asistencia': {
            $id = (string) ($body['id'] ?? '');
            $asistentes = is_array($body['asistentes'] ?? null) ? $body['asistentes'] : [];
            $asistentes = array_values(array_unique(array_map('strval', $asistentes)));
            modificar_datos(function (array $d) use ($id, $asistentes) {
                $idsValidos = array_column($d['personas'], 'id');
                $asistentes = array_values(array_intersect($asistentes, $idsValidos));
                $ok = false;
                foreach ($d['actividades'] as &$a) {
                    if ($a['id'] === $id) {
                        $a['asistentes'] = $asistentes;
                        $ok = true;
                        break;
                    }
                }
                unset($a);
                if (!$ok) throw new RuntimeException('Actividad no encontrada');
                return $d;
            });
            responder(estado_completo());
        }

        case 'import': {
            if (!is_array($body) || !isset($body['personas']) || !isset($body['actividades'])) {
                error('El fichero no tiene el formato esperado (faltan "personas" o "actividades").');
            }
            modificar_datos(function (array $d) use ($body) {
                $nuevo = datos_estructura_vacia();
                $nuevo['meta'] = is_array($body['meta'] ?? null) ? $body['meta'] : $nuevo['meta'];
                foreach ($body['personas'] as $p) {
                    if (!isset($p['id'], $p['nombre'])) continue;
                    $nuevo['personas'][] = [
                        'id'        => (string) $p['id'],
                        'nombre'    => (string) $p['nombre'],
                        'categoria' => ($p['categoria'] ?? '') === 'colaborador' ? 'colaborador' : 'educador',
                        'activo'    => (bool) ($p['activo'] ?? true),
                    ];
                }
                foreach ($body['actividades'] as $a) {
                    if (!isset($a['id'], $a['nombre'])) continue;
                    $nuevo['actividades'][] = [
                        'id'          => (string) $a['id'],
                        'nombre'      => (string) $a['nombre'],
                        'fecha'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($a['fecha'] ?? '')) ? $a['fecha'] : '',
                        'ambito'      => array_key_exists($a['ambito'] ?? '', AMBITOS) ? $a['ambito'] : 'ambos',
                        'descripcion' => (string) ($a['descripcion'] ?? ''),
                        'asistentes'  => array_values(array_map('strval', is_array($a['asistentes'] ?? null) ? $a['asistentes'] : [])),
                        'creada'      => (string) ($a['creada'] ?? date('c')),
                    ];
                }
                return $nuevo;
            });
            responder(estado_completo());
        }

        default:
            error('Acción no reconocida: ' . $action, 404);
    }
} catch (Throwable $e) {
    error('Error: ' . $e->getMessage(), 500);
}
