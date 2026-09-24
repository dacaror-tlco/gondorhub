<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tesoreria.php';
exigir_login();

$modo = modo_preferido();
if (isset($_GET['modo'])) {
    setcookie('tes_modo', $modo, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
}

$data = cargar_tesoreria();
$anios = tesoreria_anios($data, $modo);
$anio = (int) ($_GET['anio'] ?? 0);
if ($anio < 2000 || $anio > 2100) $anio = periodo_actual($modo);
if (!in_array($anio, $anios, true)) { $anios[] = $anio; rsort($anios); }

$b = calcular_balance($data, $anio, $modo);
$periodo = $b['etiqueta'];
$comentario = (string) ($data['comentarios'][periodo_clave($modo, $anio)] ?? '');

// Colores de los grupos para las tartas (mismo orden que los grupos).
const COLORES_GRUPOS = [
    '#2b853e', '#a2be4f', '#5578fb', '#f0742a', '#8e5bd1', '#e0a800',
    '#d94f70', '#1f9eb5', '#7a5c3e', '#56702a', '#c8452f', '#8a978d',
];

/** Tarta SVG a partir de [[etiqueta, valor, color], …]. Solo cuenta valores > 0. */
function tarta_svg(array $segmentos, string $titulo): string
{
    $segmentos = array_values(array_filter($segmentos, fn($s) => $s[1] > 0));
    $total = array_sum(array_column($segmentos, 1));
    $svg = '<svg viewBox="-1.05 -1.05 2.1 2.1" class="tarta" role="img" aria-label="' . htmlspecialchars($titulo) . '">';
    if ($total <= 0) {
        return $svg . '<circle r="1" fill="#eaf1e2"/><text y=".08" text-anchor="middle" font-size=".2" fill="#8a978d">Sin datos</text></svg>';
    }
    if (count($segmentos) === 1) {
        return $svg . '<circle r="1" fill="' . $segmentos[0][2] . '"><title>' . htmlspecialchars($segmentos[0][0]) . ' · 100%</title></circle></svg>';
    }
    $ang = -M_PI / 2;
    foreach ($segmentos as [$etq, $valor, $color]) {
        $frac = $valor / $total;
        $a2 = $ang + $frac * 2 * M_PI;
        $x1 = cos($ang); $y1 = sin($ang);
        $x2 = cos($a2);  $y2 = sin($a2);
        $grande = $frac > .5 ? 1 : 0;
        $svg .= sprintf(
            '<path d="M0 0 L%.4f %.4f A1 1 0 %d 1 %.4f %.4f Z" fill="%s" stroke="#fff" stroke-width=".015"><title>%s · %s</title></path>',
            $x1, $y1, $grande, $x2, $y2, $color, htmlspecialchars($etq), formato_pct($frac * 100)
        );
        $ang = $a2;
    }
    return $svg . '</svg>';
}

/** Bloque de un grupo (cabecera + partidas) como en la FICHA BALANCE. */
function bloque_grupo(array $g): void
{ ?>
  <table class="ficha-grupo">
    <thead>
      <tr>
        <th><?= htmlspecialchars($g['codigo'] . ' ' . mayusculas($g['nombre'])) ?></th>
        <th class="num"><?= formato_euros($g['importe']) ?></th>
        <th class="num"><?= formato_pct($g['pct']) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($g['partidas'] as $p): ?>
        <tr<?= $p['importe'] == 0 ? ' class="cero"' : '' ?>>
          <td><?= htmlspecialchars($p['codigo'] . ' ' . $p['nombre']) ?></td>
          <td class="num"><?= formato_euros($p['importe']) ?></td>
          <td class="num"><?= formato_pct($p['pct']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php }

/** Mitad de la ficha (ingresos o gastos): grupos a dos columnas + tarta con leyenda. */
function mitad_ficha(array $parte, string $titulo, string $periodo): void
{
    $grupos = $parte['grupos'];
    $mitad = (int) ceil(count($grupos) / 2);
    $segs = [];
    foreach ($grupos as $i => $g) {
        $segs[] = [$g['nombre'], $g['importe'], COLORES_GRUPOS[$i % count(COLORES_GRUPOS)]];
    }
    ?>
  <section class="ficha-parte">
    <div class="ficha-total">
      <span><?= htmlspecialchars($titulo) ?></span>
      <span class="num"><?= formato_euros($parte['total']) ?></span>
      <span class="num"><?= $parte['total'] != 0 ? '100,00%' : '0,00%' ?></span>
    </div>
    <div class="ficha-columnas">
      <div><?php foreach (array_slice($grupos, 0, $mitad) as $g) bloque_grupo($g); ?></div>
      <div><?php foreach (array_slice($grupos, $mitad) as $g) bloque_grupo($g); ?></div>
    </div>
    <div class="ficha-grafico">
      <?= tarta_svg($segs, "$titulo $periodo") ?>
      <div>
        <h3><?= htmlspecialchars(ucfirst(strtolower($titulo)) . ' ' . $periodo) ?></h3>
        <ul class="leyenda">
          <?php foreach ($segs as [$etq, $valor, $color]): ?>
            <li<?= $valor <= 0 ? ' class="cero"' : '' ?>>
              <span class="leyenda__color" style="background:<?= $color ?>"></span>
              <?= htmlspecialchars($etq) ?>
              <span class="leyenda__pct"><?= $parte['total'] > 0 && $valor > 0 ? formato_pct($valor / $parte['total'] * 100) : '—' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>
<?php }

$claseBody = 'seccion-tesoreria';
$pagina = 'balance';
$titulo = "Balance $periodo";
$scriptPagina = 'balance.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina encabezado-pagina--con-anio no-imprimir">
  <div>
    <h1>Ficha de balance</h1>
    <p>Se genera sola a partir de los movimientos del periodo, con el mismo formato que la FICHA BALANCE de la Zona. Pulsa «Imprimir / PDF» para guardarla.</p>
  </div>
  <div class="fila-botones">
    <nav class="interruptor" aria-label="Cómo agrupar el balance">
      <?php foreach (MODOS_PERIODO as $clave => $texto): ?>
        <a href="?modo=<?= $clave ?>"<?= $clave === $modo ? ' class="activo" aria-current="true"' : '' ?>><span><?= htmlspecialchars($texto) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <form method="get" class="selector-anio">
      <input type="hidden" name="modo" value="<?= $modo ?>">
      <select id="anio" name="anio" aria-label="Periodo" onchange="this.form.submit()">
        <?php foreach ($anios as $a): ?>
          <option value="<?= $a ?>"<?= $a === $anio ? ' selected' : '' ?>><?= htmlspecialchars(periodo_etiqueta($modo, $a)) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="boton boton--pequeno">Ver</button></noscript>
    </form>
    <button type="button" class="boton boton--primario" onclick="window.print()">Imprimir / PDF</button>
  </div>
</div>

<?php if ($b['movimientos'] === 0): ?>
  <p class="aviso-info no-imprimir">No hay movimientos en <?= htmlspecialchars($modo === 'curso' ? 'el ' . lcfirst($periodo) : $periodo) ?>. Apúntalos en <a href="tesoreria.php?modo=<?= $modo ?>&amp;anio=<?= $anio ?>">Movimientos</a>.</p>
<?php endif; ?>

<article class="ficha" id="ficha">
  <h2 class="ficha-titulo">BALANCE <?= htmlspecialchars(mayusculas('Zona Interior ' . $periodo)) ?></h2>
  <?php if ($modo === 'curso'): ?>
    <p class="ficha-subtitulo">Del 1 de septiembre de <?= $anio ?> al 31 de agosto de <?= $anio + 1 ?></p>
  <?php endif; ?>

  <?php mitad_ficha($b['ingreso'], 'INGRESOS', $periodo); ?>
  <?php mitad_ficha($b['gasto'], 'GASTOS', $periodo); ?>

  <div class="ficha-total ficha-resultado <?= $b['resultado'] < 0 ? 'negativo' : 'positivo' ?>">
    <span>RESULTADO DEL EJERCICIO</span>
    <span class="num"><?= formato_euros($b['resultado']) ?></span>
    <span class="num"><?= formato_pct($b['resultado_pct']) ?></span>
  </div>

  <section class="ficha-comentario">
    <div class="no-imprimir">
      <label for="comentario"><strong>Comentario del balance</strong> (sale impreso debajo del resultado)</label>
      <textarea id="comentario" rows="6" maxlength="5000" data-anio="<?= $anio ?>" data-modo="<?= $modo ?>"
                placeholder="Como se puede observar, en <?= htmlspecialchars($modo === 'curso' ? 'el ' . lcfirst($periodo) : "el año $periodo") ?>…"><?= htmlspecialchars($comentario) ?></textarea>
      <div class="fila-botones">
        <button type="button" class="boton boton--secundario boton--pequeno" id="guardar-comentario">Guardar comentario</button>
        <span class="js-estado-guardado" id="estado-comentario"></span>
      </div>
    </div>
    <div class="ficha-comentario__texto solo-imprimir" id="comentario-impreso"><?= htmlspecialchars($comentario) ?></div>
  </section>
</article>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
