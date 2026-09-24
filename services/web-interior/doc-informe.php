<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
require_once __DIR__ . '/includes/doc.php';
exigir_login();

/** Informe económico: ?curso=2025-2026 (todas las incluidas, una por página) o ?id=a001. */

$zona = cargar_zona();
$id = limpiar_id($_GET['id'] ?? '');
if ($id && ($a = actividad($zona, $id))) {
    $lista = [$a];
    $curso = curso_de_fecha($a['fecha']);
} else {
    $curso = curso_preferido();
    $lista = array_values(array_filter(actividades_curso($zona, $curso), fn($a) => ($a['informe']['incluir'] ?? true) !== false));
}

$pagina = 'informe';
$titulo = 'Informe económico ' . curso_barra($curso);
$claseBody = 'pagina-doc';
require __DIR__ . '/includes/layout.php';
barra_documento("informe.php?curso=$curso", 'Volver al informe');
?>

<?php foreach ($lista as $a): $inf = calcular_informe($a); ?>
  <article class="doc informe-doc">
    <p class="inf-memoria">Memoria de zona</p>
    <h1 class="inf-titulo">INFORME ECONÓMICO DEL PROYECTO<br><?= h(mayusculas($a['nombre'])) ?></h1>

    <h2 class="inf-sub">INGRESOS</h2>
    <table class="inf-resumen">
      <tr><td>Ingresos por actividades</td><td class="num"><?= formato_euros($inf['ingresos_actividad']) ?></td></tr>
      <tr><td>Participación/cuota económica zona</td><td class="num"><?= formato_euros($inf['participacion']) ?></td></tr>
      <tr class="inf-total"><td>TOTAL INGRESOS</td><td class="num"><?= formato_euros($inf['total_ingresos']) ?></td></tr>
    </table>

    <h2 class="inf-sub">GASTOS</h2>
    <table class="inf-resumen">
      <?php foreach (CATEGORIAS_INFORME as $k => $nombre):
        [$principal, $detalle] = array_pad(explode(' (', $nombre, 2), 2, null); ?>
        <tr><td><?= h($principal) ?><?= $detalle ? ' <small>(' . h($detalle) . '</small>' : '' ?></td><td class="num"><?= formato_euros($inf['categorias'][$k]) ?></td></tr>
      <?php endforeach; ?>
      <tr class="inf-total"><td>TOTAL GASTOS</td><td class="num"><?= formato_euros($inf['total_gastos']) ?></td></tr>
    </table>

    <h2 class="inf-sub">CONTROL DE FACTURAS</h2>
    <table class="inf-facturas">
      <thead>
        <tr><th>Nº</th><th>Nº FACTURA</th><th>FECHA</th><th>CIF</th><th>RAZÓN SOCIAL</th><th>CONCEPTO</th><th>CANTIDAD</th></tr>
      </thead>
      <tbody>
        <?php foreach ($inf['facturas'] as $i => $f): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= h($f['numero']) ?></td>
            <td><?= fecha_corta($f['fecha']) ?></td>
            <td><?= h($f['cif']) ?></td>
            <td><?= h($f['razon']) ?></td>
            <td><?= h($f['concepto']) ?></td>
            <td class="num"><?= formato_euros((float) $f['importe']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="6">TOTAL FACTURAS</td><td class="num"><?= formato_euros($inf['total_gastos']) ?></td></tr>
      </tfoot>
    </table>
  </article>
<?php endforeach; ?>

<?php if (!$lista): ?>
  <p class="aviso-info">No hay actividades incluidas en el informe del curso <?= curso_barra($curso) ?>.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
