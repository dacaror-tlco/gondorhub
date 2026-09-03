<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
exigir_login();

$data = cargar_datos();
$resumen = calcular_resumen($data);

$nEducadores = 0;
$nColaboradores = 0;
foreach ($data['personas'] as $p) {
    if (($p['activo'] ?? true) === false) continue;
    if (($p['categoria'] ?? '') === 'colaborador') $nColaboradores++;
    else $nEducadores++;
}

// Media de asistencia por grupo (solo personas activas con actividades aplicables)
function media_grupo(array $personas, string $cat): ?float
{
    $suma = 0.0; $n = 0;
    foreach ($personas as $p) {
        if ($p['categoria'] !== $cat || !$p['activo'] || $p['aplicables'] === 0) continue;
        $suma += $p['porcentaje']; $n++;
    }
    return $n ? round($suma / $n, 1) : null;
}
$mediaEdu = media_grupo($resumen['personas'], 'educador');
$mediaCol = media_grupo($resumen['personas'], 'colaborador');

// Próximas / últimas actividades
$acts = $data['actividades'];
usort($acts, fn($a, $b) => strcmp($b['fecha'] ?: '0000', $a['fecha'] ?: '0000'));
$ultimas = array_slice($acts, 0, 5);

$pagina = 'index';
$titulo = 'Inicio';
require __DIR__ . '/includes/layout.php';
?>

<section class="hero">
  <div class="hero__texto">
    <h1>Control de asistencia</h1>
    <p>Registra qué educadores y colaboradores asisten a cada actividad y consulta el resumen del curso.</p>
  </div>
  <img src="assets/img/campana-2026-27.png" alt="Campaña Que es faça en mi" class="hero__campana">
</section>

<section class="tarjetas-num">
  <div class="tarjeta-num">
    <span class="tarjeta-num__valor"><?= (int) $resumen['totales']['actividades'] ?></span>
    <span class="tarjeta-num__etq">Actividades registradas</span>
  </div>
  <div class="tarjeta-num">
    <span class="tarjeta-num__valor"><?= $nEducadores ?></span>
    <span class="tarjeta-num__etq">Educadores activos</span>
  </div>
  <div class="tarjeta-num">
    <span class="tarjeta-num__valor"><?= $nColaboradores ?></span>
    <span class="tarjeta-num__etq">Colaboradores activos</span>
  </div>
  <div class="tarjeta-num">
    <span class="tarjeta-num__valor"><?= $mediaEdu === null ? '—' : $mediaEdu . '%' ?></span>
    <span class="tarjeta-num__etq">Media asistencia educadores</span>
  </div>
  <div class="tarjeta-num">
    <span class="tarjeta-num__valor"><?= $mediaCol === null ? '—' : $mediaCol . '%' ?></span>
    <span class="tarjeta-num__etq">Media asistencia colaboradores</span>
  </div>
</section>

<section class="accesos">
  <a class="acceso" href="actividades.php">
    <h2>Actividades</h2>
    <p>Crear una actividad nueva y marcar quién ha asistido.</p>
    <span class="acceso__ir">Ir a actividades →</span>
  </a>
  <a class="acceso" href="personas.php">
    <h2>Personas</h2>
    <p>Añadir, editar o dar de baja a educadores y colaboradores.</p>
    <span class="acceso__ir">Ir a personas →</span>
  </a>
  <a class="acceso" href="resumen.php">
    <h2>Resumen</h2>
    <p>Ver actividades totales, asistidas y porcentaje de cada persona.</p>
    <span class="acceso__ir">Ir al resumen →</span>
  </a>
</section>

<?php if ($ultimas): ?>
<section class="panel">
  <h2>Últimas actividades</h2>
  <ul class="lista-simple">
    <?php foreach ($ultimas as $a): ?>
      <li>
        <a href="actividades.php#<?= htmlspecialchars($a['id']) ?>">
          <strong><?= htmlspecialchars($a['nombre']) ?></strong>
        </a>
        <span class="lista-simple__meta">
          <?= $a['fecha'] ? htmlspecialchars(date('d/m/Y', strtotime($a['fecha']))) : 'sin fecha' ?>
          · <?= htmlspecialchars(AMBITOS[$a['ambito']] ?? $a['ambito']) ?>
          · <?= count($a['asistentes'] ?? []) ?> asistentes
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="panel">
  <h2>Copia de seguridad</h2>
  <p>Los datos se guardan en la Raspberry en <code>data/data.json</code>. Descarga una copia de vez en cuando.</p>
  <div class="fila-botones">
    <a class="boton boton--secundario" href="api.php?action=export">Exportar copia (.json)</a>
    <label class="boton boton--secundario">
      Importar copia…
      <input type="file" id="importar-archivo" accept="application/json,.json" hidden>
    </label>
  </div>
  <p class="aviso-peligro" id="importar-aviso">
    Importar <strong>sustituye</strong> todos los datos actuales por los del fichero. Exporta antes una copia por si acaso.
  </p>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
