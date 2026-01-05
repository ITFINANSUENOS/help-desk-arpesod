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
            t.tick_estado,
            t.tick_titulo,
            cat.cat_nom,
            sub.cats_nom,
            p.paso_nombre
        FROM th_ticket_asignacion h
        INNER JOIN tm_usuario u ON h.usu_asig = u.usu_id
        LEFT JOIN tm_regional r ON u.reg_id = r.reg_id
        LEFT JOIN tm_cargo c ON u.car_id = c.car_id
        INNER JOIN tm_ticket t ON h.tick_id = t.tick_id
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

// Pre-procesar para agrupar por ticket y calcular duraciones
foreach ($historial as $row) {
    $assignments_by_ticket[$row['tick_id']][] = $row;
}

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
                'novedades' => 0, // Keeps historical count if needed, but we use new breakdown
                'total_sec' => 0
            ];
        }

        // Count metrics
        $stats[$usu_id]['gestionados']++;

        if (!empty($current['estado_tiempo_paso'])) {
            // Normalizar texto por si acaso (ej. "A Tiempo", "a tiempo")
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
        // Start: current fech_asig
        // End: Next assignment fech_asig OR Ticket fech_cierre OR NOW
        $start_time = strtotime($current['fech_asig']);
        $end_time = null;

        if (isset($entries[$i + 1])) {
            // There is a next step
            $end_time = strtotime($entries[$i + 1]['fech_asig']);
        } else {
            // Last step recorded
            if ($current['tick_estado'] == 'Cerrado' && !empty($current['fech_cierre'])) {
                $end_time = strtotime($current['fech_cierre']);
                // If the ticket is closed, and this is the last assignment, and the assignment date is after the closure date,
                // it means the closure happened *after* this assignment. So, the duration for this assignment
                // should end at the closure time.
                // However, if the assignment date is *after* the closure date, it's an anomaly or the closure date is for the ticket, not the assignment.
                // For simplicity, we'll assume fech_cierre is the end of the *ticket's* last activity.
                // If the assignment is the last one, its duration ends at ticket closure.
                // If fech_asig is after fech_cierre, it's an issue with data or logic.
                // Let's ensure end_time is not before start_time.
                if ($end_time < $start_time) {
                    $end_time = $start_time; // Avoid negative duration
                }
            } else {
                // Still open/active: use NOW
                $end_time = time();
            }
        }

        if ($end_time > $start_time) {
            $duration = $end_time - $start_time;
            $stats[$usu_id]['total_sec'] += $duration;
        }
    }
}

// 4. Fill Excel
$row = 2;
// RE-FIXING SORT LOGIC TO PRESERVE ID OR MERGE ERRORS BEFORE SORT
// Re-building stats with ID inside
$final_stats = [];
foreach ($stats as $uid => $dat) {
    $dat['usu_id'] = $uid;
    // Merge errors here
    $dat['err_proceso'] = $user_errors[$uid]['proceso'] ?? 0;
    $dat['err_info'] = $user_errors[$uid]['informativo'] ?? 0;
    $final_stats[] = $dat;
}

usort($final_stats, function ($a, $b) {
    return strcmp($a['reg_nom'], $b['reg_nom']) ?: strcmp($a['usu_nom'], $b['usu_nom']);
});


foreach ($final_stats as $stat) {
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
    $sheet->setCellValue('D' . $row, $stat['car_nom']); // Cargo
    $sheet->setCellValue('E' . $row, $stat['perfiles']); // Perfiles
    $sheet->setCellValue('F' . $row, $stat['gestionados']);
    $sheet->setCellValue('G' . $row, $stat['a_tiempo']);
    $sheet->setCellValue('H' . $row, $stat['atrasados']);
    $sheet->setCellValue('I' . $row, $stat['err_proceso']);      // NEW
    $sheet->setCellValue('J' . $row, $stat['err_info']);         // NEW
    $sheet->setCellValue('K' . $row, $total_hours);
    $sheet->setCellValue('L' . $row, $avg_hours);

    // Conditional format for Late
    if ($stat['atrasados'] > 0) {
        $sheet->getStyle('H' . $row)->getFont()->setColor(new Color(Color::COLOR_RED));
    }
    if ($stat['err_proceso'] > 0) {
        $sheet->getStyle('I' . $row)->getFont()->setColor(new Color(Color::COLOR_RED));
    }

    $row++;
}

// Auto-borders
$lastRow = $row - 1;
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FFCCCCCC'],
        ],
    ],
];
$sheet->getStyle('A2:L' . $lastRow)->applyFromArray($styleArray); // Updated range


// ==========================================
// SEGUNDA HOJA: DETALLE (POR TICKET/ASIGNACION + ERRORES)
// ==========================================

$spreadsheet->createSheet();
$spreadsheet->setActiveSheetIndex(1);
$sheet2 = $spreadsheet->getActiveSheet();
$sheet2->setTitle('Detalle Tickets');

// Encabezados Detalle
$headers2 = [
    'TICKET #',
    'TITULO TICKET',
    'CATEGORIA',
    'SUBCATEGORIA',
    'PASO FLUJO',
    'TIPO REGISTRO',       // NEW: Asignación vs Error
    'REGIONAL',
    'USUARIO',
    'ROL',
    'CARGO',
    'PERFILES',
    'FECHA EVENTO',        // Renamed from FECHA ASIGNADO
    'FECHA FIN/CIERRE',
    'DURACIÓN (Horas)',
    'ESTADO TIEMPO',
    'NOVEDAD/ERROR',
    'ESTADO TICKET ACTUAL'
];

$col = 'A';
foreach ($headers2 as $header) {
    $sheet2->setCellValue($col . '1', $header);
    $sheet2->getColumnDimension($col)->setAutoSize(true);
    $col++;
}
$sheet2->getStyle('A1:Q1')->applyFromArray($headerStyle); // Range A1:Q1

$row2 = 2;

// Agrupar Errores por Ticket
$sql_all_errors = "SELECT 
                    te.*,
                    fa.answer_nom,
                    u_resp.usu_nom as resp_nom, u_resp.usu_ape as resp_ape,
                    c_resp.car_nom as resp_car,
                    r_resp.reg_nom as resp_reg,
                    u_rep.usu_nom as rep_nom, u_rep.usu_ape as rep_ape
                   FROM tm_ticket_error te
                   LEFT JOIN tm_fast_answer fa ON te.answer_id = fa.answer_id
                   LEFT JOIN tm_usuario u_resp ON te.usu_id_responsable = u_resp.usu_id
                   LEFT JOIN tm_regional r_resp ON u_resp.reg_id = r_resp.reg_id
                   LEFT JOIN tm_cargo c_resp ON u_resp.car_id = c_resp.car_id
                   LEFT JOIN tm_usuario u_rep ON te.usu_id_reporta = u_rep.usu_id
                   WHERE te.est = 1
                   ORDER BY te.fech_crea ASC";
$stmt_all_err = $conectar->prepare($sql_all_errors);
$stmt_all_err->execute();
$all_errors = $stmt_all_err->fetchAll(PDO::FETCH_ASSOC);

$errors_by_ticket = [];
foreach ($all_errors as $err) {
    $errors_by_ticket[$err['tick_id']][] = $err;
}

foreach ($assignments_by_ticket as $tick_id => $assignments) {

    // 1. Merge Lists
    $timeline = [];

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
            // Map keys to match assignment structure where possible or handle in display
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
        $t_id = $tick_id; // or $current['tick_id'] if available (assignments have it)
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
        $end_time_str = '';
        $duration_hours = '';
        $estado_tiempo = '';
        $novedad = '';
        $tick_estado = '';

        if ($type == 'ASIGNACION') {
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
            $novedad = $current['error_descrip'] ?? '';

            // Check implicit novelty in comments (if error_descrip is empty)
            if (empty($novedad) && !empty($current['asig_comentario'])) {
                // Heuristic: If comment looks like a novelty or just usage of fallback
                $comment = $current['asig_comentario'];
                // Only treat as novelty if it's substantial or matches user concern
                // "Ticket trasladado" is default; maybe check if DIFFERENT? 
                // Or if Time Status is empty, use the comment as explanation.
                if (stripos($comment, 'novedad') !== false || stripos($comment, 'error') !== false || stripos($comment, 'falta') !== false) {
                    $novedad = $comment;
                }
            }

            // FIX: If Time Status is empty but there's a novelty/error, label it
            if (empty($estado_tiempo)) {
                if (!empty($novedad)) {
                    $estado_tiempo = 'Reasignado por Novedad';
                } else {
                    // NEW: Look ahead! If the NEXT event is an error or a novelty assignment,
                    // it means THIS assignment ended because of that.
                    if (isset($timeline[$i + 1])) {
                        $next = $timeline[$i + 1];
                        if ($next['type'] != 'ASIGNACION') {
                            // Next is an Error Event
                            $estado_tiempo = 'Sin tiempo por asignación a novedad';
                        } elseif (!empty($next['asig_comentario'])) {
                            // Next is an Assignment with a potential novelty comment
                            $next_comment = $next['asig_comentario'];
                            if (stripos($next_comment, 'novedad') !== false || stripos($next_comment, 'error') !== false || stripos($next_comment, 'falta') !== false) {
                                $estado_tiempo = 'Sin tiempo por asignación a novedad';
                            }
                        }
                    }
                }
            }

            // Duration Logic (Find next ASIG start time)
            $start_time = strtotime($current['fech_asig']);
            $end_time = null;

            // Search next assignment in sorted timeline (Skip errors)
            for ($k = $i + 1; $k < $count; $k++) {
                if ($timeline[$k]['type'] == 'ASIGNACION') {
                    $end_time = strtotime($timeline[$k]['fech_asig']);
                    $end_time_str = $timeline[$k]['fech_asig'];
                    break;
                }
            }
            // If no next assignment found
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
            $ref_asig = $assignments[0] ?? null; // Get basic ticket info from first assignment
            if ($ref_asig) {
                $subcat_nom = $ref_asig['cats_nom'];
                $tick_estado = $ref_asig['tick_estado'];
                $tick_titulo = $ref_asig['tick_titulo'];
            }

            // FIX: Set a descriptive name instead of empty
            $paso_nom = ($type == 'ERROR PROCESO') ? 'Devolución por Error' : 'Reporte Informativo';
            $estado_tiempo = '-'; // FIX: Avoid empty cell for errors

            $usu_nom = $current['resp_nom'] . ' ' . $current['resp_ape']; // Responsable
            $car_nom = $current['resp_car'];
            $reg_nom = $current['resp_reg'] ?? 'N/A'; // Regional Responsible (NEW)
            $rol_nom = 'Responsable Error';
            $perfiles = ''; // Could fetch if needed

            $novedad = strip_tags($current['error_descrip']);
            if (!empty($current['answer_nom'])) {
                $novedad = '[' . $current['answer_nom'] . '] ' . $novedad;
            }

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
        $sheet2->setCellValue('P' . $row2, $novedad);
        $sheet2->setCellValue('Q' . $row2, $tick_estado);

        // Styles
        if ($type == 'ERROR PROCESO') {
            $sheet2->getStyle('F' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED)); // Shifted E->F
            $sheet2->getStyle('F' . $row2)->getFont()->setBold(true);
            $sheet2->getStyle('P' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED)); // Shifted O->P
        } elseif ($type == 'ERROR INFORMATIVO') {
            $sheet2->getStyle('F' . $row2)->getFont()->setColor(new Color(Color::COLOR_BLUE));
            $sheet2->getStyle('P' . $row2)->getFont()->setColor(new Color(Color::COLOR_BLUE));
        } else {
            // Asignacion colors
            if (!empty($estado_tiempo)) {
                $est = mb_strtolower($estado_tiempo);
                if (strpos($est, 'atrasado') !== false || strpos($est, 'vencido') !== false) {
                    $sheet2->getStyle('O' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED)); // Shifted N->O
                } elseif (strpos($est, 'tiempo') !== false) {
                    $sheet2->getStyle('O' . $row2)->getFont()->setColor(new Color(Color::COLOR_DARKGREEN));
                }
            }
            if (!empty($novedad) && $type == 'ASIGNACION') {
                // Novedades en asignacion (retornos)
                $sheet2->getStyle('P' . $row2)->getFont()->setColor(new Color(Color::COLOR_RED));
            }
        }

        $row2++;
    }
}
$lastRow2 = $row2 - 1;
$sheet2->getStyle('A2:Q' . $lastRow2)->applyFromArray($styleArray);

// Volver a la primera hoja antes de guardar
$spreadsheet->setActiveSheetIndex(0);

// 5. Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Desempeno_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
