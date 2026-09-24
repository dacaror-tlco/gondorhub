<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
require_once __DIR__ . '/includes/doc.php';
exigir_login();

/** Fichas de adjudicación de la cuota: todas (una por página) o la de ?centro=m002. */

$zona = cargar_zona();
$curso = curso_preferido();
$cuota = cuota_curso($zona, $curso);
$calc = calcular_cuota($zona, $cuota);
$aj = $zona['ajustes'];
$soloCentro = limpiar_id($_GET['centro'] ?? '');
$filas = array_values(array_filter($calc['filas'], fn($f) => !$soloCentro || $f['miembro']['id'] === $soloCentro));

$cursoTxt = curso_barra($curso);
$precioTxt = number_format($calc['precio'], 2, ',', '.') . ' €';
$euros0 = fn($v) => number_format($v, 2, ',', '.') . '€';

$pagina = 'cuota';
$titulo = 'Fichas de adjudicación ' . $cursoTxt;
$claseBody = 'pagina-doc';
require __DIR__ . '/includes/layout.php';

$selector = '<select onchange="location.href=\'?curso=' . $curso . '&centro=\'+this.value" aria-label="Centro">'
    . '<option value="">Todos los centros</option>'
    . implode('', array_map(fn($f) => '<option value="' . $f['miembro']['id'] . '"' . ($f['miembro']['id'] === $soloCentro ? ' selected' : '') . '>'
        . h($f['miembro']['nombre']) . '</option>', $calc['filas']))
    . '</select>';
barra_documento("cuota.php?curso=$curso", 'Volver a la cuota', $selector);

$faltan = array_filter([
    $calc['precio'] > 0 ? '' : 'el precio por miembro',
    $aj['iban'] ? '' : 'la cuenta bancaria de la zona (Ajustes)',
    $cuota['fecha_limite'] ? '' : 'la fecha límite de pago',
]);
if ($faltan): ?>
  <p class="aviso-info no-imprimir">Falta rellenar <?= h(implode(', ', $faltan)) ?>.</p>
<?php endif; ?>

<?php foreach ($filas as $f): $m = $f['miembro']; ?>
  <article class="doc ficha-adjudicacion">
    <header class="fa-cab">
      <img src="assets/img/zona-interior.png" alt="Juniors Zona Interior" class="fa-logo">
      <h1>FICHA DE ADJUDICACIÓN<br>CUOTA ZONA INTERIOR<br><?= $cursoTxt ?></h1>
    </header>

    <div class="fa-centro">
      <span><?= h(mayusculas($m['nombre'])) ?></span>
      <span><?= h(strtolower($m['numero'])) ?></span>
    </div>

    <div class="fa-banda">ADJUDICACIÓN CUOTA ZONA INTERIOR <?= $cursoTxt ?></div>

    <div class="fa-importe"><span>IMPORTE TOTAL</span><span><?= $euros0($f['importe']) ?></span></div>

    <table class="fa-caja">
      <tr><th>CENSO PRESENTADO DURANTE EL CURSO</th><td class="fa-verde"><?= h($curso) ?></td></tr>
      <tr><td colspan="2" class="fa-nota">NOTA: Esta cuota se ha asignado de acuerdo con los datos existentes en la Secretaría Virtual sobre el número de censados a fecha de dicha adjudicación.</td></tr>
    </table>

    <table class="fa-caja">
      <tr><th colspan="3">IMPORTE DE LA CUOTA</th></tr>
      <tr><td class="fa-num"><?= $f['censo'] ?></td><td class="fa-gris">MIEMBROS x <?= $precioTxt ?> =</td><td class="fa-verde"><?= $euros0($f['importe']) ?></td></tr>
      <?php if ($cuota['nota_aprobacion']): ?>
        <tr><td colspan="3" class="fa-nota">NOTA: La cantidad que corresponde por miembro fue aprobada en <?= h(rtrim($cuota['nota_aprobacion'], '.')) ?>.</td></tr>
      <?php endif; ?>
    </table>

    <table class="fa-caja">
      <tr><th>FECHA LÍMITE PARA EL PAGO DE LA CUOTA</th></tr>
      <tr><td class="fa-verde"><?= h(mayusculas(fecha_larga($cuota['fecha_limite']))) ?></td></tr>
      <tr><td class="fa-nota">NOTA: El pago se realizará por transferencia bancaria a la cuenta
        <span class="fa-verde-txt"><?= h($aj['iban']) ?><?= $aj['banco'] ? ' (' . h($aj['banco']) . ')' : '' ?></span>
        con el concepto <span class="fa-verde-txt">CUOTA ZONA INTERIOR <?= h(mayusculas($m['nombre'])) ?></span>.</td></tr>
    </table>

    <p class="fa-fecha"><?= fecha_larga($cuota['fecha_emision'] ?: date('Y-m-d')) ?></p>

    <footer class="fa-firmas">
      <div><strong><?= h($aj['tesorero']) ?></strong><br><?= h($aj['cargo_tesorero']) ?></div>
      <div><strong><?= h($aj['delegado']) ?></strong><br><?= h($aj['cargo_delegado']) ?></div>
    </footer>
  </article>
<?php endforeach; ?>

<?php if (!$filas): ?>
  <p class="aviso-info">No hay centros activos. Añádelos en <a href="ajustes.php">Ajustes</a>.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
