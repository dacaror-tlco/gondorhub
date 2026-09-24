<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
require_once __DIR__ . '/includes/doc.php';
exigir_login();

$zona = cargar_zona();
$act = actividad($zona, limpiar_id($_GET['id'] ?? ''));
$doc = in_array($_GET['doc'] ?? '', ['presupuesto', 'balance', 'centros'], true) ? $_GET['doc'] : 'balance';
if (!$act) {
    header('Location: actividades.php');
    exit;
}

$anio = substr($act['fecha'], 0, 4);
$nombre = mayusculas($act['nombre']);
$tituloDoc = [
    'presupuesto' => "PRESUPUESTO $nombre $anio - ZONA INTERIOR",
    'balance'     => "BALANCE $nombre $anio - ZONA INTERIOR",
    'centros'     => "INGRESOS $nombre POR CENTROS",
][$doc];

$pagina = 'actividades';
$titulo = ucfirst($doc === 'centros' ? 'Ingresos por centros' : $doc) . ' · ' . $act['nombre'];
$claseBody = 'pagina-doc' . ($doc === 'centros' ? ' doc-apaisado' : '');
require __DIR__ . '/includes/layout.php';

barra_documento("actividad.php?id={$act['id']}#" . ($doc === 'centros' ? 'centros' : $doc), 'Volver a la actividad',
    '<nav class="interruptor" aria-label="Documento">'
    . implode('', array_map(fn($k, $t) => '<a href="?id=' . h($act['id']) . '&amp;doc=' . $k . '"' . ($k === $doc ? ' class="activo"' : '') . "><span>$t</span></a>",
        ['presupuesto', 'balance', 'centros'], ['Presupuesto', 'Balance', 'Por centros']))
    . '</nav>');
?>

<article class="doc">
  <header class="doc-logo"><img src="assets/img/zona-interior-vi.png" alt="Juniors Zona Interior"></header>

<?php if ($doc === 'centros'): ?>
  <?php if (!$act['centros']): ?>
    <p class="aviso-info no-imprimir">Esta actividad no tiene ingresos por centros. Añádelos en la pestaña «Ingresos por centros».</p>
  <?php endif; ?>
  <table class="doc-tabla">
    <tr class="dt-titulo"><th colspan="4"><?= h($tituloDoc) ?></th></tr>
    <?php
    $cantTotal = 0;
    $impTotal = 0.0;
    foreach ($act['centros'] as $g) {
        [$cant, $imp] = grupo_totales($g);
        $cantTotal += $cant;
        $impTotal += $imp;
        $filas = [];
        foreach ($g['filas'] as $f) {
            $m = $f['miembro'] ? miembro($zona, $f['miembro']) : null;
            $p = isset($f['precio']) ? (float) $f['precio'] : (float) $g['precio'];
            $filas[] = [$m ? miembro_etiqueta($m) : ($f['nombre'] ?? ''), (float) $f['cantidad'], $p, euros($f['cantidad'] * $p)];
        }
        filas_bloque($g['nombre'] ?: 'Ingresos', $imp, $filas);
    }
    ?>
    <tr class="dt-total"><th>TOTAL</th><th class="centro"><?= formato_num($cantTotal) ?></th><th></th><th class="centro"><?= formato_euros($impTotal) ?></th></tr>
  </table>

<?php else:
    $hoja = $act[$doc];
    $c = calcular_hoja($hoja, $act);
    $filas = fn($ls) => array_map(fn($l) => [$l['concepto'], (float) $l['_cantidad'], (float) $l['_precio'], (float) $l['_total']], $ls);
    $vacia = !$hoja['ingresos'] && !$hoja['gastos'];
?>
  <?php if ($vacia): ?>
    <p class="aviso-info no-imprimir">Este <?= $doc ?> no tiene líneas todavía. Rellénalo en la <a href="actividad.php?id=<?= h($act['id']) ?>#<?= $doc ?>">actividad</a>.</p>
  <?php endif; ?>
  <table class="doc-tabla">
    <tr class="dt-titulo"><th colspan="4"><?= h($tituloDoc) ?></th></tr>
    <?php filas_bloque('Ingresos', $c['total_ingresos'], $filas($c['ingresos'])); ?>
    <?php filas_bloque('Gastos', $c['total_gastos'], $filas($c['gastos'])); ?>
    <tr class="dt-bloque dt-resultado"><th colspan="3">RESULTADO</th><th class="num<?= $c['resultado'] < 0 ? ' negativo' : '' ?>"><?= formato_euros($c['resultado']) ?></th></tr>
  </table>

  <?php foreach ($c['gastos'] as $l): if (empty($l['detalle'])) continue; ?>
    <table class="doc-tabla doc-desglose">
      <tr class="dt-titulo"><th colspan="2"><?= h(mayusculas($l['concepto'])) ?></th></tr>
      <?php foreach ($l['detalle'] as $x): ?>
        <tr class="dt-fila"><td><?= h(mayusculas($x['concepto'])) ?></td><td class="centro"><?= formato_euros((float) $x['importe']) ?></td></tr>
      <?php endforeach; ?>
      <tr class="dt-bloque"><th>TOTAL <?= h(mayusculas($l['concepto'])) ?></th><th class="centro"><?= formato_euros($l['_total']) ?></th></tr>
    </table>
  <?php endforeach; ?>

  <?php if (trim($hoja['comentario']) !== ''): ?>
    <div class="doc-comentario"><?= texto_comentario($hoja['comentario']) ?></div>
  <?php endif; ?>
<?php endif; ?>
</article>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
