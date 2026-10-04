<?php
/**
 * Endpoint Ajax: registra una entrada o salida de stock y responde JSON.
 * POST producto_id, tipo (entrada|salida), cantidad, motivo, csrf.
 */
require_once dirname(dirname(__FILE__)) . '/includes/inventario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder_json(array('ok' => false, 'error' => 'Método no permitido.'), 405);
}
if (usuario_actual() === null) {
    responder_json(array('ok' => false, 'error' => 'La sesión expiró. Volvé a ingresar.'), 401);
}
verificar_csrf();

$producto_id = entero(post('producto_id'));
$cantidad    = entero(post('cantidad'));
if ($producto_id < 1) {
    responder_json(array('ok' => false, 'error' => 'Producto inválido.'), 422);
}

try {
    $u = usuario_actual();
    $r = registrar_movimiento($producto_id, post('tipo'), $cantidad, post('motivo'), $u['id']);
    if (!$r['ok']) {
        responder_json($r, 422);
    }
    $producto = obtener_producto($producto_id);
    responder_json(array(
        'ok'      => true,
        'stock'   => $r['stock'],
        'minimo'  => (int) $producto['stock_minimo'],
        'mensaje' => (post('tipo') === 'entrada' ? 'Entrada' : 'Salida') . ' registrada: ' . $producto['nombre']
                     . ' queda con ' . $r['stock'] . ' unidades.',
    ));
} catch (PDOException $ex) {
    error_interno($ex);
}
