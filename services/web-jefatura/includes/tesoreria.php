<?php
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/partidas.php';

/**
 * Capa de datos de Tesorería. Va en un fichero aparte de la asistencia
 * (data/tesoreria.json) para que las copias de seguridad sean independientes.
 *
 * {
 *   "version": 1,
 *   "movimientos": [
 *     { "id": "m001", "fecha": "2026-10-04", "tipo": "ingreso|gasto",
 *       "partida": "I1.3", "concepto": "...", "cantidad": 2, "precio": 15.5,
 *       "importe": 31.0, "creado": "2026-10-04T18:00:00+02:00" }
 *   ],
 *   "comentarios": { "2026": "Texto del informe…", "curso-2026": "…" }
 * }
 *
 * Los movimientos solo guardan su fecha. El balance se puede sacar por:
 *  - año natural  ('natural'): 1 enero – 31 diciembre (como el MODELO BALANCE)
 *  - curso escolar ('curso'):  1 septiembre – 31 agosto del año siguiente
 * Un periodo se identifica por (modo, año de inicio). Los comentarios de la
 * ficha se guardan por periodo: "2026" (natural) o "curso-2026".
 */

if (!defined('TESORERIA_FILE')) {
    define('TESORERIA_FILE', dirname(DATA_FILE) . '/tesoreria.json');
}
if (!defined('TES_CENTRO')) {
    define('TES_CENTRO', 'Juniors Axenia 603 D');
}

function tesoreria_vacia(): array
{
    return ['version' => 1, 'movimientos' => [], 'comentarios' => []];
}

function cargar_tesoreria(): array
{
    if (!file_exists(TESORERIA_FILE)) {
        return tesoreria_vacia();
    }
    $data = json_decode((string) file_get_contents(TESORERIA_FILE), true);
    if (!is_array($data)) {
        @copy(TESORERIA_FILE, TESORERIA_FILE . '.corrupto-' . date('Ymd-His'));
        return tesoreria_vacia();
    }
    $data += tesoreria_vacia();
    $data['movimientos'] = array_values($data['movimientos'] ?? []);
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

/** Redondeo a céntimos evitando arrastres de coma flotante. */
function euros(float $v): float
{
    return round($v + 0.0, 2);
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

/** Periodo actual según el modo. */
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
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $m['fecha'] ?? '')) {
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
        fn($m) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $m['fecha'] ?? '')
               && periodo_de_fecha($modo, $m['fecha']) === $anio
    ));
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

/** Formato español: 1.234,56 € */
function formato_euros(float $v): string
{
    return number_format($v, 2, ',', '.') . ' €';
}

function formato_pct(float $v): string
{
    return number_format($v, 2, ',', '.') . '%';
}
