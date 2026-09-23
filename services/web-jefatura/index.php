<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tesoreria.php';
exigir_login();

// Un par de datos rápidos para cada tarjeta.
$data = cargar_datos();
$nActividades = count($data['actividades']);
$nPersonas = count(array_filter($data['personas'], fn($p) => ($p['activo'] ?? true) !== false));

$modo = modo_preferido();
$balance = calcular_balance(cargar_tesoreria(), periodo_actual($modo), $modo);

$seccion = '';
$pagina = '';
$titulo = 'Inicio';
require __DIR__ . '/includes/layout.php';
?>

<section class="hero">
  <div class="hero__texto">
    <h1>¿Qué quieres gestionar?</h1>
    <p>Elige una sección. Siempre puedes cambiar desde el menú con «⇄ Secciones».</p>
  </div>
  <img src="assets/img/campana-2026-27.png" alt="Campaña Que es faça en mi" class="hero__campana">
</section>

<section class="secciones">
  <a class="seccion-tarjeta" href="asistencia.php">
    <span class="seccion-tarjeta__icono" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg>
    </span>
    <h2>Asistencia</h2>
    <p>Actividades, educadores y colaboradores, y el resumen de asistencia del curso.</p>
    <p class="seccion-tarjeta__dato"><?= $nActividades ?> actividades · <?= $nPersonas ?> personas activas</p>
    <span class="acceso__ir">Entrar →</span>
  </a>
  <a class="seccion-tarjeta seccion-tarjeta--tesoreria" href="tesoreria.php">
    <span class="seccion-tarjeta__icono" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M16 13h.01"/><path d="M2 10h20"/><path d="M6 6V4h12v2"/></svg>
    </span>
    <h2>Tesorería</h2>
    <p>Ingresos y gastos por partidas, balance automático y ficha de balance para imprimir.</p>
    <p class="seccion-tarjeta__dato">
      Resultado <?= htmlspecialchars($modo === 'curso' ? lcfirst($balance['etiqueta']) : $balance['etiqueta']) ?>:
      <strong class="<?= $balance['resultado'] < 0 ? 'negativo' : 'positivo' ?>"><?= formato_euros($balance['resultado']) ?></strong>
    </p>
    <span class="acceso__ir">Entrar →</span>
  </a>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
