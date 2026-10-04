<?php
/**
 * Acceso a datos y reglas de negocio del inventario. Todas las consultas son preparadas.
 */
require_once dirname(__FILE__) . '/funciones.php';

/** Ejecuta una consulta paginada enlazando LIMIT y OFFSET como enteros. */
function consulta_paginada($sql, $params, $pagina, $por_pagina)
{
    $stmt = db()->prepare($sql . ' LIMIT ? OFFSET ?');
    $i = 1;
    foreach ($params as $valor) {
        $stmt->bindValue($i++, $valor, PDO::PARAM_STR);
    }
    $stmt->bindValue($i++, (int) $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue($i, (int) max(0, ($pagina - 1) * $por_pagina), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function contar($sql, $params)
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function escapar_like($texto)
{
    return '%' . str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), $texto) . '%';
}

/* ---------- Categorías ---------- */

function categorias()
{
    return db()->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
}

function categoria_existe($id)
{
    return contar('SELECT COUNT(*) FROM categorias WHERE id = ?', array($id)) > 0;
}

/* ---------- Productos ---------- */

function filtro_productos($texto, $categoria_id, $solo_bajo)
{
    $where = array();
    $params = array();
    if ($texto !== '') {
        $where[] = "(p.codigo LIKE ? ESCAPE '!' OR p.nombre LIKE ? ESCAPE '!')";
        $like = escapar_like($texto);
        array_push($params, $like, $like);
    }
    if ($categoria_id > 0) {
        $where[] = 'p.categoria_id = ?';
        $params[] = $categoria_id;
    }
    if ($solo_bajo) {
        $where[] = 'p.stock <= p.stock_minimo';
    }
    return array($where ? ' WHERE ' . implode(' AND ', $where) : '', $params);
}

function contar_productos($texto, $categoria_id, $solo_bajo)
{
    list($where, $params) = filtro_productos($texto, $categoria_id, $solo_bajo);
    return contar('SELECT COUNT(*) FROM productos p' . $where, $params);
}

function buscar_productos($texto, $categoria_id, $solo_bajo, $pagina)
{
    list($where, $params) = filtro_productos($texto, $categoria_id, $solo_bajo);
    return consulta_paginada(
        'SELECT p.id, p.codigo, p.nombre, p.precio, p.stock, p.stock_minimo, c.nombre AS categoria
           FROM productos p
           JOIN categorias c ON c.id = p.categoria_id' . $where . '
          ORDER BY (p.stock <= p.stock_minimo) DESC, p.nombre',
        $params, $pagina, POR_PAGINA
    );
}

/** Indicadores del tablero, en una sola consulta. */
function resumen_inventario()
{
    return db()->query(
        'SELECT COUNT(*) AS productos,
                COALESCE(SUM(stock), 0) AS unidades,
                COALESCE(SUM(stock * precio), 0) AS valor,
                COALESCE(SUM(CASE WHEN stock <= stock_minimo THEN 1 ELSE 0 END), 0) AS bajo_minimo,
                COALESCE(SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END), 0) AS sin_stock
           FROM productos'
    )->fetch();
}

function obtener_producto($id)
{
    $stmt = db()->prepare('SELECT id, codigo, nombre, categoria_id, precio, stock, stock_minimo FROM productos WHERE id = ?');
    $stmt->execute(array($id));
    $fila = $stmt->fetch();
    return $fila ? $fila : null;
}

/** "58.900,50", "58900,5" o "58900.50" -> 58900.5. Devuelve null si no es válido. */
function parsear_precio($texto)
{
    $texto = str_replace(array('$', ' '), '', $texto);
    if (strpos($texto, ',') !== false) {
        $texto = str_replace(array('.', ','), array('', '.'), $texto);
    }
    return is_numeric($texto) ? round((float) $texto, 2) : null;
}

function largo($texto)
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : (int) preg_match_all('/./us', $texto);
}

/**
 * Valida y normaliza un producto. El stock inicial solo se pide en el alta:
 * después el stock cambia únicamente con movimientos, para que siempre quede registrado.
 * Devuelve array($datos, $errores).
 */
function validar_producto($entrada, $id)
{
    $d = array();
    foreach (array('codigo', 'nombre', 'categoria_id', 'precio', 'stock_minimo', 'stock') as $campo) {
        $d[$campo] = isset($entrada[$campo]) && is_string($entrada[$campo]) ? trim($entrada[$campo]) : '';
    }
    $d['codigo'] = strtoupper($d['codigo']);
    $err = array();

    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $d['codigo'])) {
        $err['codigo'] = 'Entre 3 y 20 caracteres: letras, números y guiones.';
    } elseif (contar('SELECT COUNT(*) FROM productos WHERE codigo = ? AND id <> ?', array($d['codigo'], $id)) > 0) {
        $err['codigo'] = 'Ya existe un producto con ese código.';
    }

    $n = largo($d['nombre']);
    if ($n < 3 || $n > 100) {
        $err['nombre'] = 'El nombre debe tener entre 3 y 100 caracteres.';
    }

    $d['categoria_id'] = entero($d['categoria_id']);
    if ($d['categoria_id'] < 1 || !categoria_existe($d['categoria_id'])) {
        $err['categoria_id'] = 'Elegí una categoría.';
    }

    $precio = parsear_precio($d['precio']);
    if ($precio === null || $precio <= 0 || $precio >= 100000000) {
        $err['precio'] = 'Ingresá un precio válido mayor a cero.';
    } else {
        $d['precio'] = $precio;
    }

    $d['stock_minimo'] = entero($d['stock_minimo']);
    if ($d['stock_minimo'] < 0 || $d['stock_minimo'] > 100000) {
        $err['stock_minimo'] = 'Ingresá un número entero entre 0 y 100.000.';
    }

    if ($id === 0) {
        $d['stock'] = $d['stock'] === '' ? 0 : entero($d['stock']);
        if ($d['stock'] < 0 || $d['stock'] > 100000) {
            $err['stock'] = 'Ingresá un número entero entre 0 y 100.000.';
        }
    } else {
        unset($d['stock']);
    }

    return array($d, $err);
}

/** Alta o edición. En el alta, el stock inicial queda registrado como primer movimiento. */
function guardar_producto($d, $id, $usuario_id)
{
    $pdo = db();
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE productos SET codigo = ?, nombre = ?, categoria_id = ?, precio = ?, stock_minimo = ? WHERE id = ?');
        $stmt->execute(array($d['codigo'], $d['nombre'], $d['categoria_id'], $d['precio'], $d['stock_minimo'], $id));
        return $id;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO productos (codigo, nombre, categoria_id, precio, stock, stock_minimo) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($d['codigo'], $d['nombre'], $d['categoria_id'], $d['precio'], $d['stock'], $d['stock_minimo']));
        $id = (int) $pdo->lastInsertId();
        if ($d['stock'] > 0) {
            insertar_movimiento($id, $usuario_id, 'entrada', $d['stock'], $d['stock'], 'Stock inicial');
        }
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return $id;
}

/* ---------- Movimientos de stock ---------- */

function insertar_movimiento($producto_id, $usuario_id, $tipo, $cantidad, $stock_resultante, $motivo)
{
    $stmt = db()->prepare('INSERT INTO movimientos (producto_id, usuario_id, tipo, cantidad, stock_resultante, motivo, fecha)
                           VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(array($producto_id, $usuario_id, $tipo, $cantidad, $stock_resultante, $motivo, date('Y-m-d H:i:s')));
}

/**
 * Registra una entrada o salida dentro de una transacción.
 * La salida usa "UPDATE ... WHERE stock >= ?" para que dos operadores al mismo tiempo
 * nunca dejen el stock en negativo.
 * Devuelve array('ok' => true, 'stock' => int) o array('ok' => false, 'error' => string).
 */
function registrar_movimiento($producto_id, $tipo, $cantidad, $motivo, $usuario_id)
{
    if ($tipo !== 'entrada' && $tipo !== 'salida') {
        return array('ok' => false, 'error' => 'Tipo de movimiento inválido.');
    }
    if ($cantidad < 1 || $cantidad > 100000) {
        return array('ok' => false, 'error' => 'La cantidad debe ser un entero entre 1 y 100.000.');
    }
    $n = largo($motivo);
    if ($n < 3 || $n > 150) {
        return array('ok' => false, 'error' => 'Indicá un motivo (entre 3 y 150 caracteres).');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($tipo === 'entrada') {
            $stmt = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
            $stmt->execute(array($cantidad, $producto_id));
        } else {
            $stmt = $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $stmt->execute(array($cantidad, $producto_id, $cantidad));
        }

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            $producto = obtener_producto($producto_id);
            return array('ok' => false, 'error' => $producto === null
                ? 'El producto no existe.'
                : 'Stock insuficiente: hay ' . $producto['stock'] . ' unidades.');
        }

        $stock = (int) contar('SELECT stock FROM productos WHERE id = ?', array($producto_id));
        insertar_movimiento($producto_id, $usuario_id, $tipo, $cantidad, $stock, $motivo);
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    return array('ok' => true, 'stock' => $stock);
}

function filtro_movimientos($f)
{
    $where = array();
    $params = array();
    if ($f['texto'] !== '') {
        $where[] = "(p.codigo LIKE ? ESCAPE '!' OR p.nombre LIKE ? ESCAPE '!')";
        $like = escapar_like($f['texto']);
        array_push($params, $like, $like);
    }
    if ($f['tipo'] === 'entrada' || $f['tipo'] === 'salida') {
        $where[] = 'm.tipo = ?';
        $params[] = $f['tipo'];
    }
    if (fecha_valida($f['desde'])) {
        $where[] = 'm.fecha >= ?';
        $params[] = $f['desde'] . ' 00:00:00';
    }
    if (fecha_valida($f['hasta'])) {
        $where[] = 'm.fecha <= ?';
        $params[] = $f['hasta'] . ' 23:59:59';
    }
    return array($where ? ' WHERE ' . implode(' AND ', $where) : '', $params);
}

function fecha_valida($texto)
{
    $fecha = DateTime::createFromFormat('!Y-m-d', $texto);
    return $fecha && $fecha->format('Y-m-d') === $texto;
}

function sql_movimientos($where)
{
    return 'SELECT m.id, m.fecha, m.tipo, m.cantidad, m.stock_resultante, m.motivo,
                   p.codigo, p.nombre AS producto, u.nombre AS usuario
              FROM movimientos m
              JOIN productos p ON p.id = m.producto_id
              JOIN usuarios u ON u.id = m.usuario_id' . $where . '
             ORDER BY m.fecha DESC, m.id DESC';
}

function contar_movimientos($f)
{
    list($where, $params) = filtro_movimientos($f);
    return contar('SELECT COUNT(*) FROM movimientos m JOIN productos p ON p.id = m.producto_id' . $where, $params);
}

function buscar_movimientos($f, $pagina)
{
    list($where, $params) = filtro_movimientos($f);
    return consulta_paginada(sql_movimientos($where), $params, $pagina, POR_PAGINA);
}

/** Todos los movimientos filtrados, para exportar. Devuelve el PDOStatement para recorrerlo sin cargarlo en memoria. */
function movimientos_para_exportar($f)
{
    list($where, $params) = filtro_movimientos($f);
    $stmt = db()->prepare(sql_movimientos($where));
    $stmt->execute($params);
    return $stmt;
}
