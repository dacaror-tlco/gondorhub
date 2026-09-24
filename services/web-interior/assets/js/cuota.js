/* Página: Cuota de zona */
(() => {
  const $ = (s, raiz = document) => raiz.querySelector(s);
  const $$ = (s, raiz = document) => [...raiz.querySelectorAll(s)];
  let estado = null;
  let curso = new URLSearchParams(location.search).get('curso') || '';
  let cuota = null; // cuota del curso que se está editando

  const selCurso = $('#curso');
  const centros = () => estado.zona.miembros.filter((m) => m.tipo === 'centro' && m.activo !== false);

  const guardado = autoguardado(() => api('cuota.save', { curso, cuota }), $('#estado-cuota'));
  const cambio = () => {
    guardado.cambio();
    recalcular();
  };

  /** Cuota del curso; si no existe, parte de la del curso anterior (como cuota_curso() en PHP). */
  function cuotaDe(c) {
    const cuotas = mapa(estado.zona.cuotas);
    const a2 = Number(c.slice(5));
    const def = {
      precio: 0, censo: {}, referencia: { titulo: 'Cuota curso anterior', precio: 0, censo: {} }, gastos: [],
      titulo_presupuesto: `Presupuesto ${a2} Zona Interior`, escenarios: [], nota_aprobacion: '',
      fecha_limite: '', fecha_emision: '', cobros: {},
    };
    if (cuotas[c]) {
      const x = copia(cuotas[c]);
      const r = { ...def, ...x };
      r.referencia = { ...def.referencia, ...(x.referencia || {}) };
      r.censo = mapa(r.censo); r.cobros = mapa(r.cobros); r.referencia.censo = mapa(r.referencia.censo);
      r.escenarios.forEach((e) => (e.censo = mapa(e.censo)));
      return r;
    }
    const a = Number(c.slice(0, 4));
    const ant = cuotas[`${a - 1}-${a}`];
    if (ant) {
      def.precio = ant.precio;
      def.censo = copia(mapa(ant.censo));
      def.referencia = { titulo: `Cuota ${a - 1}/${a}`, precio: ant.precio, censo: copia(mapa(ant.censo)) };
      def.gastos = copia(ant.gastos || []);
    }
    return def;
  }

  /* ---------------- Cálculos ---------------- */

  const totalGastos = () => redondear(cuota.gastos.reduce((s, g) => s + num0(g.cantidad) * num0(g.precio), 0));

  function calcModelo(precio, censo) {
    const filas = centros().map((m) => {
      const n = Math.round(num0(censo[m.id]));
      const imp = redondear(n * num0(precio));
      const ref = redondear(num0(cuota.referencia.censo[m.id]) * num0(cuota.referencia.precio));
      return { m, n, imp, ref, varia: ref > 0 ? ((imp - ref) / ref) * 100 : null };
    });
    const total = redondear(filas.reduce((s, f) => s + f.imp, 0));
    return { filas, total, censo: filas.reduce((s, f) => s + f.n, 0), margen: redondear(total - totalGastos()) };
  }

  /** Transporte CJZ del curso: celebrado + previsto (si van todos). */
  function transportePrevisto() {
    const lugares = Object.fromEntries(estado.zona.lugares.map((l) => [l.id, l]));
    let t = 0;
    for (const c of estado.zona.consejos) {
      if (cursoDeFecha(c.fecha) !== curso || !c.pagar) continue;
      if (c.estado === 'previsto') {
        const tar = mapa(lugares[c.lugar]?.tarifas);
        t += estado.zona.miembros.filter((m) => m.activo !== false).reduce((s, m) => s + num0(tar[m.id]), 0);
      } else t += Object.values(mapa(c.asistentes)).reduce((s, v) => s + num0(v), 0);
    }
    return redondear(t);
  }

  /* ---------------- Render ---------------- */

  function renderGastos() {
    $('#tabla-gastos').innerHTML = `
      <thead><tr><th>Concepto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Total</th><th class="col-acciones"></th></tr></thead>
      <tbody>${cuota.gastos.map((g, i) => `
        <tr data-i="${i}">
          <td><input type="text" class="js-g" data-k="concepto" value="${esc(g.concepto)}" maxlength="150"></td>
          <td class="num"><input type="text" inputmode="decimal" class="js-g num" data-k="cantidad" value="${esc(numTexto(g.cantidad))}"></td>
          <td class="num"><input type="text" inputmode="decimal" class="js-g num" data-k="precio" value="${esc(numTexto(g.precio))}"></td>
          <td class="num nowrap js-g-total">${euros(num0(g.cantidad) * num0(g.precio))}</td>
          <td class="col-acciones"><button type="button" class="icono icono--peligro js-g-quitar" aria-label="Quitar">✕</button></td>
        </tr>`).join('')}</tbody>
      <tfoot><tr><td colspan="3">Total gastos</td><td class="num nowrap js-g-tot">${euros(totalGastos())}</td><td></td></tr></tfoot>`;
  }

  function renderCuota() {
    const r = calcModelo(cuota.precio, cuota.censo);
    const cobros = mapa(cuota.cobros);
    const guardada = !!mapa(estado.zona.cuotas)[curso];
    $('#tabla-cuota').innerHTML = `
      <thead><tr><th>Centro</th><th class="num">Censo</th><th class="num">Cuota</th><th class="num">Referencia</th><th class="num">Variación</th><th>Cobro</th><th class="col-acciones"></th></tr></thead>
      <tbody>${r.filas.map((f) => `
        <tr data-m="${f.m.id}">
          <td>${esc(nombreMiembro(f.m))}</td>
          <td class="num"><input type="text" inputmode="numeric" class="js-censo num" value="${esc(numTexto(cuota.censo[f.m.id] ?? ''))}" placeholder="0" aria-label="Censo de ${esc(f.m.nombre)}"></td>
          <td class="num nowrap js-imp"><strong>${euros(f.imp)}</strong></td>
          <td class="num nowrap gris js-ref">${euros(f.ref)}</td>
          <td class="num nowrap js-var"></td>
          <td>${cobros[f.m.id]
            ? `<span class="etiqueta etiqueta--ok">✓ Cobrada</span> <button type="button" class="enlace js-anular">deshacer</button>`
            : `<button type="button" class="boton boton--fantasma boton--pequeno js-cobrar" ${guardada ? '' : 'disabled title="Se guarda enseguida"'}>Apuntar cobro</button>`}</td>
          <td class="col-acciones"><a class="boton boton--pequeno boton--fantasma" href="fichas-cuota.php?curso=${curso}&amp;centro=${f.m.id}">Ficha</a></td>
        </tr>`).join('')}</tbody>
      <tfoot><tr><td>Total</td><td class="num js-censo-tot"></td><td class="num nowrap js-tot"></td><td class="num nowrap gris js-ref-tot"></td><td class="num js-var-tot"></td><td colspan="2"></td></tr></tfoot>`;
    $('#tabla-referencia').innerHTML = `
      <thead><tr><th>Centro</th><th class="num">Censo</th><th class="num">Cuota</th></tr></thead>
      <tbody>${centros().map((m) => `
        <tr data-m="${m.id}"><td>${esc(nombreMiembro(m))}</td>
          <td class="num"><input type="text" inputmode="numeric" class="js-ref-censo num" value="${esc(numTexto(cuota.referencia.censo[m.id] ?? ''))}" placeholder="0"></td>
          <td class="num nowrap js-ref-imp"></td></tr>`).join('')}</tbody>`;
  }

  function renderModelos() {
    const cont = $('#modelos');
    if (!cuota.escenarios.length) {
      cont.innerHTML = '<p class="vacio">No hay otros modelos.</p>';
      return;
    }
    cont.innerHTML = `<div class="tabla-scroll"><table class="tabla tabla-modelos">
      <thead><tr><th>Centro</th>${cuota.escenarios.map((e, i) => `
        <th data-e="${i}">
          <input type="text" class="js-e-nombre" value="${esc(e.nombre)}" maxlength="80" aria-label="Nombre del modelo">
          <label class="campo-inline">Precio <input type="text" inputmode="decimal" class="js-e-precio num" value="${esc(numTexto(e.precio))}"> €</label>
          <div class="fila-botones"><button type="button" class="boton boton--secundario boton--pequeno js-e-aplicar">Aplicar</button>
          <button type="button" class="icono icono--peligro js-e-quitar" aria-label="Quitar modelo">✕</button></div>
        </th>`).join('')}</tr></thead>
      <tbody>${centros().map((m) => `<tr><td>${esc(m.nombre)}</td>${cuota.escenarios.map((e, i) => `
        <td data-e="${i}" data-m="${m.id}" class="celda-modelo">
          <input type="text" inputmode="numeric" class="js-e-censo num" value="${esc(numTexto(e.censo[m.id] ?? ''))}" placeholder="0" aria-label="Censo">
          <span class="js-e-imp nowrap"></span></td>`).join('')}</tr>`).join('')}</tbody>
      <tfoot>
        <tr><td>Censo / ingresos</td>${cuota.escenarios.map((e, i) => `<td data-e="${i}" class="num nowrap js-e-tot"></td>`).join('')}</tr>
        <tr><td>Margen</td>${cuota.escenarios.map((e, i) => `<td data-e="${i}" class="num nowrap js-e-margen"></td>`).join('')}</tr>
      </tfoot></table></div>`;
  }

  function recalcular() {
    const r = calcModelo(cuota.precio, cuota.censo);
    const tg = totalGastos();
    $('#t-precio').textContent = euros(cuota.precio);
    $('#t-total').textContent = euros(r.total);
    $('#t-total-etq').textContent = `Ingresos por cuota · ${r.censo} miembros`;
    $('#t-gastos').textContent = euros(tg);
    const tm = $('#t-margen');
    tm.textContent = euros(r.margen);
    tm.className = `tarjeta-num__valor ${r.margen < 0 ? 'negativo' : 'positivo'}`;

    $$('#tabla-gastos tr[data-i]').forEach((tr) => {
      const g = cuota.gastos[Number(tr.dataset.i)];
      $('.js-g-total', tr).textContent = euros(num0(g.cantidad) * num0(g.precio));
    });
    const gt = $('#tabla-gastos .js-g-tot');
    if (gt) gt.textContent = euros(tg);

    const varTxt = (v) => (v === null ? '—' : `${v > 0 ? '+' : ''}${pct(v)}`);
    for (const f of r.filas) {
      const tr = $(`#tabla-cuota tr[data-m="${f.m.id}"]`);
      if (!tr) continue;
      $('.js-imp strong', tr).textContent = euros(f.imp);
      $('.js-ref', tr).textContent = euros(f.ref);
      const v = $('.js-var', tr);
      v.textContent = varTxt(f.varia);
      const ri = $(`#tabla-referencia tr[data-m="${f.m.id}"] .js-ref-imp`);
      if (ri) ri.textContent = euros(f.ref);
    }
    const refTot = r.filas.reduce((s, f) => s + f.ref, 0);
    if ($('.js-censo-tot')) {
      $('.js-censo-tot').textContent = r.censo;
      $('.js-tot').textContent = euros(r.total);
      $('.js-ref-tot').textContent = euros(refTot);
      $('.js-var-tot').textContent = varTxt(refTot > 0 ? ((r.total - refTot) / refTot) * 100 : null);
    }

    cuota.escenarios.forEach((e, i) => {
      const m = calcModelo(e.precio, e.censo);
      for (const f of m.filas) {
        const td = $(`#modelos td[data-e="${i}"][data-m="${f.m.id}"] .js-e-imp`);
        if (td) td.textContent = euros(f.imp);
      }
      const tot = $(`#modelos .js-e-tot[data-e="${i}"]`);
      if (tot) tot.textContent = `${m.censo} · ${euros(m.total)}`;
      const mg = $(`#modelos .js-e-margen[data-e="${i}"]`);
      if (mg) {
        mg.textContent = euros(m.margen);
        mg.classList.toggle('negativo', m.margen < 0);
      }
    });
  }

  function render() {
    estado.cursos.sort().reverse();
    if (!estado.cursos.includes(curso)) estado.cursos.unshift(curso);
    opcionesCursos(selCurso, estado.cursos, curso);
    guardarCurso(curso);
    history.replaceState(null, '', `?curso=${curso}`);
    $('#imprimir-presupuesto').href = `doc-cuota.php?curso=${curso}`;
    $('#imprimir-fichas').href = `fichas-cuota.php?curso=${curso}`;
    cuota = cuotaDe(curso);
    $('#titulo-presupuesto').value = cuota.titulo_presupuesto;
    $('#precio').value = numTexto(cuota.precio);
    $('#ref-titulo').value = cuota.referencia.titulo;
    $('#ref-precio').value = numTexto(cuota.referencia.precio);
    $('#nota-aprobacion').value = cuota.nota_aprobacion;
    $('#fecha-limite').value = cuota.fecha_limite;
    $('#fecha-emision').value = cuota.fecha_emision;
    renderGastos();
    renderCuota();
    renderModelos();
    recalcular();
    // Si el curso aún no tenía cuota guardada, la guardamos (así ya se puede cobrar).
    if (!mapa(estado.zona.cuotas)[curso]) guardarYRefrescar();
  }

  async function guardarYRefrescar() {
    try {
      estado = await api('cuota.save', { curso, cuota });
      renderCuota();
      recalcular();
    } catch (err) {
      aviso(err.message, 'error');
    }
  }

  /* ---------------- Eventos ---------------- */

  const campos = {
    '#titulo-presupuesto': (v) => (cuota.titulo_presupuesto = v),
    '#precio': (v) => (cuota.precio = num0(v)),
    '#ref-titulo': (v) => (cuota.referencia.titulo = v),
    '#ref-precio': (v) => (cuota.referencia.precio = num0(v)),
    '#nota-aprobacion': (v) => (cuota.nota_aprobacion = v),
    '#fecha-limite': (v) => (cuota.fecha_limite = v),
    '#fecha-emision': (v) => (cuota.fecha_emision = v),
  };
  for (const [sel, fn] of Object.entries(campos)) {
    $(sel).addEventListener('input', (e) => { fn(e.target.value); cambio(); });
  }

  $('#tabla-gastos').addEventListener('input', (e) => {
    const t = e.target;
    if (!t.classList.contains('js-g')) return;
    const g = cuota.gastos[Number(t.closest('tr').dataset.i)];
    g[t.dataset.k] = t.dataset.k === 'concepto' ? t.value : num0(t.value);
    cambio();
  });
  $('#tabla-gastos').addEventListener('click', (e) => {
    const b = e.target.closest('.js-g-quitar');
    if (!b) return;
    cuota.gastos.splice(Number(b.closest('tr').dataset.i), 1);
    renderGastos();
    cambio();
  });
  $('#anadir-gasto').addEventListener('click', () => {
    cuota.gastos.push({ concepto: '', cantidad: 1, precio: 0 });
    renderGastos();
    $$('#tabla-gastos input[data-k="concepto"]').pop()?.focus();
    cambio();
  });
  $('#traer-transporte').addEventListener('click', () => {
    const t = transportePrevisto();
    if (!t) return aviso('No hay consejos (celebrados ni previstos) en este curso. Créalos en Transporte CJZ.', 'info');
    let g = cuota.gastos.find((x) => /transporte/i.test(x.concepto));
    if (!g) cuota.gastos.push((g = { concepto: 'TRANSPORTE CJZ', cantidad: 1, precio: 0 }));
    g.cantidad = 1;
    g.precio = t;
    renderGastos();
    cambio();
    aviso(`Transporte CJZ previsto: ${euros(t)}.`, 'ok');
  });

  $('#tabla-cuota').addEventListener('input', (e) => {
    if (!e.target.classList.contains('js-censo')) return;
    cuota.censo[e.target.closest('tr').dataset.m] = Math.round(num0(e.target.value));
    cambio();
  });
  $('#tabla-cuota').addEventListener('click', async (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    const mid = b.closest('tr').dataset.m;
    const m = estado.zona.miembros.find((x) => x.id === mid);
    try {
      if (b.classList.contains('js-cobrar')) {
        const imp = redondear(num0(cuota.censo[mid]) * num0(cuota.precio));
        const f = prompt(`Se apuntará el ingreso de ${euros(imp)} (I2.1 Cuota Zona) de ${m.nombre} en Movimientos.\nFecha del cobro (AAAA-MM-DD):`, hoy());
        if (!f) return;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(f)) return aviso('Escribe la fecha como AAAA-MM-DD.', 'error');
        await guardado.ahora();
        estado = await api('cuota.cobro', { curso, miembro: mid, fecha: f });
        aviso('Cobro apuntado en Movimientos.', 'ok');
      } else if (b.classList.contains('js-anular')) {
        if (!confirm(`¿Borrar de Movimientos el cobro de la cuota de ${m.nombre}?`)) return;
        estado = await api('cuota.anular_cobro', { curso, miembro: mid });
      } else return;
      cuota.cobros = mapa(mapa(estado.zona.cuotas)[curso]?.cobros);
      renderCuota();
      recalcular();
    } catch (err) {
      aviso(err.message, 'error');
    }
  });
  $('#tabla-referencia').addEventListener('input', (e) => {
    if (!e.target.classList.contains('js-ref-censo')) return;
    cuota.referencia.censo[e.target.closest('tr').dataset.m] = Math.round(num0(e.target.value));
    cambio();
  });

  $('#nuevo-modelo').addEventListener('click', () => {
    const max = Math.max(0, ...cuota.escenarios.map((e) => Number(e.id.slice(1)) || 0));
    cuota.escenarios.push({ id: `e${String(max + 1).padStart(3, '0')}`, nombre: 'Nuevo modelo', precio: cuota.precio, censo: copia(cuota.censo) });
    renderModelos();
    cambio();
  });
  const contModelos = $('#modelos');
  contModelos.addEventListener('input', (e) => {
    const t = e.target;
    const cel = t.closest('[data-e]');
    if (!cel) return;
    const esc_ = cuota.escenarios[Number(cel.dataset.e)];
    if (t.classList.contains('js-e-nombre')) esc_.nombre = t.value;
    else if (t.classList.contains('js-e-precio')) esc_.precio = num0(t.value);
    else if (t.classList.contains('js-e-censo')) esc_.censo[cel.dataset.m] = Math.round(num0(t.value));
    else return;
    cambio();
  });
  contModelos.addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    const i = Number(b.closest('[data-e]').dataset.e);
    const m = cuota.escenarios[i];
    if (b.classList.contains('js-e-aplicar')) {
      if (!confirm(`¿Usar el precio (${euros(m.precio)}) y el censo de «${m.nombre}» como cuota del curso?\nEl modelo actual se guardará como otro modelo.`)) return;
      const max = Math.max(0, ...cuota.escenarios.map((x) => Number(x.id.slice(1)) || 0));
      cuota.escenarios.splice(i, 1, { id: `e${String(max + 1).padStart(3, '0')}`, nombre: 'Modelo anterior', precio: cuota.precio, censo: copia(cuota.censo) });
      cuota.precio = m.precio;
      cuota.censo = copia(m.censo);
      $('#precio').value = numTexto(cuota.precio);
      renderCuota();
    } else if (b.classList.contains('js-e-quitar')) {
      if (!confirm(`¿Quitar el modelo «${m.nombre}»?`)) return;
      cuota.escenarios.splice(i, 1);
    } else return;
    renderModelos();
    cambio();
  });

  // Antes de imprimir, guardamos lo pendiente.
  $$('#imprimir-presupuesto, #imprimir-fichas').forEach((a) => a.addEventListener('click', async (e) => {
    e.preventDefault();
    await guardado.ahora();
    location.href = a.href;
  }));
  $('#tabla-cuota').addEventListener('click', async (e) => {
    const a = e.target.closest('a');
    if (!a) return;
    e.preventDefault();
    await guardado.ahora();
    location.href = a.href;
  });

  selCurso.addEventListener('change', async () => {
    await guardado.ahora();
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
