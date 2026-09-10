'use strict';

(function () {
  var $ = function (id) { return document.getElementById(id); };

  var TOKEN = '';
  try { TOKEN = localStorage.getItem('pn_admin_token') || ''; } catch (e) { TOKEN = ''; }

  var PRECIO = 8;
  var DATOS = [];
  var editandoId = null;
  var pausarAuto = false;

  function headers() {
    var h = { 'Content-Type': 'application/json' };
    if (TOKEN) h['X-Admin-Token'] = TOKEN;
    return h;
  }

  function eur(n) {
    return new Intl.NumberFormat('es-ES').format(n) + ' €';
  }

  function fecha(iso) {
    try {
      return new Date(iso).toLocaleString('es-ES', {
        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'
      });
    } catch (e) { return iso; }
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function mensaje(texto, tipo) {
    var m = $('msg');
    m.textContent = texto;
    m.className = 'msg ' + (tipo === 'err' ? 'msg--err' : 'msg--ok');
    m.hidden = false;
    clearTimeout(mensaje._t);
    mensaje._t = setTimeout(function () { m.hidden = true; }, 4000);
  }

  // ---------------------------------------------------------------- carga ----
  function cargar() {
    return fetch('/api/admin/resumen', { headers: headers() })
      .then(function (r) {
        if (r.status === 401) { pedirToken(); throw new Error('401'); }
        return r.json();
      })
      .then(function (data) {
        $('gate').hidden = true;
        $('app').hidden = false;
        PRECIO = data.config.precioTicket;
        DATOS = data.reservas;
        pintarStats(data.resumen);
        pintarTabla();
      })
      .catch(function (e) {
        if (e.message !== '401') mensaje('No se pudo cargar. ' + e.message, 'err');
      });
  }

  function pintarStats(r) {
    var cards = [
      { n: r.numReservas, l: 'Reservas', c: '' },
      { n: r.tickets, l: 'Tickets', c: 'stat--amarillo' },
      { n: eur(r.recaudacionPrevista), l: 'Recaudación prevista', c: 'stat--verde' },
      { n: eur(r.cobrado), l: 'Cobrado', c: 'stat--verde' },
      { n: eur(r.pendienteCobro), l: 'Pendiente de cobro', c: 'stat--rojo' },
      { n: r.entregados + ' / ' + r.numReservas, l: 'Reservas entregadas', c: 'stat--naranja' }
    ];
    $('stats').innerHTML = cards.map(function (x) {
      return '<div class="stat ' + x.c + '">' +
        '<div class="stat__num">' + esc(x.n) + '</div>' +
        '<div class="stat__lbl">' + esc(x.l) + '</div></div>';
    }).join('');
  }

  function filtradas() {
    var q = $('buscar').value.trim().toLowerCase();
    var f = $('filtro').value;
    return DATOS.filter(function (r) {
      if (q) {
        var hay = (r.nombre + ' ' + r.codigo + ' ' + (r.telefono || '')).toLowerCase();
        if (hay.indexOf(q) === -1) return false;
      }
      if (f === 'sinPagar') return !r.pagado;
      if (f === 'sinEntregar') return !r.entregado;
      if (f === 'pagadas') return r.pagado;
      if (f === 'entregadas') return r.entregado;
      if (f === 'web') return r.origen === 'web';
      if (f === 'manual') return r.origen === 'manual';
      return true;
    });
  }

  function pintarTabla() {
    var lista = filtradas();
    var tb = $('tbody');
    $('vacio').hidden = lista.length > 0;
    tb.innerHTML = lista.map(function (r) {
      return '<tr data-id="' + r.id + '">' +
        '<td class="code">' + esc(r.codigo) + '</td>' +
        '<td>' + esc(r.nombre) + (r.notas ? ' <small style="color:#6B5942">· ' + esc(r.notas) + '</small>' : '') + '</td>' +
        '<td>' + r.cantidad + '</td>' +
        '<td>' + eur(r.cantidad * PRECIO) + '</td>' +
        '<td>' + esc(r.telefono || '—') + '</td>' +
        '<td class="chkcell"><input type="checkbox" class="chk" data-campo="pagado" ' + (r.pagado ? 'checked' : '') + '></td>' +
        '<td class="chkcell"><input type="checkbox" class="chk" data-campo="entregado" ' + (r.entregado ? 'checked' : '') + '></td>' +
        '<td><span class="pill pill--' + r.origen + '">' + (r.origen === 'web' ? 'web' : 'a mano') + '</span></td>' +
        '<td>' + esc(fecha(r.creadoEn)) + '</td>' +
        '<td class="acciones">' +
          '<button class="icon-btn" data-accion="editar">Editar</button>' +
          '<button class="icon-btn danger" data-accion="borrar">Borrar</button>' +
        '</td>' +
      '</tr>';
    }).join('');
  }

  // -------------------------------------------------------------- acciones ---
  function patch(id, cambios) {
    return fetch('/api/admin/reservas/' + id, {
      method: 'PATCH',
      headers: headers(),
      body: JSON.stringify(cambios)
    }).then(function (r) {
      return r.json().then(function (d) {
        if (!r.ok || !d.ok) throw new Error(d.error || 'Error al guardar');
        return d.reserva;
      });
    });
  }

  $('tbody').addEventListener('change', function (ev) {
    var chk = ev.target.closest('.chk');
    if (!chk) return;
    var tr = chk.closest('tr');
    var id = tr.getAttribute('data-id');
    var campo = chk.getAttribute('data-campo');
    var cambios = {};
    cambios[campo] = chk.checked;
    chk.disabled = true;
    patch(id, cambios)
      .then(function () { return cargar(); })
      .then(function () { mensaje('Guardado.', 'ok'); })
      .catch(function (e) { mensaje(e.message, 'err'); chk.checked = !chk.checked; })
      .finally(function () { chk.disabled = false; });
  });

  $('tbody').addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-accion]');
    if (!btn) return;
    var tr = btn.closest('tr');
    var id = tr.getAttribute('data-id');
    var reserva = DATOS.filter(function (r) { return r.id === id; })[0];
    if (!reserva) return;

    if (btn.getAttribute('data-accion') === 'borrar') {
      if (!confirm('¿Borrar la reserva de "' + reserva.nombre + '" (' + reserva.codigo + ')?')) return;
      fetch('/api/admin/reservas/' + id, { method: 'DELETE', headers: headers() })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) throw new Error(d.error || 'Error al borrar');
          return cargar();
        })
        .then(function () { mensaje('Reserva borrada.', 'ok'); })
        .catch(function (e) { mensaje(e.message, 'err'); });
      return;
    }

    // editar
    abrirModal(reserva);
  });

  // --------------------------------------------------------------- modal ----
  var modal = $('modal');

  function abrirModal(reserva) {
    editandoId = reserva ? reserva.id : null;
    $('modalTitulo').textContent = reserva ? 'Editar reserva' : 'Añadir reserva';
    $('mNombre').value = reserva ? reserva.nombre : '';
    $('mCantidad').value = reserva ? reserva.cantidad : 1;
    $('mTelefono').value = reserva ? (reserva.telefono || '') : '';
    $('mNotas').value = reserva ? (reserva.notas || '') : '';
    $('mPagado').checked = reserva ? !!reserva.pagado : false;
    $('mEntregado').checked = reserva ? !!reserva.entregado : false;
    $('modalErr').hidden = true;
    pausarAuto = true;
    modal.showModal();
  }

  function cerrarModal() {
    pausarAuto = false;
    if (modal.open) modal.close();
  }

  $('btnNueva').addEventListener('click', function () { abrirModal(null); });
  $('modalCancelar').addEventListener('click', cerrarModal);
  modal.addEventListener('cancel', function () { pausarAuto = false; });

  $('modalForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var cuerpo = {
      nombre: $('mNombre').value.trim(),
      cantidad: parseInt($('mCantidad').value, 10),
      telefono: $('mTelefono').value.trim(),
      notas: $('mNotas').value.trim(),
      pagado: $('mPagado').checked,
      entregado: $('mEntregado').checked
    };
    if (cuerpo.nombre.length < 2) { verModalErr('Escribe un nombre.'); return; }
    if (!(cuerpo.cantidad >= 1)) { verModalErr('Cantidad no válida.'); return; }

    var req = editandoId
      ? fetch('/api/admin/reservas/' + editandoId, { method: 'PATCH', headers: headers(), body: JSON.stringify(cuerpo) })
      : fetch('/api/admin/reservas', { method: 'POST', headers: headers(), body: JSON.stringify(cuerpo) });

    req.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        if (!res.ok || !res.d.ok) throw new Error(res.d.error || 'Error al guardar');
        cerrarModal();
        return cargar();
      })
      .then(function () { mensaje('Reserva guardada.', 'ok'); })
      .catch(function (e) { verModalErr(e.message); });
  });

  function verModalErr(t) {
    var e = $('modalErr');
    e.textContent = t;
    e.hidden = false;
  }

  // ---------------------------------------------------------- token gate ----
  function pedirToken() {
    $('app').hidden = true;
    $('gate').hidden = false;
  }
  $('gateBtn').addEventListener('click', function () {
    var t = $('gateInput').value.trim();
    if (!t) return;
    try { localStorage.setItem('pn_admin_token', t); } catch (e) {}
    TOKEN = t;
    $('gateErr').hidden = true;
    cargar().catch(function () {});
  });
  $('gateInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') $('gateBtn').click();
  });

  // ------------------------------------------------------------- toolbar ----
  $('buscar').addEventListener('input', pintarTabla);
  $('filtro').addEventListener('change', pintarTabla);
  $('btnRefrescar').addEventListener('click', function () { cargar(); });
  $('btnPrint').addEventListener('click', function () { window.print(); });
  $('btnCsv').addEventListener('click', function () {
    var url = '/api/admin/export.csv';
    if (TOKEN) url += '?token=' + encodeURIComponent(TOKEN);
    window.open(url, '_blank');
  });

  $('buscar').addEventListener('focus', function () { pausarAuto = true; });
  $('buscar').addEventListener('blur', function () { pausarAuto = false; });

  // ---------------------------------------------------------- arranque ------
  cargar();
  setInterval(function () {
    if (!pausarAuto && !document.hidden) cargar();
  }, 20000);
})();
