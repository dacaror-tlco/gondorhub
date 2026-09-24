/* Página: Transporte a los Consejos Juniors de Zona */
(() => {
  const $ = (s, raiz = document) => raiz.querySelector(s);
  const $$ = (s, raiz = document) => [...raiz.querySelectorAll(s)];
  let estado = null;
  let curso = new URLSearchParams(location.search).get('curso') || '';

  const selCurso = $('#curso');
  const tabla = $('#tabla-asistencia');
  const modal = $('#modal-consejo');
  const form = $('#form-consejo');
  let editando = null; // id del consejo del modal ('' = nuevo)

  const Z = () => estado.zona;
  const lugar = (id) => Z().lugares.find((l) => l.id === id);
  const miembro = (id) => Z().miembros.find((m) => m.id === id);
  const tarifa = (lugarId, mid) => num0(mapa(lugar(lugarId)?.tarifas)[mid]);

  function consejosCurso() {
    return Z().consejos.filter((c) => cursoDeFecha(c.fecha) === curso).sort((a, b) => a.fecha.localeCompare(b.fecha));
  }

  /** Importe de un consejo: lo apuntado, o (si es previsto) lo que costaría si fueran todos. */
  function totalConsejo(c) {
    if (!c.pagar) return 0;
    if (c.estado === 'previsto') {
      return redondear(Z().miembros.filter((m) => m.activo !== false).reduce((s, m) => s + tarifa(c.lugar, m.id), 0));
    }
    return redondear(Object.values(mapa(c.asistentes)).reduce((s, v) => s + num0(v), 0));
  }

  function etiquetaConsejo(c) {
    const l = lugar(c.lugar);
    return `${c.nombre || 'CJZ'} - ${fechaBonita(c.fecha)}${l ? ` (${l.nombre})` : ''}`;
  }

  /* ---------------- Guardado de la asistencia ---------------- */

  const sucios = new Set();
  const guardadoAsis = autoguardado(async () => {
    const ids = [...sucios];
    sucios.clear();
    for (const id of ids) {
      const c = Z().consejos.find((x) => x.id === id);
      if (c) await api('consejo.save', { consejo: c });
    }
  }, $('#estado-asistencia'), 700);

  /* ---------------- Tabla de asistencia ---------------- */

  function filasMiembros(consejos) {
    const usados = new Set(consejos.flatMap((c) => Object.keys(mapa(c.asistentes))));
    return Z().miembros.filter((m) => m.activo !== false || usados.has(m.id));
  }

  function renderAsistencia() {
    const consejos = consejosCurso();
    $('#cont-consejos').textContent = consejos.length;
    $('#asistencia-vacio').hidden = consejos.length > 0;
    tabla.hidden = consejos.length === 0;
    if (!consejos.length) return;
    const filas = filasMiembros(consejos);
    const celda = (c, m) => {
      const as = mapa(c.asistentes);
      if (c.estado === 'previsto') {
        const t = c.pagar ? tarifa(c.lugar, m.id) : 0;
        return `<td class="celda-asis celda-asis--prevista" title="Previsto: tarifa ${euros(t)}">${t ? euros(t) : '—'}</td>`;
      }
      const va = m.id in as;
      return `<td class="celda-asis${va ? ' celda-asis--va' : ''}" data-c="${c.id}" data-m="${m.id}">
        <label><input type="checkbox" class="js-va" ${va ? 'checked' : ''} aria-label="${esc(m.nombre)} asistió">
        <input type="text" inputmode="decimal" class="js-imp num" value="${va ? esc(numTexto(as[m.id])) : ''}" ${va && c.pagar ? '' : 'hidden'} aria-label="Importe"></label></td>`;
    };
    tabla.innerHTML = `
      <thead><tr>
        <th>Miembro</th>
        ${consejos.map((c) => {
          const l = lugar(c.lugar);
          return `<th class="th-consejo${c.estado === 'previsto' ? ' th-consejo--previsto' : ''}">
            <button type="button" class="js-editar" data-id="${c.id}" title="Editar consejo">
              <span>${esc(c.nombre || 'CJZ')}</span><strong>${fechaBonita(c.fecha).slice(0, 5)}</strong><span>${esc(l?.nombre || '¿sede?')}</span>
              ${c.estado === 'previsto' ? '<em>previsto</em>' : ''}${!c.pagar ? '<em>sin pago</em>' : ''}
            </button></th>`;
        }).join('')}
        <th class="num">Total</th>
      </tr></thead>
      <tbody>
        ${filas.map((m) => `<tr data-m="${m.id}"><th scope="row">${esc(m.nombre)}</th>${consejos.map((c) => celda(c, m)).join('')}<td class="num nowrap js-total-m"></td></tr>`).join('')}
      </tbody>
      <tfoot><tr><td>Total</td>${consejos.map((c) => `<td class="num nowrap js-total-c" data-c="${c.id}"></td>`).join('')}<td class="num nowrap js-total"></td></tr></tfoot>`;
    recalcular();
  }

  /** Totales de filas, columnas y tarjetas (sin redibujar los campos). */
  function recalcular() {
    const consejos = consejosCurso();
    const cel = consejos.filter((c) => c.estado === 'celebrado');
    const prev = consejos.filter((c) => c.estado === 'previsto');
    $$('tr[data-m]', tabla).forEach((tr) => {
      const t = cel.reduce((s, c) => s + num0(mapa(c.asistentes)[tr.dataset.m]), 0);
      $('.js-total-m', tr).textContent = euros(t);
    });
    $$('.js-total-c', tabla).forEach((td) => {
      const c = consejos.find((x) => x.id === td.dataset.c);
      td.textContent = euros(totalConsejo(c));
      td.classList.toggle('gris', c.estado === 'previsto');
    });
    const gastado = cel.reduce((s, c) => s + totalConsejo(c), 0);
    const previsto = prev.reduce((s, c) => s + totalConsejo(c), 0);
    if ($('.js-total', tabla)) $('.js-total', tabla).textContent = euros(gastado);
    $('#t-celebrados').textContent = `${cel.length} / ${consejos.length}`;
    $('#t-gastado').textContent = euros(gastado);
    $('#t-previsto').textContent = euros(previsto);
    $('#t-previsto-etq').textContent = `Previsto (${prev.length} por celebrar) · total curso ${euros(gastado + previsto)}`;
    const liquidados = new Set(Z().liquidaciones.flatMap((q) => q.consejos));
    $('#t-pendiente').textContent = euros(cel.filter((c) => !liquidados.has(c.id)).reduce((s, c) => s + totalConsejo(c), 0));
  }

  tabla.addEventListener('change', (e) => {
    const t = e.target;
    if (!t.classList.contains('js-va')) return;
    const td = t.closest('td');
    const c = Z().consejos.find((x) => x.id === td.dataset.c);
    c.asistentes = mapa(c.asistentes);
    const imp = $('.js-imp', td);
    if (t.checked) {
      c.asistentes[td.dataset.m] = c.pagar ? tarifa(c.lugar, td.dataset.m) : 0;
      imp.value = numTexto(c.asistentes[td.dataset.m]);
      imp.hidden = !c.pagar;
    } else {
      delete c.asistentes[td.dataset.m];
      imp.hidden = true;
    }
    td.classList.toggle('celda-asis--va', t.checked);
    sucios.add(c.id);
    guardadoAsis.cambio();
    recalcular();
    renderLiquidaciones();
  });
  tabla.addEventListener('input', (e) => {
    const t = e.target;
    if (!t.classList.contains('js-imp')) return;
    const td = t.closest('td');
    const c = Z().consejos.find((x) => x.id === td.dataset.c);
    c.asistentes[td.dataset.m] = num0(t.value);
    sucios.add(c.id);
    guardadoAsis.cambio();
    recalcular();
  });
  tabla.addEventListener('click', (e) => {
    const b = e.target.closest('.js-editar');
    if (b) abrirConsejo(Z().consejos.find((c) => c.id === b.dataset.id));
  });

  /* ---------------- Modal de consejo ---------------- */

  function abrirConsejo(c) {
    editando = c ? c.id : '';
    $('#modal-consejo-titulo').textContent = c ? 'Editar consejo' : 'Nuevo consejo';
    form.lugar.innerHTML = '<option value="">Elige sede…</option>' + Z().lugares.map((l) => `<option value="${l.id}">${esc(l.nombre)}</option>`).join('');
    const f = c ? c.fecha : hoy();
    form.fecha.value = f;
    form.nombre.value = c ? c.nombre : 'CJZ';
    form.lugar.value = c ? c.lugar || '' : '';
    form.estado.value = c ? c.estado : (f > hoy() ? 'previsto' : 'celebrado');
    form.pagar.checked = c ? c.pagar : true;
    form.notas.value = c ? c.notas || '' : '';
    $('#borrar-consejo').hidden = !c;
    abrirModal(modal);
  }

  $('#nuevo-consejo').addEventListener('click', () => abrirConsejo(null));
  form.fecha.addEventListener('change', () => {
    if (!editando) form.estado.value = form.fecha.value > hoy() ? 'previsto' : 'celebrado';
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const previo = editando ? Z().consejos.find((c) => c.id === editando) : null;
    const consejo = {
      id: editando,
      fecha: form.fecha.value,
      nombre: form.nombre.value.trim() || 'CJZ',
      lugar: form.lugar.value,
      estado: form.estado.value,
      pagar: form.pagar.checked,
      notas: form.notas.value.trim(),
      asistentes: previo ? mapa(previo.asistentes) : {},
    };
    // Si cambia la sede o deja de pagarse, recalculamos lo que tenían los asistentes.
    if (previo && (previo.lugar !== consejo.lugar || previo.pagar !== consejo.pagar)) {
      for (const mid of Object.keys(consejo.asistentes)) consejo.asistentes[mid] = consejo.pagar ? tarifa(consejo.lugar, mid) : 0;
    }
    try {
      await guardadoAsis.ahora();
      estado = await api('consejo.save', { consejo });
      modal.close();
      const cc = cursoDeFecha(consejo.fecha);
      if (cc !== curso) {
        curso = cc;
        aviso(`Guardado en el curso ${cc.replace('-', '/')}.`, 'info');
      } else aviso('Consejo guardado.', 'ok');
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  $('#borrar-consejo').addEventListener('click', async () => {
    const c = Z().consejos.find((x) => x.id === editando);
    if (!confirm(`¿Borrar el consejo «${etiquetaConsejo(c)}» y su asistencia?`)) return;
    try {
      estado = await api('consejo.delete', { id: editando });
      modal.close();
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  /* ---------------- Liquidaciones ---------------- */

  function liqDelCurso(q) {
    if (!q.consejos.length) return q._curso === curso || !q._curso;
    return q.consejos.some((id) => {
      const c = Z().consejos.find((x) => x.id === id);
      return c && cursoDeFecha(c.fecha) === curso;
    });
  }

  function porMiembro(q) {
    const tot = {};
    for (const c of Z().consejos) {
      if (!q.consejos.includes(c.id) || !c.pagar || c.estado !== 'celebrado') continue;
      for (const [mid, v] of Object.entries(mapa(c.asistentes))) tot[mid] = redondear((tot[mid] || 0) + num0(v));
    }
    return tot;
  }

  const guardadoLiq = new Map(); // id -> autoguardado

  function renderLiquidaciones() {
    const cont = $('#liquidaciones');
    const lista = Z().liquidaciones.filter(liqDelCurso);
    const consejos = consejosCurso();
    if (!lista.length) {
      cont.innerHTML = '<p class="vacio">No hay liquidaciones en este curso.</p>';
      return;
    }
    cont.innerHTML = lista.map((q) => {
      const pm = porMiembro(q);
      const total = redondear(Object.values(pm).reduce((s, v) => s + v, 0));
      return `
      <div class="liquidacion${q.movimiento ? ' liquidacion--apuntada' : ''}" data-id="${q.id}">
        <div class="liquidacion__cab">
          <div class="campo campo--grande"><label>Nombre</label><input type="text" class="js-q-nombre" value="${esc(q.nombre)}" maxlength="80"></div>
          <div class="liquidacion__total"><span>Total</span><strong>${euros(total)}</strong></div>
        </div>
        <fieldset class="liquidacion__consejos">
          <legend>Consejos que incluye</legend>
          ${consejos.map((c) => `<label class="check-inline"><input type="checkbox" class="js-q-consejo" value="${c.id}" ${q.consejos.includes(c.id) ? 'checked' : ''} ${c.estado === 'previsto' ? 'disabled' : ''}>
            ${esc(etiquetaConsejo(c))}${c.estado === 'previsto' ? ' <small>(previsto)</small>' : ''}</label>`).join('')}
        </fieldset>
        <div class="liquidacion__pie">
          ${q.movimiento
            ? `<span class="etiqueta etiqueta--ok">✓ Apuntada en Movimientos${q.fecha ? ' el ' + fechaBonita(q.fecha) : ''}</span>
               <button type="button" class="boton boton--fantasma boton--pequeno js-q-desapuntar">Quitar de Movimientos</button>`
            : `<label class="check-inline">Fecha de pago <input type="date" class="js-q-fecha" value="${esc(q.fecha || hoy())}"></label>
               <button type="button" class="boton boton--secundario boton--pequeno js-q-apuntar" ${total > 0 ? '' : 'disabled'}>Apuntar gasto en Movimientos</button>
               <label class="check-inline"><input type="checkbox" class="js-q-pagada" ${q.pagada ? 'checked' : ''}> Pagada (sin apuntar)</label>`}
          <a class="boton boton--primario boton--pequeno" href="doc-transporte.php?liq=${q.id}">Imprimir hoja de pago</a>
          <button type="button" class="boton boton--peligro-fantasma boton--pequeno js-q-borrar">Borrar</button>
          <span class="js-estado-guardado js-q-estado"></span>
        </div>
      </div>`;
    }).join('');
  }

  function guardarLiq(q, div) {
    if (!guardadoLiq.has(q.id)) {
      guardadoLiq.set(q.id, autoguardado(() => api('liquidacion.save', { liquidacion: q }), null, 600));
    }
    guardadoLiq.get(q.id).cambio();
    const est = $('.js-q-estado', div);
    if (est) est.textContent = 'Guardando…';
    setTimeout(() => { if (est) est.textContent = ''; }, 1500);
  }

  const contLiq = $('#liquidaciones');
  const liqDe = (el) => {
    const div = el.closest('.liquidacion');
    return [Z().liquidaciones.find((q) => q.id === div.dataset.id), div];
  };
  contLiq.addEventListener('input', (e) => {
    if (!e.target.classList.contains('js-q-nombre')) return;
    const [q, div] = liqDe(e.target);
    q.nombre = e.target.value;
    if (q.nombre.trim()) guardarLiq(q, div);
  });
  contLiq.addEventListener('change', (e) => {
    const t = e.target;
    const [q, div] = liqDe(t);
    if (t.classList.contains('js-q-consejo')) {
      q.consejos = t.checked ? [...q.consejos, t.value] : q.consejos.filter((id) => id !== t.value);
      renderLiquidaciones();
      recalcular();
    } else if (t.classList.contains('js-q-fecha')) q.fecha = t.value;
    else if (t.classList.contains('js-q-pagada')) q.pagada = t.checked;
    else return;
    guardarLiq(q, div);
  });
  contLiq.addEventListener('click', async (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    const [q, div] = liqDe(b);
    try {
      if (b.classList.contains('js-q-apuntar')) {
        const fecha = $('.js-q-fecha', div).value || hoy();
        if (!confirm(`Se apuntará un gasto de transporte (G3.1) con fecha ${fechaBonita(fecha)} en Movimientos. ¿Seguir?`)) return;
        await guardadoLiq.get(q.id)?.ahora();
        estado = await api('liquidacion.apuntar', { id: q.id, fecha });
        aviso('Gasto apuntado en Movimientos.', 'ok');
      } else if (b.classList.contains('js-q-desapuntar')) {
        if (!confirm('Se borrará el gasto apuntado en Movimientos. ¿Seguir?')) return;
        estado = await api('liquidacion.desapuntar', { id: q.id });
      } else if (b.classList.contains('js-q-borrar')) {
        if (!confirm(`¿Borrar la liquidación «${q.nombre}»?${q.movimiento ? '\n(El gasto apuntado en Movimientos se conserva.)' : ''}`)) return;
        estado = await api('liquidacion.delete', { id: q.id });
      } else return;
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  $('#nueva-liquidacion').addEventListener('click', async () => {
    const liquidados = new Set(Z().liquidaciones.flatMap((q) => q.consejos));
    const pendientes = consejosCurso().filter((c) => c.estado === 'celebrado' && !liquidados.has(c.id)).map((c) => c.id);
    const n = Z().liquidaciones.filter(liqDelCurso).length;
    try {
      await guardadoAsis.ahora();
      estado = await api('liquidacion.save', {
        liquidacion: { id: '', nombre: n ? `Pago ${n + 1}` : 'Pago 1er trimestre', consejos: pendientes, fecha: '', pagada: false },
      });
      render();
      aviso(pendientes.length ? `Liquidación creada con los ${pendientes.length} consejos celebrados sin liquidar.` : 'Liquidación creada.', 'ok');
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  /* ---------------- Sedes y tarifas ---------------- */

  const tablaTarifas = $('#tabla-tarifas');
  const guardadoTarifas = autoguardado(async () => {
    const r = await api('lugares.save', { lugares: Z().lugares });
    // Las sedes nuevas reciben id en el servidor: lo copiamos sin redibujar.
    r.zona.lugares.forEach((l, i) => { if (Z().lugares[i] && !Z().lugares[i].id) Z().lugares[i].id = l.id; });
  }, $('#estado-tarifas'), 800);

  function renderTarifas() {
    const lugares = Z().lugares;
    const ms = Z().miembros.filter((m) => m.activo !== false);
    const opcionesAnfitrion = (sel) => '<option value="">(no es un centro)</option>' + Z().miembros.filter((m) => m.tipo === 'centro')
      .map((m) => `<option value="${m.id}" ${m.id === sel ? 'selected' : ''}>${esc(m.nombre)}</option>`).join('');
    tablaTarifas.innerHTML = `
      <thead><tr><th>Miembro \\ Sede</th>${lugares.map((l, i) => `
        <th data-l="${i}">
          <input type="text" class="js-l-nombre" value="${esc(l.nombre)}" maxlength="80" aria-label="Nombre de la sede">
          <select class="js-l-miembro mini-select" aria-label="Centro anfitrión">${opcionesAnfitrion(l.miembro)}</select>
          <button type="button" class="icono icono--peligro js-l-borrar" title="Quitar sede" aria-label="Quitar sede">✕</button>
        </th>`).join('')}</tr></thead>
      <tbody>${ms.map((m) => `<tr><th scope="row">${esc(m.nombre)}</th>${lugares.map((l, i) => `
        <td data-l="${i}" data-m="${m.id}"><input type="text" inputmode="decimal" class="js-tarifa num" value="${esc(numTexto(mapa(l.tarifas)[m.id] ?? 0))}"></td>`).join('')}</tr>`).join('')}</tbody>
      <tfoot><tr><td>Si van todos</td>${lugares.map((l) => `<td class="num nowrap">${euros(ms.reduce((s, m) => s + num0(mapa(l.tarifas)[m.id]), 0))}</td>`).join('')}</tr></tfoot>`;
  }

  tablaTarifas.addEventListener('input', (e) => {
    const t = e.target;
    const cel = t.closest('[data-l]');
    if (!cel) return;
    const l = Z().lugares[Number(cel.dataset.l)];
    if (t.classList.contains('js-tarifa')) {
      l.tarifas = mapa(l.tarifas);
      l.tarifas[cel.dataset.m] = num0(t.value);
    } else if (t.classList.contains('js-l-nombre')) {
      if (!t.value.trim()) return;
      l.nombre = t.value;
    } else return;
    guardadoTarifas.cambio();
    recalcular();
  });
  tablaTarifas.addEventListener('change', (e) => {
    if (!e.target.classList.contains('js-l-miembro')) return;
    Z().lugares[Number(e.target.closest('[data-l]').dataset.l)].miembro = e.target.value || null;
    guardadoTarifas.cambio();
  });
  tablaTarifas.addEventListener('click', (e) => {
    const b = e.target.closest('.js-l-borrar');
    if (!b) return;
    const i = Number(b.closest('[data-l]').dataset.l);
    const l = Z().lugares[i];
    if (Z().consejos.some((c) => c.lugar === l.id)) return aviso('Esa sede tiene consejos: cámbialos de sede antes de quitarla.', 'error');
    if (!confirm(`¿Quitar la sede «${l.nombre}»?`)) return;
    Z().lugares.splice(i, 1);
    renderTarifas();
    guardadoTarifas.cambio();
  });
  $('#nueva-sede').addEventListener('click', () => {
    Z().lugares.push({ id: '', nombre: 'Nueva sede', miembro: null, tarifas: {} });
    renderTarifas();
    guardadoTarifas.cambio();
    $$('.js-l-nombre', tablaTarifas).pop()?.select();
  });

  /* ---------------- Arranque ---------------- */

  function render() {
    if (!curso || !estado.cursos.includes(curso)) {
      if (!estado.cursos.includes(curso) && curso) estado.cursos.push(curso);
      curso ||= estado.curso;
    }
    estado.cursos.sort().reverse();
    opcionesCursos(selCurso, estado.cursos, curso);
    guardarCurso(curso);
    history.replaceState(null, '', `?curso=${curso}`);
    $('#imprimir-curso').href = `doc-transporte.php?curso=${curso}`;
    renderAsistencia();
    renderLiquidaciones();
    renderTarifas();
  }

  selCurso.addEventListener('change', async () => {
    await guardadoAsis.ahora();
    curso = selCurso.value;
    render();
  });

  (async () => {
    try {
      estado = await api('state');
      if (!curso) curso = document.cookie.match(/zona_curso=(\d{4}-\d{4})/)?.[1] || estado.curso;
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  })();
})();
