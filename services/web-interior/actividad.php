<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
exigir_login();

$zona = cargar_zona();
$act = actividad($zona, limpiar_id($_GET['id'] ?? ''));

$pagina = 'actividades';
$titulo = $act ? $act['nombre'] : 'Actividad no encontrada';
$scriptPagina = $act ? 'actividad.js' : '';
require __DIR__ . '/includes/layout.php';

if (!$act): ?>
  <p class="aviso-info">No se ha encontrado la actividad. <a href="actividades.php">Volver a actividades</a>.</p>
<?php require __DIR__ . '/includes/layout_fin.php'; exit; endif; ?>

<script>
window.DATOS = <?= json_encode([
    'actividad'  => $act,
    'miembros'   => $zona['miembros'],
    'categorias' => CATEGORIAS_INFORME,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
</script>

<p class="migas"><a href="actividades.php?curso=<?= curso_de_fecha($act['fecha']) ?>">← Actividades del curso <?= curso_barra(curso_de_fecha($act['fecha'])) ?></a></p>

<div class="encabezado-pagina encabezado-pagina--con-anio">
  <div>
    <h1 id="titulo-actividad"><?= h($act['nombre']) ?></h1>
    <p><span class="js-estado-guardado" id="estado-guardado">Los cambios se guardan solos.</span></p>
  </div>
  <div class="fila-botones">
    <button type="button" class="boton boton--fantasma boton--pequeno" id="duplicar">Duplicar</button>
    <button type="button" class="boton boton--peligro-fantasma boton--pequeno" id="borrar">Borrar actividad</button>
  </div>
</div>

<section class="panel">
  <form id="form-datos" class="form-linea" autocomplete="off">
    <div class="campo campo--grande">
      <label for="a-nombre">Nombre</label>
      <input type="text" id="a-nombre" name="nombre" maxlength="120" required>
    </div>
    <div class="campo">
      <label for="a-fecha">Fecha de inicio</label>
      <input type="date" id="a-fecha" name="fecha" required>
    </div>
    <div class="campo">
      <label for="a-fin">Fecha de fin</label>
      <input type="date" id="a-fin" name="fecha_fin">
    </div>
    <div class="campo campo--grande">
      <label for="a-lugar">Lugar</label>
      <input type="text" id="a-lugar" name="lugar" maxlength="120" placeholder="Ej. Albergue Mariola">
    </div>
  </form>
</section>

<nav class="pestanas" role="tablist" aria-label="Partes de la actividad">
  <button type="button" role="tab" data-tab="presupuesto">Presupuesto</button>
  <button type="button" role="tab" data-tab="balance">Balance</button>
  <button type="button" role="tab" data-tab="centros">Ingresos por centros</button>
  <button type="button" role="tab" data-tab="informe">Facturas e informe</button>
  <button type="button" role="tab" data-tab="movimientos">Movimientos</button>
</nav>

<section class="panel pestana" id="tab-presupuesto" data-hoja="presupuesto" hidden>
  <div class="panel__cabecera">
    <h2>Presupuesto</h2>
    <a class="boton boton--primario boton--pequeno js-imprimir" href="doc-actividad.php?id=<?= h($act['id']) ?>&amp;doc=presupuesto">Imprimir / PDF</a>
  </div>
  <div class="js-hoja"></div>
</section>

<section class="panel pestana" id="tab-balance" data-hoja="balance" hidden>
  <div class="panel__cabecera">
    <h2>Balance</h2>
    <div class="fila-botones">
      <button type="button" class="boton boton--fantasma boton--pequeno" id="copiar-presupuesto">Copiar líneas del presupuesto</button>
      <a class="boton boton--primario boton--pequeno js-imprimir" href="doc-actividad.php?id=<?= h($act['id']) ?>&amp;doc=balance">Imprimir / PDF</a>
    </div>
  </div>
  <div class="js-hoja"></div>
</section>

<section class="panel pestana" id="tab-centros" hidden>
  <div class="panel__cabecera">
    <h2>Ingresos por centros</h2>
    <div class="fila-botones">
      <button type="button" class="boton boton--secundario boton--pequeno" id="nuevo-grupo">＋ Añadir grupo</button>
      <a class="boton boton--primario boton--pequeno js-imprimir" href="doc-actividad.php?id=<?= h($act['id']) ?>&amp;doc=centros">Imprimir / PDF</a>
    </div>
  </div>
  <p class="ayuda">Cuántos participantes trae cada centro y a qué precio. Usa un grupo por precio (p. ej. «Niños» a 30 € y «Educadores» a 15 €).
    Con «Usar en el balance» la línea de ingresos del balance toma la cantidad y el precio de aquí.</p>
  <div id="grupos"></div>
</section>

<section class="panel pestana" id="tab-informe" hidden>
  <div class="panel__cabecera">
    <h2>Facturas e informe económico</h2>
    <a class="boton boton--primario boton--pequeno" href="informe.php?curso=<?= curso_de_fecha($act['fecha']) ?>">Ver informe del curso</a>
  </div>
  <div id="informe"></div>
</section>

<section class="panel pestana" id="tab-movimientos" hidden>
  <div class="panel__cabecera">
    <h2>Movimientos vinculados</h2>
    <a class="boton boton--secundario boton--pequeno" href="tesoreria.php">Ir a Movimientos</a>
  </div>
  <p class="ayuda">Ingresos y gastos apuntados en <a href="tesoreria.php">Movimientos</a> con esta actividad elegida
    (en «Actividad y datos de la factura»). Puedes pasarlos al balance o a las facturas del informe.</p>
  <div id="movimientos"></div>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
