<?php
// ============================================
// CONEXIÓN A LA BASE DE DATOS
// Edita estos valores con los de tu servidor
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'barbershop');
define('DB_USER', 'root');       // Cambia por tu usuario
define('DB_PASS', '');           // Cambia por tu contraseña
define('DB_CHARSET', 'utf8mb4');

function conectar(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
    }

    return $pdo;
}