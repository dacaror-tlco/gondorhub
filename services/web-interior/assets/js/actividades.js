/* Página: listado de actividades (crear / duplicar) */
(() => {
  const form = document.getElementById('form-actividad');
  const plantilla = document.getElementById('form-plantilla');
  const curso = new URLSearchParams(location.search).get('curso');
  if (curso) guardarCurso(curso);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const r = await api('actividad.save', {
        actividad: {
          nombre: form.nombre.value.trim(),
          fecha: form.fecha.value,
          fecha_fin: form.fecha_fin.value,
        },
      });
      location.href = `actividad.php?id=${r.actividad.id}`;
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  plantilla?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const r = await api('actividad.duplicar', { id: plantilla.plantilla.value });
      location.href = `actividad.php?id=${r.actividad.id}`;
    } catch (err) {
      aviso(err.message, 'error');
    }
  });
})();
