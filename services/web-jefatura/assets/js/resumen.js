/* Página: Resumen */
(() => {
  let estado = null;
  let orden = { col: 'nombre', dir: 1 };

  const $ = (s) => document.querySelector(s);
  const tbody = $('#tbody-resumen');
  const vacio = $('#resumen-vacio');
  const buscar = $('#buscar-resumen');
  const filtroCat = $('#filtro-categoria');
  const verBajas = $('#resumen-ver-bajas');
  const modal = $('#modal-detalle');

  const CAT_TXT = { educador: 'Educador/a', colaborador: 'Colaborador/a' };

  async function cargar() {
    estado = await api('state');
    render();
  }

  function mediaGrupo(personas, cat) {
    const v = personas.filter((p) => p.categoria === cat && p.activo && p.aplicables > 0);
    if (!v.length) return null;
    return Math.round((v.reduce((s, p) => s + p.porcentaje, 0) / v.length) * 10) / 10;
  }

  function filas() {
    const q = normaliza(buscar.value);
    let lista = estado.resumen.personas.slice();
    if (filtroCat.value !== 'todos') lista = lista.filter((p) => p.categoria === filtroCat.value);
    if (!verBajas.checked) lista = lista.filter((p) => p.activo);
    if (q) lista = lista.filter((p) => normaliza(p.nombre).includes(q));

    const { col, dir } = orden;
    lista.sort((a, b) => {
      let x = a[col], y = b[col];
      if (col === 'nombre' || col === 'categoria') return String(x).localeCompare(String(y), 'es') * dir;
      return (x - y) * dir;
    });
    return lista;
  }

  function render() {
    const t = estado.resumen.totales;
    $('#t-actividades').textContent = t.actividades;
    $('#t-educadores').textContent = t.para_educadores;
    $('#t-colaboradores').textContent = t.para_colaboradores;
    const me = mediaGrupo(estado.resumen.personas, 'educador');
    const mc = mediaGrupo(estado.resumen.personas, 'colaborador');
    $('#t-media-edu').textContent = me === null ? '—' : me + '%';
    $('#t-media-col').textContent = mc === null ? '—' : mc + '%';

    const lista = filas();
    tbody.innerHTML = '';
    vacio.hidden = lista.length !== 0;

    for (const p of lista) {
      const tr = document.createElement('tr');
      if (!p.activo) tr.classList.add('fila-baja');
      tr.innerHTML = `
        <td>${esc(p.nombre)}${p.activo ? '' : ' <span class="etiqueta etiqueta--baja">baja</span>'}</td>
        <td>${CAT_TXT[p.categoria] || p.categoria}</td>
        <td class="num">${p.asistidas}</td>
        <td class="num">${p.aplicables}</td>
        <td class="num">
          <div class="barra-pct">
            <div class="barra-pct__relleno ${claseColorPct(p.porcentaje)}" style="width:${Math.min(p.porcentaje, 100)}%"></div>
            <span>${p.aplicables ? p.porcentaje.toFixed(1) + '%' : '—'}</span>
          </div>
        </td>
        <td><button class="boton boton--pequeno boton--fantasma js-detalle">Detalle</button></td>`;
      tr.querySelector('.js-detalle').addEventListener('click', () => abrirDetalle(p));
      tbody.appendChild(tr);
    }

    document.querySelectorAll('.tabla-resumen th.js-ordenar').forEach((th) => {
      th.classList.toggle('orden-asc', orden.col === th.dataset.col && orden.dir === 1);
      th.classList.toggle('orden-desc', orden.col === th.dataset.col && orden.dir === -1);
    });
  }

  function abrirDetalle(p) {
    $('#modal-titulo').textContent = p.nombre;
    $('#modal-sub').textContent =
      `${CAT_TXT[p.categoria]} · ${p.asistidas} de ${p.aplicables} actividades · ${p.aplicables ? p.porcentaje.toFixed(1) + '%' : 'sin actividades'}`;
    const mt = $('#modal-tbody');
    mt.innerHTML = '';
    const det = p.detalle.slice().sort((a, b) => (b.fecha || '').localeCompare(a.fecha || ''));
    for (const d of det) {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${esc(d.nombre)}</td>
        <td>${fechaBonita(d.fecha)}</td>
        <td class="num">${d.asistio ? '<span class="si">Sí</span>' : '<span class="no">No</span>'}</td>`;
      mt.appendChild(tr);
    }
    if (!det.length) mt.innerHTML = '<tr><td colspan="3" class="celda-vacia">Sin actividades aplicables todavía.</td></tr>';
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', '');
  }

  modal.querySelector('.modal__cerrar').addEventListener('click', () => modal.close());
  modal.addEventListener('click', (e) => {
    const r = modal.getBoundingClientRect();
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) modal.close();
  });

  document.querySelectorAll('.tabla-resumen th.js-ordenar').forEach((th) => {
    th.addEventListener('click', () => {
      const col = th.dataset.col;
      if (orden.col === col) orden.dir *= -1;
      else orden = { col, dir: col === 'nombre' || col === 'categoria' ? 1 : -1 };
      render();
    });
  });

  const BOM = String.fromCharCode(0xfeff); // para que Excel abra el CSV con acentos

  function exportarCSV() {
    const lista = filas();
    const cab = ['Nombre', 'Categoría', 'Asistidas', 'Totales', 'Porcentaje', 'Estado'];
    const filasCsv = lista.map((p) => [
      p.nombre,
      CAT_TXT[p.categoria] || p.categoria,
      p.asistidas,
      p.aplicables,
      p.aplicables ? p.porcentaje.toFixed(1).replace('.', ',') + '%' : '',
      p.activo ? 'Activo' : 'Baja',
    ]);
    const csv = [cab, ...filasCsv]
      .map((f) => f.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(';'))
      .join('\r\n');
    const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `resumen-asistencia-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  $('#exportar-csv').addEventListener('click', exportarCSV);
  buscar.addEventListener('input', render);
  filtroCat.addEventListener('change', render);
  verBajas.addEventListener('change', render);

  cargar().catch((e) => aviso(e.message, 'error'));
})();
