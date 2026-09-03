/* Página: Personas */
(() => {
  let estado = { personas: [] };

  const $ = (s) => document.querySelector(s);
  const tbodies = {
    educador: $('#tbody-educadores'),
    colaborador: $('#tbody-colaboradores'),
  };
  const contadores = {
    educador: $('#cont-educadores'),
    colaborador: $('#cont-colaboradores'),
  };
  const buscar = $('#buscar-persona');
  const verBajas = $('#ver-bajas');

  const CAT_TXT = { educador: 'Educador/a', colaborador: 'Colaborador/a' };

  async function cargar() {
    const d = await api('state');
    estado = d;
    render();
  }

  function personasFiltradas(cat) {
    const q = normaliza(buscar.value);
    return estado.personas
      .filter((p) => p.categoria === cat)
      .filter((p) => verBajas.checked || p.activo !== false)
      .filter((p) => !q || normaliza(p.nombre).includes(q))
      .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
  }

  function filaPersona(p) {
    const tr = document.createElement('tr');
    if (p.activo === false) tr.classList.add('fila-baja');
    tr.dataset.id = p.id;
    tr.innerHTML = `
      <td>
        <span class="js-nombre celda-editable" tabindex="0" role="button"
              title="Clic para editar">${esc(p.nombre)}</span>
      </td>
      <td>
        <select class="js-cat mini-select">
          <option value="educador"${p.categoria === 'educador' ? ' selected' : ''}>Educador/a</option>
          <option value="colaborador"${p.categoria === 'colaborador' ? ' selected' : ''}>Colaborador/a</option>
        </select>
      </td>
      <td>
        <label class="switch">
          <input type="checkbox" class="js-activo" ${p.activo !== false ? 'checked' : ''}>
          <span>${p.activo !== false ? 'Activo' : 'Baja'}</span>
        </label>
      </td>
      <td class="col-acciones">
        <button class="boton boton--pequeno boton--peligro-fantasma js-borrar">Borrar</button>
      </td>`;

    // Editar nombre
    const nombreEl = tr.querySelector('.js-nombre');
    const editarNombre = () => iniciarEdicionNombre(tr, p);
    nombreEl.addEventListener('click', editarNombre);
    nombreEl.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); editarNombre(); }
    });

    tr.querySelector('.js-cat').addEventListener('change', async (e) => {
      await guardar(p.id, { categoria: e.target.value }, 'Categoría actualizada');
    });

    tr.querySelector('.js-activo').addEventListener('change', async (e) => {
      await guardar(p.id, { activo: e.target.checked }, e.target.checked ? 'Persona reactivada' : 'Persona dada de baja');
    });

    tr.querySelector('.js-borrar').addEventListener('click', async () => {
      if (!confirm(`¿Borrar a "${p.nombre}"?\n\nSe eliminará también de la asistencia de todas las actividades. Si solo se ha ido del grupo, es mejor darle de baja.`)) return;
      try {
        estado = await api('persona.delete', { id: p.id });
        render();
        aviso('Persona borrada', 'ok');
      } catch (err) { aviso(err.message, 'error'); }
    });

    return tr;
  }

  function iniciarEdicionNombre(tr, p) {
    const td = tr.querySelector('td');
    if (td.querySelector('input')) return;
    td.innerHTML = `<input type="text" class="js-nombre-input" value="${esc(p.nombre)}" maxlength="200">`;
    const input = td.querySelector('input');
    input.focus();
    input.select();
    const confirmar = async () => {
      const nuevo = input.value.trim();
      if (nuevo && nuevo !== p.nombre) {
        await guardar(p.id, { nombre: nuevo }, 'Nombre actualizado');
      } else {
        render();
      }
    };
    input.addEventListener('blur', confirmar);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') input.blur();
      if (e.key === 'Escape') render();
    });
  }

  async function guardar(id, cambios, msg) {
    try {
      estado = await api('persona.update', { id, ...cambios });
      render();
      aviso(msg, 'ok');
    } catch (err) {
      aviso(err.message, 'error');
      render();
    }
  }

  function render() {
    for (const cat of ['educador', 'colaborador']) {
      const lista = personasFiltradas(cat);
      const tb = tbodies[cat];
      tb.innerHTML = '';
      lista.forEach((p) => tb.appendChild(filaPersona(p)));
      const totalCat = estado.personas.filter(
        (p) => p.categoria === cat && (verBajas.checked || p.activo !== false)
      ).length;
      contadores[cat].textContent = totalCat;
      if (!lista.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="4" class="celda-vacia">Sin resultados</td>`;
        tb.appendChild(tr);
      }
    }
  }

  $('#form-persona').addEventListener('submit', async (e) => {
    e.preventDefault();
    const nombre = $('#np-nombre').value.trim();
    const categoria = $('#np-categoria').value;
    if (!nombre) return;
    try {
      estado = await api('persona.add', { nombre, categoria });
      $('#np-nombre').value = '';
      $('#np-nombre').focus();
      render();
      aviso('Persona añadida', 'ok');
    } catch (err) { aviso(err.message, 'error'); }
  });

  buscar.addEventListener('input', render);
  verBajas.addEventListener('change', render);

  cargar().catch((e) => aviso(e.message, 'error'));
})();
