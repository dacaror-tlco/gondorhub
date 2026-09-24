<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
require_once __DIR__ . '/includes/doc.php';
exigir_login();

/**
 * ?curso=2025-2026  → tabla del curso (como la hoja «Transporte Zona Interior»)
 * ?liq=q001         → hoja de pago de una liquidación, con las cuentas bancarias
 */

$zona = cargar_zona();
$liq = null;
foreach ($zona['liquidaciones'] as $q) if ($q['id'] === limpiar_id($_GET['liq'] ?? '')) $liq = $q;

if ($liq) {
    $consejos = array_values(array_filter($zona['consejos'], fn($c) => in_array($c['id'], $liq['consejos'], true) && $c['estado'] === 'celebrado'));
    usort($consejos, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    $curso = $consejos ? curso_de_fecha($consejos[0]['fecha']) : curso_preferido();
    $bloques = [[$liq['nombre'], $consejos]];
} else {
    $curso = curso_preferido();
    $todos = consejos_curso($zona, $curso);
    // Agrupamos los consejos por liquidación, en orden; los que no tienen, al final.
    $bloques = [];
    $usados = [];
    foreach ($zona['liquidaciones'] as $q) {
        $cs = array_values(array_filter($todos, fn($c) => in_array($c['id'], $q['consejos'], true)));
        if ($cs) {
            $bloques[] = [$q['nombre'], $cs];
            foreach ($cs as $c) $usados[] = $c['id'];
        }
    }
    $resto = array_values(array_filter($todos, fn($c) => !in_array($c['id'], $usados, true)));
    if ($resto) $bloques[] = [$bloques ? 'Sin liquidar' : 'Total', $resto];
}

// Filas: miembros activos y los que tengan algún importe.
$idsConImporte = [];
foreach ($bloques as [, $cs]) foreach ($cs as $c) foreach (array_keys($c['asistentes']) as $mid) $idsConImporte[$mid] = true;
$filas = array_values(array_filter($zona['miembros'], fn($m) => ($m['activo'] ?? true) && !$liq || isset($idsConImporte[$m['id']])));

/** Importe de un miembro en un consejo (celebrado) o tarifa si está previsto. */
$importe = function (array $c, string $mid) use ($zona): ?float {
    if (($c['estado'] ?? '') === 'previsto') return null;
    return isset($c['asistentes'][$mid]) ? (float) $c['asistentes'][$mid] : 0.0;
};

$pagina = 'transporte';
$titulo = $liq ? 'Hoja de pago · ' . $liq['nombre'] : 'Transporte CJZ ' . curso_barra($curso);
$claseBody = 'pagina-doc doc-apaisado';
require __DIR__ . '/includes/layout.php';
barra_documento("transporte.php?curso=$curso", 'Volver a transporte');
?>

<article class="doc doc--ancho">
  <header class="doc-logo"><img src="assets/img/zona-interior-vi.png" alt="Juniors Zona Interior"></header>
  <table class="doc-tabla doc-transporte">
    <?php $ncol = 1 + array_sum(array_map(fn($b) => count($b[1]) + 1, $bloques)) + ($liq ? 1 : 0) + (!$liq && count($bloques) > 1 ? 1 : 0); ?>
    <tr class="dt-titulo"><th colspan="<?= $ncol ?>">
      TRANSPORTE CONSEJOS JUNIORS DE ZONA · <?= $liq ? h(mayusculas($liq['nombre'])) . ' · ' : '' ?>CURSO <?= curso_barra($curso) ?>
    </th></tr>
    <tr class="dt-cab">
      <th>MIEMBROS DE LA ZONA</th>
      <?php foreach ($bloques as [$nombre, $cs]): ?>
        <?php foreach ($cs as $c): $l = lugar($zona, $c['lugar']); ?>
          <th><?= h(mayusculas($c['nombre'])) ?> - <?= fecha_corta($c['fecha']) ?><?= $l ? '<br>(' . h(mayusculas($l['nombre'])) . ')' : '' ?><?= $c['estado'] === 'previsto' ? '<br><small>PREVISTO</small>' : '' ?></th>
        <?php endforeach; ?>
        <th class="dt-subtotal"><?= h(mayusculas($nombre)) ?></th>
      <?php endforeach; ?>
      <?php if (!$liq && count($bloques) > 1): ?><th class="dt-subtotal">TOTAL CURSO</th><?php endif; ?>
      <?php if ($liq): ?><th>NÚMERO DE CUENTA</th><?php endif; ?>
    </tr>
    <?php
    $totBloque = array_fill(0, count($bloques), 0.0);
    $totConsejo = [];
    foreach ($filas as $m):
      $totFila = 0.0; ?>
      <tr class="dt-fila">
        <td class="izq"><?= h(mayusculas(($m['tipo'] === 'centro' ? 'Centro Juniors ' : '') . $m['nombre'])) ?></td>
        <?php foreach ($bloques as $bi => [, $cs]):
          $sub = 0.0;
          foreach ($cs as $c):
            $v = $importe($c, $m['id']);
            $sub += (float) $v;
            $totConsejo[$c['id']] = ($totConsejo[$c['id']] ?? 0) + (float) $v; ?>
            <td class="centro"><?= $v === null ? '<span class="gris">—</span>' : formato_euros($v) ?></td>
          <?php endforeach;
          $totBloque[$bi] += $sub;
          $totFila += $sub; ?>
          <td class="centro dt-subtotal"><?= formato_euros($sub) ?></td>
        <?php endforeach; ?>
        <?php if (!$liq && count($bloques) > 1): ?><td class="centro dt-subtotal"><?= formato_euros($totFila) ?></td><?php endif; ?>
        <?php if ($liq): ?><td class="centro nowrap"><?= h($m['iban'] ?: '—') ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <tr class="dt-total">
      <th>TOTAL</th>
      <?php foreach ($bloques as $bi => [, $cs]): ?>
        <?php foreach ($cs as $c): ?><th class="centro"><?= $c['estado'] === 'previsto' ? '' : formato_euros($totConsejo[$c['id']] ?? 0) ?></th><?php endforeach; ?>
        <th class="centro"><?= formato_euros($totBloque[$bi]) ?></th>
      <?php endforeach; ?>
      <?php if (!$liq && count($bloques) > 1): ?><th class="centro"><?= formato_euros(array_sum($totBloque)) ?></th><?php endif; ?>
      <?php if ($liq): ?><th></th><?php endif; ?>
    </tr>
  </table>
  <?php if ($liq && $liq['fecha']): ?>
    <p class="doc-fecha"><?= fecha_larga($liq['fecha']) ?></p>
  <?php endif; ?>
</article>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
