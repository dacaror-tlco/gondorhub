<?php
require_once __DIR__ . '/auth.php';

/**
 * Uso:
 *   $seccion = 'asistencia';       // 'asistencia' | 'tesoreria' | '' (selector)
 *   $pagina = 'resumen';           // clave del menú activo
 *   $titulo = 'Resumen';
 *   require 'includes/layout.php'; // abre <html>..<main>
 *   ... contenido ...
 *   require 'includes/layout_fin.php';
 */

$seccion = $seccion ?? '';
$pagina = $pagina ?? '';
$titulo = $titulo ?? '';

$secciones = [
    'asistencia' => [
        'nombre' => 'Asistencia',
        'inicio' => 'asistencia.php',
        'nav'    => [
            'asistencia'  => ['Inicio', 'asistencia.php'],
            'actividades' => ['Actividades', 'actividades.php'],
            'personas'    => ['Personas', 'personas.php'],
            'resumen'     => ['Resumen', 'resumen.php'],
        ],
    ],
    'tesoreria' => [
        'nombre' => 'Tesorería',
        'inicio' => 'tesoreria.php',
        'nav'    => [
            'tesoreria' => ['Movimientos', 'tesoreria.php'],
            'balance'   => ['Balance', 'balance.php'],
        ],
    ],
];
$sec = $secciones[$seccion] ?? ['nombre' => 'Juniors Axenia', 'inicio' => 'index.php', 'nav' => []];
$nav = $sec['nav'];

$tituloCompleto = implode(' · ', array_filter([$titulo, $seccion ? $sec['nombre'] : '', 'Juniors Axenia 603 D']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($tituloCompleto) ?></title>
<link rel="icon" href="assets/img/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="assets/img/favicon-32.png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=8">
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body<?= $seccion ? ' class="seccion-' . $seccion . '"' : '' ?>>
<a class="saltar" href="#principal">Saltar al contenido</a>
<header class="cabecera">
  <div class="cabecera__interior">
    <a class="marca" href="<?= $sec['inicio'] ?>">
      <img src="assets/img/juniors-axenia.png" alt="Juniors Axenia 603 D" class="marca__logo">
      <span class="marca__texto">
        <strong><?= htmlspecialchars($sec['nombre']) ?></strong>
        <small>Juniors Axenia 603 D · Curso <?= htmlspecialchars(APP_CURSO) ?></small>
      </span>
    </a>
    <button class="menu-boton" aria-expanded="false" aria-controls="menu-principal" aria-label="Abrir menú">
      <span></span><span></span><span></span>
    </button>
    <nav class="navegacion" id="menu-principal">
      <?php foreach ($nav as $clave => [$texto, $url]): ?>
        <a href="<?= $url ?>"<?= $clave === $pagina ? ' class="activo" aria-current="page"' : '' ?>><?= htmlspecialchars($texto) ?></a>
      <?php endforeach; ?>
      <?php if ($seccion): ?>
        <a href="index.php" class="navegacion__secciones" title="Cambiar de sección">⇄ Secciones</a>
      <?php endif; ?>
      <a href="logout.php" class="navegacion__salir">Salir</a>
    </nav>
  </div>
</header>
<main id="principal" class="contenido">
