<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** ¿Hay sesión iniciada? */
function esta_autenticado(): bool
{
    return !empty($_SESSION['auth']) && $_SESSION['auth'] === true;
}

/** Comprueba una contraseña contra la configurada. */
function password_correcta(string $intento): bool
{
    return hash_equals(APP_PASSWORD, $intento);
}

/** Marca la sesión como iniciada y crea el token CSRF. */
function iniciar_sesion(): void
{
    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    return $token && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Exige sesión para páginas HTML: si no hay, redirige al login. */
function exigir_login(): void
{
    if (!esta_autenticado()) {
        header('Location: login.php');
        exit;
    }
}

/** Exige sesión para el API: si no hay, responde 401 JSON. */
function exigir_login_api(): void
{
    if (!esta_autenticado()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sesión no iniciada']);
        exit;
    }
}
