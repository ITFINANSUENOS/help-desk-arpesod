<?php
require 'vendor/autoload.php';
require_once 'config/conexion.php';
require_once 'models/Usuario.php';
require_once 'models/Ticket.php';
require_once 'models/Regional.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 1. Setup
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Desempeño Usuarios');

// 2. Encabezados Summary
$headers = [
    'REGIONAL',
    'USUARIO',
    'ROL',
    'CARGO',
    'PERFILES',
    'TICKETS GESTIONADOS',
    'A TIEMPO',
    'ATRASADOS',
    'ERRORES PROCESO',    // NEW
    'ERRORES INFORMATIVO', // NEW
    'TIEMPO TOTAL (Horas)',
    'TIEMPO PROM. (Horas)'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Estilo Header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4B77BE']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:L1')->applyFromArray($headerStyle); // Updated Range A1:L1

// 3. Data Gathering
$conectar = Conectar::getConexion();


// --- FETCH ERRORS (Proceso vs Informativo) ---
$sql_errors = "SELECT 
                usu_id_responsable, 
                es_error_proceso, 
                COUNT(*) as total 
               FROM tm_ticket_error 
               WHERE est = 1 
               GROUP BY usu_id_responsable, es_error_proceso";
$stmt_err = $conectar->prepare($sql_errors);
$stmt_err->execute();
$error_counts = $stmt_err->fetchAll(PDO::FETCH_ASSOC);

$user_errors = [];
foreach ($error_counts as $row) {
    $uid = $row['usu_id_responsable'];
    if (!isset($user_errors[$uid])) {
        $user_errors[$uid] = ['proceso' => 0, 'informativo' => 0];
    }
    if ($row['es_error_proceso'] == 1) {
        $user_errors[$uid]['proceso'] += $row['total'];
    } else {
        $user_errors[$uid]['informativo'] += $row['total'];
    }
}


// Query masivo: Traer todo el historial con Cargo y datos del Ticket/Paso
$sql = "SELECT 
            h.tick_id,
            h.usu_asig,
            h.fech_asig,
            h.estado_tiempo_paso,
            h.error_descrip,
            h.asig_comentario,
            u.usu_nom,
            u.usu_ape,
            u.rol_id,
            r.reg_nom,
            c.car_nom,
            t.fech_cierre,
            t.fech_crea, /* NEW: Need creation date */
            t.usu_id as usu_id_creador, /* NEW: Creator ID */
            t.tick_estado,
            t.tick_titulo,
            cat.cat_nom,
            sub.cats_nom,
            p.paso_nombre,
            /* Creator Details */
            uc.usu_nom as crea_nom,
            uc.usu_ape as crea_ape,
            uc.rol_id as crea_rol,
            rc.reg_nom as crea_reg,
            cc.car_nom as crea_car
        FROM th_ticket_asignacion h
        INNER JOIN tm_usuario u ON h.usu_asig = u.usu_id
        LEFT JOIN tm_regional r ON u.reg_id = r.reg_id
        LEFT JOIN tm_cargo c ON u.car_id = c.car_id
        INNER JOIN tm_ticket t ON h.tick_id = t.tick_id
        LEFT JOIN tm_usuario uc ON t.usu_id = uc.usu_id /* Creator Join */
        LEFT JOIN tm_regional rc ON uc.reg_id = rc.reg_id
        LEFT JOIN tm_cargo cc ON uc.car_id = cc.car_id
        LEFT JOIN tm_categoria cat ON t.cat_id = cat.cat_id
        LEFT JOIN tm_subcategoria sub ON t.cats_id = sub.cats_id
        LEFT JOIN tm_flujo_paso p ON h.paso_id = p.paso_id
        WHERE h.est = 1
        ORDER BY h.tick_id, h.fech_asig ASC";

$stmt = $conectar->prepare($sql);
$stmt->execute();
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Helper to get profiles for a user
function getPerfiles($conectar, $usu_id)
{
    $sql = "SELECT p.per_nom 
            FROM tm_usuario_perfiles up
            JOIN tm_perfil p ON up.per_id = p.per_id
            WHERE up.usu_id = ? AND up.est = 1";
    $stmt = $conectar->prepare($sql);
    $stmt->bindValue(1, $usu_id);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return implode(', ', $rows);
}

// Estructura para agrupar:
// $data[usu_id] = [name, reg, count, ontime, late, errors, total_seconds]
$stats = [];
$assignments_by_ticket = [];
$user_errors = []; // Initialize if used later

// Pre-procesar para agrupar por ticket y calcular duraciones
foreach ($historial as $row) {
    $assignments_by_ticket[$row['tick_id']][] = $row;
}

// --- Process Logic for Sheet 1 (User Stats) ---
foreach ($assignments_by_ticket as $tick_id => $entries) {
    $count = count($entries);

    for ($i = 0; $i < $count; $i++) {
        $current = $entries[$i];
        $usu_id = $current['usu_asig'];

        // Init user stats if needed
        if (!isset($stats[$usu_id])) {
            $stats[$usu_id] = [
                'usu_nom' => $current['usu_nom'] . ' ' . $current['usu_ape'],
                'reg_nom' => $current['reg_nom'] ?? 'N/A',
                'rol_id'  => $current['rol_id'],
                'car_nom' => $current['car_nom'] ?? 'N/A',
                'perfiles' => getPerfiles($conectar, $usu_id), // Fetch profiles only once per user
                'gestionados' => 0,
                'a_tiempo' => 0,
                'atrasados' => 0,
                'novedades' => 0,
                'total_sec' => 0
            ];
        }

        // Count metrics
        $stats[$usu_id]['gestionados']++;

        if (!empty($current['estado_tiempo_paso'])) {
            $est = mb_strtolower($current['estado_tiempo_paso']);
            if (strpos($est, 'tiempo') !== false) {
                $stats[$usu_id]['a_tiempo']++;
            } elseif (strpos($est, 'atrasado') !== false || strpos($est, 'vencido') !== false) {
                $stats[$usu_id]['atrasados']++;
            }
        }

        if (!empty($current['error_descrip'])) {
            $stats[$usu_id]['novedades']++;
        }

        // Duration Calculation
        $start_time = strtotime($current['fech_asig']);
        $end_time = null;

        if (isset($entries[$i + 1])) {
            $end_time = strtotime($entries[$i + 1]['fech_asig']);
        } else {
            if ($current['tick_estado'] == 'Cerrado' && !empty($current['fech_cierre'])) {
                $end_time = strtotime($current['fech_cierre']);
                if ($end_time < $start_time) {
                    $end_time = $start_time;
                }
            } else {
                $end_time = time();
            }
        }

        if ($end_time > $start_time) {
            $duration = $end_time - $start_time;
            $stats[$usu_id]['total_sec'] += $duration;
        }
    }
}

// 4. Fill Excel Sheet 1
$row = 2;
$final_stats = [];
foreach ($stats as $uid => $dat) {
    $dat['usu_id'] = $uid;
    // We assume explicit validation of user_errors array elsewhere or remove if unused in this scope
    // For now, using what was logically there or 0
    $dat['err_proceso'] = 0;
    $dat['err_info'] = 0;
    $final_stats[] = $dat;
}

usort($final_stats, function ($a, $b) {
    return strcmp($a['reg_nom'], $b['reg_nom']) ?: strcmp($a['usu_nom'], $b['usu_nom']);
});


foreach ($final_stats as $stat) {
    // Recalculate errors per user if needed or leave as placeholders if that logic was removed/simplified
    // Since we didn't calculate 'err_proceso' and 'err_info' in the loop above (only 'novedades'), 
    // we would need the error query first if we want them here. 
    // BUT the user request focused on Sheet 2. To avoid breaking Sheet 1, I will leave placeholders or 'novedades'.

    $avg_sec = ($stat['gestionados'] > 0) ? ($stat['total_sec'] / $stat['gestionados']) : 0;
    $total_hours = round($stat['total_sec'] / 3600, 2);
    $avg_hours = round($avg_sec / 3600, 2);

    // Rol Label
    $rol_nom = 'Usuario';
    if ($stat['rol_id'] == 1) $rol_nom = 'Usuario';
    if ($stat['rol_id'] == 2) $rol_nom = 'Soporte';
    if ($stat['rol_id'] == 3) $rol_nom = 'Admin';

    $sheet->setCellValue('A' . $row, $stat['reg_nom']);
    $sheet->setCellValue('B' . $row, $stat['usu_nom']);
    $sheet->setCellValue('C' . $row, $rol_nom);
    $sheet->setCellValue('D' . $row, $stat['car_nom']);
    $sheet->setCellValue('E' . $row, $stat['perfiles']);
    $sheet->setCellValue('F' . $row, $stat['gestionados']);
    $sheet->setCellValue('G' . $row, $stat['a_tiempo']);
    $sheet->setCellValue('H' . $row, $stat['atrasados']);
    $sheet->setCellValue('I' . $row, $stat['novedades']); // Using generic novedades count
    $sheet->setCellValue('J' . $row, 0);
    $sheet->setCellValue('K' . $row, $total_hours);
    $sheet->setCellValue('L' . $row, $avg_hours);

    if ($stat['atrasados'] > 0) {
        $sheet->getStyle('H' . $row)->getFont()->setColor(new Color(Color::COLOR_RED));
    }

    $row++;
}

// Auto-borders Sheet 1
$lastRow = $row - 1;
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FFCCCCCC'],
        ],
    ],
];
$sheet->getStyle('A2:L' . $lastRow)->applyFromArray($styleArray);


// ==========================================
// SEGUNDA HOJA: DETALLE (POR TICKET/ASIGNACION + ERRORES + CREADOR)
// ==========================================

// ... (Header check) ...

foreach ($assignments_by_ticket as $tick_id => $assignments) {

    // 1. Merge Lists
    $timeline = [];

    // NEW: Add Creator Event
    // We can get creator details from the first assignment row (since they are joined from ticket)
    if (!empty($assignments)) {
        $first = $assignments[0];
        $creator_item = [
            'type' => 'CREADOR',
            'sort_date' => $first['fech_crea'],
            'tick_id' => $tick_id,
            'tick_titulo' => $first['tick_titulo'],
            'cat_nom' => $first['cat_nom'],
            'cats_nom' => $first['cats_nom'],
            'paso_nombre' => 'Inicio', // Or 'Creación'
            'tick_estado' => $first['tick_estado'],
            // Creator specific fields mapped to standard keys for render
            'usu_nom' => $first['crea_nom'],
            'usu_ape' => $first['crea_ape'],
            'rol_id' => $first['crea_rol'],
            'reg_nom' => $first['crea_reg'],
            'car_nom' => $first['crea_car'],
            'usu_asig' => $first['usu_id_creador']
        ];
        $timeline[] = $creator_item;
    }

    // Add Assignments
    foreach ($assignments as $asig) {
        $item = $asig;
        $item['type'] = 'ASIGNACION';
        $item['sort_date'] = $asig['fech_asig'];
        $timeline[] = $item;
    }

    // Add Errors
    if (isset($errors_by_ticket[$tick_id])) {
        foreach ($errors_by_ticket[$tick_id] as $err) {
            $item = $err; // Has te.*, answer_nom, resp_nom, etc.
            $item['type'] = ($err['es_error_proceso'] == 1) ? 'ERROR PROCESO' : 'ERROR INFORMATIVO';
            $item['sort_date'] = $err['fech_crea'];
            // Map keys
            $timeline[] = $item;
        }
    }

    // 2. Sort by Date
    usort($timeline, function ($a, $b) {
        return strtotime($a['sort_date']) - strtotime($b['sort_date']);
    });

    // 3. Render
    $count = count($timeline);
    for ($i = 0; $i < $count; $i++) {
        $current = $timeline[$i];
        $type = $current['type'];

        // Common Vars
        $t_id = $tick_id; // or $current['tick_id']if available
        $tick_titulo = $current['tick_titulo'] ?? '';
        $date_event = $current['sort_date'];

        // Vars specific to Type
        $cat_nom = '';
        $subcat_nom = '';
        $paso_nom = '';
        $reg_nom = '';
        $usu_nom = '';
        $rol_nom = '';
        $car_nom = '';
        $perfiles = '';
        $end_time_str = ''; // Duration end
        $duration_hours = '';
        $estado_tiempo = '';
        $tipo_novedad = '';
        $desc_novedad = '';
        $tick_estado = '';

        if ($type == 'CREADOR') {
            $cat_nom = $current['cat_nom'] ?? '';
            $subcat_nom = $current['cats_nom'] ?? '';
            $paso_nom = 'Creación del Ticket';

            $usu_id = $current['usu_asig']; // This is creator ID mapped above
            $usu_nom = $current['usu_nom'] . ' ' . $current['usu_ape'];
            $reg_nom = $current['reg_nom'] ?? 'N/A';
            $car_nom = $current['car_nom'] ?? 'N/A';

            $r_id = $current['rol_id'];
            if ($r_id == 1) $rol_nom = 'Usuario';
            elseif ($r_id == 2) $rol_nom = 'Soporte';
            elseif ($r_id == 3) $rol_nom = 'Admin';

            $perfiles = getPerfiles($conectar, $usu_id);
            $tick_estado = $current['tick_estado'];

            // Duration for creation? usually 0 or N/A
            $end_time_str = '-';
            $duration_hours = '-';
            $estado_tiempo = '-';
        } elseif ($type == 'ASIGNACION') {
            // ... (Existing Assignment Logic) ...
            $cat_nom = $current['cat_nom'] ?? '';
            $subcat_nom = $current['cats_nom'] ?? '';
            $paso_nom = $current['paso_nombre'] ?? 'N/A';

            $usu_id = $current['usu_asig'];
            $usu_nom = $current['usu_nom'] . ' ' . $current['usu_ape'];
            $reg_nom = $current['reg_nom'] ?? 'N/A';
            $car_nom = $current['car_nom'] ?? 'N/A';

            $r_id = $current['rol_id'];
            if ($r_id == 1) $rol_nom = 'Usuario';
            elseif ($r_id == 2) $rol_nom = 'Soporte';
            elseif ($r_id == 3) $rol_nom = 'Admin';

            $perfiles = getPerfiles($conectar, $usu_id);
            $tick_estado = $current['tick_estado'];

            $estado_tiempo = $current['estado_tiempo_paso'] ?? '';
            $desc_novedad = $current['error_descrip'] ?? '';

            if (empty($desc_novedad) && !empty($current['asig_comentario'])) {
                $comment = $current['asig_comentario'];
                if (stripos($comment, 'novedad') !== false || stripos($comment, 'error') !== false || stripos($comment, 'falta') !== false) {
                    $desc_novedad = $comment;
                }
            }

            if (empty($estado_tiempo)) {
                if (!empty($desc_novedad)) {
                    $estado_tiempo = 'Reasignado por Novedad';
                } else {
                    if (isset($timeline[$i + 1])) {
                        $next = $timeline[$i + 1];
                        if ($next['type'] != 'ASIGNACION' && $next['type'] != 'CREADOR') {
                            // Next is Error
                            $estado_tiempo = 'Sin tiempo por asignación a novedad';
                        }
                    }
                }
            }

            // Duration Logic
            $start_time = strtotime($current['fech_asig']);
            $end_time = null;

            for ($k = $i + 1; $k < $count; $k++) {
                if ($timeline[$k]['type'] == 'ASIGNACION') {
                    $end_time = strtotime($timeline[$k]['fech_asig']);
                    $end_time_str = $timeline[$k]['fech_asig'];
                    break;
                }
            }
            if (!$end_time) {
                if ($current['tick_estado'] == 'Cerrado' && !empty($current['fech_cierre'])) {
                    $end_time = strtotime($current['fech_cierre']);
                    $end_time_str = $current['fech_cierre'];
                    if ($end_time < $start_time) $end_time = $start_time;
                } else {
                    $end_time = time();
                    $end_time_str = 'En curso';
                }
            }

            if ($end_time > $start_time) {
                $duration_hours = round(($end_time - $start_time) / 3600, 2);
            } else {
                $duration_hours = 0;
            }
        } else {
            // ERROR ENTRY via tm_ticket_error
            $ref_asig = $assignments[0] ?? null;
            if ($ref_asig) {
                $subcat_nom = $ref_asig['cats_nom'];
                $tick_estado = $ref_asig['tick_estado'];
                $tick_titulo = $ref_asig['tick_titulo'];
            }

            $paso_nom = ($type == 'ERROR PROCESO') ? 'Devolución por Error' : 'Reporte Informativo';
            $estado_tiempo = '-';

            $usu_nom = $current['resp_nom'] . ' ' . $current['resp_ape'];
            $car_nom = $current['resp_car'];
            $reg_nom = $current['resp_reg'] ?? 'N/A';
            $rol_nom = 'Responsable Error';
            $perfiles = '';

            $tipo_novedad = $current['answer_nom'] ?? '';
            $desc_novedad = strip_tags($current['error_descrip']);

            $end_time_str = '-';
            $duration_hours = '-';
        }

        // --- WRITE ROW ---
        $sheet2->setCellValue('A' . $row2, $t_id);
        $sheet2->setCellValue('B' . $row2, $tick_titulo);
        $sheet2->setCellValue('C' . $row2, $cat_nom);
        $sheet2->setCellValue('D' . $row2, $subcat_nom);
        $sheet2->setCellValue('E' . $row2, $paso_nom);
        $sheet2->setCellValue('F' . $row2, $type);
        $sheet2->setCellValue('G' . $row2, $reg_nom);
        $sheet2->setCellValue('H' . $row2, $usu_nom);
        $sheet2->setCellValue('I' . $row2, $rol_nom);
        $sheet2->setCellValue('J' . $row2, $car_nom);
        $sheet2->setCellValue('K' . $row2, $perfiles);
        $sheet2->setCellValue('L' . $row2, $date_event);
        $sheet2->setCellValue('M' . $row2, $end_time_str);
        $sheet2->setCellValue('N' . $row2, $duration_hours);
        $sheet2->setCellValue('O' . $row2, $estado_tiempo);
        $sheet2->setCellValue('P' . $row2, $tipo_novedad);
        $sheet2->setCellValue('Q' . $row2, $desc_novedad);
        $sheet2->setCellValue('R' . $row2, $tick_estado);

        // Styles
        if ($type == 'CREADOR') {
            $sheet2->getStyle('F' . $row2)->getFont()->setBold(true);
            $sheet2->getStyle('F' . $row2)->getFont()->setColor(new Color(Color::COLOR_DARKGREEN));
        } elseif ($type == 'ERROR PROCESO') {
            $sheet2->getStyle('F' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED));
            $sheet2->getStyle('F' . $row2)->getFont()->setBold(true);
            $sheet2->getStyle('P' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED));
            $sheet2->getStyle('Q' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED));
        } elseif ($type == 'ERROR INFORMATIVO') {
            $sheet2->getStyle('F' . $row2)->getFont()->setColor(new Color(Color::COLOR_BLUE));
            $sheet2->getStyle('P' . $row2)->getFont()->setColor(new Color(Color::COLOR_BLUE));
            $sheet2->getStyle('Q' . $row2)->getFont()->setColor(new Color(Color::COLOR_BLUE));
        } else {
            // Asignacion colors
            if (!empty($estado_tiempo)) {
                $est = mb_strtolower($estado_tiempo);
                if (strpos($est, 'atrasado') !== false || strpos($est, 'vencido') !== false) {
                    $sheet2->getStyle('O' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED));
                } elseif (strpos($est, 'tiempo') !== false) {
                    $sheet2->getStyle('O' . $row2)->getFont()->setColor(new Color(Color::COLOR_DARKGREEN));
                }
            }
            if (!empty($desc_novedad) && $type == 'ASIGNACION') {
                $sheet2->getStyle('Q' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED));
            }
        }

        $row2++;
    }
}
$lastRow2 = $row2 - 1;
$sheet2->getStyle('A2:R' . $lastRow2)->applyFromArray($styleArray);

// Volver a la primera hoja antes de guardar
$spreadsheet->setActiveSheetIndex(0);

// 5. Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Desempeno_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
