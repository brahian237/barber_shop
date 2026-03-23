<?php
// API DE CITAS
// Endpoints:
//   GET  citas.php?accion=disponibilidad&fecha=YYYY-MM-DD
//   GET  citas.php?accion=citas_del_dia&fecha=YYYY-MM-DD
//   POST citas.php  (body JSON)


require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

define('HORA_INICIO', '09:00');
define('HORA_FIN',    '19:00');
define('INTERVALO',   30);

// Helpers
function responder(array $datos, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function horarios_del_dia(): array {
    $horarios = [];
    $inicio   = strtotime(HORA_INICIO);
    $fin      = strtotime(HORA_FIN);

    while ($inicio < $fin) {
        $horarios[] = date('H:i', $inicio);
        $inicio += INTERVALO * 60;
    }

    return $horarios;
}

// Sesión (para endpoints protegidos)
session_start();

// Router

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// Endpoints que requieren sesión de admin
$accionesProtegidas = ['pendientes', 'pendientes_detalle'];
if (in_array($accion, $accionesProtegidas) && empty($_SESSION['admin'])) {
    responder(['error' => 'No autorizado.'], 401);
}

// GET: disponibilidad
if ($metodo === 'GET' && $accion === 'disponibilidad') {

    $fecha = $_GET['fecha'] ?? '';

    if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        responder(['error' => 'Fecha inválida. Usa el formato YYYY-MM-DD.'], 400);
    }

    $pdo  = conectar();
    $stmt = $pdo->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') AS hora
         FROM citas
         WHERE fecha = ? AND estado != 'cancelada'"
    );
    $stmt->execute([$fecha]);
    $ocupadas = array_column($stmt->fetchAll(), 'hora');

    $todos       = horarios_del_dia();
    $disponibles = array_values(array_diff($todos, $ocupadas));

    responder([
        'fecha'       => $fecha,
        'disponibles' => $disponibles,
        'ocupadas'    => $ocupadas,
    ]);
}

// GET: citas del día
if ($metodo === 'GET' && $accion === 'citas_del_dia') {

    $fecha = $_GET['fecha'] ?? '';

    if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        responder(['error' => 'Fecha inválida. Usa el formato YYYY-MM-DD.'], 400);
    }

    $pdo  = conectar();
    $stmt = $pdo->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') AS hora, estado
         FROM citas
         WHERE fecha = ? AND estado != 'cancelada'
         ORDER BY hora"
    );
    $stmt->execute([$fecha]);

    responder([
        'fecha' => $fecha,
        'citas' => $stmt->fetchAll(),
    ]);
}

// ¿POST: crear cita
if ($metodo === 'POST') {

    $body = json_decode(file_get_contents('php://input'), true);

    // Validar campos requeridos
    $requeridos = ['nombre', 'contacto', 'email', 'fecha', 'hora'];
    foreach ($requeridos as $campo) {
        if (empty($body[$campo])) {
            responder(['error' => "El campo '$campo' es obligatorio."], 400);
        }
    }

    $nombre      = trim($body['nombre']);
    $contacto    = trim($body['contacto']);
    $email       = trim($body['email']);
    $fecha       = trim($body['fecha']);
    $hora        = trim($body['hora']);
    $descripcion = trim($body['descripcion'] ?? '');

    // Validar formato email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder(['error' => 'El correo electrónico no es válido.'], 400);
    }

    // Validar formato fecha y hora
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        responder(['error' => 'Formato de fecha inválido. Usa YYYY-MM-DD.'], 400);
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        responder(['error' => 'Formato de hora inválido. Usa HH:MM.'], 400);
    }

    // Validar que la hora esté dentro del horario del negocio
    if (!in_array($hora, horarios_del_dia())) {
        responder(['error' => 'Hora fuera del horario de atención.'], 400);
    }

    // Validar que la fecha no sea en el pasado
    if ($fecha < date('Y-m-d')) {
        responder(['error' => 'No puedes agendar citas en fechas pasadas.'], 400);
    }

    try {
        $pdo  = conectar();
        $stmt = $pdo->prepare(
            "INSERT INTO citas (nombre, contacto, email, fecha, hora, descripcion)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nombre, $contacto, $email, $fecha, $hora, $descripcion]);

        responder([
            'mensaje' => 'Cita agendada correctamente.',
            'cita'    => [
                'id'     => $pdo->lastInsertId(),
                'nombre' => $nombre,
                'fecha'  => $fecha,
                'hora'   => $hora,
                'estado' => 'pendiente',
            ],
        ], 201);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            responder(['error' => 'Esa fecha y hora ya están ocupadas.'], 409);
        }
        responder(['error' => 'Error interno del servidor.'], 500);
    }
}

// GET: conteo de pendientes (para notificaciones)
if ($metodo === 'GET' && $accion === 'pendientes') {
    $pdo  = conectar();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN fecha = CURDATE() THEN 1 ELSE 0 END) AS hoy
         FROM citas
         WHERE estado = 'pendiente' AND fecha >= CURDATE()"
    );
    $stmt->execute();
    $row = $stmt->fetch();

    responder([
        'total' => (int) $row['total'],
        'hoy'   => (int) $row['hoy'],
    ]);
}

// GET: detalle de pendientes (para el panel)
if ($metodo === 'GET' && $accion === 'pendientes_detalle') {
    $pdo  = conectar();
    $stmt = $pdo->prepare(
        "SELECT nombre,
                DATE_FORMAT(fecha, '%d/%m/%Y') AS fecha,
                fecha                          AS fecha_iso,
                TIME_FORMAT(hora, '%H:%i')     AS hora
         FROM citas
         WHERE estado = 'pendiente' AND fecha >= CURDATE()
         ORDER BY fecha ASC, hora ASC
         LIMIT 50"
    );
    $stmt->execute();

    responder(['citas' => $stmt->fetchAll()]);
}

// Método no permitido
responder(['error' => 'Acción no reconocida.'], 405);