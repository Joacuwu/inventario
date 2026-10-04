<?php
/**
 * Exporta los movimientos filtrados a CSV para abrir en Excel.
 * Usa ";" como separador y BOM UTF-8, que es lo que espera Excel con configuración regional argentina.
 */
require_once dirname(__FILE__) . '/includes/inventario.php';
requiere_login();

$filtros = array(
    'texto' => get('buscar'),
    'tipo'  => get('tipo'),
    'desde' => get('desde'),
    'hasta' => get('hasta'),
);

/** Evita que Excel interprete como fórmula un texto que empieza con =, +, - o @ (inyección CSV). */
function celda_segura($valor)
{
    $valor = (string) $valor;
    return $valor !== '' && strpos('=+-@', $valor[0]) !== false ? "'" . $valor : $valor;
}

try {
    $stmt = movimientos_para_exportar($filtros);
} catch (PDOException $ex) {
    error_interno($ex);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="movimientos-' . date('Y-m-d') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, array('Fecha', 'Código', 'Producto', 'Tipo', 'Cantidad', 'Stock después', 'Motivo', 'Usuario'), ';');
while ($m = $stmt->fetch()) {
    fputcsv($salida, array(
        date('d/m/Y H:i', strtotime($m['fecha'])),
        celda_segura($m['codigo']),
        celda_segura($m['producto']),
        $m['tipo'] === 'entrada' ? 'Entrada' : 'Salida',
        (int) $m['cantidad'],
        (int) $m['stock_resultante'],
        celda_segura($m['motivo']),
        celda_segura($m['usuario']),
    ), ';');
}
fclose($salida);
