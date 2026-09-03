</main>
<footer class="pie">
  <div class="pie__interior">
    <div class="pie__logos">
      <img src="assets/img/juniors-axenia-blanco.png" alt="Juniors Axenia 603 D">
      <img src="assets/img/xaipe-h2.svg" alt="Projecte Xaipe" class="pie__xaipe">
      <img src="assets/img/parroquia-blanco.png" alt="Parroquia Asunción de Nuestra Señora · Ayora">
    </div>
    <p class="pie__nota">
      Herramienta interna de seguimiento de asistencia ·
      Curso <?= htmlspecialchars(APP_CURSO) ?> ·
      Campaña <em>«Que es faça en mi»</em>
    </p>
  </div>
</footer>
<div id="avisos" class="avisos" aria-live="polite"></div>
<script src="assets/js/app.js?v=6"></script>
<?php if (!empty($scriptPagina)): ?>
<script src="assets/js/<?= htmlspecialchars($scriptPagina) ?>?v=6"></script>
<?php endif; ?>
</body>
</html>
