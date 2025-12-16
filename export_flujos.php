<?php
require 'vendor/autoload.php';
require_once("config/conexion.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

$conectar = Conectar::getConexion();

// 1. Obtener datos principales: Subcategorias con Flujos y Reglas de Mapeo
$sql = "SELECT 
            s.cats_id, s.cats_nom,
            c.cat_nom,
            f.flujo_id, f.flujo_nom,
            rm.regla_id
        FROM tm_subcategoria s
        INNER JOIN tm_categoria c ON s.cat_id = c.cat_id
        INNER JOIN tm_flujo f ON s.cats_id = f.cats_id
        LEFT JOIN tm_regla_mapeo rm ON s.cats_id = rm.cats_id AND rm.est = 1
        WHERE s.est = 1 AND c.est = 1 AND f.est = 1
        ORDER BY c.cat_nom, s.cats_nom, f.flujo_nom";

$stmt = $conectar->prepare($sql);
$stmt->execute();
$flujos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crear Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte de Flujos Detallado');

// Encabezados
$headers = [
    'CATEGORÍA',
    'SUBCATEGORÍA',
    'FLUJO',
    'QUIÉN CREA (CARGOS)',
    'QUIÉN CREA (PERFILES)',
    'ASIGNADO INICIAL A',
    'ORDEN',
    'PASO',
    'DESCRIPCIÓN',
    'TIPO',
    'CONDICIÓN',
    'DESTINO/SIGUIENTE'
];

$col = 'A';
foreach ($headers as $header) {
    // Ancho fijo inicial, luego ajustaremos
    $sheet->getColumnDimension($col)->setWidth(20);
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// Estilo Encabezados
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']], // Azul oscuro
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => 'FFFFFF']]]
];
$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

$row = 2;
$flowColorToggle = false;

foreach ($flujos as $flujo) {
    $flujo_id = $flujo['flujo_id'];
    $cats_id = $flujo['cats_id'];
    $regla_id = $flujo['regla_id'];

    // -- Obtener Info de Mapeo (Quién crea y a quién se asigna) --
    $creadores_cargos = "";
    $creadores_perfiles = "";
    $asignados = "";

    if ($regla_id) {
        // Creadores Cargos
        $sql_cc = "SELECT c.car_nom FROM regla_creadores rc 
                   JOIN tm_cargo c ON rc.creador_car_id = c.car_id 
                   WHERE rc.regla_id = ?";
        $stmt_cc = $conectar->prepare($sql_cc);
        $stmt_cc->execute([$regla_id]);
        $cc_arr = $stmt_cc->fetchAll(PDO::FETCH_COLUMN);
        $creadores_cargos = implode(",\n", $cc_arr);

        // Creadores Perfiles
        $sql_cp = "SELECT p.per_nom FROM regla_creadores_perfil rcp 
                   JOIN tm_perfil p ON rcp.creator_per_id = p.per_id 
                   WHERE rcp.regla_id = ? AND rcp.est = 1";
        $stmt_cp = $conectar->prepare($sql_cp);
        $stmt_cp->execute([$regla_id]);
        $cp_arr = $stmt_cp->fetchAll(PDO::FETCH_COLUMN);
        $creadores_perfiles = implode(",\n", $cp_arr);

        // Asignados
        $sql_as = "SELECT c.car_nom FROM regla_asignados ra 
                   JOIN tm_cargo c ON ra.asignado_car_id = c.car_id 
                   WHERE ra.regla_id = ?";
        $stmt_as = $conectar->prepare($sql_as);
        $stmt_as->execute([$regla_id]);
        $as_arr = $stmt_as->fetchAll(PDO::FETCH_COLUMN);
        $asignados = implode(",\n", $as_arr);
    } else {
        $creadores_cargos = "Sin regla definida";
        $asignados = "Sin regla definida";
    }

    // -- Obtener Pasos --
    $sql_pasos = "SELECT * FROM tm_flujo_paso WHERE flujo_id = ? AND est = 1 ORDER BY paso_orden ASC";
    $stmt_pasos = $conectar->prepare($sql_pasos);
    $stmt_pasos->execute([$flujo_id]);
    $pasos = $stmt_pasos->fetchAll(PDO::FETCH_ASSOC);

    // Preparar filas para este flujo
    $flujo_rows = [];

    if (count($pasos) == 0) {
        $flujo_rows[] = [
            'orden' => '-',
            'paso' => 'Sin pasos configurados',
            'desc' => '',
            'tipo' => '',
            'cond' => '',
            'dest' => ''
        ];
    } else {
        foreach ($pasos as $paso) {
            $paso_id = $paso['paso_id'];
            $tipo_paso = "Normal";
            if ($paso['permite_cerrar']) $tipo_paso = "Cierre";

            // Buscar transiciones
            $sql_trans = "SELECT 
                            t.condicion_nombre,
                            pd.paso_nombre AS paso_destino,
                            pd.paso_orden AS orden_destino,
                            r.ruta_id,
                            r.ruta_nombre
                          FROM tm_flujo_transiciones t
                          LEFT JOIN tm_flujo_paso pd ON t.paso_destino_id = pd.paso_id
                          LEFT JOIN tm_ruta r ON t.ruta_id = r.ruta_id
                          WHERE t.paso_origen_id = ? AND t.est = 1";

            $stmt_trans = $conectar->prepare($sql_trans);
            $stmt_trans->execute([$paso_id]);
            $transiciones = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);

            if (count($transiciones) > 0) {
                // Es un paso con decisiones
                foreach ($transiciones as $trans) {
                    $destino = "";
                    if (!empty($trans['paso_destino'])) {
                        $destino = "Ir a Paso " . $trans['orden_destino'] . ": " . $trans['paso_destino'];
                        $flujo_rows[] = [
                            'orden' => $paso['paso_orden'],
                            'paso' => $paso['paso_nombre'],
                            'desc' => strip_tags($paso['paso_descripcion']),
                            'tipo' => $tipo_paso,
                            'cond' => $trans['condicion_nombre'],
                            'dest' => $destino,
                            'is_decision' => true
                        ];
                    } elseif (!empty($trans['ruta_nombre'])) {
                        // === EXPANSION DE RUTA ===
                        $destino = "Ir a Ruta: " . $trans['ruta_nombre'];

                        // Agregar el paso que lleva a la ruta
                        $flujo_rows[] = [
                            'orden' => $paso['paso_orden'],
                            'paso' => $paso['paso_nombre'],
                            'desc' => strip_tags($paso['paso_descripcion']),
                            'tipo' => $tipo_paso,
                            'cond' => $trans['condicion_nombre'],
                            'dest' => $destino,
                            'is_decision' => true
                        ];

                        // Obtener los pasos de la ruta
                        $ruta_id = $trans['ruta_id'];
                        $sql_ruta_pasos = "SELECT rp.orden, fp.paso_nombre, fp.paso_descripcion 
                                           FROM tm_ruta_paso rp 
                                           JOIN tm_flujo_paso fp ON rp.paso_id = fp.paso_id 
                                           WHERE rp.ruta_id = ? AND rp.est = 1 
                                           ORDER BY rp.orden ASC";
                        $stmt_rp = $conectar->prepare($sql_ruta_pasos);
                        $stmt_rp->execute([$ruta_id]);
                        $pasos_ruta = $stmt_rp->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($pasos_ruta as $pr) {
                            $flujo_rows[] = [
                                'orden' => "-> R." . $pr['orden'], // Indicar que es paso de ruta
                                'paso' => "  [Ruta: " . $trans['ruta_nombre'] . "] " . $pr['paso_nombre'], // Indentación visual
                                'desc' => strip_tags($pr['paso_descripcion']),
                                'tipo' => "Paso de Ruta",
                                'cond' => "Parte de Ruta",
                                'dest' => "Siguiente paso en ruta o fin",
                                'is_route_step' => true
                            ];
                        }
                    } else {
                        // Caso transición simple sin destino claro (raro, pero posible si borraron paso destino)
                        $flujo_rows[] = [
                            'orden' => $paso['paso_orden'],
                            'paso' => $paso['paso_nombre'],
                            'desc' => strip_tags($paso['paso_descripcion']),
                            'tipo' => $tipo_paso,
                            'cond' => $trans['condicion_nombre'],
                            'dest' => "Destino no encontrado",
                            'is_decision' => true
                        ];
                    }
                }
            } else {
                // Lineal (sin decisiones explícitas)
                $sql_next = "SELECT paso_nombre, paso_orden FROM tm_flujo_paso 
                             WHERE flujo_id = ? AND paso_orden > ? AND est = 1 
                             ORDER BY paso_orden ASC LIMIT 1";
                $stmt_next = $conectar->prepare($sql_next);
                $stmt_next->execute([$flujo_id, $paso['paso_orden']]);
                $next = $stmt_next->fetch(PDO::FETCH_ASSOC);

                $destino = $next ? "Siguiente: Paso " . $next['paso_orden'] . " (" . $next['paso_nombre'] . ")" : "FIN DEL FLUJO";

                $flujo_rows[] = [
                    'orden' => $paso['paso_orden'],
                    'paso' => $paso['paso_nombre'],
                    'desc' => strip_tags($paso['paso_descripcion']),
                    'tipo' => $tipo_paso,
                    'cond' => "N/A",
                    'dest' => $destino,
                    'is_decision' => false
                ];
            }
        }
    }

    // -- Escribir en Excel y Unir Celdas --
    $startRow = $row;

    // Color de fondo para este flujo (alternar)
    $bgColor = $flowColorToggle ? 'F2F2F2' : 'FFFFFF'; // Gris claro o Blanco
    $flowColorToggle = !$flowColorToggle;

    foreach ($flujo_rows as $fdata) {
        // Datos comunes (columnas A-F)
        $sheet->setCellValue('A' . $row, $flujo['cat_nom']);
        $sheet->setCellValue('B' . $row, $flujo['cats_nom']);
        $sheet->setCellValue('C' . $row, $flujo['flujo_nom']);
        $sheet->setCellValue('D' . $row, $creadores_cargos);
        $sheet->setCellValue('E' . $row, $creadores_perfiles);
        $sheet->setCellValue('F' . $row, $asignados);

        // Datos del paso (G-L)
        $sheet->setCellValue('G' . $row, $fdata['orden']);
        $sheet->setCellValue('H' . $row, $fdata['paso']);
        $sheet->setCellValue('I' . $row, $fdata['desc']);
        $sheet->setCellValue('J' . $row, $fdata['tipo']);
        $sheet->setCellValue('K' . $row, $fdata['cond']);
        $sheet->setCellValue('L' . $row, $fdata['dest']);

        // Estilos condicionales
        if (isset($fdata['is_decision']) && $fdata['is_decision']) {
            $sheet->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C'); // Amarillo/Naranja claro para condiciones
            $sheet->getStyle('K' . $row)->getFont()->getColor()->setARGB('9C5700');
        }

        // Estilo diferente para pasos de ruta
        if (isset($fdata['is_route_step']) && $fdata['is_route_step']) {
            $sheet->getStyle('G' . $row . ':L' . $row)->getFont()->setItalic(true);
            $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB('555555'); // Gris oscuro
        }

        // Estilo general de la fila
        $rowStyle = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
        ];
        $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray($rowStyle);

        $row++;
    }

    $endRow = $row - 1;

    // Unir celdas de las columnas comunes si hay más de 1 fila
    if ($endRow > $startRow) {
        $sheet->mergeCells("A{$startRow}:A{$endRow}");
        $sheet->mergeCells("B{$startRow}:B{$endRow}");
        $sheet->mergeCells("C{$startRow}:C{$endRow}");
        $sheet->mergeCells("D{$startRow}:D{$endRow}");
        $sheet->mergeCells("E{$startRow}:E{$endRow}");
        $sheet->mergeCells("F{$startRow}:F{$endRow}");
    }

    // Borde inferior fuerte para separar flujos completamente
    $sheet->getStyle("A{$endRow}:L{$endRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->setColor(new Color('000000'));
}

// Auto-size final adjustment (limited width)
foreach (range('A', 'L') as $colID) {
    if (in_array($colID, ['D', 'E', 'F', 'I', 'L'])) {
        $sheet->getColumnDimension($colID)->setWidth(35); // Más ancho para textos largos
    } else {
        $sheet->getColumnDimension($colID)->setAutoSize(true);
    }
}

// -- WEB DOWNLOAD HEADERS --
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="reporte_flujos_completo.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
