<?php
require_once("../config/conexion.php");
require_once("../models/Consulta.php");
$consulta = new Consulta();

switch ($_GET["op"]) {
    case "guardaryeditar":
        if (empty($_POST["cons_id"])) {
            $consulta->insert_consulta($_POST["cons_nom"], $_POST["cons_sql"]);
        } else {
            $consulta->update_consulta($_POST["cons_id"], $_POST["cons_nom"], $_POST["cons_sql"]);
        }
        break;

    case "listar":
        $datos = $consulta->get_consulta();
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row["cons_nom"];
            // Mostrar un previo corto del SQL
            $sub_array[] = substr($row["cons_sql"], 0, 50) . (strlen($row["cons_sql"]) > 50 ? "..." : "");
            $sub_array[] = '<button type="button" onClick="editar(' . $row["cons_id"] . ');"  id="' . $row["cons_id"] . '" class="btn btn-inline btn-warning btn-sm ladda-button"><i class="fa fa-edit"></i></button>';
            $sub_array[] = '<button type="button" onClick="eliminar(' . $row["cons_id"] . ');"  id="' . $row["cons_id"] . '" class="btn btn-inline btn-danger btn-sm ladda-button"><i class="fa fa-trash"></i></button>';
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

    case "eliminar":
        $consulta->delete_consulta($_POST["cons_id"]);
        break;

    case "mostrar":
        $datos = $consulta->get_consulta_x_id($_POST["cons_id"]);
        if (is_array($datos) == true and count($datos) > 0) {
            foreach ($datos as $row) {
                $output["cons_id"] = $row["cons_id"];
                $output["cons_nom"] = $row["cons_nom"];
                $output["cons_sql"] = $row["cons_sql"];
            }
            echo json_encode($output);
        }
        break;

    case "combo":
        $datos = $consulta->get_consulta();
        $html = "<option value=''>Ninguna</option>";
        if (is_array($datos) == true and count($datos) > 0) {
            foreach ($datos as $row) {
                $html .= "<option value='" . $row['cons_id'] . "'>" . $row['cons_nom'] . "</option>";
            }
        }
        $html .= "<option value='CUSTOM'>SQL Personalizado</option>";
        echo $html;
        break;
}
