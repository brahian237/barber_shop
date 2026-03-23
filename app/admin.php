<?php
// ============================================
// PANEL DE ADMINISTRACIÓN DE CITAS
// ============================================

require_once 'conexion.php';

// ── PHPMailer (instalación manual) ───────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/mailer/Exception.php';
require_once __DIR__ . '/mailer/PHPMailer.php';
require_once __DIR__ . '/mailer/SMTP.php';

// ── Configuración del correo ─────────────────
// Cambia estos valores cuando el cliente tenga su propio correo
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USER',     'brahianstivenmolina19@gmail.com'); //cambiar al cooreo del cliente
define('MAIL_PASS',     'moeg qsel tkex mjmn'); //contraseña de aplicación generada en Gmail
define('MAIL_FROM',     'brahianstivenmolina19@gmail.com'); //Mismo correo del cliente
define('MAIL_FROM_NAME','Barber Shop'); //Cambiar por el nombre del negocio del cliente

// ── Autenticación básica ─────────────────────
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
$mensaje       = '';
$mensaje_tipo  = 'success';

if ($logueado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion       = $_POST['accion'] ?? '';
    $id           = (int) ($_POST['id'] ?? 0);
    $fecha_filtro = $_POST['fecha_filtro'] ?? date('Y-m-d');

    if ($id > 0 && in_array($accion, ['confirmar', 'cancelar'])) {
        $nuevo_estado = $accion === 'confirmar' ? 'confirmada' : 'cancelada';
        $pdo  = conectar();
        $stmt = $pdo->prepare("UPDATE citas SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $id]);

        // Obtener datos de la cita para el correo
        $stmt = $pdo->prepare(
            "SELECT nombre, email,
                    DATE_FORMAT(fecha, '%d/%m/%Y') AS fecha,
                    TIME_FORMAT(hora,  '%H:%i')    AS hora
             FROM citas WHERE id = ?"
        );
        $stmt->execute([$id]);
        $cita = $stmt->fetch();

        if ($cita && !empty($cita['email'])) {
            if ($accion === 'confirmar') {
                $enviado = enviarCorreoConfirmacion(
                    $cita['email'], $cita['nombre'], $cita['fecha'], $cita['hora']
                );
                $_SESSION['mensaje']      = $enviado
                    ? "Cita #$id confirmada. Correo enviado a {$cita['email']}."
                    : "Cita #$id confirmada, pero no se pudo enviar el correo.";
                $_SESSION['mensaje_tipo'] = $enviado ? 'success' : 'warning';
            } else {
                $enviado = enviarCorreoCancelacion(
                    $cita['email'], $cita['nombre'], $cita['fecha'], $cita['hora']
                );
                $_SESSION['mensaje']      = $enviado
                    ? "Cita #$id cancelada. Correo enviado a {$cita['email']}."
                    : "Cita #$id cancelada, pero no se pudo enviar el correo.";
                $_SESSION['mensaje_tipo'] = $enviado ? 'success' : 'warning';
            }
        } else {
            $_SESSION['mensaje']      = "Cita #$id marcada como $nuevo_estado.";
            $_SESSION['mensaje_tipo'] = 'success';
        }

        // ── PRG: redirigir para evitar reenvío del formulario al recargar ──
        header("Location: admin.php?fecha=" . urlencode($fecha_filtro));
        exit;
    }
}

// ── Leer mensaje de sesión (si viene de un redirect) ────
$mensaje      = $_SESSION['mensaje']      ?? '';
$mensaje_tipo = $_SESSION['mensaje_tipo'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']);

// ── Función: enviar correo de confirmación ───
function enviarCorreoConfirmacion(
    string $destinatario,
    string $nombre,
    string $fecha,
    string $hora
): bool {
    // Convertir hora a formato 12h para el correo
    [$hh, $mm] = explode(':', $hora);
    $hh     = (int) $hh;
    $periodo = $hh >= 12 ? 'PM' : 'AM';
    if ($hh === 0)  $hh = 12;
    if ($hh > 12)   $hh -= 12;
    $hora12 = sprintf('%02d:%s %s', $hh, $mm, $periodo);

    try {
        $mail = new PHPMailer(true);

        // Servidor SMTP
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Remitente y destinatario
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($destinatario, $nombre);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = '✅ Tu cita en Barber Shop ha sido confirmada';
        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto;'>
            <h2 style='color: #212529;'>¡Cita Confirmada!</h2>
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>Tu cita en <strong>Barber Shop</strong> ha sido confirmada. Te esperamos:</p>
            <table style='width:100%; border-collapse:collapse; margin: 16px 0;'>
                <tr>
                    <td style='padding: 10px; background:#f8f9fa; border: 1px solid #dee2e6;'><strong>Fecha</strong></td>
                    <td style='padding: 10px; border: 1px solid #dee2e6;'>{$fecha}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; background:#f8f9fa; border: 1px solid #dee2e6;'><strong>Hora</strong></td>
                    <td style='padding: 10px; border: 1px solid #dee2e6;'>{$hora12}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; background:#f8f9fa; border: 1px solid #dee2e6;'><strong>Dirección</strong></td>
                    <td style='padding: 10px; border: 1px solid #dee2e6;'>Calle 00 # 00-00, La Ceja, Antioquia</td>
                </tr>
            </table>
            <p>Si necesitas cancelar o cambiar tu cita, contáctanos por WhatsApp:
               <a href='https://wa.me/573001234567'>+57 300 123 4567</a>
            </p>
            <hr style='border:none; border-top:1px solid #dee2e6; margin: 24px 0;'>
            <p style='color:#6c757d; font-size: 13px;'>
                Barber Shop · La Ceja, Antioquia · Lunes a Sábado, 9:00 am – 7:00 pm
            </p>
        </div>";

        $mail->AltBody = "¡Cita confirmada, {$nombre}! Te esperamos el {$fecha} a las {$hora12} en Barber Shop, Calle 00 # 00-00, La Ceja, Antioquia.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Error PHPMailer: ' . $e->getMessage());
        return false;
    }
}

// ── Función: enviar correo de cancelación ────
function enviarCorreoCancelacion(
    string $destinatario,
    string $nombre,
    string $fecha,
    string $hora
): bool {
    [$hh, $mm] = explode(':', $hora);
    $hh      = (int) $hh;
    $periodo = $hh >= 12 ? 'PM' : 'AM';
    if ($hh === 0) $hh = 12;
    if ($hh > 12)  $hh -= 12;
    $hora12 = sprintf('%02d:%s %s', $hh, $mm, $periodo);

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($destinatario, $nombre);

        $mail->isHTML(true);
        $mail->Subject = '❌ Tu cita en Barber Shop ha sido cancelada';
        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto;'>
            <h2 style='color: #dc3545;'>Cita Cancelada</h2>
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>Lamentamos informarte que tu cita en <strong>Barber Shop</strong> ha sido cancelada:</p>
            <table style='width:100%; border-collapse:collapse; margin: 16px 0;'>
                <tr>
                    <td style='padding: 10px; background:#f8f9fa; border: 1px solid #dee2e6;'><strong>Fecha</strong></td>
                    <td style='padding: 10px; border: 1px solid #dee2e6;'>{$fecha}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; background:#f8f9fa; border: 1px solid #dee2e6;'><strong>Hora</strong></td>
                    <td style='padding: 10px; border: 1px solid #dee2e6;'>{$hora12}</td>
                </tr>
            </table>
            <p>Si deseas reagendar tu cita o tienes alguna pregunta, contáctanos por WhatsApp:
               <a href='https://wa.me/573001234567'>+57 300 123 4567</a>
            </p>
            <hr style='border:none; border-top:1px solid #dee2e6; margin: 24px 0;'>
            <p style='color:#6c757d; font-size: 13px;'>
                Barber Shop · La Ceja, Antioquia · Lunes a Sábado, 9:00 am – 7:00 pm
            </p>
        </div>";

        $mail->AltBody = "Hola {$nombre}, tu cita del {$fecha} a las {$hora12} en Barber Shop ha sido cancelada. Para reagendar contáctanos: +57 300 123 4567.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Error PHPMailer: ' . $e->getMessage());
        return false;
    }
}

// ── Consultar citas ──────────────────────────
$citas        = [];
$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');

if ($logueado) {
    $pdo  = conectar();
    $stmt = $pdo->prepare(
        "SELECT id, nombre, contacto, email,
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

  <div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Citas del día</h2>
      <form method="POST">
        <button name="cerrar_sesion" class="btn btn-outline-secondary btn-sm">Cerrar sesión</button>
      </form>
    </div>

    <?php if ($mensaje): ?>
      <div class="alert alert-<?= $mensaje_tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

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
              <th>Correo</th>
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
              <td><?= htmlspecialchars($cita['email'] ?? '—') ?></td>
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
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="accion" value="confirmar" />
                    <input type="hidden" name="id" value="<?= $cita['id'] ?>" />
                    <input type="hidden" name="fecha_filtro" value="<?= $filtro_fecha ?>" />
                    <button type="submit" class="btn btn-success btn-sm">Confirmar</button>
                  </form>
                <?php endif; ?>

                <?php if ($cita['estado'] !== 'cancelada'): ?>
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

    <p class="text-muted mt-3" style="font-size: 0.85rem;">
      La página se actualiza automáticamente cada 30 segundos.
    </p>

  </div>

  <script>
    setTimeout(() => location.reload(), 30000);
  </script>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>