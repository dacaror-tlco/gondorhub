<?php
/**
 * Configuración de la web de asistencia — PLANTILLA
 * -------------------------------------------------
 * Copia este archivo a `config.php` y ajusta los valores:
 *
 *     cp includes/config.example.php includes/config.php
 *
 * `config.php` está en .gitignore: NUNCA se sube al repositorio.
 */

// --- Contraseña de acceso -----------------------------------------------
// Cámbiala por una tuya. Se pide una vez por navegador.
const APP_PASSWORD = 'CAMBIA_ESTA_CONTRASENA';

// Nombre del curso que se muestra en la cabecera.
const APP_CURSO = '2026 · 2027';

// Título de la web.
const APP_TITULO = 'Asistencia · Juniors Axenia 603 D';

// --- Rutas (normalmente no hay que tocar) ------------------------------
const DATA_FILE = __DIR__ . '/../data/data.json';
const SEED_FILE = __DIR__ . '/../data/seed.json';

// --- Zona horaria -----------------------------------------------------
date_default_timezone_set('Europe/Madrid');

// --- Ámbitos de actividad --------------------------------------------------
const AMBITOS = [
    'educadores'   => 'Solo educadores',
    'colaboradores'=> 'Solo colaboradores',
    'ambos'        => 'Educadores y colaboradores',
];
