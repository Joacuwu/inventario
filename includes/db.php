<?php
require_once dirname(__FILE__) . '/config.php';

/**
 * Conexión PDO única. En modo demo (sin DSN) crea una base SQLite con datos de ejemplo.
 */
function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $opciones = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    );

    if (DB_DSN !== '') {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $opciones);
        return $pdo;
    }

    $archivo = dirname(dirname(__FILE__)) . '/data/demo.sqlite';
    $nueva = !file_exists($archivo);
    $pdo = new PDO('sqlite:' . $archivo, null, null, $opciones);
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ($nueva) {
        crear_base_demo($pdo);
    }
    return $pdo;
}

function crear_base_demo(PDO $pdo)
{
    $pdo->exec('CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        rol VARCHAR(10) NOT NULL
    )');
    $pdo->exec('CREATE TABLE categorias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre VARCHAR(60) NOT NULL UNIQUE
    )');
    $pdo->exec('CREATE TABLE productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo VARCHAR(20) NOT NULL UNIQUE,
        nombre VARCHAR(100) NOT NULL,
        categoria_id INTEGER NOT NULL REFERENCES categorias(id),
        precio DECIMAL(12,2) NOT NULL,
        stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
        stock_minimo INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE movimientos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        producto_id INTEGER NOT NULL REFERENCES productos(id),
        usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
        tipo VARCHAR(7) NOT NULL,
        cantidad INTEGER NOT NULL,
        stock_resultante INTEGER NOT NULL,
        motivo VARCHAR(150) NOT NULL,
        fecha DATETIME NOT NULL
    )');
    $pdo->exec('CREATE INDEX idx_movimientos_fecha ON movimientos (fecha)');

    $stmt = $pdo->prepare('INSERT INTO usuarios (usuario, password_hash, nombre, rol) VALUES (?, ?, ?, ?)');
    $stmt->execute(array('admin', password_hash('admin1234', PASSWORD_DEFAULT), 'Carla Admin', 'admin'));
    $stmt->execute(array('operador', password_hash('operador1234', PASSWORD_DEFAULT), 'Julián Depósito', 'operador'));

    $stmt = $pdo->prepare('INSERT INTO categorias (nombre) VALUES (?)');
    foreach (array('Electricidad', 'Herramientas', 'Pinturería', 'Plomería', 'Tornillería') as $c) {
        $stmt->execute(array($c));
    }

    // codigo, nombre, categoria, precio, stock, minimo
    $productos = array(
        array('ELE-001', 'Cable unipolar 2,5 mm (rollo 100 m)', 1, 58900, 14, 5),
        array('ELE-002', 'Tomacorriente doble 10 A', 1, 3250, 3, 10),
        array('ELE-003', 'Lámpara LED 12 W luz fría', 1, 1890, 120, 40),
        array('ELE-004', 'Térmica bipolar 20 A', 1, 15400, 8, 4),
        array('HER-001', 'Taladro percutor 650 W', 2, 74500, 6, 2),
        array('HER-002', 'Juego de destornilladores x6', 2, 12800, 1, 3),
        array('HER-003', 'Martillo carpintero 25 mm', 2, 9900, 11, 4),
        array('HER-004', 'Cinta métrica 5 m', 2, 4600, 0, 5),
        array('PIN-001', 'Látex interior blanco 20 L', 3, 89900, 9, 3),
        array('PIN-002', 'Rodillo antigota 22 cm', 3, 5400, 17, 6),
        array('PIN-003', 'Esmalte sintético negro 1 L', 3, 14200, 4, 4),
        array('PLO-001', 'Caño termofusión 20 mm (4 m)', 4, 6300, 45, 15),
        array('PLO-002', 'Llave de paso 1/2"', 4, 8700, 12, 5),
        array('PLO-003', 'Cinta teflón 3/4"', 4, 650, 200, 50),
        array('TOR-001', 'Tornillo autoperforante 8x1" (x100)', 5, 4100, 30, 10),
        array('TOR-002', 'Tarugo Fischer 8 mm (x100)', 5, 3900, 7, 10),
        array('TOR-003', 'Bulón hexagonal 1/4" x 2" (x50)', 5, 6800, 22, 8),
    );
    $prod = $pdo->prepare('INSERT INTO productos (codigo, nombre, categoria_id, precio, stock, stock_minimo) VALUES (?, ?, ?, ?, ?, ?)');
    $mov = $pdo->prepare('INSERT INTO movimientos (producto_id, usuario_id, tipo, cantidad, stock_resultante, motivo, fecha)
                          VALUES (?, ?, ?, ?, ?, ?, ?)');
    $base = strtotime('-20 days');
    foreach ($productos as $i => $p) {
        $prod->execute($p);
        $id = (int) $pdo->lastInsertId();
        // Historial de ejemplo: una compra inicial y, si corresponde, una venta.
        $vendido = (int) floor($p[4] / 3);
        $inicial = $p[4] + $vendido;
        $mov->execute(array($id, 1, 'entrada', $inicial, $inicial, 'Compra a proveedor', date('Y-m-d H:i:s', $base + $i * 3600)));
        if ($vendido > 0) {
            $mov->execute(array($id, 2, 'salida', $vendido, $p[4], 'Venta mostrador', date('Y-m-d H:i:s', $base + 86400 * (1 + $i % 15))));
        }
    }
}
