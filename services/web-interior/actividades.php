<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
exigir_login();

$zona = cargar_zona();
$curso = curso_preferido();
$cursos = cursos_disponibles($zona);
if (!in_array($curso, $cursos, true)) { $cursos[] = $curso; rsort($cursos); }
$lista = actividades_curso($zona, $curso);

// Actividades de cursos anteriores, para usarlas de plantilla.
$anteriores = array_values(array_filter($zona['actividades'], fn($a) => curso_de_fecha($a['fecha']) < $curso));
usort($anteriores, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

$pagina = 'actividades';
$titulo = 'Actividades';
$scriptPagina = 'actividades.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina encabezado-pagina--con-anio">
  <div>
    <h1>Actividades</h1>
    <p>Presupuesto y balance de cada actividad (convivencias, oraciones, festival…), ingresos por centros y facturas para el informe económico.</p>
  </div>
  <form method="get" class="selector-anio">
    <select name="curso" aria-label="Curso" onchange="this.form.submit()">
      <?php foreach ($cursos as $c): ?>
        <option value="<?= $c ?>"<?= $c === $curso ? ' selected' : '' ?>>Curso <?= curso_barra($c) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="boton boton--pequeno">Ver</button></noscript>
  </form>
</div>

<section class="panel">
  <h2>Nueva actividad</h2>
  <form id="form-actividad" class="form-linea" autocomplete="off">
    <div class="campo campo--grande">
      <label for="na-nombre">Nombre</label>
      <input type="text" id="na-nombre" name="nombre" required maxlength="120" placeholder="Ej. Convivencia de Estilo">
    </div>
    <div class="campo">
      <label for="na-fecha">Fecha de inicio</label>
      <input type="date" id="na-fecha" name="fecha" required>
    </div>
    <div class="campo">
      <label for="na-fin">Fecha de fin <small>(opcional)</small></label>
      <input type="date" id="na-fin" name="fecha_fin">
    </div>
    <button type="submit" class="boton boton--primario">Crear y abrir</button>
  </form>
  <?php if ($anteriores): ?>
    <form id="form-plantilla" class="form-linea form-plantilla" autocomplete="off">
      <div class="campo campo--grande">
        <label for="plantilla">…o parte de una actividad de otro curso <small>(copia su presupuesto o, si no tenía, su balance)</small></label>
        <select id="plantilla" name="plantilla" required>
          <option value="">Elige una actividad…</option>
          <?php foreach ($anteriores as $a): ?>
            <option value="<?= h($a['id']) ?>"><?= h($a['nombre']) ?> · <?= fecha_corta($a['fecha']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="boton boton--secundario">Duplicar para este año</button>
    </form>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Curso <?= curso_barra($curso) ?> <span class="contador"><?= count($lista) ?></span></h2>
  </div>
  <?php if (!$lista): ?>
    <p class="vacio">Todavía no hay actividades en este curso.</p>
  <?php else: ?>
    <div class="tabla-scroll">
      <table class="tabla">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Actividad</th>
            <th class="num">Presupuesto</th>
            <th class="num">Balance</th>
            <th class="num">Facturas</th>
            <th class="col-acciones"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lista as $a):
            $p = calcular_hoja($a['presupuesto'], $a);
            $bal = calcular_hoja($a['balance'], $a);
            $tieneP = $a['presupuesto']['ingresos'] || $a['presupuesto']['gastos'];
            $tieneB = $a['balance']['ingresos'] || $a['balance']['gastos'];
            $nf = count($a['informe']['facturas'] ?? []);
          ?>
            <tr>
              <td class="nowrap"><?= fecha_corta($a['fecha']) ?></td>
              <td><a href="actividad.php?id=<?= h($a['id']) ?>"><strong><?= h($a['nombre']) ?></strong></a>
                <?php if ($a['lugar']): ?><br><small class="gris"><?= h($a['lugar']) ?></small><?php endif; ?></td>
              <td class="num nowrap"><?= $tieneP ? '<span class="' . ($p['resultado'] < 0 ? 'negativo' : '') . '">' . formato_euros($p['resultado']) . '</span><br><small class="gris">gastos ' . formato_euros($p['total_gastos']) . '</small>' : '<span class="gris">—</span>' ?></td>
              <td class="num nowrap"><?= $tieneB ? '<span class="' . ($bal['resultado'] < 0 ? 'negativo' : 'positivo') . '">' . formato_euros($bal['resultado']) . '</span><br><small class="gris">gastos ' . formato_euros($bal['total_gastos']) . '</small>' : '<span class="gris">—</span>' ?></td>
              <td class="num"><?= $nf ?: '<span class="gris">—</span>' ?></td>
              <td class="col-acciones"><a class="boton boton--pequeno boton--fantasma" href="actividad.php?id=<?= h($a['id']) ?>">Abrir</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
