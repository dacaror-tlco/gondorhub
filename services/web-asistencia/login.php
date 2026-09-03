<?php
require_once __DIR__ . '/includes/auth.php';

if (esta_autenticado()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clave = (string) ($_POST['clave'] ?? '');
    // Pequeña espera para dificultar la fuerza bruta.
    usleep(300000);
    if (password_correcta($clave)) {
        iniciar_sesion();
        header('Location: index.php');
        exit;
    }
    $error = 'Contraseña incorrecta.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Entrar · <?= htmlspecialchars(APP_TITULO) ?></title>
<link rel="icon" href="assets/img/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="assets/img/favicon-32.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body class="pagina-login">
<main class="login">
  <div class="login__tarjeta">
    <img src="assets/img/juniors-axenia-full.png" alt="Juniors Axenia 603 D" class="login__logo">
    <h1>Control de asistencia</h1>
    <p class="login__sub">Educadores y colaboradores · Curso <?= htmlspecialchars(APP_CURSO) ?></p>
    <form method="post" class="login__form" autocomplete="off">
      <label for="clave">Contraseña</label>
      <input type="password" id="clave" name="clave" required autofocus
             inputmode="text" autocomplete="current-password">
      <?php if ($error): ?>
        <p class="login__error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      <button type="submit" class="boton boton--primario">Entrar</button>
    </form>
  </div>
  <p class="login__campana">Campaña «Que es faça en mi»</p>
</main>
</body>
</html>
