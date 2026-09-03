<?php
require_once __DIR__ . '/auth.php';

/**
 * Uso:
 *   $pagina = 'resumen';           // clave del menú activo
 *   $titulo = 'Resumen';
 *   require 'includes/layout.php'; // abre <html>..<main>
 *   ... contenido ...
 *   require 'includes/layout_fin.php';
 */

$pagina = $pagina ?? '';
$titulo = $titulo ?? '';
$tituloCompleto = $titulo ? "$titulo · " . APP_TITULO : APP_TITULO;

$nav = [
    'index'       => ['Inicio', 'index.php'],
    'actividades' => ['Actividades', 'actividades.php'],
    'personas'    => ['Personas', 'personas.php'],
    'resumen'     => ['Resumen', 'resumen.php'],
];
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
<link rel="stylesheet" href="assets/css/style.css?v=6">
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body>
<a class="saltar" href="#principal">Saltar al contenido</a>
<header class="cabecera">
  <div class="cabecera__interior">
    <a class="marca" href="index.php">
      <img src="assets/img/juniors-axenia.png" alt="Juniors Axenia 603 D" class="marca__logo">
      <span class="marca__texto">
        <strong>Asistencia</strong>
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
      <a href="logout.php" class="navegacion__salir">Salir</a>
    </nav>
  </div>
</header>
<main id="principal" class="contenido">
