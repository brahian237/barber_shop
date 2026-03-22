<?php
// ============================================
// PANEL DE ADMINISTRACIÓN DE CITAS
// Acciones disponibles (POST):
//   accion=confirmar  + id
//   accion=cancelar   + id
// ============================================

require_once 'conexion.php';

// ── Autenticación básica ─────────────────────
// Cambia estas credenciales antes de publicar
define('ADMIN_USUARIO',  'admin');
define('ADMIN_PASSWORD', 'barbershop2025');

session_start();

$error_login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario'])) {
    if (
        $_POST['usuario'] === ADMIN_USUARIO &&
        $_POST['password'] === ADMIN_PASSWORD
    ) {
        $_SESSION['admin'] = true;
    } else {
        $error_login = 'Usuario o contraseña incorrectos.';
    }
}

if (isset($_POST['cerrar_sesion'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$logueado = !empty($_SESSION['admin']);

// ── Acciones sobre citas ─────────────────────
$mensaje = '';

if ($logueado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && in_array($accion, ['confirmar', 'cancelar'])) {
        $nuevo_estado = $accion === 'confirmar' ? 'confirmada' : 'cancelada';
        $pdo  = conectar();
        $stmt = $pdo->prepare("UPDATE citas SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $id]);
        $mensaje = "Cita #$id marcada como $nuevo_estado.";
    }
}

// ── Consultar citas ──────────────────────────
$citas      = [];
$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');

if ($logueado) {
    $pdo  = conectar();
    $stmt = $pdo->prepare(
        "SELECT id, nombre, contacto,
                DATE_FORMAT(fecha, '%d/%m/%Y') AS fecha,
                TIME_FORMAT(hora,  '%H:%i')    AS hora,
                descripcion, estado, creado_en
         FROM citas
         WHERE fecha = ?
         ORDER BY hora"
    );
    $stmt->execute([$filtro_fecha]);
    $citas = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="es-CO">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — Barber Shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">

<?php if (!$logueado): ?>

  <!-- ========================
       FORMULARIO DE LOGIN
  ========================= -->
  <div class="container mt-5" style="max-width: 400px;">
    <h2 class="mb-4">Panel de Administración</h2>

    <?php if ($error_login): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error_login) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label for="usuario" class="form-label">Usuario</label>
        <input type="text" class="form-control" id="usuario" name="usuario" required />
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control" id="password" name="password" required />
      </div>
      <button type="submit" class="btn btn-dark w-100">Ingresar</button>
    </form>
  </div>

<?php else: ?>

  <!-- ========================
       PANEL PRINCIPAL
  ========================= -->
  <div class="container mt-4">

    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Citas del día</h2>
      <form method="POST">
        <button name="cerrar_sesion" class="btn btn-outline-secondary btn-sm">Cerrar sesión</button>
      </form>
    </div>

    <!-- Mensaje de confirmación -->
    <?php if ($mensaje): ?>
      <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- Filtro por fecha -->
    <form method="GET" class="mb-4 d-flex gap-2 align-items-center">
      <label for="fecha" class="form-label mb-0">Ver citas del día:</label>
      <input
        type="date"
        id="fecha"
        name="fecha"
        class="form-control"
        style="width: auto;"
        value="<?= htmlspecialchars($filtro_fecha) ?>"
      />
      <button type="submit" class="btn btn-dark btn-sm">Buscar</button>
    </form>

    <!-- Tabla de citas -->
    <?php if (empty($citas)): ?>
      <p class="text-muted">No hay citas registradas para esta fecha.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover bg-white">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Hora</th>
              <th>Nombre</th>
              <th>Contacto</th>
              <th>Descripción</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($citas as $cita): ?>
            <tr>
              <td><?= $cita['id'] ?></td>
              <td><?= htmlspecialchars($cita['hora']) ?></td>
              <td><?= htmlspecialchars($cita['nombre']) ?></td>
              <td><?= htmlspecialchars($cita['contacto']) ?></td>
              <td><?= htmlspecialchars($cita['descripcion'] ?? '—') ?></td>
              <td>
                <?php
                  $badge = match($cita['estado']) {
                    'confirmada' => 'success',
                    'cancelada'  => 'danger',
                    default      => 'warning',
                  };
                ?>
                <span class="badge bg-<?= $badge ?>"><?= $cita['estado'] ?></span>
              </td>
              <td>
                <?php if ($cita['estado'] === 'pendiente'): ?>
                  <!-- Confirmar -->
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="accion" value="confirmar" />
                    <input type="hidden" name="id" value="<?= $cita['id'] ?>" />
                    <input type="hidden" name="fecha_filtro" value="<?= $filtro_fecha ?>" />
                    <button type="submit" class="btn btn-success btn-sm">Confirmar</button>
                  </form>
                <?php endif; ?>

                <?php if ($cita['estado'] !== 'cancelada'): ?>
                  <!-- Cancelar -->
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="accion" value="cancelar" />
                    <input type="hidden" name="id" value="<?= $cita['id'] ?>" />
                    <input type="hidden" name="fecha_filtro" value="<?= $filtro_fecha ?>" />
                    <button
                      type="submit"
                      class="btn btn-danger btn-sm"
                      onclick="return confirm('¿Cancelar esta cita?')"
                    >Cancelar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- Auto-refresh cada 30 segundos para tiempo real -->
    <p class="text-muted mt-3" style="font-size: 0.85rem;">
      La página se actualiza automáticamente cada 30 segundos.
    </p>

  </div>

  <script>
    // Refresca la página cada 30 segundos manteniendo el filtro de fecha
    setTimeout(() => location.reload(), 30000);
  </script>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>