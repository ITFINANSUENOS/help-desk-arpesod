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

    // Crear nueva etiqueta global (si hiciéramos mantenimiento de etiquetas)
    case "guardar":
        if (isset($_POST["eti_nom"])) {
            $usu_id = $_SESSION["usu_id"];
            $etiqueta->insert_etiqueta($usu_id, $_POST["eti_nom"], $_POST["eti_color"]);
            echo "1";
        }
        break;
}
