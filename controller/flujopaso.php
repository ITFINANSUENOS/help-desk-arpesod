<?php
require_once('../config/conexion.php');
require_once('../models/FlujoPaso.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$flujopaso = new FlujoPaso();

switch ($_GET["op"]) {
    case "combo":
        $datos = $flujopaso->get_flujopaso();
        if (is_array($datos) and count($datos) > 0) {
            $html = "";
            foreach ($datos as $row) {
                // CORREGIDO: La columna se llama 'paso_nombre', no 'flujopaso_nom'
                $html .= "<option value='" . $row["paso_id"] . "'>" . $row["paso_nombre"] . "</option>";
            }
            echo $html;
        }
        break;

    case "combo_por_flujo":
        if (isset($_POST["flujo_id"])) {
            // Necesitas una función en tu modelo FlujoPaso que obtenga los pasos por flujo_id
            $datos = $flujopaso->get_pasos_por_flujo($_POST["flujo_id"]);
            $html = "<option value='' selected disabled>-- Seleccione un Paso --</option>";
            if (is_array($datos) && count($datos) > 0) {
                foreach ($datos as $row) {
                    // El valor es el ID del paso, el texto es el nombre del paso
                    $html .= "<option value='" . $row['paso_id'] . "'>" . $row['paso_nombre'] . "</option>";
                }
            }
            echo $html;
        }
        break;

    case "guardaryeditar":
        $requiere_seleccion_manual = isset($_POST['requiere_seleccion_manual']) ? 1 : 0;
        $es_tarea_nacional = isset($_POST['es_tarea_nacional']) ? 1 : 0;
        $es_aprobacion = isset($_POST['es_aprobacion']) ? 1 : 0;
        $permite_cerrar = isset($_POST['permite_cerrar']) ? 1 : 0;
        $necesita_aprobacion_jefe = isset($_POST['necesita_aprobacion_jefe']) ? 1 : 0;
        $es_paralelo = isset($_POST['es_paralelo']) ? 1 : 0;
        $requiere_firma = isset($_POST['requiere_firma']) ? 1 : 0;
        $requiere_campos_plantilla = isset($_POST['requiere_campos_plantilla']) ? 1 : 0;
        $asignar_a_creador = isset($_POST['asignar_a_creador']) ? 1 : 0;
        $cerrar_ticket_obligatorio = isset($_POST['cerrar_ticket_obligatorio']) ? 1 : 0;
        $permite_despacho_masivo = isset($_POST['permite_despacho_masivo']) ? 1 : 0;

        $paso_nom_adjunto = '';
        if (isset($_FILES['paso_nom_adjunto']) && $_FILES['paso_nom_adjunto']['name'] != '') {
            $file_name = $_FILES['paso_nom_adjunto']['name'];
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = uniqid('paso_', true) . '.' . $extension;
            $destination_path = '../public/document/paso/' . $new_file_name;
            if (move_uploaded_file($_FILES['paso_nom_adjunto']['tmp_name'], $destination_path)) {
                $paso_nom_adjunto = $new_file_name;
            } else {
                $paso_nom_adjunto = '';
            }
        } else {
            $paso_nom_adjunto = isset($_POST['current_paso_nom_adjunto']) ? $_POST['current_paso_nom_adjunto'] : '';
        }

        $cargo_id_asignado = isset($_POST['cargo_id_asignado']) ? $_POST['cargo_id_asignado'] : null;
        $campo_id_referencia_jefe = isset($_POST['campo_id_referencia_jefe']) && $_POST['campo_id_referencia_jefe'] !== '' ? $_POST['campo_id_referencia_jefe'] : null;

        if (empty($_POST['paso_id'])) {
            $paso_id = $flujopaso->insert_paso(
                $_POST['flujo_id'],
                $_POST['paso_orden'],
                $_POST['paso_nombre'],
                $cargo_id_asignado,
                $_POST['paso_tiempo_habil'],
                $_POST['paso_descripcion'],
                $requiere_seleccion_manual,
                $es_tarea_nacional,
                $es_aprobacion,
                $paso_nom_adjunto,
                $permite_cerrar,
                $necesita_aprobacion_jefe,
                $es_paralelo,
                $requiere_firma,
                $requiere_campos_plantilla,
                $campo_id_referencia_jefe,
                $asignar_a_creador,
                $cerrar_ticket_obligatorio,
                $permite_despacho_masivo
            );
        } else {
            $paso_id = $_POST['paso_id'];
            $flujopaso->update_paso(
                $paso_id,
                $_POST['paso_orden'],
                $_POST['paso_nombre'],
                $cargo_id_asignado,
                $_POST['paso_tiempo_habil'],
                $_POST['paso_descripcion'],
                $requiere_seleccion_manual,
                $es_tarea_nacional,
                $es_aprobacion,
                $paso_nom_adjunto,
                $permite_cerrar,
                $necesita_aprobacion_jefe,
                $es_paralelo,
                $requiere_firma,
                $requiere_campos_plantilla,
                $campo_id_referencia_jefe,
                $asignar_a_creador,
                $cerrar_ticket_obligatorio,
                $permite_despacho_masivo
            );
        }

        if (($requiere_seleccion_manual || $es_paralelo)) {
            $usuarios_especificos = isset($_POST['usuarios_especificos']) && is_array($_POST['usuarios_especificos']) ? $_POST['usuarios_especificos'] : [];
            $cargos_especificos = isset($_POST['cargos_especificos']) && is_array($_POST['cargos_especificos']) ? $_POST['cargos_especificos'] : [];

            $flujopaso->set_usuarios_especificos($paso_id, $usuarios_especificos, $cargos_especificos);
        } else {
            $flujopaso->set_usuarios_especificos($paso_id, [], []);
        }

        if ($requiere_firma && isset($_POST['firma_config'])) {
            $firma_config = json_decode($_POST['firma_config'], true);
            $flujopaso->set_firma_config($paso_id, $firma_config);
        } else if (!$requiere_firma) {
            $flujopaso->set_firma_config($paso_id, []);
        }

        if ($requiere_campos_plantilla && isset($_POST['campos_plantilla_config'])) {
            $campos_config = json_decode($_POST['campos_plantilla_config'], true);
            $flujopaso->set_campos_plantilla($paso_id, $campos_config);
        } else if (!$requiere_campos_plantilla) {
            $flujopaso->set_campos_plantilla($paso_id, []);
        }

        break;

    case "listar":
        $datos = $flujopaso->get_pasos_por_flujo($_POST['flujo_id']);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row["paso_orden"];
            $sub_array[] = $row["paso_nombre"];
            $sub_array[] = $row["car_nom"] ?? 'JEFE INMEDIATO';
            $sub_array[] = ($row["requiere_seleccion_manual"] == 1) ? '<span class="label label-info">Sí</span>' : '<span class="label label-default">No</span>';
            $sub_array[] = ($row["es_tarea_nacional"] == 1) ? '<span class="label label-info">Sí</span>' : '<span class="label label-default">No</span>';
            $sub_array[] = ($row["es_aprobacion"] == 1) ? '<span class="label label-info">Sí</span>' : '<span class="label label-default">No</span>';
            $sub_array[] = '<button type="button" onClick="editar(' . $row['paso_id'] . ');" class="btn btn-inline btn-warning btn-sm"><i class="fa fa-edit"></i></button>';
            $sub_array[] = '<button type="button" onClick="eliminar(' . $row['paso_id'] . ');" class="btn btn-inline btn-danger btn-sm"><i class="fa fa-trash"></i></button>';
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

    case "get_transiciones_inicio":
        $flujo_id = isset($_POST["flujo_id"]) ? $_POST["flujo_id"] : null;

        if (!$flujo_id && isset($_POST["cats_id"])) {
            require_once("../models/Flujo.php");
            $flujoModel = new Flujo();
            $flujo = $flujoModel->get_flujo_por_subcategoria($_POST["cats_id"]);
            $flujo_id = isset($flujo['flujo_id']) ? $flujo['flujo_id'] : null;
        }

        if ($flujo_id) {
            $datos = $flujopaso->get_transiciones_inicio($flujo_id);
            echo json_encode($datos);
        } else {
            echo json_encode([]);
        }
        break;

    case "eliminar":
        $flujopaso->delete_paso($_POST["paso_id"]);
        break;

    case "get_siguientes_pasos":
        $datos = $flujopaso->get_siguientes_pasos($_POST['paso_actual_id']);
        echo json_encode($datos);
        break;

    case "get_usuarios_por_paso":
        $paso_id = $_POST['paso_id'];
        $paso_data = $flujopaso->get_paso_por_id($paso_id);
        if ($paso_data) {
            $cargo_id_necesario = $paso_data['cargo_id_asignado'];
            $usuarios = $usuario->get_usuarios_por_cargo($cargo_id_necesario);

            $html = "<option value=''>Seleccione un Agente</option>";
            if (is_array($usuarios) && count($usuarios) > 0) {
                foreach ($usuarios as $row) {
                    $html .= "<option value='" . $row["usu_id"] . "'>" . $row["usu_nom"] . " " . $row["usu_ape"] . "</option>";
                }
            }
            echo $html;
        }
        break;

    case "mostrar":
        $datos = $flujopaso->get_paso_por_id($_POST['paso_id']);
        if ($datos) {
            $output = $datos;
            $output['requiere_seleccion_manual'] = $datos['requiere_seleccion_manual'];
            $output['es_aprobacion'] = $datos['es_aprobacion'];
            $output['permite_cerrar'] = $datos['permite_cerrar'];
            $output['necesita_aprobacion_jefe'] = isset($datos['necesita_aprobacion_jefe']) ? $datos['necesita_aprobacion_jefe'] : 0;
            $output['es_paralelo'] = isset($datos['es_paralelo']) ? $datos['es_paralelo'] : 0;
            $output['paso_nom_adjunto'] = isset($datos['paso_nom_adjunto']) ? $datos['paso_nom_adjunto'] : null;
            $output['requiere_campos_plantilla'] = isset($datos['requiere_campos_plantilla']) ? $datos['requiere_campos_plantilla'] : 0;
            $output['asignar_a_creador'] = isset($datos['asignar_a_creador']) ? $datos['asignar_a_creador'] : 0;
            $output['cerrar_ticket_obligatorio'] = isset($datos['cerrar_ticket_obligatorio']) ? $datos['cerrar_ticket_obligatorio'] : 0;
            $output['permite_despacho_masivo'] = isset($datos['permite_despacho_masivo']) ? $datos['permite_despacho_masivo'] : 0;
            echo json_encode($output);
        }
        break;

    case "get_campos_primer_paso":
        require_once('../models/Flujo.php');
        require_once('../models/CampoPlantilla.php');
        $flujoModel = new Flujo();
        $campoModel = new CampoPlantilla();

        $cats_id = $_POST['cats_id'];
        $flujo = $flujoModel->get_flujo_por_subcategoria($cats_id);

        if ($flujo && isset($flujo['flujo_id'])) {
            $pasos = $flujopaso->get_pasos_por_flujo($flujo['flujo_id']);
            if (count($pasos) > 0) {
                $primer_paso = $pasos[0]; // Ordered by paso_orden ASC
                if ($primer_paso['requiere_campos_plantilla'] == 1) {
                    $campos = $campoModel->get_campos_por_paso($primer_paso['paso_id']);

                    // Inject Server Side Date
                    foreach ($campos as &$campo) {
                        // error_log("Campo: " . $campo['campo_nombre'] . " Query: [" . $campo['campo_query'] . "]");
                        if (trim($campo['campo_query']) === 'PRESET_FECHA_ACTUAL') {
                            $campo['prefilled_value'] = date("Y-m-d H:i:s"); // Format: YYYY-MM-DD HH:mm:ss
                            $campo['is_readonly'] = true;
                            // error_log("Injecting date for " . $campo['campo_nombre']);
                        }
                    }
                    unset($campo); // Break reference

                    echo json_encode(['requiere' => true, 'campos' => $campos]);
                    return;
                }
            }
        }
        echo json_encode(['requiere' => false]);
        break;

    case "get_campos_paso":
        require_once('../models/CampoPlantilla.php');
        $campoModel = new CampoPlantilla();
        $paso_id = $_POST['paso_id'];

        // Primero verificamos si el paso requiere campos
        $paso_data = $flujopaso->get_paso_por_id($paso_id);

        if ($paso_data && $paso_data['requiere_campos_plantilla'] == 1) {
            $campos = $campoModel->get_campos_por_paso($paso_id);
            echo json_encode(['requiere' => true, 'campos' => $campos]);
        } else {
            echo json_encode(['requiere' => false]);
        }
        break;
    case "get_pdf_path":
        $paso_id = isset($_POST["paso_id"]) ? $_POST["paso_id"] : null;
        $flujo_id = isset($_POST["flujo_id"]) ? $_POST["flujo_id"] : null;

        $path = "";

        // 1. Try to get step-specific PDF
        if (!empty($paso_id)) {
            $paso_info = $flujopaso->get_paso_por_id($paso_id);
            if (!empty($paso_info['paso_nom_adjunto'])) {
                $path = '../../public/document/paso/' . $paso_info['paso_nom_adjunto'];
            }
        }

        // 2. Fallback to flow-default PDF
        if (empty($path) && !empty($flujo_id)) {
            require_once("../models/Flujo.php");
            $flujoModel = new Flujo();
            $flujo_info = $flujoModel->get_flujo_x_id($flujo_id);
            if (!empty($flujo_info['flujo']['flujo_nom_adjunto'])) {
                $path = '../../public/document/flujo/' . $flujo_info['flujo']['flujo_nom_adjunto'];
            } else {
                // 3. Fallback to ANY company template
                $plantilla_cualquiera = $flujoModel->get_plantilla_cualquiera($flujo_id);
                if (!empty($plantilla_cualquiera)) {
                    $path = '../../public/document/flujo/' . $plantilla_cualquiera;
                }
            }
        }

        if (!empty($path) && file_exists(str_replace('../../', '../', $path))) {
            echo json_encode(["status" => "success", "path" => $path]);
        } else {
            echo json_encode(["status" => "error", "message" => "No se encontró plantilla PDF."]);
        }
        break;
}
