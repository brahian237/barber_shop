const API = '../app/citas.php';
    let notifOpen = false;

    // Toggle panel de notificaciones
    function toggleNotifPanel() {
      notifOpen = !notifOpen;
      document.getElementById('notif-panel').classList.toggle('open', notifOpen);
      document.getElementById('notif-overlay').classList.toggle('open', notifOpen);
      if (notifOpen) cargarPendientes();
    }

    // Cargar lista de pendientes
    async function cargarPendientes() {
      const body = document.getElementById('notif-panel-body');
      body.innerHTML = '<div class="notif-empty">Cargando...</div>';
      try {
        const res  = await fetch(`${API}?accion=pendientes_detalle`);
        const data = await res.json();

        if (!data.citas || data.citas.length === 0) {
          body.innerHTML = '<div class="notif-empty">Sin citas pendientes</div>';
          return;
        }

        const hoy = new Date().toISOString().split('T')[0];
        body.innerHTML = data.citas.map(c => {
          const esHoy = c.fecha_iso === hoy;
          const [hhStr, mm] = c.hora.split(':');
          let h = parseInt(hhStr), per = h >= 12 ? 'PM' : 'AM';
          if (h === 0) h = 12; else if (h > 12) h -= 12;
          const hora12 = `${String(h).padStart(2,'0')}:${mm} ${per}`;
          return `
            <div class="notif-item" onclick="window.location='admin.php?fecha=${c.fecha_iso}'">
              <div class="notif-item-hora">${hora12}</div>
              <div class="notif-item-info">
                <div class="notif-item-nombre">${c.nombre}</div>
                <div class="notif-item-fecha">
                  ${c.fecha}
                  ${esHoy ? '<span class="badge-hoy">Hoy</span>' : ''}
                </div>
              </div>
            </div>`;
        }).join('');

      } catch(e) {
        body.innerHTML = '<div class="notif-empty">Error al cargar</div>';
      }
    }

    // Actualizar badge pendientes
    async function actualizarBadge() {
      try {
        const res  = await fetch(`${API}?accion=pendientes`);
        const data = await res.json();
        const badge = document.getElementById('notif-badge');
        const btn   = document.getElementById('notif-btn');

        if (data.total > 0) {
          badge.textContent = data.total > 99 ? '99+' : data.total;
          badge.style.display = 'flex';
          btn.classList.add('has-pending');

          if (Notification.permission === 'granted') {
            const anterior = parseInt(localStorage.getItem('bs_pendientes') || '0');
            if (data.total > anterior) {
              new Notification('Barber Shop — Nueva cita', {
                body: `Tienes ${data.total} cita${data.total > 1 ? 's' : ''} pendiente${data.total > 1 ? 's' : ''} por confirmar.`,
                icon: '../assets/media/img/predef.ico',
                tag:  'bs-pendientes'
              });
            }
            localStorage.setItem('bs_pendientes', data.total);
          }
        } else {
          badge.style.display = 'none';
          btn.classList.remove('has-pending');
          localStorage.setItem('bs_pendientes', 0);
        }
      } catch(e) {}
    }

    // Solicitar permiso push 
    async function solicitarPermisoNotif() {
      if (!('Notification' in window)) {
        alert('Tu navegador no soporta notificaciones push.');
        return;
      }
      const permiso = await Notification.requestPermission();
      if (permiso === 'granted') {
        new Notification('Barber Shop', { body: 'Notificaciones activadas correctamente.' });
        actualizarEstadoPushBtn();
        cargarPendientes();
      }
    }

    function actualizarEstadoPushBtn() {
      const btn   = document.getElementById('btn-push');
      const label = document.getElementById('push-label');
      if (!btn || !label) return;
      if (Notification.permission === 'granted') {
        btn.classList.add('enabled');
        label.textContent = 'Notificaciones activas';
      } else {
        btn.classList.remove('enabled');
        label.textContent = 'Activar notificaciones push';
      }
    }

    // Inicializar
    actualizarBadge();
    actualizarEstadoPushBtn();
    setInterval(actualizarBadge, 60000);
    setTimeout(() => location.reload(), 30000);