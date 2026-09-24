<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
exigir_login();

$pagina = 'transporte';
$titulo = 'Transporte CJZ';
$scriptPagina = 'transporte.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina encabezado-pagina--con-anio">
  <div>
    <h1>Transporte a los Consejos Juniors de Zona</h1>
    <p>Marca qué miembros van a cada CJZ; el importe sale de la tarifa según dónde se celebre. Agrupa los consejos en liquidaciones para pagarlos.</p>
  </div>
  <div class="selector-anio">
    <select id="curso" aria-label="Curso"></select>
  </div>
</div>

<section class="tarjetas-num">
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-celebrados">—</span><span class="tarjeta-num__etq">Consejos celebrados</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-gastado">—</span><span class="tarjeta-num__etq">Transporte de los celebrados</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-previsto">—</span><span class="tarjeta-num__etq" id="t-previsto-etq">Previsto (consejos por celebrar)</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-pendiente">—</span><span class="tarjeta-num__etq">Celebrado sin liquidar</span></div>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Asistencia y transporte <span class="contador" id="cont-consejos">0</span></h2>
    <div class="fila-botones">
      <span class="js-estado-guardado" id="estado-asistencia"></span>
      <a class="boton boton--fantasma boton--pequeno" id="imprimir-curso" href="doc-transporte.php">Imprimir tabla del curso</a>
      <button type="button" class="boton boton--primario boton--pequeno" id="nuevo-consejo">＋ Nuevo consejo</button>
    </div>
  </div>
  <p class="ayuda">Pulsa la casilla de quien asistió: se pone la tarifa de esa sede (puedes cambiar el importe). Los consejos
    <em>previstos</em> cuentan como si fueran todos, para el presupuesto. Pulsa la cabecera de un consejo para editarlo.</p>
  <div class="tabla-scroll">
    <table class="tabla tabla-asistencia" id="tabla-asistencia"></table>
  </div>
  <p class="vacio" id="asistencia-vacio" hidden>No hay consejos en este curso. Crea el primero con «Nuevo consejo» (puedes crear ya todos los del calendario como «previstos»).</p>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Liquidaciones</h2>
    <button type="button" class="boton boton--secundario boton--pequeno" id="nueva-liquidacion">＋ Nueva liquidación</button>
  </div>
  <p class="ayuda">Cada liquidación junta varios consejos (p. ej. «Pago 1er trimestre» y «Pago final»). Imprime la hoja con los importes
    y las cuentas de cada centro y, cuando hagas las transferencias, apúntala como gasto (G3.1 Transporte) en Movimientos.</p>
  <div id="liquidaciones"></div>
</section>

<section class="panel">
  <details id="panel-tarifas">
    <summary><h2>Sedes y tarifas</h2></summary>
    <p class="ayuda">Lo que se paga a cada miembro por ir a un consejo según dónde se celebra (normalmente 0 € a quien lo acoge,
      10 € o 20 € según la distancia). Añade también sedes que no son centros (Online, Serra Mariola…).</p>
    <div class="tabla-scroll">
      <table class="tabla tabla-tarifas" id="tabla-tarifas"></table>
    </div>
    <div class="fila-botones">
      <button type="button" class="boton boton--secundario boton--pequeno" id="nueva-sede">＋ Añadir sede</button>
      <span class="js-estado-guardado" id="estado-tarifas"></span>
    </div>
  </details>
</section>

<!-- Modal consejo -->
<dialog id="modal-consejo" class="modal">
  <div class="modal__cab">
    <h2 id="modal-consejo-titulo">Consejo</h2>
    <button class="modal__cerrar" type="button" aria-label="Cerrar">&times;</button>
  </div>
  <form id="form-consejo" class="form-rejilla" autocomplete="off">
    <div class="campo">
      <label for="c-fecha">Fecha</label>
      <input type="date" id="c-fecha" name="fecha" required>
    </div>
    <div class="campo">
      <label for="c-nombre">Tipo</label>
      <input type="text" id="c-nombre" name="nombre" list="tipos-consejo" value="CJZ" maxlength="60">
      <datalist id="tipos-consejo"><option value="CJZ"><option value="CJZ extraordinario"></datalist>
    </div>
    <div class="campo campo--ancho">
      <label for="c-lugar">Sede</label>
      <select id="c-lugar" name="lugar" required></select>
    </div>
    <div class="campo">
      <label for="c-estado">Estado</label>
      <select id="c-estado" name="estado">
        <option value="previsto">Previsto</option>
        <option value="celebrado">Celebrado</option>
      </select>
    </div>
    <div class="campo">
      <span class="campo__etq">Transporte</span>
      <label class="check-inline"><input type="checkbox" name="pagar" checked> Se paga transporte</label>
    </div>
    <div class="campo campo--ancho">
      <label for="c-notas">Notas</label>
      <input type="text" id="c-notas" name="notas" maxlength="300">
    </div>
    <div class="fila-botones campo--ancho">
      <button type="submit" class="boton boton--primario">Guardar</button>
      <button type="button" class="boton boton--fantasma js-cancelar">Cancelar</button>
      <button type="button" class="boton boton--peligro-fantasma" id="borrar-consejo">Borrar consejo</button>
    </div>
  </form>
</dialog>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
