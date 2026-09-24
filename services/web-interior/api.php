<?php
/**
 * API JSON de la Zona (todo menos los movimientos, que van en api-tesoreria.php).
 * Todas las peticiones necesitan sesión iniciada; los POST, la cabecera X-CSRF-Token.
 *
 * GET  ?action=state                  -> todos los datos de zona.json + cursos
 * GET  ?action=export                 -> descarga copia de TODO (zona + tesorería)
 * POST ?action=import                 { zona, tesoreria } (sustituye todo)
 * POST ?action=ajustes.save           { ajustes }
 * POST ?action=miembros.save          { miembros:[…] }
 * POST ?action=lugares.save           { lugares:[…] }
 * POST ?action=consejo.save           { consejo }            (sin id = nuevo)
 * POST ?action=consejo.delete         { id }
 * POST ?action=liquidacion.save       { liquidacion }
 * POST ?action=liquidacion.delete     { id }
 * POST ?action=liquidacion.apuntar    { id, fecha }          -> gasto G3.1 en tesorería
 * POST ?action=liquidacion.desapuntar { id }
 * POST ?action=actividad.save         { actividad }          (sin id = nueva) -> { actividad }
 * POST ?action=actividad.delete       { id }
 * POST ?action=actividad.duplicar     { id }                 -> { actividad }
 * POST ?action=cuota.save             { curso, cuota }
 * POST ?action=cuota.cobro            { curso, miembro, fecha } -> ingreso I2.1 en tesorería
 * POST ?action=cuota.anular_cobro     { curso, miembro }
 *
 * Todas las respuestas POST devuelven el estado completo (como "state").
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
require_once __DIR__ . '/includes/tesoreria.php';

exigir_login_api();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'export' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="tesoreria-zona-interior-' . date('Ymd-His') . '.json"');
    echo json_encode(['version' => 1, 'exportado' => date('c'), 'zona' => cargar_zona(), 'tesoreria' => cargar_tesoreria()],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

/**
 * Estado completo. Los enlaces a movimientos que ya no existen (porque se
 * borraron en Movimientos) se muestran como pendientes.
 */
function estado(array $extra = []): array
{
    $d = cargar_zona();
    $tes = cargar_tesoreria();
    foreach ($d['liquidaciones'] as &$q) {
        if ($q['movimiento'] && !existe_movimiento($tes, $q['movimiento'])) $q['movimiento'] = null;
    }
    unset($q);
    foreach ($d['cuotas'] as &$c) {
        $c['cobros'] = array_filter($c['cobros'] ?? [], fn($mov) => existe_movimiento($tes, $mov));
    }
    unset($c);
    return [
        'ok'          => true,
        'zona'        => zona_para_js($d),
        'curso'       => curso_actual(),
        'cursos'      => cursos_disponibles($d),
        'categorias'  => CATEGORIAS_INFORME,
        'estados'     => ESTADOS_CONSEJO,
    ] + $extra;
}

function buscar_indice(array $lista, string $id): int
{
    foreach ($lista as $i => $x) if (($x['id'] ?? '') === $id) return $i;
    throw new RuntimeException('No se ha encontrado el elemento');
}

if ($method === 'GET') {
    if ($action === 'state') responder(estado());
    error('Acción GET no reconocida: ' . $action, 404);
}

if ($method !== 'POST') {
    error('Método no permitido', 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
if (!csrf_valido($token)) {
    error('Token de seguridad no válido. Recarga la página.', 403);
}

$b = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($b)) {
    error('No se pudieron leer los datos enviados (JSON no válido).');
}

try {
    switch ($action) {

        /* ---------------- Ajustes, miembros y lugares ---------------- */

        case 'ajustes.save':
            modificar_zona(function ($d) use ($b) {
                $d['ajustes'] = sanear_ajustes($b['ajustes'] ?? []);
                return $d;
            });
            responder(estado());

        case 'miembros.save':
            modificar_zona(function ($d) use ($b) {
                $d['miembros'] = sanear_miembros($b['miembros'] ?? [], $d['miembros']);
                return $d;
            });
            responder(estado());

        case 'lugares.save':
            modificar_zona(function ($d) use ($b) {
                $d['lugares'] = sanear_lugares($b['lugares'] ?? [], $d['lugares']);
                return $d;
            });
            responder(estado());

        /* ---------------- Consejos y liquidaciones de transporte ---------------- */

        case 'consejo.save': {
            $c = sanear_consejo($b['consejo'] ?? []);
            modificar_zona(function ($d) use ($c) {
                if ($c['id'] === '') {
                    $c['id'] = nuevo_id('k', $d['consejos']);
                    $d['consejos'][] = $c;
                } else {
                    $d['consejos'][buscar_indice($d['consejos'], $c['id'])] = $c;
                }
                return $d;
            });
            responder(estado());
        }

        case 'consejo.delete': {
            $id = limpiar_id($b['id'] ?? '');
            modificar_zona(function ($d) use ($id) {
                $d['consejos'] = array_values(array_filter($d['consejos'], fn($c) => $c['id'] !== $id));
                foreach ($d['liquidaciones'] as &$q) {
                    $q['consejos'] = array_values(array_diff($q['consejos'], [$id]));
                }
                return $d;
            });
            responder(estado());
        }

        case 'liquidacion.save': {
            $q = sanear_liquidacion($b['liquidacion'] ?? []);
            modificar_zona(function ($d) use ($q) {
                if ($q['id'] === '') {
                    $q['id'] = nuevo_id('q', $d['liquidaciones']);
                    $q['movimiento'] = null;
                    $d['liquidaciones'][] = $q;
                } else {
                    $i = buscar_indice($d['liquidaciones'], $q['id']);
                    $q['movimiento'] = $d['liquidaciones'][$i]['movimiento'] ?? null; // solo lo cambia «apuntar»
                    $d['liquidaciones'][$i] = $q;
                }
                return $d;
            });
            responder(estado());
        }

        case 'liquidacion.delete': {
            $id = limpiar_id($b['id'] ?? '');
            modificar_zona(function ($d) use ($id) {
                $d['liquidaciones'] = array_values(array_filter($d['liquidaciones'], fn($q) => $q['id'] !== $id));
                return $d;
            });
            responder(estado());
        }

        case 'liquidacion.apuntar': {
            $id = limpiar_id($b['id'] ?? '');
            $fecha = limpiar_fecha($b['fecha'] ?? '') ?: date('Y-m-d');
            $d = cargar_zona();
            $q = $d['liquidaciones'][buscar_indice($d['liquidaciones'], $id)];
            if ($q['movimiento'] && existe_movimiento(cargar_tesoreria(), $q['movimiento'])) {
                error('Esta liquidación ya está apuntada en Movimientos.');
            }
            $total = euros(array_sum(liquidacion_por_miembro($d, $q)));
            if ($total <= 0) error('La liquidación no tiene importe (¿consejos celebrados con asistentes?).');
            $mov = apuntar_movimiento([
                'fecha' => $fecha, 'tipo' => 'gasto', 'partida' => PARTIDA_TRANSPORTE,
                'concepto' => 'Ayuda transporte CJZ · ' . $q['nombre'], 'importe' => $total, 'origen' => 'transporte:' . $id,
            ]);
            modificar_zona(function ($d) use ($id, $mov, $fecha) {
                $i = buscar_indice($d['liquidaciones'], $id);
                $d['liquidaciones'][$i]['movimiento'] = $mov;
                $d['liquidaciones'][$i]['pagada'] = true;
                $d['liquidaciones'][$i]['fecha'] = $d['liquidaciones'][$i]['fecha'] ?: $fecha;
                return $d;
            });
            responder(estado());
        }

        case 'liquidacion.desapuntar': {
            $id = limpiar_id($b['id'] ?? '');
            $mov = null;
            modificar_zona(function ($d) use ($id, &$mov) {
                $i = buscar_indice($d['liquidaciones'], $id);
                $mov = $d['liquidaciones'][$i]['movimiento'];
                $d['liquidaciones'][$i]['movimiento'] = null;
                return $d;
            });
            borrar_movimiento($mov);
            responder(estado());
        }

        /* ---------------- Actividades ---------------- */

        case 'actividad.save': {
            $a = sanear_actividad($b['actividad'] ?? []);
            modificar_zona(function ($d) use (&$a) {
                if ($a['id'] === '') {
                    $a['id'] = nuevo_id('a', $d['actividades']);
                    $d['actividades'][] = $a;
                } else {
                    $d['actividades'][buscar_indice($d['actividades'], $a['id'])] = $a;
                }
                return $d;
            });
            responder(estado(['actividad' => $a]));
        }

        case 'actividad.delete': {
            $id = limpiar_id($b['id'] ?? '');
            modificar_zona(function ($d) use ($id) {
                $d['actividades'] = array_values(array_filter($d['actividades'], fn($a) => $a['id'] !== $id));
                return $d;
            });
            // Los movimientos se conservan, solo pierden el vínculo.
            modificar_tesoreria(function ($t) use ($id) {
                foreach ($t['movimientos'] as &$m) if (($m['actividad'] ?? null) === $id) $m['actividad'] = null;
                return $t;
            });
            responder(estado());
        }

        case 'actividad.duplicar': {
            $id = limpiar_id($b['id'] ?? '');
            $nueva = null;
            modificar_zona(function ($d) use ($id, &$nueva) {
                $o = $d['actividades'][buscar_indice($d['actividades'], $id)];
                // El presupuesto de la copia parte del presupuesto (o, si no hay, del balance) del original.
                $base = ($o['presupuesto']['ingresos'] || $o['presupuesto']['gastos']) ? $o['presupuesto'] : $o['balance'];
                $limpiar = fn($ls) => array_map(function ($l) {
                    if (!empty($l['detalle'])) {
                        $l['cantidad'] = 1;
                        $l['precio'] = euros(array_sum(array_column($l['detalle'], 'importe')));
                    }
                    unset($l['detalle'], $l['grupo'], $l['mov']);
                    return $l;
                }, $ls);
                $fecha = date('Y-m-d', strtotime($o['fecha'] . ' +1 year'));
                $nueva = [
                    'id'          => nuevo_id('a', $d['actividades']),
                    'nombre'      => $o['nombre'],
                    'fecha'       => $fecha,
                    'fecha_fin'   => $o['fecha_fin'] ? date('Y-m-d', strtotime($o['fecha_fin'] . ' +1 year')) : '',
                    'lugar'       => $o['lugar'],
                    'presupuesto' => ['ingresos' => $limpiar($base['ingresos']), 'gastos' => $limpiar($base['gastos']), 'comentario' => ''],
                    'balance'     => ['ingresos' => [], 'gastos' => [], 'comentario' => ''],
                    'centros'     => [],
                    'informe'     => ['incluir' => true, 'ingresos_actividad' => null, 'participacion' => null, 'facturas' => []],
                ];
                $d['actividades'][] = $nueva;
                return $d;
            });
            responder(estado(['actividad' => $nueva]));
        }

        /* ---------------- Cuota de zona ---------------- */

        case 'cuota.save': {
            $curso = curso_valido($b['curso'] ?? '');
            if (!$curso) error('Curso no válido');
            $c = sanear_cuota($b['cuota'] ?? [], $curso);
            modificar_zona(function ($d) use ($curso, $c) {
                $c['cobros'] = $d['cuotas'][$curso]['cobros'] ?? []; // solo los cambian cobro/anular
                $d['cuotas'][$curso] = $c;
                return $d;
            });
            responder(estado());
        }

        case 'cuota.cobro': {
            $curso = curso_valido($b['curso'] ?? '');
            $mid = limpiar_id($b['miembro'] ?? '');
            $fecha = limpiar_fecha($b['fecha'] ?? '') ?: date('Y-m-d');
            $d = cargar_zona();
            $m = miembro($d, $mid);
            if (!$curso || !$m) error('Datos no válidos');
            $cuota = cuota_curso($d, $curso);
            if (!isset($d['cuotas'][$curso])) error('Guarda primero la cuota de este curso.');
            $prev = $cuota['cobros'][$mid] ?? null;
            if ($prev && existe_movimiento(cargar_tesoreria(), $prev)) error('Este cobro ya está apuntado.');
            $imp = euros((int) ($cuota['censo'][$mid] ?? 0) * (float) $cuota['precio']);
            if ($imp <= 0) error('Este centro no tiene cuota que cobrar.');
            $mov = apuntar_movimiento([
                'fecha' => $fecha, 'tipo' => 'ingreso', 'partida' => PARTIDA_CUOTA_ZONA,
                'concepto' => 'Cuota Zona curso ' . $curso . ' · ' . $m['nombre'], 'importe' => $imp,
                'origen' => "cuota:$curso:$mid",
            ]);
            modificar_zona(function ($d) use ($curso, $mid, $mov) {
                $d['cuotas'][$curso]['cobros'][$mid] = $mov;
                return $d;
            });
            responder(estado());
        }

        case 'cuota.anular_cobro': {
            $curso = curso_valido($b['curso'] ?? '');
            $mid = limpiar_id($b['miembro'] ?? '');
            $mov = null;
            modificar_zona(function ($d) use ($curso, $mid, &$mov) {
                $mov = $d['cuotas'][$curso]['cobros'][$mid] ?? null;
                unset($d['cuotas'][$curso]['cobros'][$mid]);
                return $d;
            });
            borrar_movimiento($mov);
            responder(estado());
        }

        /* ---------------- Copia de seguridad ---------------- */

        case 'import': {
            if (!is_array($b['zona'] ?? null) || !is_array($b['tesoreria'] ?? null)
                || !isset($b['tesoreria']['movimientos'])) {
                error('El fichero no tiene el formato esperado (faltan "zona" y "tesoreria").');
            }
            // Deja una copia de lo que había, por si acaso.
            $dir = dirname(DATA_FILE);
            $sello = date('Ymd-His');
            if (file_exists(DATA_FILE)) @copy(DATA_FILE, "$dir/zona.antes-de-importar-$sello.json.bak");
            if (file_exists(TESORERIA_FILE)) @copy(TESORERIA_FILE, "$dir/tesoreria.antes-de-importar-$sello.json.bak");
            modificar_zona(fn() => $b['zona'] + zona_vacia());
            modificar_tesoreria(fn() => $b['tesoreria'] + tesoreria_vacia());
            responder(estado());
        }

        default:
            error('Acción no reconocida: ' . $action, 404);
    }
} catch (InvalidArgumentException $e) {
    error($e->getMessage());
} catch (Throwable $e) {
    error('Error: ' . $e->getMessage(), 500);
}
