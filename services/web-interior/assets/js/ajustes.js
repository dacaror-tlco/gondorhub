/* Página: Centros y ajustes */
(() => {
  const $ = (s, raiz = document) => raiz.querySelector(s);
  const $$ = (s, raiz = document) => [...raiz.querySelectorAll(s)];
  let estado = null;
  const tabla = $('#tabla-miembros');
  const form = $('#form-ajustes');
  const estadoEl = $('#estado-ajustes');

  const guardadoAjustes = autoguardado(() => api('ajustes.save', { ajustes: estado.zona.ajustes }), estadoEl);
  const guardadoMiembros = autoguardado(async () => {
    const r = await api('miembros.save', { miembros: estado.zona.miembros });
    // Los nuevos reciben id en el servidor: lo copiamos sin redibujar (mismo orden).
    const validos = estado.zona.miembros.filter((m) => m.nombre.trim());
    r.zona.miembros.forEach((m, i) => { if (validos[i] && !validos[i].id) validos[i].id = m.id; });
  }, estadoEl);

  function renderMiembros() {
    tabla.innerHTML = `
      <thead><tr><th>Nombre</th><th>Nº centro</th><th>Localidad</th><th>Tipo</th><th>Cuenta bancaria (IBAN)</th><th>Activo</th><th class="col-acciones"></th></tr></thead>
      <tbody>${estado.zona.miembros.map((m, i) => `
        <tr data-i="${i}" class="${m.activo === false ? 'fila-baja' : ''}">
          <td><input type="text" data-k="nombre" value="${esc(m.nombre)}" maxlength="80" placeholder="Nombre" aria-label="Nombre"></td>
          <td><input type="text" data-k="numero" value="${esc(m.numero)}" maxlength="12" placeholder="603D" aria-label="Número de centro" class="corto"></td>
          <td><input type="text" data-k="localidad" value="${esc(m.localidad)}" maxlength="80" aria-label="Localidad"></td>
          <td><select data-k="tipo" aria-label="Tipo"><option value="centro" ${m.tipo !== 'otro' ? 'selected' : ''}>Centro</option><option value="otro" ${m.tipo === 'otro' ? 'selected' : ''}>Otro</option></select></td>
          <td><input type="text" data-k="iban" value="${esc(m.iban)}" maxlength="40" placeholder="ES00 …" aria-label="IBAN" class="iban"></td>
          <td><input type="checkbox" data-k="activo" ${m.activo !== false ? 'checked' : ''} aria-label="Activo"></td>
          <td class="col-acciones">${m.id ? '' : '<button type="button" class="icono icono--peligro js-quitar" aria-label="Quitar">✕</button>'}</td>
        </tr>`).join('')}</tbody>`;
  }

  tabla.addEventListener('input', (e) => {
    const t = e.target;
    if (!t.dataset.k || t.type === 'checkbox') return;
    const m = estado.zona.miembros[Number(t.closest('tr').dataset.i)];
    m[t.dataset.k] = t.value;
    if (m.nombre.trim()) guardadoMiembros.cambio();
  });
  tabla.addEventListener('change', (e) => {
    const t = e.target;
    if (t.type !== 'checkbox' && t.tagName !== 'SELECT') return;
    const tr = t.closest('tr');
    const m = estado.zona.miembros[Number(tr.dataset.i)];
    m[t.dataset.k] = t.type === 'checkbox' ? t.checked : t.value;
    tr.classList.toggle('fila-baja', m.activo === false);
    if (m.nombre.trim()) guardadoMiembros.cambio();
  });
  tabla.addEventListener('click', (e) => {
    const b = e.target.closest('.js-quitar');
    if (!b) return;
    estado.zona.miembros.splice(Number(b.closest('tr').dataset.i), 1);
    renderMiembros();
  });
  $('#nuevo-miembro').addEventListener('click', () => {
    estado.zona.miembros.push({ id: '', nombre: '', numero: '', localidad: '', tipo: 'centro', iban: '', activo: true });
    renderMiembros();
    $$('input[data-k="nombre"]', tabla).pop().focus();
  });

  form.addEventListener('input', (e) => {
    estado.zona.ajustes[e.target.name] = e.target.value;
    guardadoAjustes.cambio();
  });

  const importar = $('#importar');
  importar.addEventListener('change', async () => {
    const f = importar.files[0];
    if (!f) return;
    if (!confirm('Vas a SUSTITUIR todos los datos por los del fichero. ¿Continuar?')) {
      importar.value = '';
      return;
    }
    try {
      estado = await api('import', JSON.parse(await f.text()));
      aviso('Copia importada correctamente.', 'ok');
      render();
    } catch (err) {
      aviso('No se pudo importar: ' + err.message, 'error');
    } finally {
      importar.value = '';
    }
  });

  function render() {
    renderMiembros();
    for (const el of form.elements) if (el.name) el.value = estado.zona.ajustes[el.name] || '';
  }

  (async () => {
    try {
      estado = await api('state');
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  })();
})();
