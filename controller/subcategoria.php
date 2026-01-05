<?php
require_once('../config/conexion.php');
require_once('../models/Subcategoria.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$subcategoria = new Subcategoria();

switch ($_GET["op"]) {
    case "combo":

        $datos = $subcategoria->get_subcategoria($_POST['cat_id']);
        if (is_array($datos) and count($datos) > 0) {
            $html = "";
            foreach ($datos as $row) {
                $html .= "<option value='" . $row["cats_id"] . "'>" . $row["cats_nom"] . "</option>";
            }
            echo $html;
        }

        break;

    case "combo_all":
        $datos = $subcategoria->get_subcategoriatodo();
        if (is_array($datos) and count($datos) > 0) {
            $html = "<option value=''>Seleccionar Subcategoria</option>";
            foreach ($datos as $row) {
                $html .= "<option value='" . $row["cats_id"] . "'>" . $row["cats_nom"] . "</option>";
            }
            echo $html;
        }
        break;

    case "combo_usuario_tickets":
        $usu_id = $_SESSION["usu_id"];

        // 1. Get Hierarchy Scope using Kpi Model
        require_once("../models/Kpi.php");
        $kpi = new Kpi();
        $scope = $kpi->get_hierarchy_scope($usu_id);

        // 2. Fetch subcats for this scope
        $datos = $subcategoria->get_subcategorias_scope($scope);

        if (is_array($datos) and count($datos) > 0) {
            $html = "<option value=''>Seleccionar Subcategoria</option>";
            foreach ($datos as $row) {
                $html .= "<option value='" . $row["cats_id"] . "'>" . $row["cats_nom"] . "</option>";
            }
            echo $html;
        } else {
            echo "<option value=''>Seleccionar Subcategoria</option>";
        }
        break;

    case "guardaryeditar":

        if (empty($_POST['cats_id'])) {
            $subcategoria->insert_subcategoria($_POST['cat_id'], $_POST['pd_id'], $_POST['cats_nom'], $_POST['cats_descrip']);
        } else {
            $subcategoria->update_subcategoria($_POST['cats_id'], $_POST['cat_id'], $_POST['pd_id'], $_POST['cats_nom'], $_POST['cats_descrip']);
        }

        break;

    case "listar":
        $datos = $subcategoria->get_subcategoriatodo();
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row["cat_nom"];
            $sub_array[] = $row["cats_nom"];
            $sub_array[] = $row["pd_nom"];
            $sub_array[] = '<button type="button" onClick="editar(' . $row['cats_id'] . ');" id="' . $row['cats_id'] . '" class="btn btn-inline btn-waring btn-sm ladda-button"><i class="fa fa-edit"></i></button>';
            $sub_array[] = '<button type="button" onClick="eliminar(' . $row['cats_id'] . ');" id="' . $row['cats_id'] . '" class="btn btn-inline btn-danger btn-sm ladda-button"><i class="fa fa-trash"></i></button>';
            $data[] = $sub_array;
        }
        $result = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($result);
        break;

    case "eliminar":
        $subcategoria->delete_subcategoria($_POST["cats_id"]);

        break;

    case "mostrar":
        // 1. Llamamos a la nueva función del modelo que devuelve el array estructurado
        $datos = $subcategoria->get_subcategoria_x_id($_POST['cats_id']);

        // 2. Si se encontraron datos, simplemente los enviamos como JSON
        if ($datos) {
            echo json_encode($datos);
        } else {
            // Opcional: manejar el caso de que no se encuentre la subcategoría
            echo json_encode(["error" => "Subcategoría no encontrada"]);
        }
        break;

    case "combo_filtrado":
        // Validar entrada
        $creador_car_id = isset($_POST['creador_car_id']) ? intval($_POST['creador_car_id']) : 0;
        $dp_id = isset($_POST['dp_id']) && !empty($_POST['dp_id']) ? intval($_POST['dp_id']) : null;

        // 1. Obtener perfiles del usuario desde la sesión
        $creador_per_ids = [];
        if (isset($_SESSION['usu_id'])) {
            require_once("../models/Usuario.php");
            $usuario = new Usuario();
            $perfiles = $usuario->get_perfiles_por_usuario($_SESSION['usu_id']);
            $creador_per_ids = array_column($perfiles, 'per_id');
        }

        // Consultar datos en el modelo
        $datos = $subcategoria->get_subcategorias_filtradas($creador_car_id, $creador_per_ids, $dp_id);

        $html = "";
        if (is_array($datos) && count($datos) > 0) {
            foreach ($datos as $row) {
                $html .= "<option value='" . htmlspecialchars($row["cats_id"]) . "'>"
                    . htmlspecialchars($row["cats_nom"])
                    . "</option>";
            }
        } else {
            $html = "<option value=''>No hay resultados</option>";
        }

        // Devolver ambos: html y datos
        echo json_encode([
            "html" => $html,
            "datos" => $datos
        ]);
        break;
}
