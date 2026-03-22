<?php
// ============================================
// API DE CITAS
// Endpoints:
//   GET  citas.php?accion=disponibilidad&fecha=YYYY-MM-DD
//         devuelve horas ocupadas en esa fecha
//
//   GET  citas.php?accion=citas_del_dia&fecha=YYYY-MM-DD
//         devuelve todas las citas de esa fecha (sin datos privados)
//
//   POST citas.php  (body JSON)
//         crea una nueva cita
// ============================================

require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Horario del negocio: cada 30 minutos de 9am a 7pm
define('HORA_INICIO', '09:00');
define('HORA_FIN',    '19:00');
define('INTERVALO',   30);       // minutos

// ── Helpers ─────────────────────────────────

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

// ── Router ──────────────────────────────────

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// ── GET: disponibilidad ──────────────────────
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

// ── GET: citas del día (vista pública, sin datos privados) ──
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

// ── POST: crear cita ─────────────────────────
if ($metodo === 'POST') {

    $body = json_decode(file_get_contents('php://input'), true);

    // Validar campos requeridos
    $requeridos = ['nombre', 'contacto', 'fecha', 'hora'];
    foreach ($requeridos as $campo) {
        if (empty($body[$campo])) {
            responder(['error' => "El campo '$campo' es obligatorio."], 400);
        }
    }

    $nombre      = trim($body['nombre']);
    $contacto    = trim($body['contacto']);
    $fecha       = trim($body['fecha']);
    $hora        = trim($body['hora']);
    $descripcion = trim($body['descripcion'] ?? '');

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
            "INSERT INTO citas (nombre, contacto, fecha, hora, descripcion)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nombre, $contacto, $fecha, $hora, $descripcion]);

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
        // Error de clave única: la hora ya está ocupada
        if ($e->getCode() === '23000') {
            responder(['error' => 'Esa fecha y hora ya están ocupadas.'], 409);
        }
        responder(['error' => 'Error interno del servidor.'], 500);
    }
}

// ── Método no permitido ──────────────────────
responder(['error' => 'Acción no reconocida.'], 405);