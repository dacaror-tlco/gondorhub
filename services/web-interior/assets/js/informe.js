/* Página: Informe económico (solo marca qué actividades se incluyen; el resto lo pinta PHP) */
(() => {
  const curso = new URLSearchParams(location.search).get('curso');
  if (curso) guardarCurso(curso);

  document.querySelectorAll('.js-incluir').forEach((chk) =>
    chk.addEventListener('change', async () => {
      try {
        const { zona } = await api('state');
        const act = zona.actividades.find((a) => a.id === chk.dataset.id);
        act.informe = { ...(act.informe || {}), incluir: chk.checked };
        await api('actividad.save', { actividad: act });
        chk.closest('tr').classList.toggle('fila-baja', !chk.checked);
        aviso(chk.checked ? 'Incluida en el informe.' : 'Quitada del informe.', 'ok');
        setTimeout(() => location.reload(), 600); // para actualizar los totales
      } catch (err) {
        chk.checked = !chk.checked;
        aviso(err.message, 'error');
      }
    })
  );
})();
