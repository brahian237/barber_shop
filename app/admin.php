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
define('MAIL_USER',     'brahianstivenmolina19@gmail.com');
define('MAIL_PASS',     'moeg qsel tkex mjmn');
define('MAIL_FROM',     'brahianstivenmolina19@gmail.com');
define('MAIL_FROM_NAME','Barber Shop');

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
                    <td style='padding: 10px; border: 1px solid #dee2e6;'>Calle 10 # 43-25, El Poblado, Medellín</td>
                </tr>
            </table>
            <p>Si necesitas cancelar o cambiar tu cita, contáctanos por WhatsApp:
               <a href='https://wa.me/573001234567'>+57 300 123 4567</a>
            </p>
            <hr style='border:none; border-top:1px solid #dee2e6; margin: 24px 0;'>
            <p style='color:#6c757d; font-size: 13px;'>
                Barber Shop · El Poblado, Medellín · Lunes a Sábado, 9:00 am – 7:00 pm
            </p>
        </div>";

        $mail->AltBody = "¡Cita confirmada, {$nombre}! Te esperamos el {$fecha} a las {$hora12} en Barber Shop, Calle 10 # 43-25, El Poblado, Medellín.";

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
                Barber Shop · El Poblado, Medellín · Lunes a Sábado, 9:00 am – 7:00 pm
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
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/admin.css" />
</head>
<body>

<?php if (!$logueado): ?>

  <div class="login-wrap">
    <div class="login-card">
      <div class="brand">Barber<span>Shop</span></div>
      <div class="subtitle">Panel de Administración</div>
      <span class="gold-divider"></span>

      <?php if ($error_login): ?>
        <div class="alert-login"><?= htmlspecialchars($error_login) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field-group">
          <label for="usuario">Usuario</label>
          <input type="text" id="usuario" name="usuario" placeholder="admin" required autocomplete="username" />
        </div>
        <div class="field-group">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" />
        </div>
        <button type="submit" class="btn-gold"><span>Ingresar</span></button>
      </form>
    </div>
  </div>

<?php else: ?>

  <!-- Header -->
  <header class="admin-header">
    <div class="brand">Barber<span>Shop</span> <span style="font-size:0.8rem;color:var(--text-faint);font-family:var(--font-body);letter-spacing:0.06em;font-style:normal;">/ Admin</span></div>
    <form method="POST">
      <button name="cerrar_sesion" class="btn-logout">Cerrar sesión</button>
    </form>
  </header>

  <div class="admin-body">

    <h1 class="section-title">Citas del día</h1>

    <?php if ($mensaje): ?>
      <div class="msg-<?= $mensaje_tipo === 'warning' ? 'warning' : 'success' ?>">
        <?= htmlspecialchars($mensaje) ?>
      </div>
    <?php endif; ?>

    <!-- Filtro de fecha -->
    <form method="GET" class="fecha-form">
      <label for="fecha">Ver citas del día</label>
      <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" />
      <button type="submit" class="btn-buscar">Buscar</button>
    </form>

    <?php if (empty($citas)): ?>
      <div class="empty-state">No hay citas registradas para esta fecha.</div>

    <?php else: ?>

      <!-- ── CARDS (móvil) ── -->
      <div class="citas-list">
        <?php foreach ($citas as $cita): ?>
          <?php
            $estadoClass = match($cita['estado']) {
              'confirmada' => 'estado-confirmada',
              'cancelada'  => 'estado-cancelada',
              default      => 'estado-pendiente',
            };
            // Convertir hora a 12h
            [$hh, $mm] = explode(':', $cita['hora']);
            $hh = (int)$hh;
            $per = $hh >= 12 ? 'PM' : 'AM';
            if ($hh === 0) $hh = 12; elseif ($hh > 12) $hh -= 12;
            $hora12 = sprintf('%02d:%s %s', $hh, $mm, $per);
          ?>
          <div class="cita-card">
            <div class="cita-card-header">
              <div class="cita-hora"><?= $hora12 ?></div>
              <span class="cita-estado <?= $estadoClass ?>"><?= $cita['estado'] ?></span>
            </div>
            <div class="cita-info">
              <div class="cita-row"><strong>Nombre</strong><span><?= htmlspecialchars($cita['nombre']) ?></span></div>
              <div class="cita-row"><strong>Contacto</strong><span><?= htmlspecialchars($cita['contacto']) ?></span></div>
              <div class="cita-row"><strong>Correo</strong><span><?= htmlspecialchars($cita['email'] ?? '—') ?></span></div>
              <?php if (!empty($cita['descripcion'])): ?>
              <div class="cita-row"><strong>Nota</strong><span><?= htmlspecialchars($cita['descripcion']) ?></span></div>
              <?php endif; ?>
            </div>
            <div class="cita-actions">
              <?php if ($cita['estado'] === 'pendiente'): ?>
                <form method="POST" style="flex:1">
                  <input type="hidden" name="accion" value="confirmar" />
                  <input type="hidden" name="id" value="<?= $cita['id'] ?>" />
                  <input type="hidden" name="fecha_filtro" value="<?= $filtro_fecha ?>" />
                  <button type="submit" class="btn-confirmar" style="width:100%">Confirmar</button>
                </form>
              <?php endif; ?>
              <?php if ($cita['estado'] !== 'cancelada'): ?>
                <form method="POST" style="flex:1">
                  <input type="hidden" name="accion" value="cancelar" />
                  <input type="hidden" name="id" value="<?= $cita['id'] ?>" />
                  <input type="hidden" name="fecha_filtro" value="<?= $filtro_fecha ?>" />
                  <button type="submit" class="btn-cancelar" style="width:100%" onclick="return confirm('¿Cancelar esta cita?')">Cancelar</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ── TABLA (desktop) ── -->
      <div class="citas-table-wrap">
        <table>
          <thead>
            <tr>
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
              <?php
                $estadoClass = match($cita['estado']) {
                  'confirmada' => 'estado-confirmada',
                  'cancelada'  => 'estado-cancelada',
                  default      => 'estado-pendiente',
                };
                [$hh, $mm] = explode(':', $cita['hora']);
                $hh = (int)$hh;
                $per = $hh >= 12 ? 'PM' : 'AM';
                if ($hh === 0) $hh = 12; elseif ($hh > 12) $hh -= 12;
                $hora12 = sprintf('%02d:%s %s', $hh, $mm, $per);
              ?>
              <tr>
                <td class="hora-cell"><?= $hora12 ?></td>
                <td><?= htmlspecialchars($cita['nombre']) ?></td>
                <td class="muted"><?= htmlspecialchars($cita['contacto']) ?></td>
                <td class="muted"><?= htmlspecialchars($cita['email'] ?? '—') ?></td>
                <td class="muted"><?= htmlspecialchars($cita['descripcion'] ?? '—') ?></td>
                <td><span class="cita-estado <?= $estadoClass ?>"><?= $cita['estado'] ?></span></td>
                <td>
                  <div class="table-actions">
                    <?php if ($cita['estado'] === 'pendiente'): ?>
                      <form method="POST">
                        <input type="hidden" name="accion" value="confirmar" />
                        <input type="hidden" name="id" value="<?= $cita['id'] ?>" />
                        <input type="hidden" name="fecha_filtro" value="<?= $filtro_fecha ?>" />
                        <button type="submit" class="btn-confirmar">Confirmar</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($cita['estado'] !== 'cancelada'): ?>
                      <form method="POST">
                        <input type="hidden" name="accion" value="cancelar" />
                        <input type="hidden" name="id" value="<?= $cita['id'] ?>" />
                        <input type="hidden" name="fecha_filtro" value="<?= $filtro_fecha ?>" />
                        <button type="submit" class="btn-cancelar" onclick="return confirm('¿Cancelar esta cita?')">Cancelar</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>

    <p class="auto-refresh-note">Actualización automática cada 30 segundos</p>

  </div>

  <script>setTimeout(() => location.reload(), 30000);</script>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>