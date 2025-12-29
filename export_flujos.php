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

// Helper para limpiar HTML y preservar saltos de línea
function cleanHtml($html)
{
    if (empty($html)) return "";

    // Reemplazar saltos de línea HTML por reales
    $html = str_ireplace(['<br />', '<br>', '<br/>'], "\n", $html);
    $html = str_ireplace(['</p>', '</div>'], "\n", $html);

    // Decodificar entidades y limpiar tags
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Convertir &nbsp; a espacio normal
    $text = str_replace("\xc2\xa0", ' ', $text); 

    return trim($text);
}

// 1. Obtener datos principales: Subcategorias con Flujos y Reglas de Mapeo
$sql = "SELECT 
            s.cats_id, s.cats_nom, s.cats_descrip,
            c.cat_nom,
            p.pd_nom,
            f.flujo_id, f.flujo_nom,
            rm.regla_id
        FROM tm_subcategoria s
        INNER JOIN tm_categoria c ON s.cat_id = c.cat_id
        LEFT JOIN td_prioridad p ON s.pd_id = p.pd_id
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
    'PRIORIDAD',
    'FLUJO',
    'QUIÉN CREA (CARGOS)',
    'QUIÉN CREA (PERFILES)',
    'ASIGNADO INICIAL A',
    'ORDEN',
    'PASO',
    'RESPONSABLES',
    'DESCRIPCIÓN',
    'TIPO',
    'CONDICIÓN',
    'ACCIÓN TRANSICIÓN',
    'DETALLE DESTINO'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->getColumnDimension($col)->setWidth(20);
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// Estilo Encabezados
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => 'FFFFFF']]]
];
$sheet->getStyle('A1:N1')->applyFromArray($headerStyle);
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
        $sql_cc = "SELECT c.car_nom FROM regla_creadores rc 
                   JOIN tm_cargo c ON rc.creador_car_id = c.car_id 
                   WHERE rc.regla_id = ?";
        $stmt_cc = $conectar->prepare($sql_cc);
        $stmt_cc->execute([$regla_id]);
        $cc_arr = $stmt_cc->fetchAll(PDO::FETCH_COLUMN);
        $creadores_cargos = implode(",\n", $cc_arr);

        $sql_cp = "SELECT p.per_nom FROM regla_creadores_perfil rcp 
                   JOIN tm_perfil p ON rcp.creator_per_id = p.per_id 
                   WHERE rcp.regla_id = ? AND rcp.est = 1";
        $stmt_cp = $conectar->prepare($sql_cp);
        $stmt_cp->execute([$regla_id]);
        $cp_arr = $stmt_cp->fetchAll(PDO::FETCH_COLUMN);
        $creadores_perfiles = implode(",\n", $cp_arr);

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

    $flujo_rows = [];

    // --- PASO 0: Creación del Ticket (Descripción de Subcategoría) ---
    // Usamos la descripción de la subcategoría si existe
    if (!empty($flujo['cats_descrip'])) {
        $flujo_rows[] = [
            'orden' => '0',
            'paso' => 'Creación del Ticket',
            'resp' => "Creador (Usuario)",
            'desc' => cleanHtml($flujo['cats_descrip']),
            'tipo' => 'Inicio',
            'cond' => 'N/A',
            'accion' => 'Inicio',
            'dest' => 'Siguiente: Paso 1'
        ];
    }


    // -- Obtener Pasos del Flujo --
    $sql_pasos = "SELECT fp.*, c.car_nom AS cargo_asignado 
                  FROM tm_flujo_paso fp
                  LEFT JOIN tm_cargo c ON fp.cargo_id_asignado = c.car_id
                  WHERE fp.flujo_id = ? AND fp.est = 1 
                  ORDER BY fp.paso_orden ASC";
    $stmt_pasos = $conectar->prepare($sql_pasos);
    $stmt_pasos->execute([$flujo_id]);
    $pasos = $stmt_pasos->fetchAll(PDO::FETCH_ASSOC);


    if (count($pasos) == 0) {
        $flujo_rows[] = [
            'orden' => '-',
            'paso' => 'Sin pasos configurados',
            'resp' => '',
            'desc' => '',
            'tipo' => '',
            'cond' => '',
            'accion' => '',
            'dest' => ''
        ];
    } else {
        foreach ($pasos as $paso) {
            $paso_id = $paso['paso_id'];
            $tipo_paso = "Normal";
            if ($paso['permite_cerrar']) $tipo_paso = "Cierre";

            // --- OBTENER RESPONSABLES (SOLO ROLES) ---
            $responsables_arr = [];

            // 1. Cargo principal
            if (!empty($paso['cargo_asignado'])) {
                $responsables_arr[] = "Rol: " . $paso['cargo_asignado'];
            }

            // 2. Cargos Adicionales Específicos
            $sql_car_add = "SELECT c.car_nom, pu.car_id 
                            FROM tm_flujo_paso_usuarios pu
                            LEFT JOIN tm_cargo c ON pu.car_id = c.car_id
                            WHERE pu.paso_id = ? AND pu.car_id IS NOT NULL";
            $stmt_car_add = $conectar->prepare($sql_car_add);
            $stmt_car_add->execute([$paso_id]);
            $cargos_add = $stmt_car_add->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cargos_add as $ca) {
                if ($ca['car_id'] == -1) {
                    $responsables_arr[] = "Rol: Jefe Inmediato";
                } elseif (!empty($ca['car_nom'])) {
                    $responsables_arr[] = "Rol Add: " . $ca['car_nom'];
                }
            }

            // Eliminamos duplicados y unimos
            $responsables_arr = array_unique($responsables_arr);
            $responsables_str = implode("\n", $responsables_arr);


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

            // Limpiar descripción
            $desc_paso = cleanHtml($paso['paso_descripcion']);

            if (count($transiciones) > 0) {
                // Es un paso con decisiones
                foreach ($transiciones as $trans) {
                    $destino = "";
                    if (!empty($trans['paso_destino'])) {
                        $accion = "Ir a Paso";
                        $destino = "Paso " . $trans['orden_destino'] . ": " . $trans['paso_destino'];
                        $flujo_rows[] = [
                            'orden' => $paso['paso_orden'],
                            'paso' => $paso['paso_nombre'],
                            'resp' => $responsables_str,
                            'desc' => $desc_paso,
                            'tipo' => $tipo_paso,
                            'cond' => $trans['condicion_nombre'],
                            'accion' => $accion,
                            'dest' => $destino,
                            'is_decision' => true
                        ];
                    } elseif (!empty($trans['ruta_nombre'])) {
                        // === EXPANSION DE RUTA ===
                        $accion = "Ir a Ruta";
                        $destino = "Ruta: " . $trans['ruta_nombre'];

                        $flujo_rows[] = [
                            'orden' => $paso['paso_orden'],
                            'paso' => $paso['paso_nombre'],
                            'resp' => $responsables_str,
                            'desc' => $desc_paso,
                            'tipo' => $tipo_paso,
                            'cond' => $trans['condicion_nombre'],
                            'accion' => $accion,
                            'dest' => $destino,
                            'is_decision' => true
                        ];

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
                                'orden' => "-> R." . $pr['orden'],
                                'paso' => "  [Ruta: " . $trans['ruta_nombre'] . "] " . $pr['paso_nombre'],
                                'resp' => "Ver Definición Ruta",
                                'desc' => cleanHtml($pr['paso_descripcion']),
                                'tipo' => "Paso de Ruta",
                                'cond' => "Parte de Ruta",
                                'accion' => "Pasos de Ruta",
                                'dest' => "Ver detalle en gestión de rutas",
                                'is_route_step' => true
                            ];
                        }
                    } else {
                        $flujo_rows[] = [
                            'orden' => $paso['paso_orden'],
                            'paso' => $paso['paso_nombre'],
                            'resp' => $responsables_str,
                            'desc' => $desc_paso,
                            'tipo' => $tipo_paso,
                            'cond' => $trans['condicion_nombre'],
                            'accion' => "Error",
                            'dest' => "Destino no encontrado",
                            'is_decision' => true
                        ];
                    }
                }
            } else {
                // Lineal 
                $sql_next = "SELECT paso_nombre, paso_orden FROM tm_flujo_paso 
                             WHERE flujo_id = ? AND paso_orden > ? AND est = 1 
                             ORDER BY paso_orden ASC LIMIT 1";
                $stmt_next = $conectar->prepare($sql_next);
                $stmt_next->execute([$flujo_id, $paso['paso_orden']]);
                $next = $stmt_next->fetch(PDO::FETCH_ASSOC);

                $accion = $next ? "Continuar" : "Fin";
                $destino = $next ? "Paso " . $next['paso_orden'] . ": " . $next['paso_nombre'] : "Fin del Flujo";

                $flujo_rows[] = [
                    'orden' => $paso['paso_orden'],
                    'paso' => $paso['paso_nombre'],
                    'resp' => $responsables_str,
                    'desc' => $desc_paso,
                    'tipo' => $tipo_paso,
                    'cond' => "N/A",
                    'accion' => $accion,
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
        $sheet->setCellValue('C' . $row, $flujo['pd_nom']); // Nueva Columna Prioridad
        $sheet->setCellValue('D' . $row, $flujo['flujo_nom']);
        $sheet->setCellValue('E' . $row, $creadores_cargos);
        $sheet->setCellValue('F' . $row, $creadores_perfiles);
        $sheet->setCellValue('G' . $row, $asignados);

        // Datos del paso (H-N) -> Ahora desplazados por 1
        $sheet->setCellValue('H' . $row, $fdata['orden']);
        $sheet->setCellValue('I' . $row, $fdata['paso']);
        $sheet->setCellValue('J' . $row, $fdata['resp']);
        $sheet->setCellValue('K' . $row, $fdata['desc']);
        $sheet->setCellValue('L' . $row, $fdata['tipo']);
        $sheet->setCellValue('M' . $row, $fdata['cond']);
        $sheet->setCellValue('N' . $row, $fdata['accion']);
        $sheet->setCellValue('O' . $row, $fdata['dest']); // Ojo: Ahora llega hasta O

        // Estilos condicionales (Columnas M, N, O ahora)
        // Antes era L-N (Cond, Accion, Dest) -> Ahora M-O
        if (isset($fdata['is_decision']) && $fdata['is_decision']) {
            $sheet->getStyle('M' . $row . ':O' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C');
            $sheet->getStyle('M' . $row . ':O' . $row)->getFont()->getColor()->setARGB('9C5700');
        }

        // Estilo diferente para pasos de ruta (Cols H-O)
        if (isset($fdata['is_route_step']) && $fdata['is_route_step']) {
            $sheet->getStyle('H' . $row . ':O' . $row)->getFont()->setItalic(true);
            $sheet->getStyle('I' . $row)->getFont()->getColor()->setARGB('555555');
        }

        // Estilo general de la fila (A-O)
        $rowStyle = [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
        ];
        $sheet->getStyle('A' . $row . ':O' . $row)->applyFromArray($rowStyle);

        $row++;
    }

    $endRow = $row - 1;

    if ($endRow > $startRow) {
        $sheet->mergeCells("A{$startRow}:A{$endRow}");
        $sheet->mergeCells("B{$startRow}:B{$endRow}");
        $sheet->mergeCells("C{$startRow}:C{$endRow}");
        $sheet->mergeCells("D{$startRow}:D{$endRow}");
        $sheet->mergeCells("E{$startRow}:E{$endRow}");
        $sheet->mergeCells("F{$startRow}:F{$endRow}");
    }

    $sheet->getStyle("A{$endRow}:N{$endRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->setColor(new Color('000000'));
}

// Auto-size final adjustment (limited width)
foreach (range('A', 'N') as $colID) {
    if (in_array($colID, ['D', 'E', 'F', 'I', 'J', 'N'])) {
        $sheet->getColumnDimension($colID)->setWidth(35);
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
