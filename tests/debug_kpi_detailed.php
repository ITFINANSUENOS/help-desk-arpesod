<?php
if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde la consola.\n");
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Kpi.php';

// Obtener ID del usuario
$usu_id = 0;
if (isset($argv[1])) {
    $usu_id = (int)$argv[1];
} else {
    echo "Por favor ingrese el ID del usuario a consultar: ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $usu_id = (int)trim($line);
    fclose($handle);
}

if ($usu_id <= 0) {
    die("ID de usuario invalido.\n");
}

$kpi = new Kpi();
$conectar = Conectar::getConexion();

echo "\n=================================================\n";
echo " REPORTE KPI E HISTORIAL - USUARIO: " . $usu_id . "\n";
echo "=================================================\n\n";

// -----------------------------------------------------------------------
// 0. HISTORIAL CRUDO (RAW)
// -----------------------------------------------------------------------
echo "--- 0. HISTORIAL COMPLETO (th_ticket_asignacion) ---\n";
echo "Todas las veces que este usuario ha sido 'usu_asig' o 'how_asig'.\n\n";

echo str_pad("Tick", 8) . str_pad("Fecha", 20) . str_pad("Usu Asig", 10) . str_pad("How Asig", 10) . "Rol\n";
echo str_repeat("-", 60) . "\n";

$sql_raw = "SELECT * FROM th_ticket_asignacion 
            WHERE (usu_asig = $usu_id OR how_asig = $usu_id) AND est = 1
            ORDER BY fech_asig ASC";
$stmt_raw = $conectar->query($sql_raw);
$rows_raw = $stmt_raw->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows_raw as $r) {
    $rol = "";
    if ($r['usu_asig'] == $usu_id && $r['how_asig'] == $usu_id) $rol = "[AUTO-ASIG]";
    elseif ($r['usu_asig'] == $usu_id) $rol = "[RECIBIDO]";
    elseif ($r['how_asig'] == $usu_id) $rol = "[ENVIADO]";

    echo str_pad($r['tick_id'], 8) . str_pad($r['fech_asig'], 20) . str_pad($r['usu_asig'], 10) . str_pad($r['how_asig'], 10) . $rol . "\n";
}
echo "\nTotal registros encontrados: " . count($rows_raw) . "\n\n";


// -----------------------------------------------------------------------
// 1. PASOS ASIGNADOS
// -----------------------------------------------------------------------
echo "--- 1. KPI: PASOS ASIGNADOS ---\n";
$asignados = $kpi->get_pasos_asignados($usu_id);
echo "VALOR KPI: " . $asignados . "\n";
echo "Logica: Registros donde Usu Asig = $usu_id Y How Asig != $usu_id (Recibidos de otro)\n";
echo "--------------------------------------------------\n";

foreach ($rows_raw as $r) {
    if ($r['usu_asig'] == $usu_id) {
        $count = ($r['how_asig'] != $usu_id);
        $status = $count ? "CUENTA" : "EXCLUIDO (Auto)";
        echo "Ticket " . $r['tick_id'] . " | Asignado por: " . ($r['how_asig'] ? $r['how_asig'] : "System") . " -> " . $status . "\n";
    }
}


// -----------------------------------------------------------------------
// 2. PASOS FINALIZADOS
// -----------------------------------------------------------------------
echo "\n--- 2. KPI: PASOS FINALIZADOS ---\n";
$finalizados = $kpi->get_pasos_finalizados($usu_id);
echo "VALOR KPI: " . $finalizados . "\n";
echo "Logica: \n";
echo "  A) Movidos: El usuario ($usu_id) asignó a otro, PERO antes debió recibirlo de alguien más.\n";
echo "  B) Cerrados: El usuario ($usu_id) cerró el ticket, y se lo había asignado otro.\n";
echo "--------------------------------------------------\n";

// Analisis manual de finalizados
// Buscar movidos
foreach ($rows_raw as $r) {
    if ($r['how_asig'] == $usu_id && $r['usu_asig'] != $usu_id) {
        // Es un movimiento hecho por el usuario. Verificar si lo recibió de otro antes.
        $received_sql = "SELECT count(*) FROM th_ticket_asignacion 
                        WHERE tick_id = {$r['tick_id']} 
                        AND usu_asig = $usu_id 
                        AND (how_asig != $usu_id OR how_asig IS NULL) 
                        AND fech_asig < '{$r['fech_asig']}'";
        $has_received = $conectar->query($received_sql)->fetchColumn() > 0;

        $status = $has_received ? "CUENTA (Movido)" : "EXCLUIDO (No recibido de otro)";
        echo "Ticket " . $r['tick_id'] . " enviado a " . $r['usu_asig'] . " -> " . $status . "\n";
    }
}

// Buscar cerrados (consulta extra)
$sql_cerrados = "SELECT tick_id, how_asig FROM tm_ticket WHERE usu_asig = $usu_id AND tick_estado = 'Cerrado' AND est = 1";
$res_cerrados = $conectar->query($sql_cerrados)->fetchAll(PDO::FETCH_ASSOC);
foreach ($res_cerrados as $rc) {
    // Verificar quien se lo asignó
    $valid = ($rc['how_asig'] != $usu_id);
    $status = $valid ? "CUENTA (Cerrado)" : "EXCLUIDO (Auto-cierre sin asignacion externa)";
    echo "Ticket " . $rc['tick_id'] . " (Cerrado) | Asignado por: " . $rc['how_asig'] . " -> " . $status . "\n";
}


// -----------------------------------------------------------------------
// 3. TIEMPO MEDIANA RESPUESTA
// -----------------------------------------------------------------------
echo "\n--- 3. KPI: MEDIANA RESPUESTA ---\n";
$mediana = $kpi->get_mediana_respuesta($usu_id);
echo "VALOR KPI: " . $mediana . " min\n";
echo "--------------------------------------------------\n";

// Replicar logica para mostrar tiempos
$sql_asigs = "SELECT tick_id, fech_asig FROM th_ticket_asignacion WHERE usu_asig = $usu_id AND est = 1";
$asigs = $conectar->query($sql_asigs)->fetchAll(PDO::FETCH_ASSOC);
$tiempos = [];

echo str_pad("Ticket", 10) . str_pad("Inicio", 20) . str_pad("Fin", 20) . "Minutos\n";

foreach ($asigs as $a) {
    $tick_id = $a['tick_id'];
    $inicio = strtotime($a['fech_asig']);

    // Buscar la accion siguiente
    $sql_move = "SELECT fech_asig FROM th_ticket_asignacion WHERE tick_id = $tick_id AND how_asig = $usu_id AND fech_asig > '{$a['fech_asig']}' ORDER BY fech_asig ASC LIMIT 1";
    $move = $conectar->query($sql_move)->fetchColumn();

    $sql_comm = "SELECT fech_crea FROM td_ticketdetalle WHERE tick_id = $tick_id AND usu_id = $usu_id AND fech_crea > '{$a['fech_asig']}' ORDER BY fech_crea ASC LIMIT 1";
    $comm = $conectar->query($sql_comm)->fetchColumn();

    $fin = null;
    $tipo = "";

    if ($move && $comm) {
        $fin = min(strtotime($move), strtotime($comm));
        $tipo = (strtotime($move) < strtotime($comm)) ? "Mov" : "Com";
    } elseif ($move) {
        $fin = strtotime($move);
        $tipo = "Mov";
    } elseif ($comm) {
        $fin = strtotime($comm);
        $tipo = "Com";
    }

    if ($fin) {
        $mins = round(($fin - $inicio) / 60, 2);
        $tiempos[] = $mins;
        echo str_pad($tick_id, 10) . str_pad($a['fech_asig'], 20) . str_pad(date('Y-m-d H:i:s', $fin), 20) . $mins . " ($tipo)\n";
    }
}

if (!empty($tiempos)) {
    sort($tiempos);
    echo "\nTiempos Ordenados: [" . implode(", ", $tiempos) . "]\n";
} else {
    echo "\nNo hay tiempos registrados.\n";
}

echo "\n=================================================\n";
