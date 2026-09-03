<?php
require_once __DIR__ . '/config.php';

/**
 * Capa de datos: lee y escribe data.json de forma segura (con bloqueo de
 * fichero y escritura atómica). Estructura del fichero:
 *
 * {
 *   "version": 1,
 *   "meta": { "curso": "2026-2027" },
 *   "personas": [
 *     { "id": "p001", "nombre": "...", "categoria": "educador|colaborador", "activo": true }
 *   ],
 *   "actividades": [
 *     { "id": "a001", "nombre": "...", "fecha": "2026-09-15",
 *       "ambito": "educadores|colaboradores|ambos", "descripcion": "",
 *       "asistentes": ["p001", ...], "creada": "2026-09-03T18:00:00+02:00" }
 *   ]
 * }
 */

function datos_estructura_vacia(): array
{
    return [
        'version'     => 1,
        'meta'        => ['curso' => APP_CURSO],
        'personas'    => [],
        'actividades' => [],
    ];
}

/** Carga los datos. La primera vez copia seed.json -> data.json. */
function cargar_datos(): array
{
    if (!file_exists(DATA_FILE)) {
        $inicial = datos_estructura_vacia();
        if (file_exists(SEED_FILE)) {
            $seed = json_decode((string) file_get_contents(SEED_FILE), true);
            if (is_array($seed)) {
                $inicial = array_merge($inicial, $seed);
            }
        }
        guardar_datos($inicial);
        return $inicial;
    }

    $raw = file_get_contents(DATA_FILE);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        // No perdemos el fichero: lo guardamos como .corrupto y empezamos limpio.
        @copy(DATA_FILE, DATA_FILE . '.corrupto-' . date('Ymd-His'));
        $data = datos_estructura_vacia();
        guardar_datos($data);
    }
    // Normaliza claves que pudieran faltar.
    $data += datos_estructura_vacia();
    $data['personas']    = array_values($data['personas'] ?? []);
    $data['actividades'] = array_values($data['actividades'] ?? []);
    return $data;
}

/** Guarda los datos de forma atómica. */
function guardar_datos(array $data): void
{
    $dir = dirname(DATA_FILE);
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
    if (!rename($tmp, DATA_FILE)) {
        @unlink($tmp);
        throw new RuntimeException('No se pudo reemplazar data.json');
    }
}

/**
 * Ejecuta $fn recibiendo los datos, y guarda lo que devuelva.
 * Usa un fichero .lock para evitar escrituras simultáneas.
 * Devuelve los datos ya guardados.
 */
function modificar_datos(callable $fn): array
{
    $lockPath = dirname(DATA_FILE) . '/.data.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException('No se pudo abrir el bloqueo');
    }
    try {
        flock($lock, LOCK_EX);
        $data = cargar_datos();
        $nuevo = $fn($data);
        if (!is_array($nuevo)) {
            throw new RuntimeException('La operación no devolvió datos válidos');
        }
        guardar_datos($nuevo);
        return $nuevo;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/* ---------------------------------------------------------------------------
 *  Utilidades de dominio
 * ------------------------------------------------------------------------- */

function nuevo_id(string $prefijo, array $existentes): string
{
    $max = 0;
    foreach ($existentes as $e) {
        if (preg_match('/^' . preg_quote($prefijo, '/') . '(\d+)$/', $e['id'] ?? '', $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return $prefijo . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

/** ¿Una actividad de $ambito cuenta para una persona de $categoria? */
function actividad_aplica_a(string $ambito, string $categoria): bool
{
    if ($ambito === 'ambos') {
        return true;
    }
    if ($ambito === 'educadores') {
        return $categoria === 'educador';
    }
    if ($ambito === 'colaboradores') {
        return $categoria === 'colaborador';
    }
    return false;
}

/**
 * Calcula el resumen de asistencia.
 * Devuelve:
 *  - totales: nº de actividades por ámbito y aplicables a cada categoría
 *  - personas: por cada persona -> asistidas, aplicables, porcentaje, detalle
 */
function calcular_resumen(array $data): array
{
    $acts = $data['actividades'];
    $totalEducadores = 0;   // actividades que cuentan para un educador
    $totalColaboradores = 0; // actividades que cuentan para un colaborador
    $porAmbito = ['educadores' => 0, 'colaboradores' => 0, 'ambos' => 0];

    foreach ($acts as $a) {
        $amb = $a['ambito'] ?? 'ambos';
        $porAmbito[$amb] = ($porAmbito[$amb] ?? 0) + 1;
        if (actividad_aplica_a($amb, 'educador'))    $totalEducadores++;
        if (actividad_aplica_a($amb, 'colaborador')) $totalColaboradores++;
    }

    $personas = [];
    foreach ($data['personas'] as $p) {
        $cat = $p['categoria'] ?? 'educador';
        $aplicables = 0;
        $asistidas = 0;
        $detalle = [];
        foreach ($acts as $a) {
            $amb = $a['ambito'] ?? 'ambos';
            if (!actividad_aplica_a($amb, $cat)) {
                continue;
            }
            $aplicables++;
            $vino = in_array($p['id'], $a['asistentes'] ?? [], true);
            if ($vino) $asistidas++;
            $detalle[] = [
                'id'     => $a['id'],
                'nombre' => $a['nombre'],
                'fecha'  => $a['fecha'] ?? '',
                'asistio'=> $vino,
            ];
        }
        $pct = $aplicables > 0 ? round($asistidas / $aplicables * 100, 1) : 0.0;
        $personas[] = [
            'id'         => $p['id'],
            'nombre'     => $p['nombre'],
            'categoria'  => $cat,
            'activo'     => $p['activo'] ?? true,
            'asistidas'  => $asistidas,
            'aplicables' => $aplicables,
            'porcentaje' => $pct,
            'detalle'    => $detalle,
        ];
    }

    return [
        'totales' => [
            'actividades'         => count($acts),
            'para_educadores'     => $totalEducadores,
            'para_colaboradores'  => $totalColaboradores,
            'por_ambito'          => $porAmbito,
        ],
        'personas' => $personas,
    ];
}
