<?php
require_once __DIR__ . '/config.php';

/**
 * Capa de datos: lee y escribe los .json de data/ de forma segura (bloqueo de
 * fichero y escritura atómica). Hay dos ficheros:
 *
 *  - data/zona.json        centros, transporte, cuotas y actividades (ver zona.php)
 *  - data/tesoreria.json   movimientos y comentarios del balance (ver tesoreria.php)
 *
 * La primera vez que se abre la web, cada fichero se crea a partir de la clave
 * correspondiente de data/seed.json ("zona" y "tesoreria"), si existe.
 */

/** Lee la parte $clave de seed.json, o null. */
function leer_seed(string $clave): ?array
{
    if (!file_exists(SEED_FILE)) {
        return null;
    }
    $seed = json_decode((string) file_get_contents(SEED_FILE), true);
    return is_array($seed) && is_array($seed[$clave] ?? null) ? $seed[$clave] : null;
}

/**
 * Carga un fichero JSON. Si no existe lo crea con $vacio (+ seed); si está
 * corrupto lo aparta como .corrupto-FECHA y empieza limpio.
 */
function cargar_json(string $fichero, array $vacio, string $claveSeed): array
{
    if (!file_exists($fichero)) {
        $inicial = array_merge($vacio, leer_seed($claveSeed) ?? []);
        escribir_json_atomico($fichero, $inicial);
        return $inicial;
    }
    $data = json_decode((string) file_get_contents($fichero), true);
    if (!is_array($data)) {
        @copy($fichero, $fichero . '.corrupto-' . date('Ymd-His'));
        $data = $vacio;
        escribir_json_atomico($fichero, $data);
    }
    return $data + $vacio;
}

/** Escribe $data como JSON en $fichero de forma atómica (tmp + rename). */
function escribir_json_atomico(string $fichero, array $data): void
{
    $dir = dirname($fichero);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudieron serializar los datos');
    }
    $tmp = $dir . '/.data-' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo escribir el fichero temporal');
    }
    if (!rename($tmp, $fichero)) {
        @unlink($tmp);
        throw new RuntimeException('No se pudo reemplazar ' . basename($fichero));
    }
}

/** Ejecuta $fn con un bloqueo exclusivo sobre data/$nombreLock. */
function con_bloqueo(string $nombreLock, callable $fn)
{
    $lock = fopen(dirname(DATA_FILE) . '/' . $nombreLock, 'c');
    if ($lock === false) {
        throw new RuntimeException('No se pudo abrir el bloqueo');
    }
    try {
        flock($lock, LOCK_EX);
        return $fn();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/* ---------------------------------------------------------------------------
 *  Utilidades de validación
 * ------------------------------------------------------------------------- */

/** Recorta espacios, colapsa blancos y limita la longitud. */
function limpiar_texto($v, int $max): string
{
    $v = trim((string) (is_scalar($v) ? $v : ''));
    $v = preg_replace('/\s+/u', ' ', $v);
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

/** Texto largo (conserva saltos de línea). */
function limpiar_parrafo($v, int $max = 5000): string
{
    $v = str_replace("\r\n", "\n", trim((string) (is_scalar($v) ? $v : '')));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

/** Acepta 12,5 / 12.5 / "1.234,56". Devuelve null si no es un número. */
function leer_numero($v): ?float
{
    if (is_int($v) || is_float($v)) return (float) $v;
    if (!is_string($v)) return null;
    $s = str_replace([' ', '€'], '', trim($v));
    if ($s === '') return null;
    if (strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

/** Número o 0 (o $defecto). */
function numero($v, float $defecto = 0.0): float
{
    return leer_numero($v) ?? $defecto;
}

/** Fecha YYYY-MM-DD válida o ''. */
function limpiar_fecha($v): string
{
    $v = (string) (is_scalar($v) ? $v : '');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
}

/** Id con formato prefijo+dígitos o ''. */
function limpiar_id($v): string
{
    $v = (string) (is_scalar($v) ? $v : '');
    return preg_match('/^[a-z]\d{1,6}$/', $v) ? $v : '';
}

/** Siguiente id libre: m001, m002… */
function nuevo_id(string $prefijo, array $existentes): string
{
    $max = 0;
    foreach ($existentes as $e) {
        if (preg_match('/^' . preg_quote($prefijo, '/') . '(\d+)$/', (string) ($e['id'] ?? ''), $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return $prefijo . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

/** Redondeo a céntimos evitando arrastres de coma flotante. */
function euros(float $v): float
{
    return round($v + 0.0, 2);
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

/** 3 → "3", 2,5 → "2,5" */
function formato_num(float $v): string
{
    return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
}

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
    'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** 2026-04-20 → "20 de abril de 2026" */
function fecha_larga(string $f): string
{
    if (!limpiar_fecha($f)) return '';
    return (int) substr($f, 8, 2) . ' de ' . MESES[(int) substr($f, 5, 2) - 1] . ' de ' . substr($f, 0, 4);
}

/** 2026-04-20 → "20/04/2026" */
function fecha_corta(string $f): string
{
    return limpiar_fecha($f) ? substr($f, 8, 2) . '/' . substr($f, 5, 2) . '/' . substr($f, 0, 4) : '';
}

/** Mayúsculas respetando tildes aunque no esté la extensión mbstring. */
function mayusculas(string $s): string
{
    if (function_exists('mb_strtoupper')) return mb_strtoupper($s, 'UTF-8');
    return strtr(strtoupper($s), ['á' => 'Á', 'é' => 'É', 'í' => 'Í', 'ó' => 'Ó', 'ú' => 'Ú',
        'à' => 'À', 'è' => 'È', 'ò' => 'Ò', 'ñ' => 'Ñ', 'ü' => 'Ü', 'ç' => 'Ç']);
}

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
