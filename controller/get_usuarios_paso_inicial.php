<?php

/**
 * Endpoint: Obtener usuarios disponibles para un paso destino
 * 
 * Este endpoint se llama cuando el usuario selecciona una decisión inicial
 * para verificar si hay múltiples usuarios disponibles y mostrar un select.
 */

require_once('../config/conexion.php');
require_once('../models/FlujoPaso.php');
require_once('../models/RutaPaso.php');
require_once('../models/Usuario.php');
require_once('../models/Ticket.php');

header('Content-Type: application/json');

try {
    $paso_inicio_value = $_POST['paso_inicio_id'] ?? '';
    $cats_id = $_POST['cats_id'] ?? null;
    $reg_id = $_POST['reg_id'] ?? null; // Regional del ticket

    if (empty($paso_inicio_value)) {
        echo json_encode(['success' => false, 'message' => 'paso_inicio_id requerido']);
        exit;
    }

    $flujoPasoModel = new FlujoPaso();
    $usuarioModel = new Usuario();

    // Parsear el paso_inicio_id
    $paso_id = null;
    $ruta_id = null;

    if (strpos($paso_inicio_value, 'ruta:') === 0) {
        // Es una ruta - obtener el primer paso
        $ruta_id = substr($paso_inicio_value, 5);
        require_once('../models/RutaPaso.php');
        $rutaPasoModel = new RutaPaso();
        $primer_paso_ruta = $rutaPasoModel->get_paso_por_orden($ruta_id, 1);

        if ($primer_paso_ruta) {
            $paso_id = $primer_paso_ruta['paso_id'];
        } else {
            echo json_encode(['success' => false, 'message' => 'La ruta no tiene pasos definidos']);
            exit;
        }
    } elseif (strpos($paso_inicio_value, 'paso:') === 0) {
        // Es un paso directo
        $paso_id = substr($paso_inicio_value, 5);
    } else {
        // Formato antiguo
        $paso_id = $paso_inicio_value;
    }

    // Obtener información del paso
    $paso_info = $flujoPasoModel->get_paso_por_id($paso_id);

    if (!$paso_info) {
        echo json_encode(['success' => false, 'message' => 'Paso no encontrado']);
        exit;
    }

    // Verificar si requiere selección manual
    if ($paso_info['requiere_seleccion_manual'] == 1) {
        // Obtener usuarios específicos o por cargo
        $usuarios_especificos = $flujoPasoModel->get_usuarios_especificos($paso_id);

        if (!empty($usuarios_especificos)) {
            $usuarios = $usuarioModel->get_usuarios_por_ids($usuarios_especificos);
        } else {
            $cargo_id = $paso_info['cargo_id_asignado'];
            if ($cargo_id) {
                $usuarios = $usuarioModel->get_usuarios_por_cargo($cargo_id);
            } else {
                $usuarios = [];
            }
        }

        if (count($usuarios) > 1) {
            echo json_encode([
                'success' => true,
                'requiere_seleccion' => true,
                'usuarios' => $usuarios,
                'paso_nombre' => $paso_info['paso_nombre']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'requiere_seleccion' => false
            ]);
        }
    } else {
        // No requiere selección manual, verificar si hay múltiples usuarios por cargo
        $cargo_id = $paso_info['cargo_id_asignado'];

        if (!$cargo_id) {
            echo json_encode(['success' => true, 'requiere_seleccion' => false]);
            exit;
        }

        $is_national = (!empty($paso_info['es_tarea_nacional']) && $paso_info['es_tarea_nacional'] == 1);

        if ($is_national) {
            $usuarios = $usuarioModel->get_usuarios_por_cargo($cargo_id);
        } else {
            if (!$reg_id) {
                // Si no tenemos regional, no podemos determinar usuarios
                echo json_encode(['success' => true, 'requiere_seleccion' => false]);
                exit;
            }
            $usuarios = $usuarioModel->get_usuarios_por_cargo_y_regional_all($cargo_id, $reg_id);
        }

        if (count($usuarios) > 1) {
            echo json_encode([
                'success' => true,
                'requiere_seleccion' => true,
                'usuarios' => $usuarios,
                'paso_nombre' => $paso_info['paso_nombre']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'requiere_seleccion' => false
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
