/* Página: una actividad (presupuesto, balance, ingresos por centros, facturas y movimientos) */
(() => {
  const { miembros, categorias } = window.DATOS;
  const act = window.DATOS.actividad;
  const $ = (s, raiz = document) => raiz.querySelector(s);
  const $$ = (s, raiz = document) => [...raiz.querySelectorAll(s)];
  const centros = miembros.filter((m) => m.tipo === 'centro' && m.activo !== false);
  const MIEMBRO = Object.fromEntries(miembros.map((m) => [m.id, m]));

  act.centros ||= [];
  act.informe ||= { incluir: true, ingresos_actividad: null, participacion: null, facturas: [] };
  act.informe.facturas ||= [];

  const guardado = autoguardado(() => api('actividad.save', { actividad: act }), $('#estado-guardado'));
  const cambio = () => {
    guardado.cambio();
    recalcular();
  };

  const nuevoId = (prefijo, lista) => {
    const max = Math.max(0, ...lista.map((x) => Number(String(x.id || '').slice(1)) || 0));
    return prefijo + String(max + 1).padStart(3, '0');
  };

  /* =====================================================================
   *  Cálculos (los mismos que calcular_hoja() / calcular_informe() en PHP)
   * ===================================================================== */

  function grupoTotales(g) {
    let cant = 0, imp = 0;
    for (const f of g.filas || []) {
      const c = num0(f.cantidad);
      cant += c;
      imp += c * (f.precio ?? num0(g.precio));
    }
    return [cant, redondear(imp)];
  }

  function calcLinea(l) {
    const g = l.grupo && act.centros.find((x) => x.id === l.grupo);
    if (g) {
      const [cant, imp] = grupoTotales(g);
      return { cant, precio: cant ? imp / cant : num0(g.precio), total: imp, auto: 'grupo' };
    }
    if (l.detalle?.length) {
      const precio = redondear(l.detalle.reduce((s, x) => s + num0(x.importe), 0));
      return { cant: 1, precio, total: precio, auto: 'detalle' };
    }
    const cant = num0(l.cantidad), precio = num0(l.precio);
    return { cant, precio, total: redondear(cant * precio) };
  }

  function calcHoja(hoja) {
    const gastos = hoja.gastos.map(calcLinea);
    const tg = redondear(gastos.reduce((s, x) => s + x.total, 0));
    let resto = 0;
    const ingresos = hoja.ingresos.map((l) => {
      if (l.cuadrar) return null;
      const r = calcLinea(l);
      resto += r.total;
      return r;
    });
    let cuadrado = false;
    hoja.ingresos.forEach((l, i) => {
      if (!l.cuadrar) return;
      const imp = cuadrado ? 0 : redondear(tg - resto);
      cuadrado = true;
      ingresos[i] = { cant: 1, precio: imp, total: imp, auto: 'cuadrar' };
    });
    const ti = redondear(ingresos.reduce((s, x) => s + x.total, 0));
    return { ingresos, gastos, ti, tg, resultado: redondear(ti - tg) };
  }

  function calcInforme() {
    const cats = Object.fromEntries(Object.keys(categorias).map((k) => [k, 0]));
    for (const f of act.informe.facturas) {
      const c = f.categoria in cats ? f.categoria : 'otros';
      cats[c] = redondear(cats[c] + num0(f.importe));
    }
    const tg = redondear(Object.values(cats).reduce((s, x) => s + x, 0));
    const bal = calcHoja(act.balance);
    const ingAuto = redondear(act.balance.ingresos.reduce((s, l, i) => s + (l.zona ? 0 : bal.ingresos[i].total), 0));
    const ing = act.informe.ingresos_actividad ?? ingAuto;
    const partAuto = Math.max(0, redondear(tg - ing));
    const part = act.informe.participacion ?? partAuto;
    return { cats, tg, ingAuto, ing, partAuto, part, ti: redondear(ing + part) };
  }

  /* =====================================================================
   *  Datos generales
   * ===================================================================== */

  const formDatos = $('#form-datos');
  for (const k of ['nombre', 'fecha', 'fecha_fin', 'lugar']) {
    formDatos[k].value = act[k] || '';
    formDatos[k].addEventListener('input', () => {
      if (k === 'fecha' && !formDatos.fecha.value) return; // no guardamos sin fecha
      if (k === 'nombre' && !formDatos.nombre.value.trim()) return;
      act[k] = formDatos[k].value;
      if (k === 'nombre') {
        $('#titulo-actividad').textContent = act.nombre;
        document.title = `${act.nombre} · Tesorería Zona Interior`;
      }
      cambio();
    });
  }

  /* =====================================================================
   *  Hojas (presupuesto / balance)
   * ===================================================================== */

  const abiertas = new WeakSet(); // líneas con el panel de opciones desplegado

  function filaLinea(l, tipo, i, total) {
    const bloqueado = l.grupo || l.cuadrar || l.detalle?.length;
    const marcas = [
      l.cuadrar ? '<span class="etiqueta etiqueta--zona" title="Importe = gastos − resto de ingresos">cuadra</span>' : '',
      l.zona ? '<span class="etiqueta etiqueta--zona" title="Aportación de la Zona">zona</span>' : '',
      l.grupo ? '<span class="etiqueta" title="Cantidad y precio de «Ingresos por centros»">centros</span>' : '',
      l.detalle?.length ? `<span class="etiqueta" title="Precio = suma del desglose">desglose</span>` : '',
    ].join('');
    return `
      <tr class="linea" data-tipo="${tipo}" data-i="${i}">
        <td><input type="text" class="js-concepto" value="${esc(l.concepto)}" maxlength="150" placeholder="Concepto" aria-label="Concepto">
          <div class="linea__marcas">${marcas}</div></td>
        <td class="num"><input type="text" inputmode="decimal" class="js-cantidad num" value="${esc(bloqueado ? numTexto(total.cant) : numTexto(l.cantidad))}" ${bloqueado ? 'disabled' : ''} aria-label="Cantidad"></td>
        <td class="num"><input type="text" inputmode="decimal" class="js-precio num" value="${esc(bloqueado ? numTexto(redondear(total.precio)) : numTexto(l.precio))}" ${bloqueado ? 'disabled' : ''} aria-label="Precio"></td>
        <td class="num nowrap js-total">${euros(total.total)}</td>
        <td class="col-acciones">
          <button type="button" class="icono js-opciones${abiertas.has(l) ? ' activo' : ''}" title="Más opciones (desglose${tipo === 'ingresos' ? ', aportación de la Zona, centros' : ''})" aria-label="Más opciones">⚙</button>
          <button type="button" class="icono js-subir" title="Subir" aria-label="Subir" ${i === 0 ? 'disabled' : ''}>↑</button>
          <button type="button" class="icono js-bajar" title="Bajar" aria-label="Bajar">↓</button>
          <button type="button" class="icono icono--peligro js-quitar" title="Quitar línea" aria-label="Quitar línea">✕</button>
        </td>
      </tr>
      ${abiertas.has(l) ? filaOpciones(l, tipo, i) : ''}`;
  }

  function filaOpciones(l, tipo, i) {
    const grupos = act.centros.map((g) => `<option value="${g.id}" ${l.grupo === g.id ? 'selected' : ''}>${esc(g.nombre || 'Grupo')} (${euros(g.precio)})</option>`).join('');
    const det = (l.detalle || []).map((x, j) => `
      <tr data-j="${j}">
        <td><input type="text" class="js-det-concepto" value="${esc(x.concepto)}" placeholder="Ej. MERCADONA" aria-label="Concepto del desglose"></td>
        <td class="num"><input type="text" inputmode="decimal" class="js-det-importe num" value="${esc(numTexto(x.importe))}" aria-label="Importe"></td>
        <td class="col-acciones"><button type="button" class="icono icono--peligro js-det-quitar" aria-label="Quitar">✕</button></td>
      </tr>`).join('');
    return `
      <tr class="linea-opciones" data-tipo="${tipo}" data-i="${i}">
        <td colspan="5">
          ${tipo === 'ingresos' ? `
          <div class="linea-opciones__checks">
            <label class="check-inline"><input type="checkbox" class="js-cuadrar" ${l.cuadrar ? 'checked' : ''}> Cuadra el balance <small>(importe = gastos − resto de ingresos)</small></label>
            <label class="check-inline"><input type="checkbox" class="js-zona" ${l.zona ? 'checked' : ''}> Es aportación de la Zona <small>(para el informe económico)</small></label>
            <label class="check-inline">Cantidad y precio de
              <select class="js-grupo mini-select"><option value="">— lo que escriba —</option>${grupos}</select></label>
          </div>` : ''}
          <div class="desglose">
            <strong>Desglose</strong> <small class="gris">(como la tabla de INTENDENCIA: el precio de la línea pasa a ser la suma)</small>
            ${det ? `<table class="tabla-desglose"><tbody>${det}</tbody></table>` : ''}
            <button type="button" class="boton boton--fantasma boton--pequeno js-det-anadir">＋ Añadir al desglose</button>
          </div>
        </td>
      </tr>`;
  }

  function renderHoja(nombre) {
    const hoja = act[nombre];
    const c = calcHoja(hoja);
    const cont = $(`#tab-${nombre} .js-hoja`);
    const bloque = (tipo, titulo, total) => `
      <div class="hoja-bloque hoja-bloque--${tipo}">
        <div class="hoja-cab"><h3>${titulo}</h3><span class="hoja-cab__total js-total-${tipo}">${euros(total)}</span></div>
        <div class="tabla-scroll">
          <table class="tabla tabla-lineas">
            <thead><tr><th>Concepto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Total</th><th class="col-acciones"></th></tr></thead>
            <tbody>${hoja[tipo].map((l, i) => filaLinea(l, tipo, i, c[tipo][i])).join('')}</tbody>
          </table>
        </div>
        ${hoja[tipo].length ? '' : '<p class="vacio">Sin líneas.</p>'}
        <button type="button" class="boton boton--secundario boton--pequeno js-anadir" data-tipo="${tipo}">＋ Añadir ${tipo === 'ingresos' ? 'ingreso' : 'gasto'}</button>
      </div>`;
    cont.innerHTML = `
      ${bloque('ingresos', 'Ingresos', c.ti)}
      ${bloque('gastos', 'Gastos', c.tg)}
      <div class="hoja-resultado js-resultado ${c.resultado < 0 ? 'negativo' : 'positivo'}">
        <span>${nombre === 'presupuesto' ? 'Resultado previsto' : 'Resultado'}</span><strong>${euros(c.resultado)}</strong>
      </div>
      <div class="campo">
        <label for="com-${nombre}">Comentario <small>(sale impreso debajo del ${nombre})</small></label>
        <textarea id="com-${nombre}" class="js-comentario" rows="5" maxlength="5000" placeholder="Explica el resultado, las votaciones en el CJZ, etc.">${esc(hoja.comentario || '')}</textarea>
      </div>`;
  }

  /** Solo actualiza números (sin redibujar los campos, para no perder el foco). */
  function recalcularHoja(nombre) {
    const hoja = act[nombre];
    const c = calcHoja(hoja);
    const cont = $(`#tab-${nombre} .js-hoja`);
    if (!cont.firstElementChild) return;
    for (const tipo of ['ingresos', 'gastos']) {
      $(`.js-total-${tipo}`, cont).textContent = euros(tipo === 'ingresos' ? c.ti : c.tg);
      $$(`tr.linea[data-tipo="${tipo}"]`, cont).forEach((tr) => {
        const r = c[tipo][Number(tr.dataset.i)];
        $('.js-total', tr).textContent = euros(r.total);
        if (r.auto) {
          $('.js-cantidad', tr).value = numTexto(r.cant);
          $('.js-precio', tr).value = numTexto(redondear(r.precio));
        }
      });
    }
    const res = $('.js-resultado', cont);
    res.classList.toggle('negativo', c.resultado < 0);
    res.classList.toggle('positivo', c.resultado >= 0);
    $('strong', res).textContent = euros(c.resultado);
  }

  function prepararHoja(nombre) {
    const cont = $(`#tab-${nombre} .js-hoja`);
    const linea = (el) => {
      const tr = el.closest('tr[data-tipo]');
      return tr ? [tr.dataset.tipo, Number(tr.dataset.i)] : [null, -1];
    };
    cont.addEventListener('input', (e) => {
      const t = e.target;
      const hoja = act[nombre];
      if (t.classList.contains('js-comentario')) {
        hoja.comentario = t.value;
        return cambio();
      }
      const [tipo, i] = linea(t);
      if (!tipo) return;
      const l = hoja[tipo][i];
      if (t.classList.contains('js-concepto')) l.concepto = t.value;
      else if (t.classList.contains('js-cantidad')) l.cantidad = num0(t.value);
      else if (t.classList.contains('js-precio')) l.precio = num0(t.value);
      else if (t.classList.contains('js-det-concepto')) l.detalle[Number(t.closest('tr').dataset.j)].concepto = t.value;
      else if (t.classList.contains('js-det-importe')) l.detalle[Number(t.closest('tr').dataset.j)].importe = num0(t.value);
      else return;
      cambio();
    });
    cont.addEventListener('change', (e) => {
      const t = e.target;
      const [tipo, i] = linea(t);
      if (!tipo) return;
      const l = act[nombre][tipo][i];
      if (t.classList.contains('js-cuadrar')) {
        if (t.checked) { l.cuadrar = true; l.zona = true; delete l.grupo; } else delete l.cuadrar;
      } else if (t.classList.contains('js-zona')) {
        if (t.checked) l.zona = true; else delete l.zona;
      } else if (t.classList.contains('js-grupo')) {
        if (t.value) { l.grupo = t.value; delete l.cuadrar; } else delete l.grupo;
      } else return;
      renderHoja(nombre);
      cambio();
    });
    cont.addEventListener('click', (e) => {
      const b = e.target.closest('button');
      if (!b) return;
      const hoja = act[nombre];
      if (b.classList.contains('js-anadir')) {
        hoja[b.dataset.tipo].push({ concepto: '', cantidad: 1, precio: 0 });
        renderHoja(nombre);
        $$(`tr.linea[data-tipo="${b.dataset.tipo}"] .js-concepto`, cont).pop()?.focus();
        return cambio();
      }
      const [tipo, i] = linea(b);
      if (!tipo) return;
      const lista = hoja[tipo];
      const l = lista[i];
      if (b.classList.contains('js-opciones')) {
        abiertas.has(l) ? abiertas.delete(l) : abiertas.add(l);
        return renderHoja(nombre);
      }
      if (b.classList.contains('js-subir') && i > 0) [lista[i - 1], lista[i]] = [lista[i], lista[i - 1]];
      else if (b.classList.contains('js-bajar') && i < lista.length - 1) [lista[i + 1], lista[i]] = [lista[i], lista[i + 1]];
      else if (b.classList.contains('js-quitar')) {
        if ((l.concepto || l.precio) && !confirm(`¿Quitar la línea «${l.concepto || 'sin concepto'}»?`)) return;
        lista.splice(i, 1);
      } else if (b.classList.contains('js-det-anadir')) {
        l.detalle ||= [];
        if (!l.detalle.length && num0(l.precio)) {
          // Si ya tenía importe, lo conservamos como primera línea del desglose.
          l.detalle.push({ concepto: l.concepto, importe: redondear(num0(l.cantidad) * num0(l.precio)) });
        }
        l.detalle.push({ concepto: '', importe: 0 });
      } else if (b.classList.contains('js-det-quitar')) {
        l.detalle.splice(Number(b.closest('tr').dataset.j), 1);
        if (!l.detalle.length) delete l.detalle;
      } else return;
      renderHoja(nombre);
      cambio();
    });
  }

  $('#copiar-presupuesto').addEventListener('click', () => {
    const b = act.balance;
    if ((b.ingresos.length || b.gastos.length) && !confirm('El balance ya tiene líneas. ¿Añadir detrás las del presupuesto?')) return;
    const limpia = (l) => { const c = copia(l); delete c.mov; return c; };
    b.ingresos.push(...act.presupuesto.ingresos.map(limpia));
    b.gastos.push(...act.presupuesto.gastos.map(limpia));
    renderHoja('balance');
    cambio();
    aviso('Líneas copiadas. Cambia las cantidades por las reales.', 'ok');
  });

  /* =====================================================================
   *  Ingresos por centros
   * ===================================================================== */

  function renderGrupos() {
    const cont = $('#grupos');
    if (!act.centros.length) {
      cont.innerHTML = '<p class="vacio">No hay grupos. Pulsa «Añadir grupo».</p>';
      return;
    }
    const opcionesCentro = (sel) => centros.map((m) => `<option value="${m.id}" ${m.id === sel ? 'selected' : ''}>${esc(nombreMiembro(m))}</option>`).join('')
      + (sel && !centros.some((m) => m.id === sel) && MIEMBRO[sel] ? `<option value="${sel}" selected>${esc(nombreMiembro(MIEMBRO[sel]))}</option>` : '');
    cont.innerHTML = act.centros.map((g, gi) => {
      const [cant, imp] = grupoTotales(g);
      const usado = ['presupuesto', 'balance'].some((h) => act[h].ingresos.some((l) => l.grupo === g.id));
      return `
      <div class="grupo-centros" data-g="${gi}">
        <div class="grupo-centros__cab">
          <div class="campo"><label>Grupo</label><input type="text" class="js-g-nombre" value="${esc(g.nombre)}" placeholder="Ej. Niños" maxlength="60"></div>
          <div class="campo campo--corto"><label>Precio (€)</label><input type="text" inputmode="decimal" class="js-g-precio num" value="${esc(numTexto(g.precio))}"></div>
          <div class="fila-botones">
            <button type="button" class="boton boton--fantasma boton--pequeno js-g-todos">Añadir todos los centros</button>
            ${usado ? '<span class="etiqueta">Usado en el balance</span>' : '<button type="button" class="boton boton--secundario boton--pequeno js-g-usar">Usar en el balance</button>'}
            <button type="button" class="boton boton--peligro-fantasma boton--pequeno js-g-borrar">Borrar grupo</button>
          </div>
        </div>
        <table class="tabla tabla-lineas">
          <thead><tr><th>Centro</th><th class="num">Cantidad</th><th class="num">Total</th><th class="col-acciones"></th></tr></thead>
          <tbody>
            ${(g.filas || []).map((f, fi) => `
              <tr data-f="${fi}">
                <td>${f.miembro || !('nombre' in f)
                  ? `<select class="js-f-miembro"><option value="">Elige centro…</option>${opcionesCentro(f.miembro)}<option value="__otro">Otro (escribir)…</option></select>`
                  : `<input type="text" class="js-f-nombre" value="${esc(f.nombre)}" placeholder="Nombre" maxlength="80">`}</td>
                <td class="num"><input type="text" inputmode="decimal" class="js-f-cantidad num" value="${esc(numTexto(f.cantidad))}"></td>
                <td class="num nowrap js-f-total">${euros(num0(f.cantidad) * (f.precio ?? num0(g.precio)))}</td>
                <td class="col-acciones"><button type="button" class="icono icono--peligro js-f-quitar" aria-label="Quitar">✕</button></td>
              </tr>`).join('')}
          </tbody>
          <tfoot><tr><td>Total</td><td class="num js-g-cant">${numero(cant)}</td><td class="num nowrap js-g-total">${euros(imp)}</td><td></td></tr></tfoot>
        </table>
        <button type="button" class="boton boton--fantasma boton--pequeno js-f-anadir">＋ Añadir fila</button>
      </div>`;
    }).join('');
  }

  function recalcularGrupos() {
    $$('#grupos .grupo-centros').forEach((div) => {
      const g = act.centros[Number(div.dataset.g)];
      const [cant, imp] = grupoTotales(g);
      $('.js-g-cant', div).textContent = numero(cant);
      $('.js-g-total', div).textContent = euros(imp);
      $$('tr[data-f]', div).forEach((tr) => {
        const f = g.filas[Number(tr.dataset.f)];
        $('.js-f-total', tr).textContent = euros(num0(f.cantidad) * (f.precio ?? num0(g.precio)));
      });
    });
  }

  $('#nuevo-grupo').addEventListener('click', () => {
    act.centros.push({
      id: nuevoId('g', act.centros),
      nombre: act.centros.length ? '' : 'Participantes',
      precio: 0,
      filas: centros.map((m) => ({ miembro: m.id, cantidad: 0 })),
    });
    renderGrupos();
    cambio();
  });

  const contGrupos = $('#grupos');
  const grupoDe = (el) => act.centros[Number(el.closest('[data-g]').dataset.g)];
  contGrupos.addEventListener('input', (e) => {
    const t = e.target;
    const g = grupoDe(t);
    const f = t.closest('tr[data-f]') ? g.filas[Number(t.closest('tr[data-f]').dataset.f)] : null;
    if (t.classList.contains('js-g-nombre')) g.nombre = t.value;
    else if (t.classList.contains('js-g-precio')) g.precio = num0(t.value);
    else if (t.classList.contains('js-f-cantidad')) f.cantidad = num0(t.value);
    else if (t.classList.contains('js-f-nombre')) f.nombre = t.value;
    else return;
    recalcularGrupos();
    cambio();
  });
  contGrupos.addEventListener('change', (e) => {
    const t = e.target;
    if (!t.classList.contains('js-f-miembro')) return;
    const f = grupoDe(t).filas[Number(t.closest('tr[data-f]').dataset.f)];
    if (t.value === '__otro') {
      f.miembro = null;
      f.nombre = '';
      renderGrupos();
    } else {
      f.miembro = t.value || null;
      delete f.nombre;
    }
    cambio();
  });
  contGrupos.addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    const g = grupoDe(b);
    if (b.classList.contains('js-f-anadir')) g.filas.push({ miembro: null, cantidad: 0 });
    else if (b.classList.contains('js-f-quitar')) g.filas.splice(Number(b.closest('tr').dataset.f), 1);
    else if (b.classList.contains('js-g-todos')) {
      for (const m of centros) if (!g.filas.some((f) => f.miembro === m.id)) g.filas.push({ miembro: m.id, cantidad: 0 });
    } else if (b.classList.contains('js-g-usar')) {
      act.balance.ingresos.push({ concepto: `CUOTA ${(g.nombre || 'participantes').toUpperCase()}`, cantidad: 1, precio: 0, grupo: g.id });
      renderHoja('balance');
      aviso('Añadida una línea de ingresos al balance.', 'ok');
    } else if (b.classList.contains('js-g-borrar')) {
      if (!confirm(`¿Borrar el grupo «${g.nombre || 'sin nombre'}»?`)) return;
      act.centros = act.centros.filter((x) => x !== g);
      for (const h of ['presupuesto', 'balance']) {
        for (const l of act[h].ingresos) {
          if (l.grupo === g.id) {
            // La línea conserva los últimos valores que tenía.
            const [cant, imp] = grupoTotales(g);
            l.cantidad = cant;
            l.precio = cant ? redondear(imp / cant) : 0;
            delete l.grupo;
          }
        }
      }
      renderHoja('presupuesto');
      renderHoja('balance');
    } else return;
    renderGrupos();
    cambio();
  });

  /* =====================================================================
   *  Facturas e informe económico
   * ===================================================================== */

  const inf = act.informe;

  function renderInforme() {
    const c = calcInforme();
    const optsCat = (sel) => Object.entries(categorias).map(([k, v]) => `<option value="${k}" ${k === sel ? 'selected' : ''}>${esc(v.replace(/ \(.*$/, ''))}</option>`).join('');
    $('#informe').innerHTML = `
      <label class="check-inline check-inline--grande"><input type="checkbox" id="inf-incluir" ${inf.incluir !== false ? 'checked' : ''}>
        Incluir esta actividad en el informe económico del curso</label>

      <div class="informe-resumen">
        <div>
          <h3>Ingresos</h3>
          <div class="campo">
            <label for="inf-ingresos">Ingresos por actividades</label>
            <input type="text" inputmode="decimal" id="inf-ingresos" class="num" value="${inf.ingresos_actividad === null || inf.ingresos_actividad === undefined ? '' : esc(numTexto(inf.ingresos_actividad))}" placeholder="Automático: ${euros(c.ingAuto)}">
            <small class="gris">Vacío = ingresos del balance que no son de la Zona.</small>
          </div>
          <div class="campo">
            <label for="inf-part">Participación / cuota económica zona</label>
            <input type="text" inputmode="decimal" id="inf-part" class="num" value="${inf.participacion === null || inf.participacion === undefined ? '' : esc(numTexto(inf.participacion))}" placeholder="Automático: ${euros(c.partAuto)}">
            <small class="gris">Vacío = lo que falta para cubrir los gastos.</small>
          </div>
          <p class="informe-total">Total ingresos <strong class="js-inf-ti">${euros(c.ti)}</strong></p>
        </div>
        <div>
          <h3>Gastos <small class="gris">(según las facturas)</small></h3>
          <table class="tabla tabla-compacta">
            <tbody>
              ${Object.entries(categorias).map(([k, v]) => `<tr><td>${esc(v)}</td><td class="num js-cat-${k}">${euros(c.cats[k])}</td></tr>`).join('')}
            </tbody>
            <tfoot><tr><td>Total gastos</td><td class="num js-inf-tg">${euros(c.tg)}</td></tr></tfoot>
          </table>
        </div>
      </div>

      <div class="panel__cabecera">
        <h3>Control de facturas <span class="contador">${inf.facturas.length}</span></h3>
        <div class="fila-botones">
          <button type="button" class="boton boton--fantasma boton--pequeno" id="inf-importar">Traer gastos de Movimientos</button>
          <button type="button" class="boton boton--secundario boton--pequeno" id="inf-anadir">＋ Añadir factura</button>
        </div>
      </div>
      <div class="tabla-scroll">
        <table class="tabla tabla-lineas tabla-facturas">
          <thead><tr><th>Nº</th><th>Nº factura</th><th>Fecha</th><th>CIF</th><th>Razón social</th><th>Concepto</th><th>Tipo de gasto</th><th class="num">Cantidad</th><th class="col-acciones"></th></tr></thead>
          <tbody>
            ${inf.facturas.map((f, i) => `
              <tr data-i="${i}">
                <td class="num">${i + 1}</td>
                <td><input type="text" class="js-fa" data-k="numero" value="${esc(f.numero)}" maxlength="40"></td>
                <td><input type="date" class="js-fa" data-k="fecha" value="${esc(f.fecha)}"></td>
                <td><input type="text" class="js-fa" data-k="cif" value="${esc(f.cif)}" maxlength="20"></td>
                <td><input type="text" class="js-fa" data-k="razon" value="${esc(f.razon)}" maxlength="120"></td>
                <td><input type="text" class="js-fa" data-k="concepto" value="${esc(f.concepto)}" maxlength="120"></td>
                <td><select class="js-fa" data-k="categoria">${optsCat(f.categoria)}</select></td>
                <td class="num"><input type="text" inputmode="decimal" class="js-fa num" data-k="importe" value="${esc(numTexto(f.importe))}"></td>
                <td class="col-acciones">
                  <button type="button" class="icono js-fa-subir" aria-label="Subir" ${i === 0 ? 'disabled' : ''}>↑</button>
                  <button type="button" class="icono icono--peligro js-fa-quitar" aria-label="Quitar">✕</button>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>
      ${inf.facturas.length ? '' : '<p class="vacio">Sin facturas. Añádelas a mano o tráelas de los gastos vinculados en Movimientos.</p>'}`;
  }

  function recalcularInforme() {
    const c = calcInforme();
    if (!$('#inf-ingresos')) return;
    $('#inf-ingresos').placeholder = `Automático: ${euros(c.ingAuto)}`;
    $('#inf-part').placeholder = `Automático: ${euros(c.partAuto)}`;
    $('.js-inf-ti').textContent = euros(c.ti);
    $('.js-inf-tg').textContent = euros(c.tg);
    for (const k of Object.keys(categorias)) $(`.js-cat-${k}`).textContent = euros(c.cats[k]);
  }

  const contInf = $('#informe');
  contInf.addEventListener('input', (e) => {
    const t = e.target;
    if (t.id === 'inf-ingresos' || t.id === 'inf-part') {
      const v = t.value.trim() === '' ? null : leerNumero(t.value);
      if (v !== null && isNaN(v)) return;
      inf[t.id === 'inf-ingresos' ? 'ingresos_actividad' : 'participacion'] = v;
    } else if (t.classList.contains('js-fa')) {
      const f = inf.facturas[Number(t.closest('tr').dataset.i)];
      f[t.dataset.k] = t.dataset.k === 'importe' ? num0(t.value) : t.value;
    } else return;
    cambio();
  });
  contInf.addEventListener('change', (e) => {
    if (e.target.id === 'inf-incluir') {
      inf.incluir = e.target.checked;
      cambio();
    } else if (e.target.tagName === 'SELECT') {
      e.target.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
  contInf.addEventListener('click', async (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.id === 'inf-anadir') {
      inf.facturas.push({ id: nuevoId('f', inf.facturas), numero: '', fecha: act.fecha, cif: '', razon: '', concepto: '', importe: 0, categoria: 'materiales', mov: null });
    } else if (b.id === 'inf-importar') {
      const movs = await cargarMovimientos();
      const nuevos = movs.filter((m) => m.tipo === 'gasto' && !inf.facturas.some((f) => f.mov === m.id));
      if (!nuevos.length) return aviso('No hay gastos vinculados nuevos que traer.', 'info');
      for (const m of nuevos) {
        inf.facturas.push({
          id: nuevoId('f', inf.facturas), numero: m.factura || '', fecha: m.fecha, cif: m.cif || '',
          razon: m.proveedor || '', concepto: m.concepto, importe: m.importe, categoria: adivinaCategoria(m), mov: m.id,
        });
      }
      aviso(`${nuevos.length} gasto(s) añadidos. Revisa el tipo de gasto de cada uno.`, 'ok');
    } else {
      const tr = b.closest('tr[data-i]');
      if (!tr) return;
      const i = Number(tr.dataset.i);
      if (b.classList.contains('js-fa-quitar')) {
        if (!confirm('¿Quitar esta factura?')) return;
        inf.facturas.splice(i, 1);
      } else if (b.classList.contains('js-fa-subir') && i > 0) {
        [inf.facturas[i - 1], inf.facturas[i]] = [inf.facturas[i], inf.facturas[i - 1]];
      } else return;
    }
    renderInforme();
    cambio();
  });

  /** Tipo de gasto del informe según la partida y el concepto (luego se puede cambiar). */
  function adivinaCategoria(m) {
    const t = normaliza(`${m.concepto} ${m.proveedor}`);
    if (m.partida === 'G3.1' || /autob|transporte|bus |gasolina|desplaz/.test(t)) return 'desplazamientos';
    if (m.partida === 'G9.1' || /albergue|alquiler|instalac/.test(t)) return 'alquiler';
    if (m.partida === 'G4.1' || /comida|intendencia|mercadona|consum|panader|pan |bebida|desayuno|almuerzo|cena|aperitivo|paella|carne|fruta|valencianas|dulce/.test(t)) return 'manutencion';
    if (/megafon|seguro/.test(t)) return 'otros';
    return 'materiales';
  }

  /* =====================================================================
   *  Movimientos vinculados
   * ===================================================================== */

  let movimientos = null;
  let PARTIDAS = {};
  async function cargarMovimientos() {
    const r = await api('actividad', null, { endpoint: 'api-tesoreria.php', query: { id: act.id } });
    movimientos = r.movimientos.sort((a, b) => a.fecha.localeCompare(b.fecha));
    PARTIDAS = {};
    for (const grupos of Object.values(r.partidas)) {
      for (const [, [, ps]] of Object.entries(grupos)) Object.assign(PARTIDAS, ps);
    }
    return movimientos;
  }

  async function renderMovimientos() {
    const cont = $('#movimientos');
    try {
      await cargarMovimientos();
    } catch (err) {
      cont.innerHTML = `<p class="aviso-peligro">${esc(err.message)}</p>`;
      return;
    }
    if (!movimientos.length) {
      cont.innerHTML = '<p class="vacio">Ningún movimiento está vinculado a esta actividad.</p>';
      return;
    }
    const enBalance = (m) => ['ingresos', 'gastos'].some((t) => act.balance[t].some((l) => l.mov === m.id));
    const ing = movimientos.filter((m) => m.tipo === 'ingreso').reduce((s, m) => s + m.importe, 0);
    const gas = movimientos.filter((m) => m.tipo === 'gasto').reduce((s, m) => s + m.importe, 0);
    const pendientes = movimientos.filter((m) => !enBalance(m)).length;
    cont.innerHTML = `
      <div class="tabla-scroll">
        <table class="tabla">
          <thead><tr><th>Fecha</th><th>Partida</th><th>Concepto</th><th class="num">Importe</th><th class="col-acciones"></th></tr></thead>
          <tbody>
            ${movimientos.map((m) => `
              <tr>
                <td class="nowrap">${fechaBonita(m.fecha)}</td>
                <td><span class="etiqueta etiqueta--${m.tipo}">${esc(m.partida)}</span> ${esc(PARTIDAS[m.partida] || '')}</td>
                <td>${esc(m.concepto)}${m.proveedor ? `<small class="mov-factura">${esc(m.proveedor)}</small>` : ''}</td>
                <td class="num nowrap importe importe--${m.tipo}">${m.tipo === 'gasto' ? '−' : '+'}${euros(m.importe)}</td>
                <td class="col-acciones">${enBalance(m) ? '<span class="etiqueta">En el balance</span>' : `<button type="button" class="boton boton--pequeno boton--fantasma js-al-balance" data-id="${m.id}">Al balance</button>`}</td>
              </tr>`).join('')}
          </tbody>
          <tfoot><tr><td colspan="3">Ingresos ${euros(ing)} · gastos ${euros(gas)}</td><td class="num nowrap importe ${ing - gas < 0 ? 'importe--gasto' : 'importe--ingreso'}">${euros(ing - gas)}</td><td></td></tr></tfoot>
        </table>
      </div>
      ${pendientes > 1 ? `<button type="button" class="boton boton--secundario boton--pequeno" id="todos-al-balance">Pasar los ${pendientes} pendientes al balance</button>` : ''}`;
  }

  function alBalance(m) {
    act.balance[m.tipo === 'ingreso' ? 'ingresos' : 'gastos'].push({
      concepto: m.concepto.toUpperCase(), cantidad: m.cantidad, precio: m.precio, mov: m.id,
    });
  }

  $('#movimientos').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.classList.contains('js-al-balance')) alBalance(movimientos.find((m) => m.id === b.dataset.id));
    else if (b.id === 'todos-al-balance') {
      for (const m of movimientos) {
        if (!['ingresos', 'gastos'].some((t) => act.balance[t].some((l) => l.mov === m.id))) alBalance(m);
      }
    } else return;
    renderHoja('balance');
    renderMovimientos();
    cambio();
    aviso('Añadido al balance.', 'ok');
  });

  /* =====================================================================
   *  Pestañas, acciones y arranque
   * ===================================================================== */

  function recalcular() {
    recalcularHoja('presupuesto');
    recalcularHoja('balance');
    recalcularGrupos();
    recalcularInforme();
  }

  function mostrarPestana(tab) {
    if (!$(`#tab-${tab}`)) tab = 'presupuesto';
    $$('.pestanas [data-tab]').forEach((b) => {
      b.classList.toggle('activo', b.dataset.tab === tab);
      b.setAttribute('aria-selected', b.dataset.tab === tab ? 'true' : 'false');
    });
    $$('.pestana').forEach((s) => (s.hidden = s.id !== `tab-${tab}`));
    history.replaceState(null, '', `#${tab}`);
    if (tab === 'movimientos') renderMovimientos();
    if (tab === 'informe') renderInforme();
    if (tab === 'presupuesto' || tab === 'balance') renderHoja(tab); // por si cambió algún grupo
  }
  $$('.pestanas [data-tab]').forEach((b) => b.addEventListener('click', () => mostrarPestana(b.dataset.tab)));

  // Antes de imprimir, guardamos lo pendiente.
  $$('.js-imprimir').forEach((a) => a.addEventListener('click', async (e) => {
    e.preventDefault();
    await guardado.ahora();
    location.href = a.href;
  }));

  $('#duplicar').addEventListener('click', async () => {
    if (!confirm('Se creará una copia para el año siguiente con el presupuesto de esta actividad. ¿Seguir?')) return;
    try {
      await guardado.ahora();
      const r = await api('actividad.duplicar', { id: act.id });
      location.href = `actividad.php?id=${r.actividad.id}`;
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  $('#borrar').addEventListener('click', async () => {
    if (!confirm(`¿Borrar la actividad «${act.nombre}» con su presupuesto, balance y facturas?\n\nLos movimientos de tesorería NO se borran (solo pierden el vínculo).`)) return;
    try {
      await api('actividad.delete', { id: act.id });
      location.href = `actividades.php?curso=${cursoDeFecha(act.fecha)}`;
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  prepararHoja('presupuesto');
  prepararHoja('balance');
  renderHoja('presupuesto');
  renderHoja('balance');
  renderGrupos();
  guardarCurso(cursoDeFecha(act.fecha));
  mostrarPestana(location.hash.slice(1) || (act.balance.ingresos.length || act.balance.gastos.length ? 'balance' : 'presupuesto'));
})();
