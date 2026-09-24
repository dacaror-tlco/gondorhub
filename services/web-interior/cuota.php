<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
exigir_login();

$pagina = 'cuota';
$titulo = 'Cuota de zona';
$scriptPagina = 'cuota.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina encabezado-pagina--con-anio">
  <div>
    <h1>Cuota de zona</h1>
    <p>Calcula la cuota de cada centro (miembros del censo × precio), compárala con otros modelos y con la del curso anterior,
      e imprime las fichas de adjudicación. <span class="js-estado-guardado" id="estado-cuota"></span></p>
  </div>
  <div class="fila-botones">
    <select id="curso" aria-label="Curso"></select>
    <a class="boton boton--secundario boton--pequeno" id="imprimir-presupuesto" href="doc-cuota.php">Imprimir cálculo</a>
    <a class="boton boton--primario boton--pequeno" id="imprimir-fichas" href="fichas-cuota.php">Imprimir fichas</a>
  </div>
</div>

<section class="tarjetas-num">
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-precio">—</span><span class="tarjeta-num__etq">Precio por miembro</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-total">—</span><span class="tarjeta-num__etq" id="t-total-etq">Ingresos por cuota</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-gastos">—</span><span class="tarjeta-num__etq">Gastos a cubrir</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-margen">—</span><span class="tarjeta-num__etq">Margen</span></div>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Gastos que cubre la cuota</h2>
    <div class="campo campo--titulo"><input type="text" id="titulo-presupuesto" maxlength="100" aria-label="Título del presupuesto"></div>
  </div>
  <div class="tabla-scroll">
    <table class="tabla tabla-lineas" id="tabla-gastos"></table>
  </div>
  <div class="fila-botones">
    <button type="button" class="boton boton--secundario boton--pequeno" id="anadir-gasto">＋ Añadir gasto</button>
    <button type="button" class="boton boton--fantasma boton--pequeno" id="traer-transporte">Poner el transporte CJZ previsto</button>
  </div>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Cuota por centro</h2>
    <label class="campo-inline">Precio por miembro
      <input type="text" inputmode="decimal" id="precio" class="num" aria-label="Precio por miembro"> €</label>
  </div>
  <div class="tabla-scroll">
    <table class="tabla tabla-cuota" id="tabla-cuota"></table>
  </div>
  <details class="mas-datos">
    <summary>Cuota de referencia <small>(para calcular la variación: normalmente la del curso anterior)</small></summary>
    <div class="form-linea">
      <div class="campo campo--grande"><label for="ref-titulo">Título</label><input type="text" id="ref-titulo" maxlength="80"></div>
      <div class="campo campo--corto"><label for="ref-precio">Precio</label><input type="text" inputmode="decimal" id="ref-precio" class="num"></div>
    </div>
    <div class="tabla-scroll"><table class="tabla tabla-lineas" id="tabla-referencia"></table></div>
  </details>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Otros modelos para comparar</h2>
    <button type="button" class="boton boton--secundario boton--pequeno" id="nuevo-modelo">＋ Añadir modelo</button>
  </div>
  <p class="ayuda">Por ejemplo «solo niños» frente a «niños y educadores», o con el censo del año pasado. «Aplicar» copia su precio y censo a la cuota.</p>
  <div id="modelos"></div>
</section>

<section class="panel">
  <h2>Fichas de adjudicación</h2>
  <div class="form-rejilla">
    <div class="campo campo--ancho">
      <label for="nota-aprobacion">Aprobación del precio por miembro <small>(«La cantidad… fue aprobada en …»)</small></label>
      <input type="text" id="nota-aprobacion" maxlength="300" placeholder="Consejo Juniors de Zona celebrado en el Centro Juniors Èxode el 27 de marzo de 2026">
    </div>
    <div class="campo">
      <label for="fecha-limite">Fecha límite de pago</label>
      <input type="date" id="fecha-limite">
    </div>
    <div class="campo">
      <label for="fecha-emision">Fecha de la ficha</label>
      <input type="date" id="fecha-emision">
    </div>
  </div>
  <p class="ayuda">La cuenta bancaria y los nombres del tesorero y del delegado salen de <a href="ajustes.php">Ajustes</a>.</p>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
