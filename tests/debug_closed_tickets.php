<?php
if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde la consola.\n");
}

require_once __DIR__ . '/../config/conexion.php';

$usu_id = 120;
if (isset($argv[1])) {
    $usu_id = (int)$argv[1];
}

$conectar = Conectar::getConexion();

echo "\n=======================================================\n";
echo " DIAGNOSTICO DE TICKETS CERRADOS - USUARIO: " . $usu_id . "\n";
echo "=======================================================\n\n";

// 1. Conteo simple en tm_ticket
$sql_count = "SELECT count(*) FROM tm_ticket WHERE usu_asig = $usu_id AND tick_estado = 'Cerrado' AND est = 1";
$total_cerrados_asig = $conectar->query($sql_count)->fetchColumn();
echo "Total tickets en estado 'Cerrado' asignados actualmente a $usu_id: " . $total_cerrados_asig . "\n";


// 2. Conteo de KPI (con filtro de exclusión)
$sql_kpi = "SELECT COUNT(*) as total 
            FROM tm_ticket 
            WHERE usu_asig = $usu_id 
            AND tick_estado = 'Cerrado' 
            AND (how_asig != $usu_id OR how_asig IS NULL)
            AND est = 1";
$total_kpi = $conectar->query($sql_kpi)->fetchColumn();
echo "Total tickets 'Cerrado' KPI (Excluyendo auto-asignados): " . $total_kpi . "\n\n";


echo "--- DETALLE DE TICKETS CERRADOS por el Usuario $usu_id ---\n";
echo "Criterio: El usuario aparece como 'usu_asig' y estado es 'Cerrado'.\n\n";

echo str_pad("Ticket", 10) . str_pad("Asignado Por", 15) . str_pad("KPI?", 10) . "Razón\n";
echo str_repeat("-", 80) . "\n";

$sql_detail = "SELECT tick_id, how_asig FROM tm_ticket WHERE usu_asig = $usu_id AND tick_estado = 'Cerrado' AND est = 1";
$rows = $conectar->query($sql_detail)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $how = $r['how_asig'];
    $kpi_valid = ($how != $usu_id); // KPI cuenta si NO fue asignado por el mismo usuario

    $status = $kpi_valid ? "SI" : "NO";
    $reason = $kpi_valid ? "Asignado por otro ($how)" : "Auto-asignado/Creado por él mismo";

    echo str_pad($r['tick_id'], 10) . str_pad($how ? $how : "NULL", 15) . str_pad($status, 10) . $reason . "\n";
}

// 3. Revisión del Historial (Disclaimer)
echo "\n\n--- ANALISIS DEL HISTORIAL (Total 177 que mencionas) ---\n";
echo "Si ves 177 en el historial, probablemente son:\n";
echo "1. Tickets que el usuario cerró, pero YA NO TIENE ASIGNADOS (al cerrar se reasignan o cambian).\n";
echo "2. Tickets que el usuario movió (pasos finalizados) pero no necesariamente cerró.\n";

$sql_hist = "SELECT count(*) FROM th_ticket_asignacion WHERE (usu_asig = $usu_id OR how_asig = $usu_id) AND est = 1";
$hist_total = $conectar->query($sql_hist)->fetchColumn();
echo "Total registros en th_ticket_asignacion para usuario $usu_id: " . $hist_total . "\n";

echo "\n=======================================================\n";
