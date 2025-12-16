<?php
require_once("../config/conexion.php");
require_once("../models/Kpi.php");

$kpi = new Kpi();

switch ($_GET["op"]) {
    case "menukpi":
        $usu_id = $_POST["usu_id"]; // O usar $_SESSION["usu_id"] si se prefiere seguridad

        if (empty($usu_id) && isset($_SESSION["usu_id"])) {
            $usu_id = $_SESSION["usu_id"];
        }

        $asignados = $kpi->get_pasos_asignados($usu_id);
        $finalizados = $kpi->get_pasos_finalizados($usu_id);
        $promedio = $kpi->get_mediana_respuesta($usu_id);

        $results = array(
            "asignados" => $asignados,
            "finalizados" => $finalizados,
            "promedio" => $promedio, // En minutos (MEDIANA)
            "promedio_formato" => formatTime($promedio)
        );

        echo json_encode($results);
        break;
}

function formatTime($minutes)
{
    if ($minutes == 0) return "0 min";

    $days = floor($minutes / 1440);
    $hours = floor(($minutes % 1440) / 60);
    $mins = $minutes % 60;

    $format = "";
    if ($days > 0) $format .= $days . "d ";
    if ($hours > 0) $format .= $hours . "h ";
    $format .= round($mins) . "m";

    return trim($format);
}
