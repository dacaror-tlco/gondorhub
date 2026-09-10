'use strict';

(function () {
  var CFG = {
    precioTicket: 8,
    maxTicketsPorReserva: 30,
    telefonoContacto: '',
    reservasAbiertas: true
  };

  var $ = function (id) { return document.getElementById(id); };

  var form = $('form');
  var inputNombre = $('nombre');
  var inputCantidad = $('cantidad');
  var inputTelefono = $('telefono');
  var totalEl = $('total');
  var submitBtn = $('submit');
  var errorEl = $('formError');
  var successEl = $('success');
  var closedEl = $('closed');

  function eur(n) {
    return new Intl.NumberFormat('es-ES').format(n) + ' €';
  }

  function telHref(t) {
    return 'tel:' + String(t || '').replace(/[^0-9+]/g, '');
  }

  function clampCantidad() {
    var v = parseInt(inputCantidad.value, 10);
    if (isNaN(v) || v < 1) v = 1;
    if (v > CFG.maxTicketsPorReserva) v = CFG.maxTicketsPorReserva;
    inputCantidad.value = v;
    return v;
  }

  function pintarTotal() {
    var v = clampCantidad();
    totalEl.textContent = eur(v * CFG.precioTicket);
  }

  function mostrarError(msg) {
    errorEl.textContent = msg;
    errorEl.hidden = false;
  }

  function limpiarError() {
    errorEl.hidden = true;
    errorEl.textContent = '';
  }

  // ---- configuración desde el servidor ----
  fetch('/api/config')
    .then(function (r) { return r.json(); })
    .then(function (cfg) {
      CFG = Object.assign(CFG, cfg);

      setText('cfgNombre', null); // el título lo dejamos con su formato del HTML
      setText('cfgSubtitulo', cfg.subtitulo);
      setText('cfgTagline', cfg.tagline);
      setText('cfgFecha', cfg.fechaTexto);
      setText('cfgHora', cfg.hora);
      setText('cfgLugar', cfg.lugar);
      setText('cfgDireccion', cfg.direccion);
      setText('cfgPrecio', cfg.precioTicket);
      setText('ftDir', cfg.direccion);

      if (cfg.mapsUrl) {
        $('cfgMaps').href = cfg.mapsUrl;
        $('ftMaps').href = cfg.mapsUrl;
      }

      if (Array.isArray(cfg.incluye) && cfg.incluye.length) {
        var clases = { paella: 'chip--paella', bebida: 'chip--bebida', fruta: 'chip--fruta' };
        var cont = $('cfgIncluye');
        cont.innerHTML = '';
        cfg.incluye.forEach(function (item) {
          var s = document.createElement('span');
          s.className = 'chip ' + (clases[String(item).toLowerCase()] || '');
          s.textContent = item;
          cont.appendChild(s);
        });
      }

      var tel = cfg.telefonoContacto || '';
      var ftTel = $('ftTel');
      ftTel.textContent = tel;
      ftTel.href = telHref(tel);
      var okCambios = $('okCambios');
      if (okCambios) okCambios.textContent = tel ? ('¿Algún cambio en tu reserva? Llámanos al ' + tel + '.') : '';

      inputCantidad.max = CFG.maxTicketsPorReserva;

      if (cfg.reservasAbiertas === false) {
        form.hidden = true;
        closedEl.hidden = false;
        $('closedMsg').textContent = cfg.mensajeCerrado || 'La reserva por la web está cerrada.';
        var ct = $('closedTel');
        ct.textContent = tel || '—';
        ct.href = telHref(tel);
      } else if (typeof cfg.plazasDisponibles === 'number') {
        var lead = document.querySelector('.reserva__lead');
        if (cfg.plazasDisponibles <= 0) {
          form.hidden = true;
          closedEl.hidden = false;
          $('closedMsg').textContent = 'Se han agotado los tickets.';
          $('closedTel').textContent = tel || '—';
          $('closedTel').href = telHref(tel);
        } else if (cfg.plazasDisponibles <= 40 && lead) {
          lead.textContent += ' Quedan ' + cfg.plazasDisponibles + ' tickets.';
        }
      }

      pintarTotal();
    })
    .catch(function () { pintarTotal(); });

  function setText(id, value) {
    if (value == null) return;
    var el = $(id);
    if (el) el.textContent = value;
  }

  // ---- interacción ----
  document.querySelectorAll('.stepper__btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var step = parseInt(btn.getAttribute('data-step'), 10);
      inputCantidad.value = (parseInt(inputCantidad.value, 10) || 0) + step;
      pintarTotal();
    });
  });

  inputCantidad.addEventListener('input', pintarTotal);
  inputCantidad.addEventListener('blur', pintarTotal);

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    limpiarError();

    var nombre = inputNombre.value.trim();
    var cantidad = clampCantidad();

    if (nombre.length < 2) {
      mostrarError('Escribe el nombre de la reserva.');
      inputNombre.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando…';

    fetch('/api/reservas', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        nombre: nombre,
        cantidad: cantidad,
        telefono: inputTelefono.value.trim(),
        web: form.elements['web'].value
      })
    })
      .then(function (r) {
        return r.json().then(function (data) { return { ok: r.ok, data: data }; });
      })
      .then(function (res) {
        if (!res.ok || !res.data.ok) {
          mostrarError(res.data.error || 'No hemos podido guardar la reserva. Inténtalo de nuevo.');
          return;
        }
        var d = res.data;
        $('okCodigo').textContent = d.codigo;
        $('okNombre').textContent = d.nombre;
        $('okCantidad').textContent = d.cantidad;
        $('okTotal').textContent = eur(d.total) + ' (al recoger)';
        form.hidden = true;
        successEl.hidden = false;
        successEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      })
      .catch(function () {
        mostrarError('Fallo de conexión. Comprueba tu internet e inténtalo otra vez.');
      })
      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Reservar tickets';
      });
  });

  $('otra').addEventListener('click', function () {
    successEl.hidden = true;
    form.hidden = false;
    form.reset();
    inputCantidad.value = 2;
    pintarTotal();
    inputNombre.focus();
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  pintarTotal();
})();
