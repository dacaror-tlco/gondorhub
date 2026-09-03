/* Página: Actividades */
(() => {
  let estado = { personas: [], actividades: [], ambitos: {} };
  const abiertos = new Set();          // ids con el panel de asistencia desplegado
  const seleccion = new Map();         // id actividad -> Set(idPersona) sin guardar

  const $ = (s) => document.querySelector(s);
  const lista = $('#lista-actividades');
  const vacio = $('#actividades-vacio');
  const buscar = $('#buscar-actividad');
  const tplAct = $('#tpl-actividad');
  const tplEdit = $('#tpl-editar-actividad');

  const AMB_TXT = {
    ambos: 'Educadores y colaboradores',
    educadores: 'Solo educadores',
    colaboradores: 'Solo colaboradores',
  };

  async function cargar() {
    estado = await api('state');
    render();
    // Ir a la actividad indicada en la URL (#a012)
    if (location.hash.length > 1) {
      const el = document.getElementById(location.hash.slice(1));
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function personasDeAmbito(ambito) {
    return estado.personas
      .filter((p) => p.activo !== false)
      .filter((p) => {
        if (ambito === 'ambos') return true;
        if (ambito === 'educadores') return p.categoria === 'educador';
        if (ambito === 'colaboradores') return p.categoria === 'colaborador';
        return false;
      })
      .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
  }

  function actividadesOrdenadas() {
    const q = normaliza(buscar.value);
    return [...estado.actividades]
      .filter((a) => !q || normaliza(a.nombre).includes(q) || normaliza(a.descripcion).includes(q))
      .sort((a, b) => {
        const fa = a.fecha || '', fb = b.fecha || '';
        if (fa && fb) return fb.localeCompare(fa);
        if (fa) return -1;
        if (fb) return 1;
        return b.id.localeCompare(a.id);
      });
  }

  function render() {
    const acts = actividadesOrdenadas();
    $('#cont-actividades').textContent = estado.actividades.length;
    lista.querySelectorAll('.actividad').forEach((n) => n.remove());
    vacio.hidden = estado.actividades.length !== 0;

    for (const a of acts) {
      lista.appendChild(tarjeta(a));
    }
  }

  function tarjeta(a) {
    const nodo = tplAct.content.firstElementChild.cloneNode(true);
    nodo.dataset.id = a.id;
    nodo.id = a.id;

    nodo.querySelector('.actividad__nombre').textContent = a.nombre;
    nodo.querySelector('.actividad__fecha').textContent = fechaBonita(a.fecha);
    const amb = nodo.querySelector('.actividad__ambito');
    amb.textContent = AMB_TXT[a.ambito] || a.ambito;
    amb.classList.add(`etiqueta--${a.ambito}`);

    const posibles = personasDeAmbito(a.ambito).length;
    nodo.querySelector('.actividad__conteo').textContent =
      `${(a.asistentes || []).length} / ${posibles} asistentes`;

    const desc = nodo.querySelector('.actividad__desc');
    if (a.descripcion) desc.textContent = a.descripcion;
    else desc.remove();

    // --- botones cabecera ---
    nodo.querySelector('.js-toggle-asistencia').addEventListener('click', () => {
      const panel = nodo.querySelector('.actividad__panel');
      const abrir = panel.hidden;
      panel.hidden = !abrir;
      if (abrir) {
        abiertos.add(a.id);
        prepararPanel(nodo, a);
      } else {
        abiertos.delete(a.id);
      }
    });

    nodo.querySelector('.js-editar').addEventListener('click', () => editar(nodo, a));

    nodo.querySelector('.js-borrar').addEventListener('click', async () => {
      if (!confirm(`¿Borrar la actividad "${a.nombre}"?\nSe perderá su registro de asistencia.`)) return;
      try {
        estado = await api('actividad.delete', { id: a.id });
        abiertos.delete(a.id);
        seleccion.delete(a.id);
        render();
        aviso('Actividad borrada', 'ok');
      } catch (e) { aviso(e.message, 'error'); }
    });

    // Reabrir panel si estaba abierto antes del re-render
    if (abiertos.has(a.id)) {
      nodo.querySelector('.actividad__panel').hidden = false;
      prepararPanel(nodo, a);
    }
    return nodo;
  }

  function prepararPanel(nodo, a) {
    const cont = nodo.querySelector('.js-asistentes');
    const personas = personasDeAmbito(a.ambito);

    if (!seleccion.has(a.id)) {
      seleccion.set(a.id, new Set(a.asistentes || []));
    }
    const sel = seleccion.get(a.id);

    cont.innerHTML = '';
    if (!personas.length) {
      cont.innerHTML = '<p class="vacio">No hay personas activas para este ámbito. Añádelas en «Personas».</p>';
    }
    for (const p of personas) {
      const id = `chk-${a.id}-${p.id}`;
      const label = document.createElement('label');
      label.className = 'persona-chk';
      label.dataset.nombre = normaliza(p.nombre);
      label.innerHTML = `
        <input type="checkbox" id="${id}" ${sel.has(p.id) ? 'checked' : ''}>
        <span>${esc(p.nombre)}</span>
        <em class="persona-chk__cat">${p.categoria === 'educador' ? 'E' : 'C'}</em>`;
      label.querySelector('input').addEventListener('change', (e) => {
        if (e.target.checked) sel.add(p.id); else sel.delete(p.id);
        actualizarEstadoGuardado(nodo, a);
      });
      cont.appendChild(label);
    }

    const buscador = nodo.querySelector('.js-buscar-asistente');
    buscador.value = '';
    buscador.oninput = () => {
      const q = normaliza(buscador.value);
      cont.querySelectorAll('.persona-chk').forEach((el) => {
        el.hidden = q && !el.dataset.nombre.includes(q);
      });
    };

    nodo.querySelector('.js-todos').onclick = () => {
      personas.forEach((p) => sel.add(p.id));
      cont.querySelectorAll('input[type=checkbox]').forEach((c) => (c.checked = true));
      actualizarEstadoGuardado(nodo, a);
    };
    nodo.querySelector('.js-ninguno').onclick = () => {
      sel.clear();
      cont.querySelectorAll('input[type=checkbox]').forEach((c) => (c.checked = false));
      actualizarEstadoGuardado(nodo, a);
    };

    nodo.querySelector('.js-guardar-asistencia').onclick = async () => {
      try {
        estado = await api('actividad.asistencia', { id: a.id, asistentes: [...sel] });
        seleccion.delete(a.id);
        aviso('Asistencia guardada', 'ok');
        render();
      } catch (e) { aviso(e.message, 'error'); }
    };

    actualizarEstadoGuardado(nodo, a);
  }

  function actualizarEstadoGuardado(nodo, a) {
    const sel = seleccion.get(a.id) || new Set();
    const guardado = new Set(a.asistentes || []);
    const cambiado = sel.size !== guardado.size || [...sel].some((x) => !guardado.has(x));
    const est = nodo.querySelector('.js-estado-guardado');
    est.textContent = cambiado
      ? `${sel.size} marcados · sin guardar`
      : `${sel.size} marcados · guardado`;
    est.classList.toggle('sin-guardar', cambiado);
  }

  function editar(nodo, a) {
    if (nodo.querySelector('.form-editar')) return;
    const form = tplEdit.content.firstElementChild.cloneNode(true);
    form.nombre.value = a.nombre;
    form.fecha.value = a.fecha || '';
    form.ambito.value = a.ambito;
    form.descripcion.value = a.descripcion || '';
    nodo.querySelector('.actividad__cab').after(form);

    form.querySelector('.js-cancelar-edicion').onclick = () => form.remove();
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        estado = await api('actividad.update', {
          id: a.id,
          nombre: form.nombre.value.trim(),
          fecha: form.fecha.value,
          ambito: form.ambito.value,
          descripcion: form.descripcion.value.trim(),
        });
        seleccion.delete(a.id);
        aviso('Actividad actualizada', 'ok');
        render();
      } catch (err) { aviso(err.message, 'error'); }
    });
  }

  $('#form-actividad').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const nombre = f.nombre.value.trim();
    if (!nombre) return;
    try {
      estado = await api('actividad.add', {
        nombre,
        fecha: f.fecha.value,
        ambito: f.ambito.value,
        descripcion: f.descripcion.value.trim(),
      });
      f.reset();
      f.nombre.focus();
      render();
      aviso('Actividad creada', 'ok');
    } catch (err) { aviso(err.message, 'error'); }
  });

  buscar.addEventListener('input', render);

  cargar().catch((e) => aviso(e.message, 'error'));
})();
