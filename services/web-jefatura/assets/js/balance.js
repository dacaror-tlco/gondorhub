/* Página: Ficha de balance (solo guarda el comentario; el resto lo pinta PHP) */
(() => {
  const txt = document.getElementById('comentario');
  const boton = document.getElementById('guardar-comentario');
  const estado = document.getElementById('estado-comentario');
  const impreso = document.getElementById('comentario-impreso');
  if (!txt || !boton) return;

  let guardado = txt.value;

  txt.addEventListener('input', () => {
    const cambiado = txt.value !== guardado;
    estado.textContent = cambiado ? 'Sin guardar' : '';
    estado.classList.toggle('sin-guardar', cambiado);
    impreso.textContent = txt.value; // la vista de impresión siempre refleja lo escrito
  });

  boton.addEventListener('click', async () => {
    try {
      await api('comentario.save', { anio: Number(txt.dataset.anio), modo: txt.dataset.modo, texto: txt.value },
        { endpoint: 'api-tesoreria.php' });
      guardado = txt.value;
      estado.textContent = 'Guardado';
      estado.classList.remove('sin-guardar');
    } catch (e) {
      aviso(e.message, 'error');
    }
  });

  window.addEventListener('beforeunload', (e) => {
    if (txt.value !== guardado) e.preventDefault();
  });
})();
