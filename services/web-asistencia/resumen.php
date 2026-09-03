<?php
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pagina = 'resumen';
$titulo = 'Resumen';
$scriptPagina = 'resumen.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina">
  <h1>Resumen de asistencia</h1>
  <p>Actividades totales, asistidas y porcentaje de cada persona. El porcentaje se calcula sobre las actividades que le corresponden según su categoría.</p>
</div>

<section class="tarjetas-num" id="resumen-totales">
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-actividades">0</span><span class="tarjeta-num__etq">Actividades totales</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-educadores">0</span><span class="tarjeta-num__etq">Cuentan para educadores</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-colaboradores">0</span><span class="tarjeta-num__etq">Cuentan para colaboradores</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-media-edu">—</span><span class="tarjeta-num__etq">Media educadores</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-media-col">—</span><span class="tarjeta-num__etq">Media colaboradores</span></div>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Por persona</h2>
    <div class="filtros">
      <input type="search" id="buscar-resumen" placeholder="Buscar…" aria-label="Buscar persona">
      <select id="filtro-categoria" aria-label="Filtrar por categoría">
        <option value="todos">Todas las categorías</option>
        <option value="educador">Solo educadores</option>
        <option value="colaborador">Solo colaboradores</option>
      </select>
      <label class="check-inline"><input type="checkbox" id="resumen-ver-bajas"> Ver bajas</label>
      <button class="boton boton--secundario boton--pequeno" id="exportar-csv">Exportar CSV</button>
    </div>
  </div>

  <div class="tabla-scroll">
    <table class="tabla tabla-resumen">
      <thead>
        <tr>
          <th class="js-ordenar" data-col="nombre">Nombre</th>
          <th class="js-ordenar" data-col="categoria">Categoría</th>
          <th class="js-ordenar num" data-col="asistidas">Asistidas</th>
          <th class="js-ordenar num" data-col="aplicables">Totales</th>
          <th class="js-ordenar num" data-col="porcentaje">Porcentaje</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-resumen"></tbody>
    </table>
  </div>
  <p class="vacio" id="resumen-vacio" hidden>No hay personas que mostrar.</p>
</section>

<!-- Modal de detalle por persona -->
<dialog id="modal-detalle" class="modal">
  <div class="modal__cab">
    <h2 id="modal-titulo">Detalle</h2>
    <button class="modal__cerrar" aria-label="Cerrar">&times;</button>
  </div>
  <p id="modal-sub" class="modal__sub"></p>
  <div class="tabla-scroll">
    <table class="tabla">
      <thead><tr><th>Actividad</th><th>Fecha</th><th class="num">¿Asistió?</th></tr></thead>
      <tbody id="modal-tbody"></tbody>
    </table>
  </div>
</dialog>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
