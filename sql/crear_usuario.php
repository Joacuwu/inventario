<?php
/**
 * Crea un usuario desde la consola, con la contraseña hasheada.
 * Uso: php sql/crear_usuario.php <usuario> <contraseña> <admin|operador> "<Nombre completo>"
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 5 || !in_array($argv[3], array('admin', 'operador'), true) || strlen($argv[2]) < 8) {
    fwrite(STDERR, "Uso: php sql/crear_usuario.php <usuario> <contraseña de 8+ caracteres> <admin|operador> \"<Nombre>\"\n");
    exit(1);
}

$_SESSION = array();
require dirname(dirname(__FILE__)) . '/includes/db.php';

$stmt = db()->prepare('INSERT INTO usuarios (usuario, password_hash, nombre, rol) VALUES (?, ?, ?, ?)');
$stmt->execute(array($argv[1], password_hash($argv[2], PASSWORD_DEFAULT), $argv[4], $argv[3]));
echo "Usuario {$argv[1]} creado.\n";
