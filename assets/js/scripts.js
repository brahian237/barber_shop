// ============================================
// BARBER SHOP — scripts.js
// Conecta el formulario de citas con la API
// ============================================

// Ruta a la API
const API_URL = './app/citas.php';

// ── Referencias al DOM ───────────────────────
const inputFecha       = document.getElementById('cita-fecha');
const listaHoras       = document.getElementById('lista-horas');
const mensajeHora      = document.getElementById('hora-mensaje');
const inputHora        = document.getElementById('cita-hora');
const inputNombre      = document.getElementById('cita-nombre');
const inputContacto    = document.getElementById('cita-contacto');
const inputDescripcion = document.getElementById('cita-descripcion');
const btnAgendar       = document.getElementById('btn-agendar');
const estadoDiv        = document.getElementById('cita-estado');

// ── Inicialización ───────────────────────────

// Bloquear fechas pasadas: mínimo hoy
const hoy = new Date().toISOString().split('T')[0];
if (inputFecha) inputFecha.min = hoy;

// ── Evento: cambio de fecha ──────────────────
inputFecha?.addEventListener('change', () => {
  const fecha = inputFecha.value;
  inputHora.value = '';
  btnAgendar.disabled = true;
  limpiarMensaje();

  if (!fecha) return;

  consultarDisponibilidad(fecha);
});

// ── Consultar disponibilidad a la API ────────
async function consultarDisponibilidad(fecha) {
  mensajeHora.textContent = 'Cargando horarios disponibles...';
  listaHoras.innerHTML = '';

  try {
    const res  = await fetch(`${API_URL}?accion=disponibilidad&fecha=${fecha}`);
    const data = await res.json();

    if (!res.ok) {
      mensajeHora.textContent = data.error || 'Error al consultar disponibilidad.';
      return;
    }

    if (data.disponibles.length === 0) {
      mensajeHora.textContent = 'No hay horas disponibles para este día.';
      return;
    }

    mensajeHora.textContent = 'Selecciona una hora:';
    renderizarHoras(data.disponibles, data.ocupadas);

  } catch (err) {
    mensajeHora.textContent = 'No se pudo conectar con el servidor. Intenta de nuevo.';
    console.error('Error consultando disponibilidad:', err);
  }
}

// ── Renderizar botones de hora ───────────────
function renderizarHoras(disponibles, ocupadas) {
  listaHoras.innerHTML = '';

  // Horas disponibles — seleccionables
  // La API devuelve horas en 24h (ej: "09:00")
  // dataset.hora guarda 24h para enviar a la API
  // textContent muestra 12h para el usuario
  disponibles.forEach(hora24 => {
    const btn = document.createElement('button');
    btn.type         = 'button';
    btn.textContent  = convertir12h(hora24);  // muestra "09:00 AM"
    btn.dataset.hora = hora24;                // guarda "09:00" para la API
    btn.classList.add('btn-hora', 'disponible');

    btn.addEventListener('click', () => seleccionarHora(btn));
    listaHoras.appendChild(btn);
  });

  // Horas ocupadas — deshabilitadas visualmente
  ocupadas.forEach(hora24 => {
    const btn = document.createElement('button');
    btn.type        = 'button';
    btn.textContent = convertir12h(hora24);   // muestra "09:00 AM" también
    btn.disabled    = true;
    btn.classList.add('btn-hora', 'ocupada');
    listaHoras.appendChild(btn);
  });
}

// ── Seleccionar una hora ─────────────────────
function seleccionarHora(btnSeleccionado) {
  // Quitar selección anterior
  document.querySelectorAll('.btn-hora.seleccionada').forEach(btn => {
    btn.classList.remove('seleccionada');
  });

  btnSeleccionado.classList.add('seleccionada');
  inputHora.value     = btnSeleccionado.dataset.hora;  // envía en 24h a la API
  btnAgendar.disabled = false;
  limpiarMensaje();
}

// ── Evento: enviar formulario ────────────────
btnAgendar?.addEventListener('click', async () => {
  limpiarMensaje();

  const nombre      = inputNombre.value.trim();
  const contacto    = inputContacto.value.trim();
  const fecha       = inputFecha.value;
  const hora        = inputHora.value;           // siempre en 24h
  const descripcion = inputDescripcion.value.trim();

  // Validación del lado del cliente
  if (!nombre) {
    mostrarMensaje('Por favor ingresa tu nombre.', 'error');
    inputNombre.focus();
    return;
  }
  if (!contacto) {
    mostrarMensaje('Por favor ingresa tu número de contacto.', 'error');
    inputContacto.focus();
    return;
  }
  if (!fecha || !hora) {
    mostrarMensaje('Por favor selecciona una fecha y una hora.', 'error');
    return;
  }

  btnAgendar.disabled    = true;
  btnAgendar.textContent = 'Agendando...';

  try {
    const res = await fetch(API_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ nombre, contacto, fecha, hora, descripcion }),
    });

    const data = await res.json();

    if (res.ok) {
      mostrarMensaje(
        `¡Cita agendada! Te esperamos el ${formatearFecha(fecha)} a las ${convertir12h(hora)}.`,
        'exito'
      );
      limpiarFormulario();
      // Refrescar disponibilidad para que la hora quede marcada como ocupada
      consultarDisponibilidad(fecha);
    } else {
      mostrarMensaje(data.error || 'No se pudo agendar la cita.', 'error');
      btnAgendar.disabled = false;
    }

  } catch (err) {
    mostrarMensaje('Error de conexión. Intenta de nuevo.', 'error');
    btnAgendar.disabled = false;
    console.error('Error al agendar:', err);
  }

  btnAgendar.textContent = 'Agendar Cita';
});

// ── Helpers ──────────────────────────────────

function mostrarMensaje(texto, tipo) {
  estadoDiv.textContent = texto;
  estadoDiv.className   = tipo === 'exito' ? 'mensaje-exito' : 'mensaje-error';
}

function limpiarMensaje() {
  estadoDiv.textContent = '';
  estadoDiv.className   = '';
}

function limpiarFormulario() {
  inputNombre.value      = '';
  inputContacto.value    = '';
  inputDescripcion.value = '';
  inputHora.value        = '';
  btnAgendar.disabled    = true;

  document.querySelectorAll('.btn-hora.seleccionada').forEach(btn => {
    btn.classList.remove('seleccionada');
  });
}

function formatearFecha(fechaISO) {
  const [anio, mes, dia] = fechaISO.split('-');
  return `${dia}/${mes}/${anio}`;
}

// Convierte "13:30" → "01:30 PM"  |  "09:00" → "09:00 AM"
function convertir12h(hora24) {
  const [hhStr, mm] = hora24.split(':');
  let hh = parseInt(hhStr, 10);
  const periodo = hh >= 12 ? 'PM' : 'AM';
  if (hh === 0)  hh = 12;
  if (hh > 12)   hh -= 12;
  return `${String(hh).padStart(2, '0')}:${mm} ${periodo}`;
}