<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
exigir_login();

$zona = cargar_zona();
$curso = curso_preferido();
$cursos = cursos_disponibles($zona);
if (!in_array($curso, $cursos, true)) { $cursos[] = $curso; rsort($cursos); }
$lista = actividades_curso($zona, $curso);

$pagina = 'informe';
$titulo = 'Informe económico';
$scriptPagina = 'informe.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina encabezado-pagina--con-anio">
  <div>
    <h1>Informe económico</h1>
    <p>Una hoja por proyecto para la memoria de zona: ingresos, gastos por tipo y control de facturas.
      Las facturas se rellenan en cada actividad (pestaña «Facturas e informe»).</p>
  </div>
  <div class="fila-botones">
    <form method="get" class="selector-anio">
      <select name="curso" aria-label="Curso" onchange="this.form.submit()">
        <?php foreach ($cursos as $c): ?>
          <option value="<?= $c ?>"<?= $c === $curso ? ' selected' : '' ?>>Curso <?= curso_barra($c) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a class="boton boton--primario" href="doc-informe.php?curso=<?= $curso ?>">Imprimir informe</a>
  </div>
</div>

<section class="panel">
  <?php if (!$lista): ?>
    <p class="vacio">No hay actividades en el curso <?= curso_barra($curso) ?>. Créalas en <a href="actividades.php?curso=<?= $curso ?>">Actividades</a>.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla">
        <thead>
          <tr>
            <th>Incluir</th>
            <th>Proyecto</th>
            <th class="num">Ingresos actividad</th>
            <th class="num">Participación zona</th>
            <th class="num">Total gastos</th>
            <th class="num">Facturas</th>
            <th class="col-acciones"></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $tot = ['ing' => 0, 'part' => 0, 'gas' => 0];
          foreach ($lista as $a):
            $inf = calcular_informe($a);
            $incl = ($a['informe']['incluir'] ?? true) !== false;
            if ($incl) { $tot['ing'] += $inf['ingresos_actividad']; $tot['part'] += $inf['participacion']; $tot['gas'] += $inf['total_gastos']; }
            $descuadre = euros($inf['total_ingresos'] - $inf['total_gastos']);
          ?>
            <tr class="<?= $incl ? '' : 'fila-baja' ?>">
              <td><input type="checkbox" class="js-incluir" data-id="<?= h($a['id']) ?>" <?= $incl ? 'checked' : '' ?> aria-label="Incluir <?= h($a['nombre']) ?>"></td>
              <td><strong><?= h($a['nombre']) ?></strong><br><small class="gris"><?= fecha_corta($a['fecha']) ?></small>
                <?php if ($descuadre != 0): ?><br><small class="negativo" title="Ingresos − gastos del informe">No cuadra: <?= formato_euros($descuadre) ?></small><?php endif; ?></td>
              <td class="num nowrap"><?= formato_euros($inf['ingresos_actividad']) ?></td>
              <td class="num nowrap"><?= formato_euros($inf['participacion']) ?></td>
              <td class="num nowrap"><?= formato_euros($inf['total_gastos']) ?></td>
              <td class="num"><?= count($inf['facturas']) ?></td>
              <td class="col-acciones">
                <a class="boton boton--pequeno boton--fantasma" href="actividad.php?id=<?= h($a['id']) ?>#informe">Facturas</a>
                <a class="boton boton--pequeno boton--fantasma" href="doc-informe.php?id=<?= h($a['id']) ?>">Imprimir</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td></td><td>Total de lo incluido</td>
            <td class="num nowrap"><?= formato_euros($tot['ing']) ?></td>
            <td class="num nowrap"><?= formato_euros($tot['part']) ?></td>
            <td class="num nowrap"><?= formato_euros($tot['gas']) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="ayuda">«No cuadra» avisa cuando los ingresos del informe no suman lo mismo que las facturas; puede ser correcto
      (p. ej. una actividad con déficit), pero revísalo.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
