<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tesoreria.php';
require_once __DIR__ . '/includes/zona.php';
exigir_login();

$zona = cargar_zona();
$curso = curso_actual();
$modo = modo_preferido();
$balance = calcular_balance(cargar_tesoreria(), periodo_actual($modo), $modo);

$nActividades = count(actividades_curso($zona, $curso));
$consejos = consejos_curso($zona, $curso);
$nCelebrados = count(array_filter($consejos, fn($c) => $c['estado'] === 'celebrado'));
$transporte = array_sum(array_map(fn($c) => $c['estado'] === 'celebrado' ? consejo_total($zona, $c) : 0, $consejos));
$cuota = calcular_cuota($zona, cuota_curso($zona, $curso));

$tarjetas = [
    ['tesoreria.php', 'Movimientos y balance', 'Ingresos y gastos por partidas y ficha de balance para imprimir.',
        'Resultado ' . ($modo === 'curso' ? lcfirst($balance['etiqueta']) : $balance['etiqueta']) . ': <strong class="'
        . ($balance['resultado'] < 0 ? 'negativo' : 'positivo') . '">' . formato_euros($balance['resultado']) . '</strong>',
        '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M16 13h.01"/><path d="M2 10h20"/><path d="M6 6V4h12v2"/>', ''],
    ['actividades.php', 'Actividades', 'Presupuestos y balances de convivencias, oraciones y demás actividades.',
        $nActividades . ' actividades este curso',
        '<path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>', 'naranja'],
    ['transporte.php', 'Transporte CJZ', 'Asistencia de los centros a los Consejos Juniors de Zona y pago del transporte.',
        $nCelebrados . ' consejos celebrados · ' . formato_euros($transporte),
        '<path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/>', 'azul'],
    ['cuota.php', 'Cuota de zona', 'Cálculo de la cuota de los centros según el censo y fichas de adjudicación.',
        $cuota['precio'] > 0 ? formato_euros($cuota['precio']) . ' por miembro · total ' . formato_euros($cuota['total']) : 'Sin calcular para ' . curso_barra($curso),
        '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>', 'morado'],
    ['informe.php', 'Informe económico', 'Informe económico de los proyectos para la memoria de zona, con control de facturas.',
        'Curso ' . curso_barra($curso),
        '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M8 17h2"/><path d="M14 13h2"/><path d="M14 17h2"/>', 'rojo'],
    ['ajustes.php', 'Centros y ajustes', 'Centros de la zona, cuentas bancarias, sedes, tarifas y copias de seguridad.',
        count(miembros_activos($zona, 'centro')) . ' centros activos',
        '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>', 'gris'],
];

$pagina = 'inicio';
$titulo = 'Inicio';
require __DIR__ . '/includes/layout.php';
?>

<section class="hero">
  <div class="hero__texto">
    <h1>Tesorería de la Zona Interior</h1>
    <p>Todo lo económico de la zona en un sitio: movimientos, actividades, transporte a los consejos, cuota de los centros e informe económico.</p>
  </div>
  <img src="assets/img/crismon.png" alt="" class="hero__crismon">
</section>

<section class="secciones">
  <?php foreach ($tarjetas as [$url, $nombre, $desc, $dato, $icono, $color]): ?>
    <a class="seccion-tarjeta<?= $color ? " seccion-tarjeta--$color" : '' ?>" href="<?= $url ?>">
      <span class="seccion-tarjeta__icono" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icono ?></svg>
      </span>
      <h2><?= h($nombre) ?></h2>
      <p><?= h($desc) ?></p>
      <p class="seccion-tarjeta__dato"><?= $dato ?></p>
      <span class="acceso__ir">Entrar →</span>
    </a>
  <?php endforeach; ?>
</section>

<?php require __DIR__ . '/includes/layout_fin.php'; ?>
