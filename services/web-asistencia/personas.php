<?php
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pagina = 'personas';
$titulo = 'Personas';
$scriptPagina = 'personas.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina">
  <h1>Personas</h1>
  <p>Educadores y colaboradores del curso. Las personas dadas de baja no cuentan en el resumen pero se conserva su historial.</p>
</div>

<section class="panel">
  <h2>Añadir persona</h2>
  <form id="form-persona" class="form-linea">
    <div class="campo">
      <label for="np-nombre">Nombre y apellidos</label>
      <input type="text" id="np-nombre" name="nombre" required maxlength="200" placeholder="Ej. Ana Rubio Linares">
    </div>
    <div class="campo">
      <label for="np-categoria">Categoría</label>
      <select id="np-categoria" name="categoria">
        <option value="educador">Educador/a</option>
        <option value="colaborador">Colaborador/a</option>
      </select>
    </div>
    <button type="submit" class="boton boton--primario">Añadir</button>
  </form>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Listado</h2>
    <div class="filtros">
      <input type="search" id="buscar-persona" placeholder="Buscar por nombre…" aria-label="Buscar persona">
      <label class="check-inline">
        <input type="checkbox" id="ver-bajas"> Ver bajas
      </label>
    </div>
  </div>

  <div class="grupo-listado" data-cat="educador">
    <h3>Educadores <span class="contador" id="cont-educadores">0</span></h3>
    <div class="tabla-scroll">
      <table class="tabla tabla-personas">
        <thead>
          <tr><th>Nombre</th><th>Categoría</th><th>Estado</th><th class="col-acciones">Acciones</th></tr>
        </thead>
        <tbody id="tbody-educadores"></tbody>
      </table>
    </div>
  </div>

  <div class="grupo-listado" data-cat="colaborador">
    <h3>Colaboradores <span class="contador" id="cont-colaboradores">0</span></h3>
    <div class="tabla-scroll">
      <table class="tabla tabla-personas">
        <thead>
          <tr><th>Nombre</th><th>Categoría</th><th>Estado</th><th class="col-acciones">Acciones</th></tr>
        </thead>
        <tbody id="tbody-colaboradores"></tbody>
      </table>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
