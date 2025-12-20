<?php
require_once("../config/conexion.php");
require_once("../models/Etiqueta.php");

$etiqueta = new Etiqueta();

switch ($_GET["op"]) {
    // Listar todas las etiquetas activas (combo o lista)
    case "combo":
        $usu_id = $_SESSION["usu_id"];
        $datos = $etiqueta->listar_etiquetas($usu_id);
        if (is_array($datos) == true and count($datos) > 0) {
            foreach ($datos as $row) {
                echo "<option value='" . $row['eti_id'] . "' data-color='" . $row['eti_color'] . "'>" . $row['eti_nom'] . "</option>";
            }
        }
        break;

    // Listar etiquetas asignadas a un ticket específico (JSON)
    case "listar_x_ticket":
        $usu_id = $_SESSION["usu_id"];
        $datos = $etiqueta->listar_etiquetas_x_ticket($_POST["tick_id"], $usu_id);
        echo json_encode($datos);
        break;

    // Asignar etiqueta a ticket
    case "asignar":
        // Validar que vengan datos
        if (isset($_POST["tick_id"]) && isset($_POST["eti_id"]) && isset($_POST["usu_id"])) {
            $etiqueta->asignar_etiqueta_ticket($_POST["tick_id"], $_POST["eti_id"], $_POST["usu_id"]);
            echo "1";
        } else {
            echo "0";
        }
        break;

    // Quitar etiqueta de ticket (por tick_id y eti_id)
    case "desligar":
        if (isset($_POST["tick_id"]) && isset($_POST["eti_id"])) {
            $etiqueta->desligar_etiqueta_ticket($_POST["tick_id"], $_POST["eti_id"]);
            echo "1";
        } else {
            echo "0";
        }
        break;

    // Quitar etiqueta de ticket (por tick_eti_id - Legacy/Direct)
    case "eliminar_ticket":
        if (isset($_POST["tick_eti_id"])) {
            $etiqueta->eliminar_etiqueta_ticket($_POST["tick_eti_id"]);
            echo "1";
        } else {
            echo "0";
        }
        break;

    // Guardar o Editar etiqueta (Si trae eti_id es edición)
    case "guardar":
        if (isset($_POST["eti_nom"])) {
            $usu_id = $_SESSION["usu_id"];
            if (empty($_POST["eti_id"])) {
                // Crear
                $etiqueta->insert_etiqueta($usu_id, $_POST["eti_nom"], $_POST["eti_color"]);
            } else {
                // Actualizar
                $etiqueta->update_etiqueta($_POST["eti_id"], $_POST["eti_nom"], $_POST["eti_color"]);
            }
            echo "1";
        }
        break;

    // Mostrar una etiqueta específica para edición
    case "mostrar":
        if (isset($_POST["eti_id"])) {
            $datos = $etiqueta->get_etiqueta_x_id($_POST["eti_id"]);
            echo json_encode($datos);
        }
        break;

    // Eliminar etiqueta globalmente (solo la propia del usuario)
    case "eliminar":
        if (isset($_POST["eti_id"])) {
            $etiqueta->delete_etiqueta($_POST["eti_id"]);
            echo "1";
        }
        break;

    // Listar mis etiquetas para gestión (Tabla)
    case "listar_mis_etiquetas":
        $usu_id = $_SESSION["usu_id"];
        $datos = $etiqueta->listar_etiquetas($usu_id);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $labelClass = "label label-" . $row['eti_color'];
            if ($row['eti_color'] == "secondary") $labelClass = "label label-default";
            if ($row['eti_color'] == "dark") $labelClass = "label label-primary";

            $sub_array[] = '<span class="' . $labelClass . '">' . $row['eti_nom'] . '</span>';
            $sub_array[] = '<button type="button" onClick="editarEtiqueta(' . $row['eti_id'] . ');"  id="' . $row['eti_id'] . '" class="btn btn-inline btn-warning btn-sm ladda-button"><i class="fa fa-edit"></i></button>';
            $sub_array[] = '<button type="button" onClick="eliminarEtiqueta(' . $row['eti_id'] . ');"  id="' . $row['eti_id'] . '" class="btn btn-inline btn-danger btn-sm ladda-button"><i class="fa fa-trash"></i></button>';
            $data[] = $sub_array;
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;
}
