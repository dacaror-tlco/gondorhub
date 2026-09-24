<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
require_once __DIR__ . '/includes/doc.php';
exigir_login();

$zona = cargar_zona();
$curso = curso_preferido();
$cuota = cuota_curso($zona, $curso);
$aplicado = calcular_cuota($zona, $cuota);
$ref = calcular_cuota($zona, $cuota, (float) $cuota['referencia']['precio'], $cuota['referencia']['censo']);

/** Tabla de ingresos por cuota de un modelo. */
function tabla_modelo(string $titulo, array $r, bool $conVariacion): void
{ ?>
  <table class="doc-tabla doc-tabla--estrecha">
    <tr class="dt-titulo"><th colspan="<?= $conVariacion ? 5 : 4 ?>"><?= h(mayusculas($titulo)) ?></th></tr>
    <tr class="dt-bloque"><th colspan="3">INGRESOS</th><th class="num"><?= formato_euros($r['total']) ?></th><?php if ($conVariacion): ?><th class="dt-vacia"></th><?php endif; ?></tr>
    <tr class="dt-cab"><th>CONCEPTO</th><th>CANTIDAD</th><th>PRECIO</th><th>TOTAL</th><?php if ($conVariacion): ?><th>VARIACIÓN</th><?php endif; ?></tr>
    <?php foreach ($r['filas'] as $f): ?>
      <tr class="dt-fila">
        <td>CUOTA <?= h(mayusculas($f['miembro']['nombre'])) ?></td>
        <td class="centro"><?= $f['censo'] ?></td>
        <td class="centro"><?= formato_euros($r['precio']) ?></td>
        <td class="centro"><?= formato_euros($f['importe']) ?></td>
        <?php if ($conVariacion): ?><td class="centro"><?= $f['variacion'] === null ? '—' : formato_pct($f['variacion']) ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <tr class="dt-total">
      <th>TOTAL</th><th class="centro"><?= $r['total_censo'] ?></th>
      <?php if ($conVariacion): ?>
        <th class="centro">MARGEN</th><th class="centro<?= $r['margen'] < 0 ? ' negativo' : '' ?>"><?= formato_euros($r['margen']) ?></th><th class="dt-vacia"></th>
      <?php else: ?>
        <th></th><th></th>
      <?php endif; ?>
    </tr>
  </table>
<?php }

$pagina = 'cuota';
$titulo = 'Cálculo de la cuota ' . curso_barra($curso);
$claseBody = 'pagina-doc';
require __DIR__ . '/includes/layout.php';
barra_documento("cuota.php?curso=$curso", 'Volver a la cuota');
?>

<article class="doc">
  <header class="doc-logo"><img src="assets/img/zona-interior-vi.png" alt="Juniors Zona Interior"></header>

  <table class="doc-tabla doc-tabla--estrecha">
    <tr class="dt-titulo"><th colspan="4"><?= h(mayusculas($cuota['titulo_presupuesto'])) ?></th></tr>
    <?php filas_bloque('Gastos', $aplicado['total_gastos'], array_map(
        fn($g) => [$g['concepto'], (float) $g['cantidad'], (float) $g['precio'], euros($g['cantidad'] * $g['precio'])],
        $cuota['gastos'])); ?>
  </table>

  <?php if ($ref['total'] > 0) tabla_modelo('Ingresos ' . $cuota['referencia']['titulo'] . ' Zona Interior', $ref, false); ?>

  <?php tabla_modelo('Cuota Zona Interior ' . curso_barra($curso), $aplicado, true); ?>

  <?php foreach ($cuota['escenarios'] as $e): ?>
    <div class="doc-salto-evitar">
      <?php tabla_modelo('Modelo: ' . $e['nombre'], calcular_cuota($zona, $cuota, (float) $e['precio'], $e['censo']), true); ?>
    </div>
  <?php endforeach; ?>
</article>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
