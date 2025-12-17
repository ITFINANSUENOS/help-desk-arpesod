<?php
if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde la consola.\nUso: php tests/kpi_validator.php <USU_ID>\n");
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Kpi.php';

// 1. Obtener ID de usuario
$usu_id = 0;
if (isset($argv[1])) {
    $usu_id = (int)$argv[1];
} else {
    echo "Ingrese el ID del usuario: ";
    $handle = fopen("php://stdin", "r");
    $usu_id = (int)trim(fgets($handle));
    fclose($handle);
}

if ($usu_id <= 0) die("ID invalido.\n");

$kpi = new Kpi();
$conectar = Conectar::getConexion();

echo "\n================================================================================\n";
echo " KPI VALIDATOR - AUDITORIA COMPLETA - USUARIO: $usu_id\n";
echo "================================================================================\n";

// -----------------------------------------------------------------------------
// SECCION 1: PASOS ASIGNADOS
// -----------------------------------------------------------------------------
echo "\n[1] KPI: PASOS ASIGNADOS\n";
echo "    Definición: Tickets entregados a este usuario por OTRO usuario/sistema.\n";
echo "    Excluye: Tickets que el mismo usuario creó y se auto-asignó al mismo tiempo.\n";
echo str_repeat("-", 80) . "\n";
echo str_pad("Ticket", 10) . str_pad("Fecha Asig", 22) . str_pad("Asignado Por", 20) . "Estado\n";
echo str_repeat("-", 80) . "\n";

$sql_asig = "SELECT t1.tick_id, t1.fech_asig, t1.how_asig,
             (SELECT usu_nom FROM tm_usuario WHERE usu_id = t1.how_asig) as nom_asigno
             FROM th_ticket_asignacion t1
             WHERE t1.usu_asig = ? AND t1.est = 1
             ORDER BY t1.fech_asig DESC";
$stmt = $conectar->prepare($sql_asig);
$stmt->bindValue(1, $usu_id);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count_assigned = 0;
foreach ($rows as $r) {
    $is_valid = ($r['how_asig'] != $usu_id); // Valido si no se lo asignó él mismo
    $status = $is_valid ? "[ OK ]" : "[EXCLUIDO]";
    if ($is_valid) $count_assigned++;

    $quien = $r['how_asig'] ? $r['how_asig'] . " (" . substr($r['nom_asigno'], 0, 10) . ")" : "Sistema";

    echo str_pad($r['tick_id'], 10) . str_pad($r['fech_asig'], 22) . str_pad($quien, 20) . $status . "\n";
}
echo str_repeat("-", 80) . "\n";
echo "TOTAL ASIGNADOS (Calculado): $count_assigned\n";
echo "TOTAL ASIGNADOS (Modelo KPI): " . $kpi->get_pasos_asignados($usu_id) . "\n";


// -----------------------------------------------------------------------------
// SECCION 2: PASOS FINALIZADOS
// -----------------------------------------------------------------------------
echo "\n\n[2] KPI: PASOS FINALIZADOS\n";
echo "    Definición: Suma de (A) Tickets movidos a otros + (B) Tickets cerrados.\n";
echo "    Requisito: La asignación INMEDIATA ANTERIOR no debió ser una auto-asignación.\n";
echo str_repeat("-", 80) . "\n";

// 2. MOVIDOS (SALIDAS A OTROS)
// CONDICIÓN: Tuvieron que recibirlo VALIDAMENTE antes.
// Nueva Logica: El predecesor inmediato no puede ser uno mismo.
$moves_valid = []; // Renamed to avoid conflict with previous $moves
$count_moves = 0;

echo "--- (A) MOVIDOS (Transferidos a otros) ---\n";
echo str_pad("Ticket", 10) . str_pad("Fecha Mov", 22) . str_pad("Movido A", 15) . "Valido?\n";
echo str_repeat("-", 80) . "\n";

$sql_mov = "SELECT t1.tick_id, t1.fech_asig, t1.usu_asig
            FROM th_ticket_asignacion t1
            WHERE t1.how_asig = ? AND t1.usu_asig != ? AND t1.est = 1
            ORDER BY t1.fech_asig DESC"; // Order by to get latest moves first, though not strictly needed for this logic
$stmt = $conectar->prepare($sql_mov);
$stmt->bindValue(1, $usu_id);
$stmt->bindValue(2, $usu_id);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Chequear predecesor inmediato
    $pred_sql = "SELECT how_asig FROM th_ticket_asignacion 
                 WHERE tick_id = ? 
                 AND usu_asig = ? 
                 AND fech_asig < ? 
                 ORDER BY fech_asig DESC LIMIT 1";
    $pred_stmt = $conectar->prepare($pred_sql);
    $pred_stmt->bindValue(1, $row['tick_id']);
    $pred_stmt->bindValue(2, $usu_id);
    $pred_stmt->bindValue(3, $row['fech_asig']);
    $pred_stmt->execute();
    $pred_how = $pred_stmt->fetchColumn();

    $is_valid = true;
    $reason = "";

    if ($pred_how == $usu_id) {
        $is_valid = false;
        $reason = "[EXCLUIDO (Auto-recuperado)]";
    } elseif ($pred_how === false) {
        // No encontré asignación previa a mí. ¿Fui el creador?
        // Si no recibí el ticket, no debería contar como finalizado (dispatcher).
        // Esto cubre casos donde el usuario mueve un ticket que nunca le fue asignado por otro.
        $is_valid = false;
        $reason = "[EXCLUIDO (No recibido previamente)]";
    }

    if ($is_valid) {
        $moves_valid[] = $row;
        $count_moves++;
        echo str_pad($row['tick_id'], 10) . str_pad($row['fech_asig'], 22) . str_pad($row['usu_asig'], 15) . "[ OK ]\n";
    } else {
        echo str_pad($row['tick_id'], 10) . str_pad($row['fech_asig'], 22) . str_pad($row['usu_asig'], 15) . $reason . "\n";
    }
}

// 2. CERRADOS
// Nueva Logica: Predecesor inmediato != Self.
// Para cerrados, la "asignacion actual" es la que cuenta.
// Miramos tm_ticket.how_asig? No, eso cambia si cerramos?
// Mejor mirar th_ticket_asignacion. El cierre NO crea un registro en th_ticket_asignacion (usualmente).
// El cierre es un estado en tm_ticket.
// Entonces, debemos mirar QUÍEN le asignó el ticket que ahora está cerrando.
// Es decir, buscar la ÚLTIMA asignación hacia el usuario.
$closed_valid = []; // Renamed to avoid conflict with previous $closed
$count_closed = 0;

echo "\n--- (B) CERRADOS (En estado 'Cerrado' actual) ---\n";
echo str_pad("Ticket", 10) . str_pad("Asignado Por", 22) . "Valido?\n";
echo str_repeat("-", 80) . "\n";

$sql_closed = "SELECT tick_id, how_asig, usu_id FROM tm_ticket WHERE usu_asig = ? AND tick_estado = 'Cerrado' AND est = 1";
$stmt_closed = $conectar->prepare($sql_closed);
$stmt_closed->bindValue(1, $usu_id);
$stmt_closed->execute();

while ($row = $stmt_closed->fetch(PDO::FETCH_ASSOC)) {
    // Buscar quien se lo dio (Ultima asignacion)
    $last_asig_sql = "SELECT how_asig FROM th_ticket_asignacion 
                      WHERE tick_id = ? 
                      AND usu_asig = ? 
                      ORDER BY fech_asig DESC LIMIT 1";
    $last_stmt = $conectar->prepare($last_asig_sql);
    $last_stmt->bindValue(1, $row['tick_id']);
    $last_stmt->bindValue(2, $usu_id);
    $last_stmt->execute();
    $last_how = $last_stmt->fetchColumn();

    $is_valid_c = true;
    $reason_c = "";

    if ($last_how == $usu_id) {
        $is_valid_c = false; // Auto-asignado y cerrado
        $reason_c = "[EXCLUIDO (Auto-recuperado)]";
    } elseif ($last_how === false) {
        // Nunca asignado en historico a este usuario?
        // Podría ser que el usuario lo creó y lo cerró directamente.
        // O que lo recibió y no hay registro en th_ticket_asignacion (anomalía).
        // Si how_asig en tm_ticket es el mismo usuario, o es null y usu_id es el mismo, entonces es propio.
        if ($row['how_asig'] == $usu_id || ($row['how_asig'] === null && $row['usu_id'] == $usu_id)) {
            $is_valid_c = false;
            $reason_c = "[EXCLUIDO (Propio/Auto-creado)]";
        } else {
            // Si no es propio y no hay registro de asignación, es una anomalía o caso no cubierto.
            // Por seguridad, lo excluimos si no hay un registro claro de recepción.
            $is_valid_c = false;
            $reason_c = "[EXCLUIDO (Sin registro de recepción)]";
        }
    }

    if ($is_valid_c) {
        $closed_valid[] = $row;
        $count_closed++;
        echo str_pad($row['tick_id'], 10) . str_pad($last_how ? $last_how : "Sistema", 22) . "[ OK ]\n";
    } else {
        echo str_pad($row['tick_id'], 10) . str_pad($last_how ? $last_how : ($row['how_asig'] ? $row['how_asig'] : "Sistema"), 22) . $reason_c . "\n";
    }
}

$total_finished = $count_moves + $count_closed;
echo str_repeat("-", 80) . "\n";
echo "TOTAL FINALIZADOS (Calculado: $count_moves Movs + $count_closed Cerr): $total_finished\n";
echo "TOTAL FINALIZADOS (Modelo KPI): " . $kpi->get_pasos_finalizados($usu_id) . "\n";


// -----------------------------------------------------------------------------
// SECCION 3: TIEMPO MEDIANA
// -----------------------------------------------------------------------------
echo "\n\n[3] KPI: TIEMPO MEDIANA RESPUESTA\n";
echo "    Definición: Mediana del tiempo entre Asignación -> Primera Respuesta (Movimiento o Comentario).\n";
echo "    Nota: Incluye horario 24/7 (Noches/Fines de semana).\n";
echo str_repeat("-", 80) . "\n";
echo str_pad("Ticket", 10) . str_pad("Inicio (Asig)", 22) . str_pad("Fin (Resp)", 22) . str_pad("Minutos", 10) . "Tipo\n";
echo str_repeat("-", 80) . "\n";

// Replicar lógica de recolección de tiempos
$sql_asignaciones = "SELECT tick_id, fech_asig FROM th_ticket_asignacion WHERE usu_asig = ? AND est = 1";
$stmt_asig = $conectar->prepare($sql_asignaciones);
$stmt_asig->bindValue(1, $usu_id);
$stmt_asig->execute();
$asignaciones = $stmt_asig->fetchAll(PDO::FETCH_ASSOC);

$tiempos = [];

foreach ($asignaciones as $asig) {
    $tick_id = $asig['tick_id'];
    $inicio = strtotime($asig['fech_asig']);

    $sql_move = "SELECT fech_asig FROM th_ticket_asignacion WHERE tick_id = ? AND how_asig = ? AND fech_asig > ? ORDER BY fech_asig ASC LIMIT 1";
    $stmt_move = $conectar->prepare($sql_move);
    $stmt_move->bindValue(1, $tick_id);
    $stmt_move->bindValue(2, $usu_id);
    $stmt_move->bindValue(3, $asig['fech_asig']);
    $stmt_move->execute();
    $move = $stmt_move->fetch(PDO::FETCH_ASSOC);

    $sql_comment = "SELECT fech_crea FROM td_ticketdetalle WHERE tick_id = ? AND usu_id = ? AND fech_crea > ? ORDER BY fech_crea ASC LIMIT 1";
    $stmt_comment = $conectar->prepare($sql_comment);
    $stmt_comment->bindValue(1, $tick_id);
    $stmt_comment->bindValue(2, $usu_id);
    $stmt_comment->bindValue(3, $asig['fech_asig']);
    $stmt_comment->execute();
    $comment = $stmt_comment->fetch(PDO::FETCH_ASSOC);

    $fin = null;
    $tipo = "";
    $fecha_fin = "";

    if ($move && $comment) {
        if (strtotime($move['fech_asig']) < strtotime($comment['fech_crea'])) {
            $fin = strtotime($move['fech_asig']);
            $fecha_fin = $move['fech_asig'];
            $tipo = "Movimiento";
        } else {
            $fin = strtotime($comment['fech_crea']);
            $fecha_fin = $comment['fech_crea'];
            $tipo = "Comentario";
        }
    } elseif ($move) {
        $fin = strtotime($move['fech_asig']);
        $fecha_fin = $move['fech_asig'];
        $tipo = "Movimiento";
    } elseif ($comment) {
        $fin = strtotime($comment['fech_crea']);
        $fecha_fin = $comment['fech_crea'];
        $tipo = "Comentario";
    }

    if ($fin) {
        $diff_minutes = ($fin - $inicio) / 60;
        $tiempos[] = $diff_minutes;
        echo str_pad($tick_id, 10) . str_pad($asig['fech_asig'], 22) . str_pad($fecha_fin, 22) . str_pad(round($diff_minutes, 2), 10) . "$tipo\n";
    }
}

if (!empty($tiempos)) {
    sort($tiempos);
    $count = count($tiempos);
    $middle = floor(($count - 1) / 2);
    $mediana = ($count % 2) ? $tiempos[$middle] : ($tiempos[$middle] + $tiempos[$middle + 1]) / 2;

    echo str_repeat("-", 80) . "\n";
    echo "MEDIANA CALCULADA: " . round($mediana, 2) . " min\n";
    echo "MEDIANA (Modelo KPI): " . $kpi->get_mediana_respuesta($usu_id) . " min\n";
} else {
    echo "No hay tiempos registrados.\n";
}

echo "\n\n[4] ANALISIS DE TICKETS ABIERTOS (ACTUALES)\n";
echo "    Objetivo: Entender la diferencia entre 'Abiertos en Sistema' vs 'Pendientes KPI'.\n";
echo str_repeat("-", 80) . "\n";
echo str_pad("Ticket", 10) . str_pad("Asignado Por", 20) . "Clasificación\n";
echo str_repeat("-", 80) . "\n";

// Buscar tickets actualmente abiertos asignados al usuario
$sql_open = "SELECT tick_id, how_asig, 
             (SELECT usu_nom FROM tm_usuario WHERE usu_id = tm_ticket.how_asig) as nom_asigno
             FROM tm_ticket 
             WHERE usu_asig = ? AND tick_estado = 'Abierto' AND est = 1";
$stmt = $conectar->prepare($sql_open);
$stmt->bindValue(1, $usu_id);
$stmt->execute();
$open_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_abiertos = count($open_tickets);
$kpi_pending = 0; // Abiertos que SÍ cuentan (recibidos de otros)
$excluded_open = 0; // Abiertos que NO cuentan (propios)

foreach ($open_tickets as $t) {
    if ($t['how_asig'] != $usu_id && $t['how_asig'] != null) {
        $status = "[KPI PENDIENTE]"; // Recibido de otro, aun no cerrado
        $kpi_pending++;
    } else {
        $status = "[PROPIO/AUTO]"; // Creado por el mismo o self-assigned
        $excluded_open++;
    }

    $quien = $t['how_asig'] ? $t['how_asig'] : "Sistema";
    echo str_pad($t['tick_id'], 10) . str_pad($quien, 20) . $status . "\n";
}

echo str_repeat("-", 80) . "\n";
echo "TOTAL TICKETS ABIERTOS EN SISTEMA: $total_abiertos\n";
echo "  -> Del KPI (Pendientes de gestion): $kpi_pending\n";
echo "  -> Excluidos (Propios/Auto-asignados): $excluded_open\n";
echo "\nNota: Si 'Total Abiertos' es el numero que ves en pantalla ($total_abiertos), \n";
echo "la diferencia son los $excluded_open tickets que el KPI ignora por ser propios.\n";

echo "\n\n[5] CHEQUEO DE CONSISTENCIA (EL MISTERIO DE LOS 18)\n";
echo "    Buscando tickets que están 'Abiertos' o 'Finalizados' pero NO aparecen en 'Asignados'.\n";
echo str_repeat("-", 80) . "\n";

// Recolectar IDs de Asignados Validados
$assigned_ids = []; // [tick_id => count]
$stmt = $conectar->prepare($sql_asig); // Reusamos query de sección 1
$stmt->bindValue(1, $usu_id);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['how_asig'] != $usu_id) {
        if (!isset($assigned_ids[$row['tick_id']])) $assigned_ids[$row['tick_id']] = 0;
        $assigned_ids[$row['tick_id']]++;
    }
}

// Chequear Abiertos KPI vs Asignados
echo "--- Tickets Abiertos (KPI) sin registro histórico valido ---\n";
$found_anomaly = false;
foreach ($open_tickets as $t) {
    if ($t['how_asig'] != $usu_id && $t['how_asig'] != null) {
        // Es un abierto valido para KPI. ¿Tiene historia?
        if (!isset($assigned_ids[$t['tick_id']])) {
            echo "ANOMALIA DETECTADA: Ticket {$t['tick_id']} está Abierto asignado por {$t['how_asig']}, pero NO TIENE registro de entrada en historial.\n";
            $found_anomaly = true;
        }
    }
}
if (!$found_anomaly) echo "Todos los tickets abiertos tienen su registro de entrada.\n";


// Chequear Finalizados (Movidos) vs Asignados
echo "\n--- Tickets Movidos (KPI) sin registro histórico valido ---\n";
$found_anomaly_move = false;
foreach ($moves as $m) {
    // Re-verificar logica de inclusion
    $received_sql = "SELECT count(*) FROM th_ticket_asignacion 
                     WHERE tick_id = {$m['tick_id']} 
                     AND usu_asig = $usu_id 
                     AND (how_asig != $usu_id OR how_asig IS NULL) 
                     AND fech_asig < '{$m['fech_asig']}'";
    $received = $conectar->query($received_sql)->fetchColumn() > 0;

    if ($received) {
        // Si contó como movido, debió contar como asignado.
        // Pero, ¿cuenta como asignado en la lista GENERAL?
        // La lista general solo mira si existe ALGUNA asignacion. 
        // Si el ticket fue movido, DEBE haber una asignacion previa.
        if (!isset($assigned_ids[$m['tick_id']])) {
            echo "ANOMALIA: Ticket {$m['tick_id']} se contó como Movido el {$m['fech_asig']}, pero no aparece en la lista de Asignados validos.\n";
            $found_anomaly_move = true;
        }
    }
}
if (!$found_anomaly_move) echo "Todos los tickets movidos tienen su registro de entrada.\n";

echo "\n\n[6] ANALISIS DE BALANCE (DETECTANDO RE-TOMAS / AUTO-RECUPERACIONES)\n";
echo "    Buscando tickets donde (Salidas + Abierto) > (Entradas Validas).\n";
echo "    Esto ocurre cuando re-tomas un ticket (auto-asignacion) y lo vuelves a gestionar.\n";
echo str_repeat("-", 80) . "\n";
echo str_pad("Ticket", 10) . str_pad("Entradas (A)", 15) . str_pad("Salidas (F)", 15) . str_pad("Abierto (O)", 15) . "Extra (Recuperado)\n";
echo str_repeat("-", 80) . "\n";

// 1. Consolidar todos los IDs involucrados
$all_ids = array_unique(array_merge(array_keys($assigned_ids), array_column($moves_valid, 'tick_id'), array_column($open_tickets, 'tick_id')));
sort($all_ids);

$total_recovered_moves = 0;

foreach ($all_ids as $tid) {
    // A: Entradas validas (Ya calculado en $assigned_ids)
    $in = isset($assigned_ids[$tid]) ? $assigned_ids[$tid] : 0;

    // F: Salidas validas (Moves)
    $out = 0;
    foreach ($moves_valid as $m) {
        if ($m['tick_id'] == $tid) $out++; // Asumiendo que todos los moves listados arriba pasaron el filtro 'received'
    }
    // Sumar cierres validos tambien a F?
    // El KPI 'Finalizados' suma Moves + Closes.
    // Si cierro un ticket, es una salida.
    foreach ($closed_valid as $c) {
        if ($c['tick_id'] == $tid && $c['how_asig'] != $usu_id) $out++;
    }

    // O: Abierto valido (Current State)
    $open_val = 0;
    foreach ($open_tickets as $ot) {
        if ($ot['tick_id'] == $tid && $ot['how_asig'] != $usu_id && $ot['how_asig'] != null) {
            $open_val = 1;
        }
    }

    // Calculo del "Extra"
    // Balance = (Out + Open) - In
    $balance = ($out + $open_val) - $in;

    if ($balance > 0) {
        $total_recovered_moves += $balance;
        echo str_pad($tid, 10) . str_pad($in, 15) . str_pad($out, 15) . str_pad($open_val, 15) . "+$balance\n";
    }
}

echo str_repeat("-", 80) . "\n";
echo "TOTAL GESTIONES 'RECUPERADAS' (Auto-asignadas y vueltas a gestionar): $total_recovered_moves\n";
echo "Esta cifra ($total_recovered_moves) deberia explicar la diferencia entre (Finalizados+Abiertos) - Asignados.\n";

echo "\n================================================================================\n";
?>
```