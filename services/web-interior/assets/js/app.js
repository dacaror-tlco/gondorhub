/* Utilidades compartidas de la web de tesorería de la Zona Interior */

const API = 'api.php';

/** Llama al API. Para POST envía JSON + token CSRF. `query` añade parámetros GET. */
async function api(action, body, { endpoint = API, query = {} } = {}) {
  const opciones = { method: body ? 'POST' : 'GET', headers: {} };
  if (body) {
    opciones.headers['Content-Type'] = 'application/json';
    opciones.headers['X-CSRF-Token'] = window.CSRF_TOKEN || '';
    opciones.body = JSON.stringify(body);
  }
  let resp;
  try {
    const params = new URLSearchParams({ action, ...query });
    resp = await fetch(`${endpoint}?${params}`, opciones);
  } catch (e) {
    throw new Error('No hay conexión con el servidor.');
  }
  if (resp.status === 401) {
    aviso('La sesión ha caducado. Vuelve a entrar.', 'error');
    setTimeout(() => (location.href = 'login.php'), 1200);
    throw new Error('Sesión caducada');
  }
  let datos;
  try {
    datos = await resp.json();
  } catch (e) {
    throw new Error('Respuesta no válida del servidor.');
  }
  if (!resp.ok || datos.ok === false) {
    throw new Error(datos.error || `Error ${resp.status}`);
  }
  return datos;
}

/** Mensaje flotante. tipo: 'ok' | 'error' | 'info' */
function aviso(texto, tipo = 'ok') {
  const cont = document.getElementById('avisos');
  if (!cont) return;
  const el = document.createElement('div');
  el.className = `aviso aviso--${tipo}`;
  el.textContent = texto;
  cont.appendChild(el);
  requestAnimationFrame(() => el.classList.add('visible'));
  setTimeout(() => {
    el.classList.remove('visible');
    setTimeout(() => el.remove(), 300);
  }, tipo === 'error' ? 5000 : 2800);
}

/** Escapar texto para insertar en HTML. */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

/** Formatea 'YYYY-MM-DD' -> 'DD/MM/YYYY'. */
function fechaBonita(iso) {
  if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return 'sin fecha';
  const [a, m, d] = iso.split('-');
  return `${d}/${m}/${a}`;
}

/** Fecha de hoy en YYYY-MM-DD (hora local). */
function hoy() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Curso escolar (sep–ago) de una fecha: '2025-11-21' -> '2025-2026'. */
function cursoDeFecha(iso) {
  let a = Number(iso.slice(0, 4));
  if (Number(iso.slice(5, 7)) < 9) a--;
  return `${a}-${a + 1}`;
}

/** Formatea un importe en euros al estilo español: 1.234,56 € */
const FORMATO_EUROS = new Intl.NumberFormat('es-ES', {
  style: 'currency', currency: 'EUR', minimumFractionDigits: 2, useGrouping: 'always',
});
function euros(n) {
  return FORMATO_EUROS.format(Number(n) || 0);
}

/** Número para mostrar en un campo de texto: 2.5 -> "2,5". */
function numTexto(n) {
  if (n === null || n === undefined || n === '') return '';
  return Number(n).toLocaleString('es-ES', { maximumFractionDigits: 4, useGrouping: false });
}

/** Número para mostrar en tablas: 1234.5 -> "1.234,5". */
function numero(n) {
  return Number(n || 0).toLocaleString('es-ES', { maximumFractionDigits: 3 });
}

function pct(n) {
  return `${Number(n || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
}

/** Lee un número escrito con coma o punto decimal ("12,5", "1.234,56"). NaN si no es válido. */
function leerNumero(s) {
  if (typeof s === 'number') return s;
  let t = String(s ?? '').replace(/[\s€%]/g, '');
  if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
  return t === '' || isNaN(t) ? NaN : Number(t);
}

/** Como leerNumero pero devuelve 0 si no es válido. */
function num0(s) {
  const n = leerNumero(s);
  return isNaN(n) ? 0 : n;
}

const redondear = (n) => Math.round((Number(n) || 0) * 100) / 100;

/** Normaliza para búsquedas (sin acentos, minúsculas). */
function normaliza(s) {
  return String(s || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '');
}

/** Los mapas vacíos pueden llegar como [] desde PHP: los convertimos en {}. */
function mapa(x) {
  return x && typeof x === 'object' && !Array.isArray(x) ? x : {};
}

/** Copia profunda sencilla (datos JSON). */
const copia = (x) => JSON.parse(JSON.stringify(x));

/** Recuerda el curso elegido (lo usan también las páginas de impresión). */
function guardarCurso(curso) {
  document.cookie = `zona_curso=${curso}; path=/; max-age=31536000; samesite=lax`;
}

/** Rellena un <select> de cursos. */
function opcionesCursos(select, cursos, valor) {
  select.innerHTML = '';
  for (const c of cursos) select.appendChild(new Option(`Curso ${c.replace('-', '/')}`, c));
  select.value = valor;
}

/** "Axenia - 603D" */
function nombreMiembro(m) {
  if (!m) return '¿?';
  return m.numero ? `${m.nombre} - ${m.numero}` : m.nombre;
}

/**
 * Guardado automático con aviso de estado: llama a `guardar()` un rato después
 * del último cambio. `el` muestra «Sin guardar / Guardando… / Guardado».
 */
function autoguardado(guardar, el, espera = 900) {
  let t = null;
  let pendiente = false;
  let enCurso = Promise.resolve();
  const pinta = (txt, clase = '') => {
    if (!el) return;
    el.textContent = txt;
    el.className = `js-estado-guardado ${clase}`;
  };
  const ejecutar = () => {
    t = null;
    enCurso = enCurso.then(async () => {
      pinta('Guardando…');
      try {
        await guardar();
        pendiente = false;
        pinta('Guardado ✓');
      } catch (e) {
        pinta('No se ha guardado', 'sin-guardar');
        aviso(e.message, 'error');
      }
    });
    return enCurso;
  };
  window.addEventListener('beforeunload', (e) => {
    if (pendiente) e.preventDefault();
  });
  return {
    cambio() {
      pendiente = true;
      pinta('Sin guardar', 'sin-guardar');
      clearTimeout(t);
      t = setTimeout(ejecutar, espera);
    },
    /** Guarda ya (p. ej. antes de ir a imprimir). */
    ahora() {
      if (!t) return enCurso;
      clearTimeout(t);
      return ejecutar();
    },
  };
}

/** Abre un <dialog> (con respaldo para navegadores antiguos). */
function abrirModal(modal) {
  if (typeof modal.showModal === 'function') modal.showModal();
  else modal.setAttribute('open', '');
}

/* --- Menú responsive --- */
document.addEventListener('DOMContentLoaded', () => {
  const boton = document.querySelector('.menu-boton');
  const menu = document.getElementById('menu-principal');
  if (boton && menu) {
    boton.addEventListener('click', () => {
      const abierto = menu.classList.toggle('abierto');
      boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
  }
  document.querySelectorAll('dialog .modal__cerrar, dialog .js-cancelar').forEach((b) =>
    b.addEventListener('click', () => b.closest('dialog').close())
  );
});
