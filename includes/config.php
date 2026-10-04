<?php
/**
 * Configuración. Compatible con PHP 5.6+ (sin sintaxis de PHP 7).
 * Las credenciales salen de config.local.php (fuera de git) o de variables de entorno.
 * Si no hay DSN se usa SQLite en /data (modo demo local).
 */
$local = dirname(__FILE__) . '/config.local.php';
$cfg = file_exists($local) ? require $local : array();

function cfg_valor($cfg, $clave, $env)
{
    if (isset($cfg[$clave])) {
        return $cfg[$clave];
    }
    $valor = getenv($env);
    return $valor === false ? '' : $valor;
}

define('DB_DSN',  cfg_valor($cfg, 'dsn',  'INV_DB_DSN'));   // ej: 'mysql:host=localhost;dbname=inventario;charset=utf8mb4'
define('DB_USER', cfg_valor($cfg, 'user', 'INV_DB_USER'));
define('DB_PASS', cfg_valor($cfg, 'pass', 'INV_DB_PASS'));

define('APP_NOMBRE', 'Inventario');
define('POR_PAGINA', 15);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Argentina/Buenos_Aires');
