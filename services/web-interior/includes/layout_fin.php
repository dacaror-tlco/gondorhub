</main>
<footer class="pie">
  <div class="pie__interior">
    <div class="pie__logos">
      <img src="assets/img/zona-interior-blanco.png" alt="Juniors Zona Interior">
    </div>
    <p class="pie__nota">
      Herramienta interna de tesorería · Juniors M.D. Zona Interior · Vicaría VI ·
      Curso <?= htmlspecialchars(APP_CURSO) ?>
    </p>
  </div>
</footer>
<div id="avisos" class="avisos" aria-live="polite"></div>
<script src="assets/js/app.js?v=<?= VERSION_ESTATICOS ?>"></script>
<?php if (!empty($scriptPagina)): ?>
<script src="assets/js/<?= htmlspecialchars($scriptPagina) ?>?v=<?= VERSION_ESTATICOS ?>"></script>
<?php endif; ?>
</body>
</html>
