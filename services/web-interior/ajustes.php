<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/zona.php';
exigir_login();

$pagina = 'ajustes';
$titulo = 'Centros y ajustes';
$scriptPagina = 'ajustes.js';
require __DIR__ . '/includes/layout.php';
?>

<div class="encabezado-pagina">
  <h1>Centros y ajustes</h1>
  <p>Centros de la zona y otros miembros (delegado…), sus cuentas bancarias, y los datos que salen en las fichas.
    <span class="js-estado-guardado" id="estado-ajustes"></span></p>
</div>

<section class="panel">
  <div class="panel__cabecera">
    <h2>Miembros de la zona</h2>
    <button type="button" class="boton boton--secundario boton--pequeno" id="nuevo-miembro">＋ Añadir</button>
  </div>
  <p class="ayuda">Los <strong>centros</strong> salen en la cuota, en los ingresos por centros y en el transporte; los <strong>otros</strong>
    (p. ej. el delegado de zona) solo en el transporte. Si un centro deja la zona, desmárcalo como activo: su historial se conserva.</p>
  <div class="tabla-scroll">
    <table class="tabla tabla-lineas tabla-miembros" id="tabla-miembros"></table>
  </div>
</section>

<section class="panel">
  <h2>Datos de la zona</h2>
  <form id="form-ajustes" class="form-rejilla" autocomplete="off">
    <div class="campo campo--ancho">
      <label for="aj-iban">Cuenta bancaria de la zona (IBAN)</label>
      <input type="text" id="aj-iban" name="iban" maxlength="40" placeholder="ES00 0000 0000 0000 0000 0000">
    </div>
    <div class="campo">
      <label for="aj-banco">Banco</label>
      <input type="text" id="aj-banco" name="banco" maxlength="120" placeholder="Caixa Popular">
    </div>
    <div class="campo">
      <label for="aj-entidad">Nombre de la zona</label>
      <input type="text" id="aj-entidad" name="entidad" maxlength="120">
    </div>
    <div class="campo">
      <label for="aj-tesorero">Tesorero/a</label>
      <input type="text" id="aj-tesorero" name="tesorero" maxlength="120">
    </div>
    <div class="campo">
      <label for="aj-cargo-tesorero">Cargo (tal como sale en la firma)</label>
      <input type="text" id="aj-cargo-tesorero" name="cargo_tesorero" maxlength="120">
    </div>
    <div class="campo">
      <label for="aj-delegado">Delegado/a de zona</label>
      <input type="text" id="aj-delegado" name="delegado" maxlength="120">
    </div>
    <div class="campo">
      <label for="aj-cargo-delegado">Cargo (tal como sale en la firma)</label>
      <input type="text" id="aj-cargo-delegado" name="cargo_delegado" maxlength="120">
    </div>
  </form>
</section>

<section class="panel">
  <h2>Copia de seguridad</h2>
  <p>Todo se guarda en la Raspberry en <code>data/zona.json</code> y <code>data/tesoreria.json</code>. Descarga una copia de vez en cuando
    (por ejemplo, guárdala en la Nextcloud).</p>
  <div class="fila-botones">
    <a class="boton boton--secundario" href="api.php?action=export">Exportar copia (.json)</a>
    <label class="boton boton--secundario">
      Importar copia…
      <input type="file" id="importar" accept="application/json,.json" hidden>
    </label>
  </div>
  <p class="aviso-peligro">
    Importar <strong>sustituye</strong> todos los datos (movimientos, actividades, transporte, cuotas y centros) por los del fichero.
    Antes de importar se deja una copia de lo actual en la carpeta <code>data/</code> de la Raspberry.
  </p>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
