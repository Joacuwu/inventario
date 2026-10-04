<?php
require_once dirname(__FILE__) . '/includes/inventario.php';
require_once dirname(__FILE__) . '/includes/layout.php';
requiere_login();

$filtros = array(
    'texto' => get('buscar'),
    'tipo'  => get('tipo'),
    'desde' => get('desde'),
    'hasta' => get('hasta'),
);
$pagina = max(1, entero(get('pagina')));

try {
    $total   = contar_movimientos($filtros);
    $paginas = max(1, (int) ceil($total / POR_PAGINA));
    $pagina  = min($pagina, $paginas);
    $filas   = buscar_movimientos($filtros, $pagina);
} catch (PDOException $ex) {
    error_interno($ex);
}

$query = array('buscar' => $filtros['texto'], 'tipo' => $filtros['tipo'], 'desde' => $filtros['desde'], 'hasta' => $filtros['hasta']);

layout_inicio('Movimientos', 'movimientos');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 m-0">Movimientos de stock</h1>
    <a href="exportar.php?<?php echo e(http_build_query($query)); ?>" class="btn btn-outline-success">
        <i class="bi bi-filetype-csv"></i> Exportar a Excel (CSV)
    </a>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-md-4">
        <label class="form-label small" for="buscar">Producto</label>
        <input type="search" id="buscar" name="buscar" class="form-control" placeholder="Código o nombre" value="<?php echo e($filtros['texto']); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="tipo">Tipo</label>
        <select id="tipo" name="tipo" class="form-select">
            <option value="">Todos</option>
            <option value="entrada"<?php echo $filtros['tipo'] === 'entrada' ? ' selected' : ''; ?>>Entradas</option>
            <option value="salida"<?php echo $filtros['tipo'] === 'salida' ? ' selected' : ''; ?>>Salidas</option>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="desde">Desde</label>
        <input type="date" id="desde" name="desde" class="form-control" value="<?php echo e($filtros['desde']); ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label small" for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" class="form-control" value="<?php echo e($filtros['hasta']); ?>">
    </div>
    <div class="col-6 col-md-2 d-grid align-self-end">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
    </div>
</form>

<p class="text-body-secondary small"><?php echo $total; ?> movimiento<?php echo $total === 1 ? '' : 's'; ?></p>

<?php if (!$filas): ?>
    <div class="alert alert-light border">No hay movimientos con ese criterio.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white">
        <thead class="table-light">
        <tr>
            <th>Fecha</th><th>Producto</th><th>Tipo</th><th class="text-end">Cantidad</th>
            <th class="text-end d-none d-md-table-cell">Stock después</th><th class="d-none d-lg-table-cell">Motivo</th>
            <th class="d-none d-lg-table-cell">Usuario</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $m): $entrada = $m['tipo'] === 'entrada'; ?>
            <tr>
                <td class="text-nowrap"><?php echo e(date('d/m/Y H:i', strtotime($m['fecha']))); ?></td>
                <td><code><?php echo e($m['codigo']); ?></code> <?php echo e($m['producto']); ?></td>
                <td><span class="badge <?php echo $entrada ? 'text-bg-success' : 'text-bg-danger'; ?>"><?php echo $entrada ? 'Entrada' : 'Salida'; ?></span></td>
                <td class="text-end"><?php echo ($entrada ? '+' : '−') . (int) $m['cantidad']; ?></td>
                <td class="text-end d-none d-md-table-cell"><?php echo (int) $m['stock_resultante']; ?></td>
                <td class="d-none d-lg-table-cell"><?php echo e($m['motivo']); ?></td>
                <td class="d-none d-lg-table-cell"><?php echo e($m['usuario']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($paginas > 1): ?>
<nav aria-label="Páginas">
    <ul class="pagination pagination-sm justify-content-center flex-wrap">
        <?php for ($n = 1; $n <= $paginas; $n++): ?>
            <li class="page-item<?php echo $n === $pagina ? ' active' : ''; ?>">
                <a class="page-link" href="movimientos.php?<?php echo e(http_build_query($query + array('pagina' => $n))); ?>"><?php echo $n; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php layout_fin(); ?>
