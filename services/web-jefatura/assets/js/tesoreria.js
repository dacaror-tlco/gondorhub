/* Página: Tesorería (movimientos) */
(() => {
  const ENDPOINT = 'api-tesoreria.php';
  let estado = null;
  // Periodo que se muestra: modo ('natural' | 'curso') + año de inicio.
  // anio = 0 -> el servidor elige el periodo actual.
  let modo = document.querySelector('input[name="modo"]:checked')?.value || 'natural';
  let anio = Number(new URLSearchParams(location.search).get('anio')) || 0;
  let editando = null; // id del movimiento abierto en el modal

  const $ = (s, raiz = document) => raiz.querySelector(s);
  const selAnio = $('#anio');
  const tbody = $('#tbody-movimientos');
  const tfoot = $('#tfoot-movimientos');
  const vacio = $('#movimientos-vacio');
  const buscar = $('#buscar-mov');
  const filtroTipo = $('#filtro-tipo');
  const filtroPartida = $('#filtro-partida');
  const formNuevo = $('#form-movimiento');
  const formEditar = $('#form-editar-mov');
  const modal = $('#modal-editar');

  const hoy = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };
  const tes = (action, body) => api(action, body, { endpoint: ENDPOINT, query: { anio, modo } });

  /** Recuerda el modo elegido (también lo usa la ficha de balance). */
  function guardarModo() {
    document.cookie = `tes_modo=${modo}; path=/; max-age=31536000; samesite=lax`;
  }

  /* ---------- Partidas ---------- */

  /** Mapa código -> { tipo, grupo, nombreGrupo, nombre } */
  function indicePartidas() {
    const idx = {};
    for (const [tipo, grupos] of Object.entries(estado.partidas)) {
      for (const [g, [nombreGrupo, partidas]] of Object.entries(grupos)) {
        for (const [cod, nombre] of Object.entries(partidas)) {
          idx[cod] = { tipo, grupo: g, nombreGrupo, nombre };
        }
      }
    }
    return idx;
  }
  let PARTIDA = {};

  /** Rellena un <select> con las partidas de un tipo, agrupadas. */
  function opcionesPartidas(select, tipo, valor = '') {
    select.innerHTML = '';
    if (!tipo) {
      select.innerHTML = '<option value="">Elige primero ingreso o gasto…</option>';
      select.disabled = true;
      return;
    }
    select.disabled = false;
    select.appendChild(new Option('Elige una partida…', ''));
    for (const [g, [nombreGrupo, partidas]] of Object.entries(estado.partidas[tipo])) {
      const og = document.createElement('optgroup');
      og.label = `${g} · ${nombreGrupo}`;
      for (const [cod, nombre] of Object.entries(partidas)) {
        og.appendChild(new Option(`${cod} ${nombre}`, cod));
      }
      select.appendChild(og);
    }
    select.value = valor;
  }

  /* ---------- Formularios (nuevo y editar comparten lógica) ---------- */

  function tipoDe(form) {
    const r = form.querySelector('input[name="tipo"]:checked');
    return r ? r.value : '';
  }

  function actualizarTotal(form) {
    const c = leerNumero(form.cantidad.value);
    const p = leerNumero(form.precio.value);
    const out = $('.js-total', form);
    const ok = !isNaN(c) && !isNaN(p);
    out.textContent = ok ? euros(Math.round(c * p * 100) / 100) : '—';
    out.classList.toggle('negativo', tipoDe(form) === 'gasto');
    out.classList.toggle('positivo', tipoDe(form) === 'ingreso');
  }

  function prepararForm(form) {
    form.querySelectorAll('input[name="tipo"]').forEach((r) =>
      r.addEventListener('change', () => {
        opcionesPartidas(form.partida, tipoDe(form));
        form.partida.focus();
        actualizarTotal(form);
      })
    );
    form.cantidad.addEventListener('input', () => actualizarTotal(form));
    form.precio.addEventListener('input', () => actualizarTotal(form));
  }

  function leerForm(form) {
    const cantidad = leerNumero(form.cantidad.value);
    const precio = leerNumero(form.precio.value);
    if (!tipoDe(form)) throw new Error('Indica si es un ingreso o un gasto.');
    if (!form.partida.value) throw new Error('Elige una partida.');
    if (isNaN(cantidad) || cantidad <= 0) throw new Error('La cantidad debe ser un número mayor que 0.');
    if (isNaN(precio) || precio === 0) throw new Error('Escribe el precio (por ejemplo 12,50).');
    return {
      tipo: tipoDe(form),
      partida: form.partida.value,
      concepto: form.concepto.value.trim(),
      cantidad,
      precio,
      fecha: form.fecha.value,
    };
  }

  function limpiarFormNuevo() {
    const tipo = tipoDe(formNuevo);
    const fecha = formNuevo.fecha.value;
    formNuevo.reset();
    // Mantenemos tipo y fecha: suele apuntarse varias cosas seguidas del mismo tipo.
    if (tipo) formNuevo.querySelector(`input[name="tipo"][value="${tipo}"]`).checked = true;
    opcionesPartidas(formNuevo.partida, tipo);
    formNuevo.fecha.value = fecha || hoy();
    actualizarTotal(formNuevo);
  }

  prepararForm(formNuevo);
  prepararForm(formEditar);
  formNuevo.fecha.value = hoy();

  formNuevo.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const mov = leerForm(formNuevo);
      // El servidor devuelve el periodo al que pertenece la fecha del movimiento.
      estado = await tes('movimiento.add', mov);
      anio = estado.anio;
      aviso(`${mov.tipo === 'ingreso' ? 'Ingreso' : 'Gasto'} guardado.`, 'ok');
      limpiarFormNuevo();
      render();
      formNuevo.concepto.focus();
    } catch (err) {
      aviso(err.message, 'error');
    }
  });

  /* ---------- Editar / borrar ---------- */

  function abrirEditar(m) {
    editando = m.id;
    formEditar.reset();
    formEditar.querySelector(`input[name="tipo"][value="${m.tipo}"]`).checked = true;
    opcionesPartidas(formEditar.partida, m.tipo, m.partida);
    formEditar.concepto.value = m.concepto;
    formEditar.cantidad.value = String(m.cantidad).replace('.', ',');
    formEditar.precio.value = String(m.precio).replace('.', ',');
    formEditar.fecha.value = m.fecha;
    actualizarTotal(formEditar);
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', '');
  }

  formEditar.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const mov = leerForm(formEditar);
      estado = await tes('movimiento.update', { id: editando, ...mov, anio });
      modal.close();
      aviso('Movimiento actualizado.', 'ok');
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  });
  $('.js-cancelar', formEditar).addEventListener('click', () => modal.close());
  $('.modal__cerrar', modal).addEventListener('click', () => modal.close());

  async function borrar(m) {
    if (!confirm(`¿Borrar el movimiento «${m.concepto}» (${euros(m.importe)})?`)) return;
    try {
      estado = await tes('movimiento.delete', { id: m.id, anio });
      aviso('Movimiento borrado.', 'ok');
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  }

  /* ---------- Listado ---------- */

  function filtrados() {
    const q = normaliza(buscar.value);
    return estado.movimientos
      .filter((m) => !filtroTipo.value || m.tipo === filtroTipo.value)
      .filter((m) => !filtroPartida.value || m.partida === filtroPartida.value)
      .filter((m) => !q || normaliza(m.concepto).includes(q) || normaliza(PARTIDA[m.partida]?.nombre).includes(q))
      .sort((a, b) => b.fecha.localeCompare(a.fecha) || b.id.localeCompare(a.id));
  }

  function numero(n) {
    return Number(n).toLocaleString('es-ES', { maximumFractionDigits: 3 });
  }

  function render() {
    PARTIDA = indicePartidas();

    // Selector de periodo
    selAnio.innerHTML = '';
    for (const a of estado.anios) selAnio.appendChild(new Option(a.etiqueta, a.valor));
    selAnio.value = estado.anio;
    $('#titulo-anio').textContent = estado.balance.etiqueta;
    history.replaceState(null, '', `?modo=${estado.modo}&anio=${estado.anio}`);

    // Tarjetas
    const b = estado.balance;
    $('#t-ingresos').textContent = euros(b.ingreso.total);
    $('#t-gastos').textContent = euros(b.gasto.total);
    const tr = $('#t-resultado');
    tr.textContent = euros(b.resultado);
    tr.classList.toggle('negativo', b.resultado < 0);
    tr.classList.toggle('positivo', b.resultado >= 0);
    $('#t-movimientos').textContent = b.movimientos;
    $('#cont-movimientos').textContent = estado.movimientos.length;

    // Filtro de partidas (solo las que se usan este año)
    const usadas = [...new Set(estado.movimientos.map((m) => m.partida))].sort((x, y) =>
      x.localeCompare(y, 'es', { numeric: true })
    );
    const previo = filtroPartida.value;
    filtroPartida.innerHTML = '<option value="">Todas las partidas</option>';
    for (const cod of usadas) filtroPartida.appendChild(new Option(`${cod} ${PARTIDA[cod]?.nombre || ''}`, cod));
    filtroPartida.value = usadas.includes(previo) ? previo : '';

    // Tabla
    const lista = filtrados();
    tbody.innerHTML = '';
    vacio.hidden = lista.length !== 0;
    let ing = 0, gas = 0;
    for (const m of lista) {
      if (m.tipo === 'ingreso') ing += m.importe; else gas += m.importe;
      const p = PARTIDA[m.partida] || { nombre: '¿?' };
      const fila = document.createElement('tr');
      fila.innerHTML = `
        <td class="nowrap">${fechaBonita(m.fecha)}</td>
        <td><span class="etiqueta etiqueta--${m.tipo}">${esc(m.partida)}</span> ${esc(p.nombre)}</td>
        <td>${esc(m.concepto)}</td>
        <td class="num">${numero(m.cantidad)}</td>
        <td class="num nowrap">${euros(m.precio)}</td>
        <td class="num nowrap importe importe--${m.tipo}">${m.tipo === 'gasto' ? '−' : '+'}${euros(m.importe)}</td>
        <td class="col-acciones">
          <button class="boton boton--pequeno boton--fantasma js-editar">Editar</button>
          <button class="boton boton--pequeno boton--peligro-fantasma js-borrar">Borrar</button>
        </td>`;
      $('.js-editar', fila).addEventListener('click', () => abrirEditar(m));
      $('.js-borrar', fila).addEventListener('click', () => borrar(m));
      tbody.appendChild(fila);
    }
    const saldo = Math.round((ing - gas) * 100) / 100;
    tfoot.innerHTML = lista.length ? `
      <tr>
        <td colspan="5">Total de lo mostrado · ingresos ${euros(ing)} · gastos ${euros(gas)}</td>
        <td class="num nowrap importe ${saldo < 0 ? 'importe--gasto' : 'importe--ingreso'}">${euros(saldo)}</td>
        <td></td>
      </tr>` : '';
  }

  /* ---------- Exportar CSV (mismas columnas que la hoja "Balance" del Excel) ---------- */

  const BOM = String.fromCharCode(0xfeff);
  $('#exportar-csv').addEventListener('click', () => {
    const cab = [estado.modo === 'curso' ? 'Curso' : 'Año', 'Fecha', 'Ingreso / Gasto', 'Partidas', 'Descripción', 'Cantidad', 'Precio', 'Importe'];
    const dec = (n) => String(n).replace('.', ',');
    const filas = filtrados().map((m) => [
      estado.modo === 'curso' ? estado.balance.etiqueta.replace('Curso ', '') : m.fecha.slice(0, 4),
      fechaBonita(m.fecha),
      m.tipo === 'ingreso' ? 'INGRESOS' : 'GASTOS',
      `${m.partida} ${PARTIDA[m.partida]?.nombre || ''}`,
      m.concepto,
      dec(m.cantidad),
      dec(m.precio),
      dec(m.importe),
    ]);
    const csv = [cab, ...filas]
      .map((f) => f.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(';'))
      .join('\r\n');
    const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `movimientos-tesoreria-${estado.balance.etiqueta.replace(/\s+/g, '-').toLowerCase()}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  });

  /* ---------- Importar copia ---------- */

  const inputImportar = $('#importar-tesoreria');
  inputImportar.addEventListener('change', async () => {
    const f = inputImportar.files[0];
    if (!f) return;
    if (!confirm('Vas a SUSTITUIR todos los movimientos de tesorería por los del fichero. ¿Continuar?')) {
      inputImportar.value = '';
      return;
    }
    try {
      const json = JSON.parse(await f.text());
      estado = await tes('import', json);
      aviso('Tesorería importada correctamente.', 'ok');
      render();
    } catch (err) {
      aviso('No se pudo importar: ' + err.message, 'error');
    } finally {
      inputImportar.value = '';
    }
  });

  /* ---------- Arranque ---------- */

  async function recargar() {
    try {
      estado = await tes('state');
      anio = estado.anio;
      render();
    } catch (err) {
      aviso(err.message, 'error');
    }
  }

  selAnio.addEventListener('change', () => {
    anio = Number(selAnio.value);
    recargar();
  });
  document.querySelectorAll('input[name="modo"]').forEach((r) =>
    r.addEventListener('change', () => {
      modo = r.value;
      anio = 0; // al cambiar de modo, vamos al periodo actual
      guardarModo();
      recargar();
    })
  );
  buscar.addEventListener('input', render);
  filtroTipo.addEventListener('change', render);
  filtroPartida.addEventListener('change', render);

  guardarModo();
  recargar();
})();
