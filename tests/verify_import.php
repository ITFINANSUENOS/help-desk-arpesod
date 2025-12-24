<?php
// Script de Prueba de Integración para Importación V2

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Subcategoria.php';
require_once __DIR__ . '/../models/Cargo.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 1. Preparar Datos de Prueba
$subcat_name = "SubCategoria Test Import V2";
$flujo_name = "Flujo Test Import V2";

// Asegurar que exista la subcategoría en BD (o el script fallará)
$conectar = Conectar::getConexion();

// 1.1 Asegurar Categoría 'Soporte'
$sql = "SELECT cat_id FROM tm_categoria WHERE cat_nom = 'Soporte'";
$stmt = $conectar->prepare($sql);
$stmt->execute();
$cat_test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cat_test) {
    $sql = "INSERT INTO tm_categoria (cat_nom, est) VALUES ('Soporte', 1)";
    $conectar->prepare($sql)->execute();
    echo "Categoría 'Soporte' creada.\n";
}

// 1.2 Asegurar Prioridad 'Alta'
$sql = "SELECT pd_id FROM td_prioridad WHERE pd_nom = 'Alta'";
$stmt = $conectar->prepare($sql);
$stmt->execute();
$pd_test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pd_test) {
    $sql = "INSERT INTO td_prioridad (pd_nom, est) VALUES ('Alta', 1)";
    $conectar->prepare($sql)->execute();
    echo "Prioridad 'Alta' creada.\n";
}

// 1.3 Asegurar Subcategoría (usando ID de cat Soporte)
// Buscamos de nuevo cat_id
$sql = "SELECT cat_id FROM tm_categoria WHERE cat_nom = 'Soporte'";
$stmt = $conectar->prepare($sql);
$stmt->execute();
$cat_id_soporte = $stmt->fetchColumn();

$sub = new Subcategoria();
$sub_id = $sub->get_id_por_nombre($subcat_name);
if (!$sub_id) {
    $sql = "INSERT INTO tm_subcategoria (cat_id, cats_nom, est) VALUES (?, ?, 1)";
    $stmt = $conectar->prepare($sql);
    $stmt->execute([$cat_id_soporte, $subcat_name]);
    echo "Subcategoría de prueba creada.\n";
} else {
    echo "Subcategoría de prueba ya existe.\n";
}

// 2. Crear Archivo Excel Dummy
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados (Solo para referencia visual, el script salta la fila 1)
$sheet->setCellValue('A1', 'CAT');
$sheet->setCellValue('B1', 'SUBCAT');
$sheet->setCellValue('C1', 'PRIORIDAD'); // Nuevo
$sheet->setCellValue('D1', 'FLUJO');
$sheet->setCellValue('H1', 'ORDEN');
$sheet->setCellValue('I1', 'PASO');
$sheet->setCellValue('J1', 'RESP');
$sheet->setCellValue('K1', 'DESC');
$sheet->setCellValue('L1', 'TIPO');
$sheet->setCellValue('M1', 'COND');
$sheet->setCellValue('N1', 'ACCION');
$sheet->setCellValue('O1', 'DETALLE');

// Fila 2: Paso 1 (Lineal)
$row = 2;
$sheet->setCellValue('A' . $row, 'Soporte'); // Cat Existente
$sheet->setCellValue('B' . $row, $subcat_name);
$sheet->setCellValue('C' . $row, 'Alta'); // Prioridad Existente (Ejemplo)
$sheet->setCellValue('D' . $row, $flujo_name);
$sheet->setCellValue('H' . $row, '1');
$sheet->setCellValue('I' . $row, 'Paso Uno');
$sheet->setCellValue('J' . $row, 'Rol: Administrador');
$sheet->setCellValue('K' . $row, 'Descripcion 1');
$sheet->setCellValue('L' . $row, 'Normal');
$sheet->setCellValue('N' . $row, 'Continuar');
$sheet->setCellValue('O' . $row, 'Siguiente: Paso 2');

// Fila 3: Paso 2 (Decisión) - Simulamos Merged Cells (Subcat, Prioridad y Flujo Vacíos)
$row = 3;
$sheet->setCellValue('A' . $row, ''); // Merged
$sheet->setCellValue('B' . $row, ''); // Merged
$sheet->setCellValue('C' . $row, ''); // Merged
$sheet->setCellValue('D' . $row, ''); // Merged
$sheet->setCellValue('H' . $row, '2');
$sheet->setCellValue('I' . $row, 'Paso Dos Decision');
$sheet->setCellValue('J' . $row, 'Rol: Administrador');
$sheet->setCellValue('K' . $row, 'Decide SI o NO');
$sheet->setCellValue('L' . $row, 'Normal');
$sheet->setCellValue('M' . $row, 'SI');
$sheet->setCellValue('N' . $row, 'Ir a Paso');
$sheet->setCellValue('O' . $row, 'Paso 3: Final SI');

// Fila 4: Paso 2 (Decisión rama NO) -> Fin - Simulamos Merged Cells
$row = 4;
$sheet->setCellValue('A' . $row, '');
$sheet->setCellValue('B' . $row, '');
$sheet->setCellValue('C' . $row, '');
$sheet->setCellValue('D' . $row, '');
$sheet->setCellValue('H' . $row, '2');
$sheet->setCellValue('I' . $row, 'Paso Dos Decision');
$sheet->setCellValue('J' . $row, 'Rol: Administrador');
$sheet->setCellValue('K' . $row, 'Decide SI o NO');
$sheet->setCellValue('L' . $row, 'Normal');
$sheet->setCellValue('M' . $row, 'NO');
$sheet->setCellValue('N' . $row, 'Fin');
$sheet->setCellValue('O' . $row, 'Fin del Flujo');

// Fila 5: Paso 3 (Final SI) - Simulamos Merged Cells
$row = 5;
$sheet->setCellValue('A' . $row, '');
$sheet->setCellValue('B' . $row, '');
$sheet->setCellValue('C' . $row, '');
$sheet->setCellValue('D' . $row, '');
$sheet->setCellValue('H' . $row, '3');
$sheet->setCellValue('I' . $row, 'Paso Tres Final');
$sheet->setCellValue('J' . $row, 'Rol: Administrador');
$sheet->setCellValue('K' . $row, 'Fin positivo');
$sheet->setCellValue('L' . $row, 'Normal');
$sheet->setCellValue('N' . $row, 'Fin');
$sheet->setCellValue('O' . $row, 'Fin');

// Fila 6: Row inválido (Sin subcat/flujo)
$row = 6;
$sheet->setCellValue('H' . $row, '4');
$sheet->setCellValue('I' . $row, 'Paso Invalido');
// Dejamos A-D vacíos

// Fila 7: Row inválido (Acción: Pasos de Ruta)
$row = 7;
$sheet->setCellValue('A' . $row, 'Soporte');
$sheet->setCellValue('B' . $row, $subcat_name);
$sheet->setCellValue('C' . $row, 'Alta');
$sheet->setCellValue('D' . $row, $flujo_name);
$sheet->setCellValue('H' . $row, '4');
$sheet->setCellValue('N' . $row, 'Pasos de Ruta');

// Fila 8: TEST AUTO-CREACIÓN SUBCATEGORÍA
// Usamos nombre nuevo, Categoría existente 'Soporte' y Prioridad 'Alta' (o la que exista en BD)
$row = 8;
$new_subcat = "SubCat AutoCreada " . rand(100, 999);
$sheet->setCellValue('A' . $row, 'Soporte'); // Debe existir
$sheet->setCellValue('B' . $row, $new_subcat);
$sheet->setCellValue('C' . $row, 'Alta'); // Debe existir en td_prioridad
$sheet->setCellValue('D' . $row, "Flujo Auto " . rand(100, 999));
$sheet->setCellValue('H' . $row, '1');
$sheet->setCellValue('I' . $row, 'Paso Unico');
$sheet->setCellValue('J' . $row, 'Rol: Administrador');
$sheet->setCellValue('N' . $row, 'Fin');

$writer = new Xlsx($spreadsheet);
$temp_file = __DIR__ . '/temp_test_import.xlsx';
$writer->save($temp_file);

// 3. Mockear $_FILES
$_FILES = [
    'archivo_flujos' => [
        'name' => 'temp_test_import.xlsx',
        'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'tmp_name' => $temp_file,
        'error' => 0,
        'size' => filesize($temp_file)
    ]
];

// Mockear $_POST si fuera necesario (no lo es para V2 script)
$_POST = [];

echo "Ejecutando script de importación...\n";

// Capturar salida
ob_start();
require __DIR__ . '/../cargues/import_reporte_flujos_v2.php';
$output = ob_get_clean();

echo "Salida del importador:\n";
echo stripe_tags_custom($output) . "\n"; // Función simple para limpiar HTML visualmente

// Limpiar archivo
unlink($temp_file);

// Verificación básica en texto
if (strpos($output, '¡Importación Finalizada!') !== false) {
    echo "\nTEST PASS: Importación reportó éxito.\n";
} else {
    echo "\nTEST FAIL: Importación no reportó éxito.\n";
}

function stripe_tags_custom($text)
{
    return strip_tags(str_replace(['<br>', '<p>', '<li>'], ["\n", "\n", "\n- "], $text));
}
