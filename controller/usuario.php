<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('../config/conexion.php');
require_once('../models/Usuario.php');
require_once('../models/Empresa.php');

$usuario = new Usuario();
$empresa = new Empresa();

switch ($_GET["op"]) {

    case "guardaryeditar":
        $perfiles = isset($_POST['perfiles']) ? $_POST['perfiles'] : [];

        if (empty($_POST["usu_id"])) {
            $usu_id = $usuario->insert_usuario($_POST["usu_nom"], $_POST["usu_ape"], $_POST["usu_correo"], $_POST["usu_pass"], $_POST["rol_id"], $_POST['dp_id'], $_POST['es_nacional'], $_POST['reg_id'], $_POST['car_id']);
            $empresa->insert_empresa_for_usu($usu_id, $_POST['emp_id']);
            $usuario->insert_usuario_perfil($usu_id, $perfiles);
        } else {
            // var_dump($_POST);
            $usu_id = $usuario->update_usuario($_POST["usu_id"], $_POST["usu_nom"], $_POST["usu_ape"], $_POST["usu_correo"], $_POST["usu_pass"], $_POST["rol_id"], $_POST['dp_id'], $_POST['es_nacional'], $_POST['reg_id'], $_POST['car_id']);
            $empresa->insert_empresa_for_usu($usu_id, $_POST['emp_id']);
            $usuario->insert_usuario_perfil($usu_id, $perfiles);
        }
        break;

    case "listar":

        $datos = $usuario->get_usuario();
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['usu_nom'];
            $sub_array[] = $row['usu_ape'];
            $sub_array[] = isset($row['dp_nom']) ? $row['dp_nom'] : 'Sin departamento';
            $sub_array[] = $row['usu_correo'];

            if ($row['rol_id'] == 1) {
                $sub_array[] = '<span class="label label-primary">Usuario</span>';
            } else {
                $sub_array[] = '<span class="label label-info">Soporte</span>';
            }

            $sub_array[] = '<button type="button" onClick="editar(' . $row['usu_id'] . ');" id="' . $row['usu_id'] . '" class="btn btn-inline btn-waring btn-sm ladda-button"><i class="fa fa-edit"></i></button>';
            $sub_array[] = '<button type="button" onClick="eliminar(' . $row['usu_id'] . ');" id="' . $row['usu_id'] . '" class="btn btn-inline btn-danger btn-sm ladda-button"><i class="fa fa-trash"></i></button>';

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
        $usuario->delete_usuario($_POST["usu_id"]);
        break;

    case "mostrar":
        $usu_id = isset($_POST['usu_id']) ? $_POST['usu_id'] : (isset($_GET['usu_id']) ? $_GET['usu_id'] : null);
        $output = [];
        if ($usu_id) {
            $datos = $usuario->get_usuario_x_id($usu_id);
            if (is_array($datos) == true and count($datos) > 0) {
                $row = $datos;
                $output['usu_id'] = $row['usu_id'];
                $output['emp_ids'] = $row['emp_ids'];
                $output['usu_nom'] = $row['usu_nom'];
                $output['usu_ape'] = $row['usu_ape'];
                $output['usu_correo'] = $row['usu_correo'];
                $output['usu_pass'] = $row['usu_pass'];
                $output['rol_id'] = $row['rol_id'];
                $output['dp_id'] = $row['dp_id'];
                $output['reg_id'] = $row['reg_id'];
                $output['car_id'] = $row['car_id'];
                $output['car_id'] = $row['car_id'];
                $output['es_nacional'] = $row['es_nacional'];

                // Get assigned profiles
                $perfiles_data = $usuario->get_perfiles_por_usuario($usu_id);
                $perfiles_ids = array();
                foreach ($perfiles_data as $p) {
                    $perfiles_ids[] = $p['per_id'];
                }
                $output['perfiles'] = $perfiles_ids;
            }
        }
        echo json_encode($output);
        break;

    case "total":
        $datos = $usuario->get_usuario_total_id($_POST['usu_id']);
        if (is_array($datos) == true and count($datos) > 0) {
            foreach ($datos as $row) {
                $output['TOTAL'] = $row['TOTAL'];
            }
            echo json_encode($output);
        }
        break;

    case "totalabierto":
        $datos = $usuario->get_usuario_totalabierto_id($_POST['usu_id']);
        if (is_array($datos) == true and count($datos) > 0) {
            foreach ($datos as $row) {
                $output['TOTAL'] = $row['TOTAL'];
            }
            echo json_encode($output);
        }
        break;

    case "totalcerrado":
        $datos = $usuario->get_usuario_totalcerrado_id($_POST['usu_id']);
        if (is_array($datos) == true and count($datos) > 0) {
            foreach ($datos as $row) {
                $output['TOTAL'] = $row['TOTAL'];
            }
            echo json_encode($output);
        }
        break;

    case "graficousuario":
        $datos = $usuario->get_total_categoria_usuario($_POST["usu_id"]);
        echo json_encode($datos);
        break;

    case "usuariosxrol":
        $datos = $usuario->get_usuario_x_rol();
        if (is_array($datos) == true and count($datos) > 0) {
            $html = "";
            foreach ($datos as $row) {
                $html .= "<option value='" . $row['usu_id'] . "'>" . $row['usu_nom'] . " " . $row['usu_ape'] . "</option>";
            }
            echo $html;
        }
        break;

    case "usuariosxdepartamento":
        $datos = $usuario->get_usuario_x_departamento($_POST['dp_id']);
        if (is_array($datos) == true and count($datos) > 0) {
            $html = "";
            $html .= "<option label='Seleccionar'></option>";
            foreach ($datos as $row) {
                $html .= "<option value='" . $row['usu_id'] . "'>" . $row['usu_nom'] . " " . $row['usu_ape'] . "</option>";
            }
            echo $html;
        }
        break;

    case "combo":
        $datos = $usuario->get_usuario();
        if (is_array($datos) and count($datos) > 0) {
            $html = "";
            foreach ($datos as $row) {
                $html .= "<option value='" . $row['usu_id'] . "'>" . $row['usu_nom'] . " " . $row['usu_ape'] . "</option>";
            }
            echo $html;
        }
        break;

    case "combo_usuarios_select2":
        $datos = $usuario->get_usuario();
        $data = array();
        if (is_array($datos) and count($datos) > 0) {
            foreach ($datos as $row) {
                $text = $row['usu_nom'] . " " . $row['usu_ape'];
                // Filtrar por término de búsqueda si existe
                if (isset($_GET['q']) && !empty($_GET['q'])) {
                    if (stripos($text, $_GET['q']) === false) {
                        continue;
                    }
                }
                $data[] = array("id" => $row['usu_id'], "text" => $text);
            }
        }
        echo json_encode($data);
        break;

    case "guardar_firma":
        $usu_id = $_POST['usu_id'];
        $firma_nombre = '';

        if (isset($_FILES['usu_firma']) && $_FILES['usu_firma']['error'] == 0) {
            $extension = pathinfo($_FILES['usu_firma']['name'], PATHINFO_EXTENSION);
            $firma_nombre = "firma_" . $usu_id . "_" . time() . "." . $extension;
            $carpeta_usuario = '../public/img/firmas/' . $usu_id . '/';
            $ruta_destino = $carpeta_usuario . $firma_nombre;

            if (!file_exists($carpeta_usuario)) {
                mkdir($carpeta_usuario, 0777, true);
            }

            if (move_uploaded_file($_FILES['usu_firma']['tmp_name'], $ruta_destino)) {
                $usuario->update_firma($usu_id, $firma_nombre);
                echo json_encode(['status' => 'success', 'message' => 'Firma actualizada correctamente', 'firma' => $firma_nombre]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al subir la imagen']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se seleccionó ninguna imagen']);
        }
        break;

    case "obtener_firma":
        $usu_id = $_POST['usu_id'];
        $datos = $usuario->get_usuario_x_id($usu_id);
        if (is_array($datos) == true and count($datos) > 0) {
            if (!empty($datos['usu_firma'])) {
                echo json_encode(['status' => 'success', 'firma' => $datos['usu_firma']]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No hay firma registrada']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
        }
        break;
    case "recuperar":
        $usu_correo = $_POST["usu_correo"];
        $token = $usuario->generar_token_recuperacion($usu_correo);
        if ($token) {
            require_once("../models/Email.php");
            $email = new Email();
            $link = $usuario->ruta() . "view/Recuperar/restablecer.php?token=" . $token;
            // Send email (handling exceptions implicitly or we could try-catch)
            try {
                if ($email->recuperar_contrasena($usu_correo, $link)) {
                    echo "1";
                } else {
                    echo "Error Sending: " . $email->ErrorInfo;
                }
            } catch (Exception $e) {
                echo "Exception: " . $e->getMessage();
            }
        } else {
            echo "2"; // User not found
        }
        break;

    case "restablecer":
        $token = $_POST["token"];
        $usu_pass = $_POST["usu_pass"];
        $datos = $usuario->validar_token_recuperacion($token);
        if (is_array($datos) && count($datos) > 0) {
            $usuario->restablecer_contrasena($token, $usu_pass);
            echo "1";
        } else {
            echo "0";
        }
        break;
}
