<?php
require_once __DIR__ . '/includes/auth.php';
cerrar_sesion();
header('Location: login.php');
exit;
