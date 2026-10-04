<?php
function layout_inicio($titulo, $seccion = '')
{
    $u = usuario_actual();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($titulo); ?> · <?php echo e(APP_NOMBRE); ?></title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<?php if ($u): ?>
<nav class="navbar navbar-expand-md navbar-dark bg-success mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-box-seam"></i> <?php echo e(APP_NOMBRE); ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu"
                aria-expanded="false" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link<?php echo $seccion === 'stock' ? ' active' : ''; ?>" href="index.php">Stock</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $seccion === 'movimientos' ? ' active' : ''; ?>" href="movimientos.php">Movimientos</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3 text-white">
                <span><i class="bi bi-person-circle"></i> <?php echo e($u['nombre']); ?>
                    <span class="badge text-bg-light"><?php echo e($u['rol']); ?></span></span>
                <form action="logout.php" method="post" class="m-0">
                    <?php echo csrf_campo(); ?>
                    <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Salir</button>
                </form>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container pb-5">
    <?php echo mostrar_flash(); ?>
<?php
}

function layout_fin()
{
    ?>
</main>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}
