<?php
require_once("../config/conexion.php");
require_once("../models/Kpi.php");

$kpi = new Kpi();

switch ($_GET["op"]) {
    case 'menukpi':
        $datos = $kpi->get_pasos_asignados($_POST["usu_id"]);
        $datos2 = $kpi->get_pasos_finalizados($_POST["usu_id"]);
        $datos3 = $kpi->get_mediana_respuesta($_POST["usu_id"]);

        if (is_array($datos) == true and count($datos) > 0) {
            foreach ($datos as $row) {
                $output["asignados"] = $row["total"];
            }
        } else {
            // Si devuelve int directamente (como asignados/finalizados), ajustar:
            $output["asignados"] = $datos;
        }

        // Ajuste porque get_pasos_finalizados devuelve int
        $output["finalizados"] = $datos2;

        // Ajuste para mediana
        $output["promedio"] = $datos3;

        // Formatear mediana a texto amigable
        if ($datos3 < 60) {
            $output["promedio_formato"] = $datos3 . " min";
        } else {
            $hours = floor($datos3 / 60);
            $mins = $datos3 % 60;

            if ($hours >= 24) {
                $days = floor($hours / 24);
                $remaining_hours = $hours % 24;
                $output["promedio_formato"] = $days . " días " . $remaining_hours . "h";
            } else {
                $output["promedio_formato"] = $hours . "h " . $mins . "m";
            }
        }

        echo json_encode($output);
        break;

    case 'dynamic_chart':
        $filters = [];
        if (!empty($_POST['dp_id'])) $filters['dp_id'] = $_POST['dp_id'];
        if (!empty($_POST['car_id'])) $filters['car_id'] = $_POST['car_id']; // New Cargo ID
        if (!empty($_POST['target_usu_id'])) $filters['target_usu_id'] = $_POST['target_usu_id'];
        if (!empty($_POST['cats_id'])) $filters['cats_id'] = $_POST['cats_id'];
        if (!empty($_POST['cat_id'])) $filters['cat_id'] = $_POST['cat_id']; // Filter by Category
        if (!empty($_POST['group_by'])) $filters['group_by'] = $_POST['group_by']; // Group By User/Cat

        $filters['view_mode'] = isset($_POST['view_mode']) ? $_POST['view_mode'] : 'dept';

        $datos = $kpi->get_dynamic_statistics($_POST["usu_id"], $filters);
        echo json_encode($datos);
        break;

    case 'subcat_metrics':
        $nombre = $_POST['subcat_name'];
        $usu_id = $_POST['usu_id']; // IMPORTANT
        $target_usu_id = isset($_POST['target_usu_id']) ? $_POST['target_usu_id'] : null;
        $datos = $kpi->get_subcategory_metrics($usu_id, $nombre, $target_usu_id);
        echo json_encode($datos);
        break;

    case 'detailed_stats':
        $subcat = isset($_POST['subcat_name']) ? $_POST['subcat_name'] : null;
        $usu_id = $_POST['usu_id']; // IMPORTANT
        $target_usu_id = isset($_POST['target_usu_id']) ? $_POST['target_usu_id'] : null;
        $datos = $kpi->get_detailed_user_stats($usu_id, $target_usu_id, $subcat);
        echo json_encode($datos, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;

    case 'user_novedades_details':
        $usu_id = $_POST['usu_id'];
        $subcat = isset($_POST['subcat_name']) ? $_POST['subcat_name'] : null;
        $datos = $kpi->get_novedades_details($usu_id, $subcat);
        echo json_encode($datos, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;

    case 'user_error_details':
        $usu_id = $_POST['usu_id'];
        $type = $_POST['type']; // 'process' or 'info'
        $subcat = isset($_POST['subcat_name']) ? $_POST['subcat_name'] : null;
        $role = isset($_POST['role']) ? $_POST['role'] : 'received';
        $datos = $kpi->get_error_details($usu_id, $type, $subcat, $role);
        $datos = utf8_converter($datos);
        echo json_encode($datos, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;

    case 'user_performance_details':
        $usu_id = $_POST['usu_id'];
        $type = $_POST['type']; // 'on_time' or 'late'
        $subcat = isset($_POST['subcat_name']) ? $_POST['subcat_name'] : null;
        $datos = $kpi->get_performance_details($usu_id, $type, $subcat);
        $datos = utf8_converter($datos);
        echo json_encode($datos, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
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

function utf8_converter($array)
{
    array_walk_recursive($array, function (&$item, $key) {
        if (!mb_detect_encoding($item, 'utf-8', true)) {
            $item = utf8_encode($item);
        }
    });
    return $array;
}
