<?php
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pagina = 'actividades';
$titulo = 'Actividades';
$scriptPagina = 'actividades.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina">
  <h1>Actividades</h1>
  <p>Crea una actividad, elige para qué grupo cuenta y marca quién ha asistido.</p>
</div>

<section class="panel">
  <h2>Nueva actividad</h2>
  <form id="form-actividad" class="form-rejilla">
    <div class="campo campo--ancho">
      <label for="na-nombre">Nombre de la actividad</label>
      <input type="text" id="na-nombre" name="nombre" required maxlength="200"
             placeholder="Ej. Reunión de educadores, Convivencia, Eucaristía…">
    </div>
    <div class="campo">
      <label for="na-fecha">Fecha</label>
      <input type="date" id="na-fecha" name="fecha">
    </div>
    <div class="campo">
      <label for="na-ambito">Cuenta para</label>
      <select id="na-ambito" name="ambito">
        <option value="ambos">Educadores y colaboradores</option>
        <option value="educadores">Solo educadores</option>
        <option value="colaboradores">Solo colaboradores</option>
      </select>
    </div>
    <div class="campo campo--ancho">
      <label for="na-descripcion">Notas (opcional)</label>
      <input type="text" id="na-descripcion" name="descripcion" maxlength="500" placeholder="Lugar, detalles…">
    </div>
    <button type="submit" class="boton boton--primario">Crear actividad</button>
  </form>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Actividades del curso <span class="contador" id="cont-actividades">0</span></h2>
    <input type="search" id="buscar-actividad" placeholder="Buscar…" aria-label="Buscar actividad">
  </div>
  <div id="lista-actividades" class="lista-actividades">
    <p class="vacio" id="actividades-vacio" hidden>Aún no hay actividades. Crea la primera arriba.</p>
  </div>
</section>

<!-- Plantilla de tarjeta de actividad -->
<template id="tpl-actividad">
  <article class="actividad" data-id="">
    <header class="actividad__cab">
      <div>
        <h3 class="actividad__nombre"></h3>
        <p class="actividad__meta">
          <span class="actividad__fecha"></span>
          <span class="etiqueta actividad__ambito"></span>
          <span class="actividad__conteo"></span>
        </p>
        <p class="actividad__desc"></p>
      </div>
      <div class="actividad__acciones">
        <button class="boton boton--pequeno js-toggle-asistencia">Marcar asistencia</button>
        <button class="boton boton--pequeno boton--fantasma js-editar">Editar</button>
        <button class="boton boton--pequeno boton--peligro-fantasma js-borrar">Borrar</button>
      </div>
    </header>

    <div class="actividad__panel" hidden>
      <div class="actividad__barra-acciones">
        <input type="search" class="js-buscar-asistente" placeholder="Buscar persona…" aria-label="Buscar persona">
        <div class="fila-botones">
          <button class="boton boton--pequeno boton--fantasma js-todos">Todos</button>
          <button class="boton boton--pequeno boton--fantasma js-ninguno">Ninguno</button>
        </div>
      </div>
      <div class="asistentes-grid js-asistentes"></div>
      <div class="actividad__guardar">
        <span class="js-estado-guardado"></span>
        <button class="boton boton--primario js-guardar-asistencia">Guardar asistencia</button>
      </div>
    </div>
  </article>
</template>

<!-- Plantilla de formulario de edición -->
<template id="tpl-editar-actividad">
  <form class="form-rejilla form-editar">
    <div class="campo campo--ancho">
      <label>Nombre
        <input type="text" name="nombre" required maxlength="200">
      </label>
    </div>
    <div class="campo">
      <label>Fecha
        <input type="date" name="fecha">
      </label>
    </div>
    <div class="campo">
      <label>Cuenta para
        <select name="ambito">
          <option value="ambos">Educadores y colaboradores</option>
          <option value="educadores">Solo educadores</option>
          <option value="colaboradores">Solo colaboradores</option>
        </select>
      </label>
    </div>
    <div class="campo campo--ancho">
      <label>Notas
        <input type="text" name="descripcion" maxlength="500">
      </label>
    </div>
    <div class="fila-botones">
      <button type="submit" class="boton boton--primario">Guardar cambios</button>
      <button type="button" class="boton boton--fantasma js-cancelar-edicion">Cancelar</button>
    </div>
  </form>
</template>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
