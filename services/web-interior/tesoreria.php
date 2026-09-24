<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tesoreria.php';
exigir_login();

/** Campos del formulario de movimiento (se usa en "nuevo" y en el modal de editar). */
function campos_movimiento(string $pre): void
{ ?>
    <fieldset class="campo campo--ancho tipo-mov">
      <legend>1 · ¿Ingreso o gasto?</legend>
      <div class="tipo-mov__opciones">
        <label class="tipo-mov__opcion tipo-mov__opcion--ingreso">
          <input type="radio" name="tipo" value="ingreso" required>
          <span>＋ Ingreso</span>
        </label>
        <label class="tipo-mov__opcion tipo-mov__opcion--gasto">
          <input type="radio" name="tipo" value="gasto" required>
          <span>− Gasto</span>
        </label>
      </div>
    </fieldset>
    <div class="campo campo--ancho">
      <label for="<?= $pre ?>-partida">2 · Partida</label>
      <select id="<?= $pre ?>-partida" name="partida" required disabled>
        <option value="">Elige primero ingreso o gasto…</option>
      </select>
    </div>
    <div class="campo campo--ancho">
      <label for="<?= $pre ?>-concepto">3 · Concepto</label>
      <input type="text" id="<?= $pre ?>-concepto" name="concepto" required maxlength="200"
             placeholder="Ej. Cuota Convivencia de Estilo, Alquiler albergue…">
    </div>
    <div class="campo">
      <label for="<?= $pre ?>-cantidad">4 · Cantidad</label>
      <input type="text" id="<?= $pre ?>-cantidad" name="cantidad" inputmode="decimal" value="1" required>
    </div>
    <div class="campo">
      <label for="<?= $pre ?>-precio">5 · Precio unitario (€)</label>
      <input type="text" id="<?= $pre ?>-precio" name="precio" inputmode="decimal" required placeholder="0,00">
    </div>
    <div class="campo">
      <label for="<?= $pre ?>-fecha">Fecha</label>
      <input type="date" id="<?= $pre ?>-fecha" name="fecha" required>
    </div>
    <div class="campo">
      <span class="campo__etq">Total</span>
      <output class="total-mov js-total">0,00 €</output>
    </div>
    <details class="campo--ancho mas-datos">
      <summary>Actividad y datos de la factura <small>(opcional)</small></summary>
      <div class="form-rejilla">
        <div class="campo campo--ancho">
          <label for="<?= $pre ?>-actividad">Actividad</label>
          <select id="<?= $pre ?>-actividad" name="actividad">
            <option value="">— Ninguna —</option>
          </select>
        </div>
        <div class="campo campo--ancho">
          <label for="<?= $pre ?>-proveedor">Proveedor (razón social)</label>
          <input type="text" id="<?= $pre ?>-proveedor" name="proveedor" maxlength="120" placeholder="Ej. MERCADONA S.A">
        </div>
        <div class="campo">
          <label for="<?= $pre ?>-cif">CIF</label>
          <input type="text" id="<?= $pre ?>-cif" name="cif" maxlength="20">
        </div>
        <div class="campo">
          <label for="<?= $pre ?>-factura">Nº de factura</label>
          <input type="text" id="<?= $pre ?>-factura" name="factura" maxlength="40">
        </div>
      </div>
    </details>
<?php }

$modo = modo_preferido();

$pagina = 'tesoreria';
$titulo = 'Movimientos';
$scriptPagina = 'tesoreria.js';
$claseBody = 'seccion-tesoreria';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina encabezado-pagina--con-anio">
  <div>
    <h1>Movimientos</h1>
    <p>Apunta cada ingreso y gasto de la zona con su partida. El balance se calcula solo.</p>
  </div>
  <div class="selector-periodo">
    <div class="interruptor" role="radiogroup" aria-label="Cómo agrupar el balance">
      <?php foreach (MODOS_PERIODO as $clave => $texto): ?>
        <label>
          <input type="radio" name="modo" value="<?= $clave ?>"<?= $clave === $modo ? ' checked' : '' ?>>
          <span><?= htmlspecialchars($texto) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <select id="anio" aria-label="Periodo"></select>
  </div>
</div>

<section class="tarjetas-num">
  <div class="tarjeta-num"><span class="tarjeta-num__valor positivo" id="t-ingresos">—</span><span class="tarjeta-num__etq">Ingresos</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor negativo" id="t-gastos">—</span><span class="tarjeta-num__etq">Gastos</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-resultado">—</span><span class="tarjeta-num__etq">Resultado del ejercicio</span></div>
  <div class="tarjeta-num"><span class="tarjeta-num__valor" id="t-movimientos">0</span><span class="tarjeta-num__etq">Movimientos</span></div>
</section>

<section class="panel">
  <h2>Nuevo movimiento</h2>
  <form id="form-movimiento" class="form-rejilla form-movimiento" autocomplete="off">
    <?php campos_movimiento('nm'); ?>
    <button type="submit" class="boton boton--primario">Guardar movimiento</button>
  </form>
</section>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Movimientos · <span id="titulo-anio"></span> <span class="contador" id="cont-movimientos">0</span></h2>
    <div class="filtros">
      <input type="search" id="buscar-mov" placeholder="Buscar concepto…" aria-label="Buscar movimiento">
      <select id="filtro-tipo" aria-label="Filtrar por tipo">
        <option value="">Ingresos y gastos</option>
        <option value="ingreso">Solo ingresos</option>
        <option value="gasto">Solo gastos</option>
      </select>
      <select id="filtro-partida" aria-label="Filtrar por partida">
        <option value="">Todas las partidas</option>
      </select>
      <select id="filtro-actividad" aria-label="Filtrar por actividad">
        <option value="">Todas las actividades</option>
      </select>
      <button class="boton boton--secundario boton--pequeno" id="exportar-csv">Exportar CSV</button>
    </div>
  </div>

  <div class="tabla-scroll">
    <table class="tabla tabla-movimientos">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Partida</th>
          <th>Concepto</th>
          <th class="num">Cant.</th>
          <th class="num">Precio</th>
          <th class="num">Importe</th>
          <th class="col-acciones"></th>
        </tr>
      </thead>
      <tbody id="tbody-movimientos"></tbody>
      <tfoot id="tfoot-movimientos"></tfoot>
    </table>
  </div>
  <p class="vacio" id="movimientos-vacio" hidden>No hay movimientos que mostrar.</p>
</section>

<!-- Modal de edición -->
<dialog id="modal-editar" class="modal modal--ancho">
  <div class="modal__cab">
    <h2>Editar movimiento</h2>
    <button class="modal__cerrar" type="button" aria-label="Cerrar">&times;</button>
  </div>
  <form id="form-editar-mov" class="form-rejilla form-movimiento" autocomplete="off">
    <?php campos_movimiento('em'); ?>
    <div class="fila-botones campo--ancho">
      <button type="submit" class="boton boton--primario">Guardar cambios</button>
      <button type="button" class="boton boton--fantasma js-cancelar">Cancelar</button>
    </div>
  </form>
</dialog>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
