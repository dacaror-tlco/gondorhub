<?php
require_once __DIR__ . '/data.php';

/**
 * Datos de la Zona (data/zona.json): todo lo que no son movimientos.
 *
 * {
 *   "version": 1,
 *   "ajustes":  { entidad, iban, banco, tesorero, cargo_tesorero, delegado, cargo_delegado },
 *   "miembros": [ { id:"m001", nombre, numero:"603D", localidad, tipo:"centro|otro", iban, activo } ],
 *   "lugares":  [ { id:"l001", nombre, miembro:"m003"|null, tarifas:{ m001: 10, … } } ],
 *   "consejos": [ { id:"k001", fecha, nombre:"CJZ", lugar:"l001", estado:"previsto|celebrado",
 *                   pagar:true, asistentes:{ m001: 10, … }, notas } ],
 *   "liquidaciones": [ { id:"q001", nombre, consejos:["k001",…], fecha, pagada, movimiento } ],
 *   "cuotas": { "2026-2027": { precio, censo:{m002: 20…}, referencia:{titulo, precio, censo},
 *               gastos:[linea], titulo_presupuesto, escenarios:[{id,nombre,precio,censo}],
 *               nota_aprobacion, fecha_limite, fecha_emision, cobros:{ m002: "m045" } } },
 *   "actividades": [ { id:"a001", nombre, fecha, fecha_fin, lugar,
 *                      presupuesto:{ ingresos:[linea], gastos:[linea], comentario },
 *                      balance:{ … igual … },
 *                      centros:[ { id:"g001", nombre, precio, filas:[{miembro, cantidad}] } ],
 *                      informe:{ incluir, ingresos_actividad|null, participacion|null,
 *                                facturas:[{id, numero, fecha, cif, razon, concepto, importe, categoria, mov}] } } ]
 * }
 *
 * linea = { concepto, cantidad, precio,
 *           detalle?:[{concepto, importe}]   → precio = suma del desglose (p. ej. INTENDENCIA)
 *           grupo?:"g001"                    → cantidad y precio salen de «ingresos por centros»
 *           cuadrar?:true                    → (ingresos) importe = gastos − resto de ingresos
 *           zona?:true                       → (ingresos) es aportación de la Zona (informe económico)
 *           mov?:"m012" }                    → movimiento de tesorería del que se importó
 */

const CATEGORIAS_INFORME = [
    'materiales'      => 'Materiales',
    'alquiler'        => 'Alquiler instalaciones',
    'manutencion'     => 'Manutención (Dietas, alimentación)',
    'desplazamientos' => 'Desplazamientos (Autobuses)',
    'otros'           => 'Otros (Megafonía…)',
];

const ESTADOS_CONSEJO = [
    'previsto'  => 'Previsto',
    'celebrado' => 'Celebrado',
];

function ajustes_defecto(): array
{
    return [
        'entidad'        => 'Zona Interior',
        'iban'           => '',
        'banco'          => '',
        'tesorero'       => '',
        'cargo_tesorero' => 'Tesorero Zona Interior',
        'delegado'       => '',
        'cargo_delegado' => 'Delegado Zona Interior',
    ];
}

function zona_vacia(): array
{
    return [
        'version'       => 1,
        'ajustes'       => ajustes_defecto(),
        'miembros'      => [],
        'lugares'       => [],
        'consejos'      => [],
        'liquidaciones' => [],
        'cuotas'        => [],
        'actividades'   => [],
    ];
}

function cargar_zona(): array
{
    $d = cargar_json(DATA_FILE, zona_vacia(), 'zona');
    $d['ajustes'] = (is_array($d['ajustes']) ? $d['ajustes'] : []) + ajustes_defecto();
    foreach (['miembros', 'lugares', 'consejos', 'liquidaciones', 'actividades'] as $k) {
        $d[$k] = array_values(is_array($d[$k]) ? $d[$k] : []);
    }
    if (!is_array($d['cuotas'])) $d['cuotas'] = [];
    return $d;
}

function modificar_zona(callable $fn): array
{
    return con_bloqueo('.zona.lock', function () use ($fn) {
        $nuevo = $fn(cargar_zona());
        if (!is_array($nuevo)) {
            throw new RuntimeException('La operación no devolvió datos válidos');
        }
        escribir_json_atomico(DATA_FILE, $nuevo);
        return $nuevo;
    });
}

/**
 * Prepara los datos para enviarlos al navegador: los mapas vacíos salen como
 * {} y no como [] (si no, JSON.stringify los perdería al volver).
 */
function zona_para_js(array $d): array
{
    $d['cuotas'] = (object) array_map(function ($c) {
        foreach (['censo', 'cobros'] as $k) $c[$k] = (object) ($c[$k] ?? []);
        $c['referencia']['censo'] = (object) ($c['referencia']['censo'] ?? []);
        $c['escenarios'] = array_map(fn($e) => ['censo' => (object) ($e['censo'] ?? [])] + $e, $c['escenarios'] ?? []);
        return $c;
    }, $d['cuotas']);
    $d['lugares'] = array_map(fn($l) => ['tarifas' => (object) ($l['tarifas'] ?? [])] + $l, $d['lugares']);
    $d['consejos'] = array_map(fn($c) => ['asistentes' => (object) ($c['asistentes'] ?? [])] + $c, $d['consejos']);
    return $d;
}

/* ---------------------------------------------------------------------------
 *  Cursos (septiembre – agosto)
 * ------------------------------------------------------------------------- */

/** 2025-11-21 → "2025-2026" */
function curso_de_fecha(string $fecha): string
{
    $a = (int) substr($fecha, 0, 4);
    if ((int) substr($fecha, 5, 2) < 9) $a--;
    return $a . '-' . ($a + 1);
}

function curso_actual(): string
{
    return curso_de_fecha(date('Y-m-d'));
}

function curso_valido($v): string
{
    $v = (string) (is_scalar($v) ? $v : '');
    if (preg_match('/^(\d{4})-(\d{4})$/', $v, $m) && (int) $m[2] === (int) $m[1] + 1) return $v;
    return '';
}

/** "2025-2026" → "2025/2026" */
function curso_barra(string $c): string
{
    return str_replace('-', '/', $c);
}

/** Curso elegido: el de la URL o, si no, el último usado (cookie) o el actual. */
function curso_preferido(): string
{
    return curso_valido($_GET['curso'] ?? '') ?: (curso_valido($_COOKIE['zona_curso'] ?? '') ?: curso_actual());
}

/** Cursos con datos, más el actual y el siguiente, de más reciente a más antiguo. */
function cursos_disponibles(array $d): array
{
    $actual = curso_actual();
    $a = (int) substr($actual, 0, 4);
    $cursos = [$actual => true, ($a + 1) . '-' . ($a + 2) => true];
    foreach ($d['actividades'] as $x) if (limpiar_fecha($x['fecha'] ?? '')) $cursos[curso_de_fecha($x['fecha'])] = true;
    foreach ($d['consejos'] as $x) if (limpiar_fecha($x['fecha'] ?? '')) $cursos[curso_de_fecha($x['fecha'])] = true;
    foreach (array_keys($d['cuotas']) as $c) if (curso_valido($c)) $cursos[$c] = true;
    $lista = array_keys($cursos);
    rsort($lista);
    return $lista;
}

/* ---------------------------------------------------------------------------
 *  Miembros
 * ------------------------------------------------------------------------- */

function miembro(array $d, ?string $id): ?array
{
    foreach ($d['miembros'] as $m) if ($m['id'] === $id) return $m;
    return null;
}

/** Miembros activos. $tipo: 'centro', 'otro' o null (todos). */
function miembros_activos(array $d, ?string $tipo = null): array
{
    return array_values(array_filter($d['miembros'],
        fn($m) => ($m['activo'] ?? true) && ($tipo === null || ($m['tipo'] ?? 'centro') === $tipo)));
}

/** "Axenia - 603D" */
function miembro_etiqueta(?array $m): string
{
    if (!$m) return '¿?';
    return $m['nombre'] . (($m['numero'] ?? '') !== '' ? ' - ' . $m['numero'] : '');
}

/* ---------------------------------------------------------------------------
 *  Hojas de presupuesto / balance de una actividad
 * ------------------------------------------------------------------------- */

function grupo_centros(array $act, ?string $gid): ?array
{
    foreach ($act['centros'] ?? [] as $g) if (($g['id'] ?? '') === $gid) return $g;
    return null;
}

/** [cantidad total, importe total] de un grupo de «ingresos por centros». */
function grupo_totales(array $g): array
{
    $cant = 0.0;
    $imp = 0.0;
    foreach ($g['filas'] ?? [] as $f) {
        $c = (float) ($f['cantidad'] ?? 0);
        $p = isset($f['precio']) && $f['precio'] !== null ? (float) $f['precio'] : (float) ($g['precio'] ?? 0);
        $cant += $c;
        $imp += $c * $p;
    }
    return [$cant, euros($imp)];
}

/**
 * Calcula una hoja (presupuesto o balance): cada línea con su cantidad, precio
 * y total efectivos, y los totales. Las líneas «cuadrar» se calculan al final.
 */
function calcular_hoja(array $hoja, array $act): array
{
    $calc = function (array $l) use ($act): array {
        $cant = (float) ($l['cantidad'] ?? 0);
        $precio = (float) ($l['precio'] ?? 0);
        if (!empty($l['grupo']) && ($g = grupo_centros($act, $l['grupo']))) {
            [$cant, $imp] = grupo_totales($g);
            $precio = $cant ? $imp / $cant : (float) ($g['precio'] ?? 0);
            return $l + ['_cantidad' => $cant, '_precio' => euros($precio), '_total' => $imp];
        }
        if (!empty($l['detalle'])) {
            $cant = 1;
            $precio = array_sum(array_map(fn($x) => (float) ($x['importe'] ?? 0), $l['detalle']));
        }
        return $l + ['_cantidad' => $cant, '_precio' => euros($precio), '_total' => euros($cant * $precio)];
    };

    $gastos = array_map($calc, $hoja['gastos'] ?? []);
    $totalGastos = euros(array_sum(array_column($gastos, '_total')));

    $ingresos = [];
    $resto = 0.0;
    foreach ($hoja['ingresos'] ?? [] as $l) {
        $r = empty($l['cuadrar']) ? $calc($l) : $l;
        if (empty($l['cuadrar'])) $resto += $r['_total'];
        $ingresos[] = $r;
    }
    $cuadrado = false;
    foreach ($ingresos as &$l) {
        if (!empty($l['cuadrar'])) {
            $imp = $cuadrado ? 0.0 : euros($totalGastos - $resto);
            $cuadrado = true;
            $l += ['_cantidad' => 1, '_precio' => $imp, '_total' => $imp];
        }
    }
    unset($l);
    $totalIngresos = euros(array_sum(array_column($ingresos, '_total')));

    return [
        'ingresos'       => $ingresos,
        'gastos'         => $gastos,
        'total_ingresos' => $totalIngresos,
        'total_gastos'   => $totalGastos,
        'resultado'      => euros($totalIngresos - $totalGastos),
    ];
}

/**
 * Informe económico de una actividad: ingresos por actividades, participación
 * de la Zona, gastos por categoría (según las facturas) y control de facturas.
 */
function calcular_informe(array $act): array
{
    $inf = $act['informe'] ?? [];
    $facturas = $inf['facturas'] ?? [];
    $cats = array_fill_keys(array_keys(CATEGORIAS_INFORME), 0.0);
    foreach ($facturas as $f) {
        $c = isset($cats[$f['categoria'] ?? '']) ? $f['categoria'] : 'otros';
        $cats[$c] += (float) ($f['importe'] ?? 0);
    }
    $cats = array_map('euros', $cats);
    $totalGastos = euros(array_sum($cats));

    // Por defecto, ingresos = ingresos del balance que no son aportación de la Zona.
    $bal = calcular_hoja($act['balance'] ?? [], $act);
    $ingAuto = 0.0;
    foreach ($bal['ingresos'] as $l) if (empty($l['zona'])) $ingAuto += $l['_total'];
    $ingresos = ($inf['ingresos_actividad'] ?? null) !== null ? (float) $inf['ingresos_actividad'] : euros($ingAuto);
    // Por defecto, la Zona pone lo que falte para cubrir los gastos.
    $partAuto = max(0.0, euros($totalGastos - $ingresos));
    $part = ($inf['participacion'] ?? null) !== null ? (float) $inf['participacion'] : $partAuto;

    return [
        'ingresos_actividad'      => euros($ingresos),
        'ingresos_actividad_auto' => euros($ingAuto),
        'participacion'           => euros($part),
        'participacion_auto'      => $partAuto,
        'total_ingresos'          => euros($ingresos + $part),
        'categorias'              => $cats,
        'total_gastos'            => $totalGastos,
        'facturas'                => $facturas,
    ];
}

function actividad(array $d, string $id): ?array
{
    foreach ($d['actividades'] as $a) if ($a['id'] === $id) return $a;
    return null;
}

function actividades_curso(array $d, string $curso): array
{
    $l = array_values(array_filter($d['actividades'],
        fn($a) => limpiar_fecha($a['fecha'] ?? '') && curso_de_fecha($a['fecha']) === $curso));
    usort($l, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    return $l;
}

/* ---------------------------------------------------------------------------
 *  Transporte a los Consejos Juniors de Zona
 * ------------------------------------------------------------------------- */

function lugar(array $d, ?string $id): ?array
{
    foreach ($d['lugares'] as $l) if ($l['id'] === $id) return $l;
    return null;
}

function tarifa(array $d, ?string $lugarId, string $miembroId): float
{
    $l = lugar($d, $lugarId);
    return $l ? (float) ($l['tarifas'][$miembroId] ?? 0) : 0.0;
}

function consejos_curso(array $d, string $curso): array
{
    $l = array_values(array_filter($d['consejos'],
        fn($c) => limpiar_fecha($c['fecha'] ?? '') && curso_de_fecha($c['fecha']) === $curso));
    usort($l, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    return $l;
}

/**
 * Importe de un consejo. Celebrado: lo apuntado a cada asistente.
 * Previsto: se supone que van todos los miembros activos (para el presupuesto).
 */
function consejo_total(array $d, array $c): float
{
    if (empty($c['pagar'])) return 0.0;
    if (($c['estado'] ?? 'celebrado') === 'previsto') {
        $t = 0.0;
        foreach (miembros_activos($d) as $m) $t += tarifa($d, $c['lugar'] ?? null, $m['id']);
        return euros($t);
    }
    return euros(array_sum(array_map('floatval', $c['asistentes'] ?? [])));
}

/** "CJZ - 27/09/2025 (Èxode)" */
function consejo_etiqueta(array $d, array $c): string
{
    $l = lugar($d, $c['lugar'] ?? null);
    return ($c['nombre'] ?: 'CJZ') . ' - ' . fecha_corta($c['fecha']) . ($l ? ' (' . $l['nombre'] . ')' : '');
}

/** Total por miembro en una liquidación: [miembroId => importe]. */
function liquidacion_por_miembro(array $d, array $q): array
{
    $tot = [];
    foreach ($d['consejos'] as $c) {
        if (!in_array($c['id'], $q['consejos'] ?? [], true) || empty($c['pagar'])) continue;
        if (($c['estado'] ?? 'celebrado') !== 'celebrado') continue;
        foreach ($c['asistentes'] ?? [] as $mid => $imp) $tot[$mid] = euros(($tot[$mid] ?? 0) + (float) $imp);
    }
    return $tot;
}

/* ---------------------------------------------------------------------------
 *  Cuota de zona
 * ------------------------------------------------------------------------- */

function cuota_defecto(string $curso): array
{
    $a2 = (int) substr($curso, 5, 4);
    return [
        'precio'             => 0.0,
        'censo'              => [],
        'referencia'         => ['titulo' => 'Cuota curso anterior', 'precio' => 0.0, 'censo' => []],
        'gastos'             => [],
        'titulo_presupuesto' => "Presupuesto $a2 Zona Interior",
        'escenarios'         => [],
        'nota_aprobacion'    => '',
        'fecha_limite'       => '',
        'fecha_emision'      => '',
        'cobros'             => [],
    ];
}

/** Cuota de un curso (con valores por defecto; si no existe, parte del curso anterior). */
function cuota_curso(array $d, string $curso): array
{
    if (isset($d['cuotas'][$curso])) {
        $c = $d['cuotas'][$curso] + cuota_defecto($curso);
        $c['referencia'] = ($c['referencia'] ?? []) + cuota_defecto($curso)['referencia'];
        return $c;
    }
    $c = cuota_defecto($curso);
    $a = (int) substr($curso, 0, 4);
    $ant = ($a - 1) . '-' . $a;
    if (isset($d['cuotas'][$ant])) {
        $p = $d['cuotas'][$ant];
        $c['precio'] = (float) ($p['precio'] ?? 0);
        $c['referencia'] = ['titulo' => 'Cuota ' . curso_barra($ant), 'precio' => (float) ($p['precio'] ?? 0),
            'censo' => $p['censo'] ?? []];
        $c['censo'] = $p['censo'] ?? [];
        $c['gastos'] = $p['gastos'] ?? [];
    }
    return $c;
}

/** Filas de la cuota por centro y totales. */
function calcular_cuota(array $d, array $cuota, ?float $precio = null, ?array $censo = null): array
{
    $precio = $precio ?? (float) $cuota['precio'];
    $censo = $censo ?? $cuota['censo'];
    $ref = $cuota['referencia'];
    $filas = [];
    $totC = 0;
    $totI = 0.0;
    foreach (miembros_activos($d, 'centro') as $m) {
        $n = (int) ($censo[$m['id']] ?? 0);
        $imp = euros($n * $precio);
        $impRef = euros((int) ($ref['censo'][$m['id']] ?? 0) * (float) $ref['precio']);
        $filas[] = [
            'miembro'   => $m,
            'censo'     => $n,
            'importe'   => $imp,
            'referencia'=> $impRef,
            'variacion' => $impRef > 0 ? round(($imp - $impRef) / $impRef * 100, 2) : null,
        ];
        $totC += $n;
        $totI += $imp;
    }
    $gastos = 0.0;
    foreach ($cuota['gastos'] as $g) $gastos += (float) ($g['cantidad'] ?? 0) * (float) ($g['precio'] ?? 0);
    return [
        'precio'       => $precio,
        'filas'        => $filas,
        'total_censo'  => $totC,
        'total'        => euros($totI),
        'total_gastos' => euros($gastos),
        'margen'       => euros($totI - $gastos),
    ];
}

/* ---------------------------------------------------------------------------
 *  Saneado de lo que llega del navegador
 * ------------------------------------------------------------------------- */

function sanear_ajustes($a): array
{
    $a = is_array($a) ? $a : [];
    $r = [];
    foreach (ajustes_defecto() as $k => $def) $r[$k] = limpiar_texto($a[$k] ?? $def, 120);
    return $r;
}

/** Sustituye la lista de miembros. Los nuevos (sin id) reciben uno. */
function sanear_miembros($lista, array $existentes): array
{
    $res = [];
    $todos = $existentes;
    foreach (is_array($lista) ? $lista : [] as $m) {
        if (!is_array($m)) continue;
        $nombre = limpiar_texto($m['nombre'] ?? '', 80);
        if ($nombre === '') continue;
        $id = limpiar_id($m['id'] ?? '');
        if ($id === '' || $id[0] !== 'm') {
            $id = nuevo_id('m', $todos);
        }
        $todos[] = ['id' => $id];
        $res[] = [
            'id'        => $id,
            'nombre'    => $nombre,
            'numero'    => limpiar_texto($m['numero'] ?? '', 12),
            'localidad' => limpiar_texto($m['localidad'] ?? '', 80),
            'tipo'      => ($m['tipo'] ?? '') === 'otro' ? 'otro' : 'centro',
            'iban'      => limpiar_texto($m['iban'] ?? '', 40),
            'activo'    => ($m['activo'] ?? true) !== false,
        ];
    }
    return $res;
}

function sanear_mapa_numeros($m, bool $enteros = false): array
{
    $r = [];
    foreach (is_array($m) ? $m : [] as $k => $v) {
        if (!limpiar_id($k)) continue;
        $n = leer_numero($v);
        if ($n === null) continue;
        $r[$k] = $enteros ? (int) round($n) : euros($n);
    }
    return $r;
}

function sanear_lugares($lista, array $existentes): array
{
    $res = [];
    $todos = $existentes;
    foreach (is_array($lista) ? $lista : [] as $l) {
        if (!is_array($l)) continue;
        $nombre = limpiar_texto($l['nombre'] ?? '', 80);
        if ($nombre === '') continue;
        $id = limpiar_id($l['id'] ?? '');
        if ($id === '' || $id[0] !== 'l') $id = nuevo_id('l', $todos);
        $todos[] = ['id' => $id];
        $res[] = [
            'id'      => $id,
            'nombre'  => $nombre,
            'miembro' => limpiar_id($l['miembro'] ?? '') ?: null,
            'tarifas' => sanear_mapa_numeros($l['tarifas'] ?? []),
        ];
    }
    return $res;
}

function sanear_consejo($c): array
{
    $c = is_array($c) ? $c : [];
    $fecha = limpiar_fecha($c['fecha'] ?? '');
    if (!$fecha) throw new InvalidArgumentException('La fecha del consejo no es válida');
    return [
        'id'         => limpiar_id($c['id'] ?? ''),
        'fecha'      => $fecha,
        'nombre'     => limpiar_texto($c['nombre'] ?? 'CJZ', 60) ?: 'CJZ',
        'lugar'      => limpiar_id($c['lugar'] ?? '') ?: null,
        'estado'     => array_key_exists($c['estado'] ?? '', ESTADOS_CONSEJO) ? $c['estado'] : 'celebrado',
        'pagar'      => ($c['pagar'] ?? true) !== false,
        'asistentes' => sanear_mapa_numeros($c['asistentes'] ?? []),
        'notas'      => limpiar_texto($c['notas'] ?? '', 300),
    ];
}

function sanear_liquidacion($q): array
{
    $q = is_array($q) ? $q : [];
    $nombre = limpiar_texto($q['nombre'] ?? '', 80);
    if ($nombre === '') throw new InvalidArgumentException('Ponle un nombre a la liquidación');
    return [
        'id'         => limpiar_id($q['id'] ?? ''),
        'nombre'     => $nombre,
        'consejos'   => array_values(array_filter(array_map('limpiar_id', (array) ($q['consejos'] ?? [])))),
        'fecha'      => limpiar_fecha($q['fecha'] ?? ''),
        'pagada'     => !empty($q['pagada']),
        'movimiento' => limpiar_id($q['movimiento'] ?? '') ?: null,
    ];
}

function sanear_lineas($lista, bool $ingresos): array
{
    $res = [];
    foreach (is_array($lista) ? $lista : [] as $l) {
        if (!is_array($l)) continue;
        $r = [
            'concepto' => limpiar_texto($l['concepto'] ?? '', 150),
            'cantidad' => round(numero($l['cantidad'] ?? 1, 1), 3),
            'precio'   => euros(numero($l['precio'] ?? 0)),
        ];
        $det = [];
        foreach (is_array($l['detalle'] ?? null) ? $l['detalle'] : [] as $x) {
            if (!is_array($x)) continue;
            $det[] = ['concepto' => limpiar_texto($x['concepto'] ?? '', 150), 'importe' => euros(numero($x['importe'] ?? 0))];
        }
        if ($det) $r['detalle'] = $det;
        if ($g = limpiar_id($l['grupo'] ?? '')) $r['grupo'] = $g;
        if ($ingresos && !empty($l['cuadrar'])) $r['cuadrar'] = true;
        if ($ingresos && !empty($l['zona'])) $r['zona'] = true;
        if ($m = limpiar_id($l['mov'] ?? '')) $r['mov'] = $m;
        $res[] = $r;
    }
    return $res;
}

function sanear_hoja($h): array
{
    $h = is_array($h) ? $h : [];
    return [
        'ingresos'   => sanear_lineas($h['ingresos'] ?? [], true),
        'gastos'     => sanear_lineas($h['gastos'] ?? [], false),
        'comentario' => limpiar_parrafo($h['comentario'] ?? ''),
    ];
}

function sanear_actividad($a): array
{
    $a = is_array($a) ? $a : [];
    $nombre = limpiar_texto($a['nombre'] ?? '', 120);
    if ($nombre === '') throw new InvalidArgumentException('La actividad necesita un nombre');
    $fecha = limpiar_fecha($a['fecha'] ?? '');
    if (!$fecha) throw new InvalidArgumentException('La fecha de la actividad no es válida');

    $grupos = [];
    foreach (is_array($a['centros'] ?? null) ? $a['centros'] : [] as $g) {
        if (!is_array($g)) continue;
        $filas = [];
        foreach (is_array($g['filas'] ?? null) ? $g['filas'] : [] as $f) {
            if (!is_array($f)) continue;
            $fila = [
                'miembro'  => limpiar_id($f['miembro'] ?? '') ?: null,
                'cantidad' => round(numero($f['cantidad'] ?? 0), 3),
            ];
            if (!$fila['miembro']) $fila['nombre'] = limpiar_texto($f['nombre'] ?? '', 80);
            if (isset($f['precio']) && ($p = leer_numero($f['precio'])) !== null) $fila['precio'] = euros($p);
            $filas[] = $fila;
        }
        $grupos[] = [
            'id'     => limpiar_id($g['id'] ?? '') ?: nuevo_id('g', $grupos),
            'nombre' => limpiar_texto($g['nombre'] ?? '', 60),
            'precio' => euros(numero($g['precio'] ?? 0)),
            'filas'  => $filas,
        ];
    }

    $inf = is_array($a['informe'] ?? null) ? $a['informe'] : [];
    $facturas = [];
    foreach (is_array($inf['facturas'] ?? null) ? $inf['facturas'] : [] as $f) {
        if (!is_array($f)) continue;
        $facturas[] = [
            'id'        => limpiar_id($f['id'] ?? '') ?: nuevo_id('f', $facturas),
            'numero'    => limpiar_texto($f['numero'] ?? '', 40),
            'fecha'     => limpiar_fecha($f['fecha'] ?? ''),
            'cif'       => limpiar_texto($f['cif'] ?? '', 20),
            'razon'     => limpiar_texto($f['razon'] ?? '', 120),
            'concepto'  => limpiar_texto($f['concepto'] ?? '', 120),
            'importe'   => euros(numero($f['importe'] ?? 0)),
            'categoria' => array_key_exists($f['categoria'] ?? '', CATEGORIAS_INFORME) ? $f['categoria'] : 'otros',
            'mov'       => limpiar_id($f['mov'] ?? '') ?: null,
        ];
    }
    $opt = fn($v) => ($v === null || $v === '') ? null : leer_numero($v);

    return [
        'id'          => limpiar_id($a['id'] ?? ''),
        'nombre'      => $nombre,
        'fecha'       => $fecha,
        'fecha_fin'   => limpiar_fecha($a['fecha_fin'] ?? ''),
        'lugar'       => limpiar_texto($a['lugar'] ?? '', 120),
        'presupuesto' => sanear_hoja($a['presupuesto'] ?? []),
        'balance'     => sanear_hoja($a['balance'] ?? []),
        'centros'     => $grupos,
        'informe'     => [
            'incluir'            => ($inf['incluir'] ?? true) !== false,
            'ingresos_actividad' => $opt($inf['ingresos_actividad'] ?? null),
            'participacion'      => $opt($inf['participacion'] ?? null),
            'facturas'           => $facturas,
        ],
    ];
}

function sanear_cuota($c, string $curso): array
{
    $c = is_array($c) ? $c : [];
    $def = cuota_defecto($curso);
    $ref = is_array($c['referencia'] ?? null) ? $c['referencia'] : [];
    $gastos = [];
    foreach (is_array($c['gastos'] ?? null) ? $c['gastos'] : [] as $g) {
        if (!is_array($g)) continue;
        $gastos[] = [
            'concepto' => limpiar_texto($g['concepto'] ?? '', 150),
            'cantidad' => round(numero($g['cantidad'] ?? 1, 1), 3),
            'precio'   => euros(numero($g['precio'] ?? 0)),
        ];
    }
    $esc = [];
    foreach (is_array($c['escenarios'] ?? null) ? $c['escenarios'] : [] as $e) {
        if (!is_array($e)) continue;
        $esc[] = [
            'id'     => limpiar_id($e['id'] ?? '') ?: nuevo_id('e', $esc),
            'nombre' => limpiar_texto($e['nombre'] ?? '', 80),
            'precio' => round(numero($e['precio'] ?? 0), 4),
            'censo'  => sanear_mapa_numeros($e['censo'] ?? [], true),
        ];
    }
    $cobros = [];
    foreach (is_array($c['cobros'] ?? null) ? $c['cobros'] : [] as $k => $v) {
        if (limpiar_id($k) && limpiar_id($v)) $cobros[$k] = $v;
    }
    return [
        'precio'             => round(numero($c['precio'] ?? 0), 4),
        'censo'              => sanear_mapa_numeros($c['censo'] ?? [], true),
        'referencia'         => [
            'titulo' => limpiar_texto($ref['titulo'] ?? $def['referencia']['titulo'], 80),
            'precio' => round(numero($ref['precio'] ?? 0), 4),
            'censo'  => sanear_mapa_numeros($ref['censo'] ?? [], true),
        ],
        'gastos'             => $gastos,
        'titulo_presupuesto' => limpiar_texto($c['titulo_presupuesto'] ?? $def['titulo_presupuesto'], 100),
        'escenarios'         => $esc,
        'nota_aprobacion'    => limpiar_texto($c['nota_aprobacion'] ?? '', 300),
        'fecha_limite'       => limpiar_fecha($c['fecha_limite'] ?? ''),
        'fecha_emision'      => limpiar_fecha($c['fecha_emision'] ?? ''),
        'cobros'             => $cobros,
    ];
}
