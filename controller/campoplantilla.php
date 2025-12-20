<?php
require_once('../config/conexion.php');
require_once('../models/CampoPlantilla.php');

$campo = new CampoPlantilla();

switch ($_GET["op"]) {
    case "combo_flujo":
        $flujo_id = $_POST['flujo_id'];
        $datos = $campo->get_campos_por_flujo($flujo_id);

        $html = "<option value=''>-- Seleccione un Campo (Opcional) --</option>";
        if (is_array($datos) && count($datos) > 0) {
            foreach ($datos as $row) {
                $html .= "<option value='" . $row['campo_id'] . "'>" . $row['campo_nombre'] . " (" . $row['campo_codigo'] . ")</option>";
            }
        }
        echo $html;
        break;

    case "ejecutar_query":
        $campo_id = $_POST['campo_id'];
        $valor = $_POST['valor'];

        $datos = $campo->ejecutar_query_campo($campo_id, $valor);

        if ($datos) {
            echo json_encode(["status" => "success", "data" => $datos]);
        } else {
            echo json_encode(["status" => "error", "message" => "No se encontraron datos."]);
        }
        break;
}
