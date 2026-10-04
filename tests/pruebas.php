<?php
/**
 * Pruebas de la capa de datos, sin dependencias. Usa una base SQLite en memoria.
 * Ejecutar desde la carpeta del proyecto:  php tests/pruebas.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('INV_DB_DSN=sqlite::memory:');
$_SESSION = array();
require dirname(dirname(__FILE__)) . '/includes/inventario.php';
crear_base_demo(db());

$ok = 0;
$fallas = 0;
function probar($descripcion, $condicion)
{
    global $ok, $fallas;
    if ($condicion) {
        $ok++;
        echo "  OK    $descripcion\n";
    } else {
        $fallas++;
        echo "  FALLA $descripcion\n";
    }
}

$sin_filtro = array('texto' => '', 'tipo' => '', 'desde' => '', 'hasta' => '');

echo "Datos de ejemplo y consultas\n";
$r = resumen_inventario();
probar('el tablero cuenta 17 productos', (int) $r['productos'] === 17);
probar('el tablero suma 509 unidades', (int) $r['unidades'] === 509);
probar('5 productos bajo el mínimo y 1 sin stock', (int) $r['bajo_minimo'] === 5 && (int) $r['sin_stock'] === 1);
probar('filtro "bajo mínimo" trae 5', contar_productos('', 0, true) === 5);
probar('los productos bajo mínimo se listan primero', (int) current(buscar_productos('', 0, false, 1))['stock'] <= 10);
probar('la página 1 trae ' . POR_PAGINA . ' y la 2 el resto', count(buscar_productos('', 0, false, 1)) === POR_PAGINA
    && count(buscar_productos('', 0, false, 2)) === 17 - POR_PAGINA);
probar('filtro por categoría Plomería (id 4) trae 3', contar_productos('', 4, false) === 3);
probar('historial: 32 movimientos, 15 salidas', contar_movimientos($sin_filtro) === 32
    && contar_movimientos(array_merge($sin_filtro, array('tipo' => 'salida'))) === 15);
probar("búsqueda \"' OR 1=1 --\" no devuelve productos", contar_productos("' OR 1=1 --", 0, false) === 0);
probar('fecha de filtro inválida se ignora', contar_movimientos(array_merge($sin_filtro, array('desde' => '2024-13-45'))) === 32);

echo "Movimientos de stock\n";
// Producto 1: ELE-001 con 14 unidades.
$r = registrar_movimiento(1, 'salida', 15, 'Venta mostrador', 1);
$p = obtener_producto(1);
probar('una salida mayor al stock se rechaza y no cambia nada', !$r['ok'] && strpos($r['error'], 'insuficiente') !== false
    && (int) $p['stock'] === 14 && contar_movimientos($sin_filtro) === 32);

$r = registrar_movimiento(1, 'salida', 14, 'Venta mostrador', 2);
probar('una salida por todo el stock deja 0', $r['ok'] && $r['stock'] === 0);

$r = registrar_movimiento(1, 'entrada', 20, 'Compra a proveedor', 1);
probar('una entrada suma al stock', $r['ok'] && $r['stock'] === 20);

$ultimo = current(buscar_movimientos($sin_filtro, 1));
probar('el movimiento guarda el stock resultante y el usuario', (int) $ultimo['stock_resultante'] === 20 && $ultimo['usuario'] === 'Carla Admin');

$r = registrar_movimiento(1, 'ajuste', 5, 'Inventario anual', 1);
probar('un tipo de movimiento inválido se rechaza', !$r['ok']);
$r = registrar_movimiento(1, 'entrada', 0, 'Compra', 1);
probar('cantidad 0 se rechaza', !$r['ok']);
$r = registrar_movimiento(1, 'entrada', 5, 'x', 1);
probar('motivo de menos de 3 caracteres se rechaza', !$r['ok']);
$r = registrar_movimiento(999, 'entrada', 5, 'Compra', 1);
probar('producto inexistente devuelve "no existe"', !$r['ok'] && strpos($r['error'], 'no existe') !== false);

echo "Productos\n";
$nuevo = array('codigo' => 'ele-005', 'nombre' => 'Zapatilla 5 tomas', 'categoria_id' => '1',
               'precio' => '15.400,50', 'stock_minimo' => '3', 'stock' => '10');
list($d, $err) = validar_producto($nuevo, 0);
probar('producto válido no tiene errores', $err === array());
probar('código se pasa a mayúsculas y precio "15.400,50" a 15400.5', $d['codigo'] === 'ELE-005' && $d['precio'] === 15400.5);

$id = guardar_producto($d, 0, 1);
$hist = buscar_movimientos(array_merge($sin_filtro, array('texto' => 'ELE-005')), 1);
probar('el alta registra el stock inicial como movimiento', count($hist) === 1 && $hist[0]['motivo'] === 'Stock inicial');

list(, $err) = validar_producto(array_merge($nuevo, array('codigo' => 'ELE-001')), 0);
probar('código repetido se rechaza', isset($err['codigo']));
list(, $err) = validar_producto(array_merge($nuevo, array('codigo' => 'ELE-005')), $id);
probar('editar el mismo producto conserva su código', !isset($err['codigo']));
list($d2) = validar_producto(array_merge($nuevo, array('stock' => '999')), $id);
probar('en la edición se ignora el stock (solo cambia con movimientos)', !array_key_exists('stock', $d2));
list(, $err) = validar_producto(array_merge($nuevo, array('categoria_id' => '99', 'stock_minimo' => '-3', 'precio' => 'abc')), 0);
probar('categoría inexistente, mínimo negativo y precio inválido se rechazan',
    isset($err['categoria_id'], $err['stock_minimo'], $err['precio']));

echo "\n$ok pruebas OK, $fallas fallas\n";
exit($fallas > 0 ? 1 : 0);
