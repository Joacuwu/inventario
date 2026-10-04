<?php
require_once dirname(__FILE__) . '/includes/inventario.php';
require_once dirname(__FILE__) . '/includes/layout.php';
requiere_admin();

$id = max(0, entero(isset($_POST['id']) && is_string($_POST['id']) ? $_POST['id'] : get('id')));
$errores = array();

try {
    $actual = $id > 0 ? obtener_producto($id) : null;
    if ($id > 0 && $actual === null) {
        flash('warning', 'El producto no existe.');
        redirigir('index.php');
    }
    $categorias = categorias();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificar_csrf();
        list($datos, $errores) = validar_producto($_POST, $id);
        if (!$errores) {
            $u = usuario_actual();
            guardar_producto($datos, $id, $u['id']);
            flash('success', $id > 0 ? 'Producto actualizado.' : 'Producto creado.');
            redirigir('index.php');
        }
    } else {
        $datos = $actual ? $actual : array('codigo' => '', 'nombre' => '', 'categoria_id' => 0, 'precio' => '', 'stock_minimo' => '0', 'stock' => '0');
    }
} catch (PDOException $ex) {
    error_interno($ex);
}

function clase($errores, $campo)
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}

function error_de($errores, $campo)
{
    return isset($errores[$campo]) ? '<div class="invalid-feedback">' . e($errores[$campo]) . '</div>' : '';
}

layout_inicio($id > 0 ? 'Editar producto' : 'Nuevo producto', 'stock');
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Stock</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?php echo $id > 0 ? 'Editar' : 'Nuevo'; ?></li>
    </ol>
</nav>

<div class="card shadow-sm" style="max-width: 680px">
    <div class="card-body">
        <h1 class="h4 mb-3"><?php echo $id > 0 ? 'Editar producto' : 'Nuevo producto'; ?></h1>
        <?php if ($errores): ?>
            <div class="alert alert-danger">Revisá los campos marcados.</div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?php echo csrf_campo(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row g-3">
                <div class="col-sm-4">
                    <label class="form-label" for="codigo">Código</label>
                    <input class="form-control<?php echo clase($errores, 'codigo'); ?>" id="codigo" name="codigo" maxlength="20"
                           placeholder="ELE-005" value="<?php echo e($datos['codigo']); ?>" required>
                    <?php echo error_de($errores, 'codigo'); ?>
                </div>
                <div class="col-sm-8">
                    <label class="form-label" for="nombre">Nombre</label>
                    <input class="form-control<?php echo clase($errores, 'nombre'); ?>" id="nombre" name="nombre" maxlength="100"
                           value="<?php echo e($datos['nombre']); ?>" required>
                    <?php echo error_de($errores, 'nombre'); ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="categoria_id">Categoría</label>
                    <select class="form-select<?php echo clase($errores, 'categoria_id'); ?>" id="categoria_id" name="categoria_id" required>
                        <option value="">Elegí una categoría</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === (int) $datos['categoria_id'] ? ' selected' : ''; ?>>
                                <?php echo e($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php echo error_de($errores, 'categoria_id'); ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="precio">Precio de venta</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text">$</span>
                        <input class="form-control<?php echo clase($errores, 'precio'); ?>" id="precio" name="precio" inputmode="decimal"
                               placeholder="15400,00" value="<?php echo e($datos['precio']); ?>" required>
                        <?php echo error_de($errores, 'precio'); ?>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="stock_minimo">Stock mínimo</label>
                    <input type="number" min="0" step="1" class="form-control<?php echo clase($errores, 'stock_minimo'); ?>" id="stock_minimo"
                           name="stock_minimo" value="<?php echo e($datos['stock_minimo']); ?>" required>
                    <div class="form-text">Por debajo de este número el producto se marca para reponer.</div>
                    <?php echo error_de($errores, 'stock_minimo'); ?>
                </div>
                <div class="col-sm-6">
                    <?php if ($id === 0): ?>
                        <label class="form-label" for="stock">Stock inicial</label>
                        <input type="number" min="0" step="1" class="form-control<?php echo clase($errores, 'stock'); ?>" id="stock"
                               name="stock" value="<?php echo e($datos['stock']); ?>">
                        <div class="form-text">Queda registrado como primer movimiento.</div>
                        <?php echo error_de($errores, 'stock'); ?>
                    <?php else: ?>
                        <label class="form-label">Stock actual</label>
                        <p class="form-control-plaintext fw-semibold"><?php echo (int) $actual['stock']; ?> unidades</p>
                        <div class="form-text">El stock solo cambia con entradas y salidas, para que quede todo registrado.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-success"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php layout_fin(); ?>
