<?php
require_once dirname(__FILE__) . '/includes/inventario.php';
require_once dirname(__FILE__) . '/includes/layout.php';
requiere_login();

$buscar    = get('buscar');
$categoria = max(0, entero(get('categoria')));
$solo_bajo = get('bajo') === '1';
$pagina    = max(1, entero(get('pagina')));

try {
    $resumen    = resumen_inventario();
    $categorias = categorias();
    $total      = contar_productos($buscar, $categoria, $solo_bajo);
    $paginas    = max(1, (int) ceil($total / POR_PAGINA));
    $pagina     = min($pagina, $paginas);
    $filas      = buscar_productos($buscar, $categoria, $solo_bajo, $pagina);
} catch (PDOException $ex) {
    error_interno($ex);
}

function url_pagina($n, $buscar, $categoria, $solo_bajo)
{
    return 'index.php?' . http_build_query(array(
        'buscar' => $buscar, 'categoria' => $categoria ?: '', 'bajo' => $solo_bajo ? '1' : '', 'pagina' => $n,
    ));
}

layout_inicio('Stock', 'stock');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Productos</div>
            <div class="fs-4 fw-semibold"><?php echo (int) $resumen['productos']; ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Unidades en depósito</div>
            <div class="fs-4 fw-semibold" id="totalUnidades"><?php echo number_format($resumen['unidades'], 0, ',', '.'); ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Valor del stock</div>
            <div class="fs-4 fw-semibold">$ <?php echo e(pesos($resumen['valor'])); ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <a class="card h-100 text-decoration-none border-warning" href="index.php?bajo=1">
            <div class="card-body">
                <div class="text-body-secondary small">Bajo el mínimo</div>
                <div class="fs-4 fw-semibold text-warning-emphasis"><?php echo (int) $resumen['bajo_minimo']; ?>
                    <small class="fs-6 text-danger">(<?php echo (int) $resumen['sin_stock']; ?> sin stock)</small></div>
            </div>
        </a>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 m-0">Stock</h1>
    <?php if (es_admin()): ?>
        <a href="producto.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Nuevo producto</a>
    <?php endif; ?>
</div>

<form method="get" class="row g-2 align-items-center mb-3" role="search">
    <div class="col-12 col-md-5">
        <input type="search" name="buscar" class="form-control" placeholder="Buscar por código o nombre" value="<?php echo e($buscar); ?>">
    </div>
    <div class="col-7 col-md-3">
        <select name="categoria" class="form-select">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === $categoria ? ' selected' : ''; ?>><?php echo e($c['nombre']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-5 col-md-2">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="bajo" value="1" id="bajo"<?php echo $solo_bajo ? ' checked' : ''; ?>>
            <label class="form-check-label" for="bajo">Bajo mínimo</label>
        </div>
    </div>
    <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Buscar</button>
    </div>
</form>

<?php if (!$filas): ?>
    <div class="alert alert-light border">No hay productos con ese criterio.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead class="table-light">
        <tr>
            <th>Código</th><th>Producto</th><th class="d-none d-lg-table-cell">Categoría</th>
            <th class="text-end d-none d-sm-table-cell">Precio</th><th class="text-center">Stock</th><th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $f):
            $bajo = (int) $f['stock'] <= (int) $f['stock_minimo']; ?>
            <tr id="producto-<?php echo (int) $f['id']; ?>" data-minimo="<?php echo (int) $f['stock_minimo']; ?>">
                <td><code><?php echo e($f['codigo']); ?></code></td>
                <td><?php echo e($f['nombre']); ?></td>
                <td class="d-none d-lg-table-cell"><?php echo e($f['categoria']); ?></td>
                <td class="text-end d-none d-sm-table-cell">$ <?php echo e(pesos($f['precio'])); ?></td>
                <td class="text-center">
                    <span class="badge fs-6 js-stock <?php echo $bajo ? ((int) $f['stock'] === 0 ? 'text-bg-danger' : 'text-bg-warning') : 'text-bg-light border'; ?>"
                          title="Mínimo: <?php echo (int) $f['stock_minimo']; ?>"><?php echo (int) $f['stock']; ?></span>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-success js-movimiento" title="Registrar entrada"
                            data-id="<?php echo (int) $f['id']; ?>" data-tipo="entrada" data-nombre="<?php echo e($f['nombre']); ?>">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger js-movimiento" title="Registrar salida"
                            data-id="<?php echo (int) $f['id']; ?>" data-tipo="salida" data-nombre="<?php echo e($f['nombre']); ?>">
                        <i class="bi bi-box-arrow-up"></i>
                    </button>
                    <?php if (es_admin()): ?>
                        <a href="producto.php?id=<?php echo (int) $f['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($paginas > 1): ?>
<nav aria-label="Páginas">
    <ul class="pagination justify-content-center">
        <?php for ($n = 1; $n <= $paginas; $n++): ?>
            <li class="page-item<?php echo $n === $pagina ? ' active' : ''; ?>">
                <a class="page-link" href="<?php echo e(url_pagina($n, $buscar, $categoria, $solo_bajo)); ?>"><?php echo $n; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<!-- Entrada o salida de stock: se envía por Ajax y actualiza la fila sin recargar. -->
<div class="modal fade" id="modalMovimiento" tabindex="-1" aria-labelledby="modalMovimientoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="formMovimiento" action="api/movimiento.php" method="post" novalidate>
            <?php echo csrf_campo(); ?>
            <input type="hidden" name="producto_id">
            <input type="hidden" name="tipo">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalMovimientoTitulo">Movimiento</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong class="js-mov-producto"></strong></p>
                <div class="alert alert-danger d-none js-mov-error" role="alert"></div>
                <div class="mb-3">
                    <label class="form-label" for="cantidad">Cantidad</label>
                    <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" max="100000" step="1" required>
                </div>
                <div>
                    <label class="form-label" for="motivo">Motivo</label>
                    <input class="form-control" id="motivo" name="motivo" maxlength="150" list="motivos" required>
                    <datalist id="motivos">
                        <option value="Compra a proveedor">
                        <option value="Venta mostrador">
                        <option value="Devolución de cliente">
                        <option value="Rotura o pérdida">
                    </datalist>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success js-mov-guardar">Registrar</button>
            </div>
        </form>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toastOk" class="toast text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
    </div>
</div>
<?php layout_fin(); ?>
