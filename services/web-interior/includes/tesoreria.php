<?php
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/partidas.php';

/**
 * Capa de datos de Tesorería (data/tesoreria.json).
 *
 * {
 *   "version": 1,
 *   "movimientos": [
 *     { "id": "m001", "fecha": "2026-10-04", "tipo": "ingreso|gasto",
 *       "partida": "I1.3", "concepto": "...", "cantidad": 2, "precio": 15.5,
 *       "importe": 31.0,
 *       "actividad": "a001"|null,           // actividad a la que pertenece (opcional)
 *       "proveedor": "", "cif": "", "factura": "",  // datos de la factura (opcional)
 *       "origen": "transporte:q001" | "cuota:2026-2027:m002" | null,
 *       "creado": "2026-10-04T18:00:00+02:00" }
 *   ],
 *   "comentarios": { "2026": "Texto del informe…", "curso-2026": "…" }
 * }
 *
 * El balance se puede sacar por año natural (enero–diciembre, como el BALANCE
 * de la Zona) o por curso escolar (septiembre–agosto).
 */

if (!defined('TESORERIA_FILE')) {
    define('TESORERIA_FILE', dirname(DATA_FILE) . '/tesoreria.json');
}

function tesoreria_vacia(): array
{
    return ['version' => 1, 'movimientos' => [], 'comentarios' => []];
}

function cargar_tesoreria(): array
{
    $data = cargar_json(TESORERIA_FILE, tesoreria_vacia(), 'tesoreria');
    $data['movimientos'] = array_values(is_array($data['movimientos']) ? $data['movimientos'] : []);
    if (!is_array($data['comentarios'])) $data['comentarios'] = [];
    return $data;
}

function modificar_tesoreria(callable $fn): array
{
    return con_bloqueo('.tesoreria.lock', function () use ($fn) {
        $nuevo = $fn(cargar_tesoreria());
        if (!is_array($nuevo)) {
            throw new RuntimeException('La operación no devolvió datos válidos');
        }
        escribir_json_atomico(TESORERIA_FILE, $nuevo);
        return $nuevo;
    });
}

/**
 * Apunta un movimiento desde otra sección (transporte, cuota…) y devuelve su id.
 * $mov: fecha, tipo, partida, concepto, importe [, actividad, origen]
 */
function apuntar_movimiento(array $mov): string
{
    $id = '';
    modificar_tesoreria(function (array $d) use ($mov, &$id) {
        $id = nuevo_id('m', $d['movimientos']);
        $d['movimientos'][] = [
            'id'        => $id,
            'fecha'     => $mov['fecha'],
            'tipo'      => $mov['tipo'],
            'partida'   => $mov['partida'],
            'concepto'  => $mov['concepto'],
            'cantidad'  => 1,
            'precio'    => euros($mov['importe']),
            'importe'   => euros($mov['importe']),
            'actividad' => $mov['actividad'] ?? null,
            'proveedor' => '',
            'cif'       => '',
            'factura'   => '',
            'origen'    => $mov['origen'] ?? null,
            'creado'    => date('c'),
        ];
        return $d;
    });
    return $id;
}

/** Borra un movimiento por id (si existe). */
function borrar_movimiento(?string $id): void
{
    if (!$id) return;
    modificar_tesoreria(function (array $d) use ($id) {
        $d['movimientos'] = array_values(array_filter($d['movimientos'], fn($m) => ($m['id'] ?? '') !== $id));
        return $d;
    });
}

/** ¿Existe el movimiento? */
function existe_movimiento(array $tes, ?string $id): bool
{
    if (!$id) return false;
    foreach ($tes['movimientos'] as $m) {
        if (($m['id'] ?? '') === $id) return true;
    }
    return false;
}

const MODOS_PERIODO = [
    'natural' => 'Año natural',
    'curso'   => 'Curso escolar',
];
const MES_INICIO_CURSO = 9; // septiembre

function modo_valido($v): string
{
    return array_key_exists((string) $v, MODOS_PERIODO) ? (string) $v : 'natural';
}

/** Modo elegido: el de la URL si viene, si no el último usado (cookie). */
function modo_preferido(): string
{
    return modo_valido($_GET['modo'] ?? ($_COOKIE['tes_modo'] ?? 'natural'));
}

/** Periodo (año de inicio) al que pertenece una fecha YYYY-MM-DD. */
function periodo_de_fecha(string $modo, string $fecha): int
{
    $anio = (int) substr($fecha, 0, 4);
    if ($modo === 'curso' && (int) substr($fecha, 5, 2) < MES_INICIO_CURSO) {
        $anio--;
    }
    return $anio;
}

function periodo_actual(string $modo): int
{
    return periodo_de_fecha($modo, date('Y-m-d'));
}

/** "2026" o "Curso 2026-2027". */
function periodo_etiqueta(string $modo, int $anio): string
{
    return $modo === 'curso' ? 'Curso ' . $anio . '-' . ($anio + 1) : (string) $anio;
}

/** Clave con la que se guarda el comentario de la ficha de un periodo. */
function periodo_clave(string $modo, int $anio): string
{
    return $modo === 'curso' ? "curso-$anio" : (string) $anio;
}

/** Periodos con movimientos (+ el actual), de más reciente a más antiguo. */
function tesoreria_anios(array $data, string $modo = 'natural'): array
{
    $anios = [periodo_actual($modo) => true];
    foreach ($data['movimientos'] as $m) {
        if (limpiar_fecha($m['fecha'] ?? '')) {
            $anios[periodo_de_fecha($modo, $m['fecha'])] = true;
        }
    }
    $lista = array_keys($anios);
    rsort($lista);
    return $lista;
}

/** Movimientos que caen dentro del periodo. */
function movimientos_periodo(array $data, string $modo, int $anio): array
{
    return array_values(array_filter(
        $data['movimientos'],
        fn($m) => limpiar_fecha($m['fecha'] ?? '') && periodo_de_fecha($modo, $m['fecha']) === $anio
    ));
}

/** Movimientos vinculados a una actividad. */
function movimientos_actividad(array $data, string $actividad): array
{
    return array_values(array_filter($data['movimientos'], fn($m) => ($m['actividad'] ?? null) === $actividad));
}

/**
 * Balance de un periodo: totales por partida, por grupo y por tipo, con
 * porcentaje sobre el total de ingresos / gastos (como la FICHA BALANCE).
 */
function calcular_balance(array $data, int $anio, string $modo = 'natural'): array
{
    $porPartida = [];
    $n = 0;
    foreach (movimientos_periodo($data, $modo, $anio) as $m) {
        $cod = $m['partida'] ?? '';
        $porPartida[$cod] = ($porPartida[$cod] ?? 0) + (float) ($m['importe'] ?? 0);
        $n++;
    }

    $res = [
        'anio'        => $anio,
        'modo'        => $modo,
        'etiqueta'    => periodo_etiqueta($modo, $anio),
        'movimientos' => $n,
    ];
    foreach (PARTIDAS as $tipo => $grupos) {
        $totalTipo = 0.0;
        $lista = [];
        foreach ($grupos as $g => [$nombreGrupo, $partidas]) {
            $filas = [];
            $totalGrupo = 0.0;
            foreach ($partidas as $cod => $nombre) {
                $imp = euros($porPartida[$cod] ?? 0);
                $totalGrupo += $imp;
                $filas[] = ['codigo' => $cod, 'nombre' => $nombre, 'importe' => $imp];
            }
            $totalTipo += $totalGrupo;
            $lista[] = [
                'codigo'   => $g,
                'nombre'   => $nombreGrupo,
                'importe'  => euros($totalGrupo),
                'partidas' => $filas,
            ];
        }
        $totalTipo = euros($totalTipo);
        foreach ($lista as &$gr) {
            $gr['pct'] = $totalTipo != 0 ? round($gr['importe'] / $totalTipo * 100, 2) : 0.0;
            foreach ($gr['partidas'] as &$p) {
                $p['pct'] = $totalTipo != 0 ? round($p['importe'] / $totalTipo * 100, 2) : 0.0;
            }
            unset($p);
        }
        unset($gr);
        $res[$tipo] = ['total' => $totalTipo, 'grupos' => $lista];
    }
    $res['resultado'] = euros($res['ingreso']['total'] - $res['gasto']['total']);
    $res['resultado_pct'] = $res['ingreso']['total'] != 0
        ? round($res['resultado'] / $res['ingreso']['total'] * 100, 2) : 0.0;
    return $res;
}
