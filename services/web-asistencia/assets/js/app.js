/* Utilidades compartidas de la web de asistencia */

const API = 'api.php';

/** Llama al API. Para POST envía JSON + token CSRF. */
async function api(action, body) {
  const opciones = { method: body ? 'POST' : 'GET', headers: {} };
  if (body) {
    opciones.headers['Content-Type'] = 'application/json';
    opciones.headers['X-CSRF-Token'] = window.CSRF_TOKEN || '';
    opciones.body = JSON.stringify(body);
  }
  let resp;
  try {
    resp = await fetch(`${API}?action=${encodeURIComponent(action)}`, opciones);
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

/** Normaliza para búsquedas (sin acentos, minúsculas). */
function normaliza(s) {
  return String(s || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '');
}

/** Clase de color según porcentaje. */
function claseColorPct(p) {
  if (p >= 75) return 'pct--alto';
  if (p >= 50) return 'pct--medio';
  if (p >= 25) return 'pct--bajo';
  return 'pct--muy-bajo';
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

  /* --- Importar copia (solo en index) --- */
  const input = document.getElementById('importar-archivo');
  if (input) {
    input.addEventListener('change', async () => {
      const f = input.files[0];
      if (!f) return;
      if (!confirm('Vas a SUSTITUIR todos los datos actuales por los del fichero. ¿Continuar?')) {
        input.value = '';
        return;
      }
      try {
        const texto = await f.text();
        const json = JSON.parse(texto);
        await api('import', json);
        aviso('Datos importados correctamente.', 'ok');
        setTimeout(() => location.reload(), 900);
      } catch (e) {
        aviso('No se pudo importar: ' + e.message, 'error');
      } finally {
        input.value = '';
      }
    });
  }
});
