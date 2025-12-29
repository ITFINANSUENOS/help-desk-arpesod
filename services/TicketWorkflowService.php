<?php

require_once('../models/DateHelper.php');
require_once('../models/FlujoPaso.php');
require_once('../models/Flujo.php');
require_once('../models/Usuario.php');
require_once('../models/Ticket.php');
require_once('../models/FlujoTransicion.php');

class TicketWorkflowService
{
    private $dateHelper;
    private $flujoPasoModel;
    private $flujoModel;
    private $usuarioModel;
    private $ticketModel;
    private $flujoTransicionModel;


    public function __construct()
    {
        $this->dateHelper = new DateHelper();
        $this->flujoPasoModel = new FlujoPaso();
        $this->usuarioModel = new Usuario();
        $this->ticketModel = new Ticket();
        $this->flujoModel = new Flujo();
        $this->flujoTransicionModel = new FlujoTransicion();
    }

    public function transitionStep($tick_id, $condicion_clave_or_step, $usu_id_que_acciona, $condicion_nombre = null)
    {
        // --- 1. Lógica de Medición de Tiempo del Paso Actual ---
        $estado_paso_actual = 'N/A';
        $asignacion_actual = $this->ticketModel->get_ultima_asignacion($tick_id);

        if ($asignacion_actual) {
            $paso_actual_info = $this->flujoPasoModel->get_paso_por_id($asignacion_actual['paso_actual_id']);
            if ($paso_actual_info && $paso_actual_info['paso_tiempo_habil'] > 0) {
                $fecha_limite = $this->dateHelper->calcularFechaLimiteHabil(
                    $asignacion_actual['fech_asig'],
                    $paso_actual_info['paso_tiempo_habil']
                );
                $estado_paso_actual = (new DateTime() > $fecha_limite) ? 'Atrasado' : 'A Tiempo';
            }
        }

        // --- 2. Obtener info del paso actual ---
        $ticket_data = $this->ticketModel->listar_ticket_x_id($tick_id);
        $paso_actual_id = $ticket_data['paso_actual_id'];

        // --- 3. Resolver siguiente paso ---
        $siguiente_paso = null;

        // Si nos pasaron un paso id numérico, usamos directamente ese paso
        if (is_numeric($condicion_clave_or_step)) {
            $paso_id = (int)$condicion_clave_or_step;
            $siguiente_paso = $this->flujoPasoModel->get_paso_por_id($paso_id);
        } else {
            // Normalizamos la clave (opcional)
            $condicion_clave = is_string($condicion_clave_or_step) ? trim($condicion_clave_or_step) : null;

            // Verificamos si el paso actual tiene transiciones
            $transiciones = $this->flujoTransicionModel->get_transiciones_por_paso($paso_actual_id);
            if ($transiciones && count($transiciones) > 0) {
                // Si hay transiciones, intentamos resolver por clave
                if ($condicion_clave && $condicion_nombre) {
                    $siguiente_paso = $this->flujoPasoModel->get_siguiente_paso_transicion($paso_actual_id, $condicion_clave, $condicion_nombre);
                } else {
                    // Si no llegó clave y hay transiciones, no avanzamos (podrías lanzar excepción o log)
                    $siguiente_paso = null;
                }
            } else {
                // Paso lineal: avanzamos al siguiente predefinido (si existe)
                if (!empty($paso_actual_id)) {
                    $siguiente_paso = $this->flujoPasoModel->get_siguientes_pasos($paso_actual_id);
                }
            }
        }

        // --- 4. Procesar avance (si se encontró siguiente paso) ---
        if ($siguiente_paso) {
            // Lógica de asignación automática (igual que antes)
            $nuevo_asignado_info = null;
            $siguiente_cargo_id = $siguiente_paso["cargo_id_asignado"] ?? null;

            if (isset($_POST['nuevo_asignado_id']) && !empty($_POST['nuevo_asignado_id'])) {
                $nuevo_asignado_info = $this->usuarioModel->get_usuario_x_id((int)$_POST['nuevo_asignado_id']);
            } else {
                if (!empty($siguiente_paso['es_tarea_nacional']) && $siguiente_paso['es_tarea_nacional'] == 1) {
                    $nuevo_asignado_info = $this->usuarioModel->get_usuario_nacional_por_cargo($siguiente_cargo_id);
                } else {
                    $regional_origen_id = $this->ticketModel->get_ticket_region($tick_id);
                    $nuevo_asignado_info = $this->usuarioModel->get_usuario_por_cargo_y_regional(
                        $siguiente_cargo_id,
                        $regional_origen_id
                    );
                }
            }

            if ($nuevo_asignado_info) {
                $nuevo_usuario_asignado = $nuevo_asignado_info["usu_id"];
                $this->ticketModel->update_asignacion_y_paso(
                    $tick_id,
                    $nuevo_usuario_asignado,
                    $siguiente_paso['paso_id'],
                    $usu_id_que_acciona
                );

                if ($asignacion_actual) {
                    $this->ticketModel->update_estado_tiempo_paso(
                        $asignacion_actual['th_id'],
                        $estado_paso_actual
                    );
                }
            } else {
                // Manejar error: no se encontró asignado. Puedes loguear o lanzar excepción.
            }
        } else {
            // --- 5. Si no hay siguiente paso --> cerrar
            if ($asignacion_actual) {
                $this->ticketModel->update_estado_tiempo_paso(
                    $asignacion_actual['th_id'],
                    $estado_paso_actual
                );
            }
            $this->ticketModel->update_ticket($tick_id);
            $this->ticketModel->insert_ticket_detalle_cerrar($tick_id, $usu_id_que_acciona);
        }
    }

    public function ApproveFlow($tickPost, $session)
    {
        $tick_id = $tickPost['tick_id'];
        $jefe_id = $session['usu_id']; // El jefe que está aprobando

        // Obtenemos los datos del ticket para saber quién lo creó y de qué subcategoría es
        $datos_ticket = $this->ticketModel->listar_ticket_x_id($tick_id)[0];
        $usu_id_creador = $datos_ticket['usu_id'];
        $cats_id = $datos_ticket['cats_id'];

        // Obtenemos todos los datos del usuario creador
        $datos_creador = $this->usuarioModel->get_usuario_x_id($usu_id_creador);
        $creador_car_id = $datos_creador['car_id'];
        $creador_reg_id = $datos_creador['reg_id'];

        // Buscamos el flujo y su primer paso
        $flujo = $this->flujoModel->get_flujo_por_subcategoria($cats_id);
        if ($flujo) {
            $paso_inicial = $this->flujoModel->get_paso_inicial_por_flujo($flujo['flujo_id']);
            if ($paso_inicial) {
                $primer_paso_id = $paso_inicial['paso_id'];
                $primer_cargo_id = $paso_inicial['cargo_id_asignado'];

                // Buscamos al agente que cumple con el primer cargo y la regional del creador
                $primer_agente_info = $this->usuarioModel->get_usuario_por_cargo_y_regional($primer_cargo_id, $creador_reg_id);

                if ($primer_agente_info) {
                    $primer_agente_id = $primer_agente_info['usu_id'];

                    // Actualizamos el ticket: se lo asignamos al primer agente y le ponemos el primer paso
                    $this->ticketModel->update_asignacion_y_paso($tick_id, $primer_agente_id, $primer_paso_id, $jefe_id);
                }
            }
        }
    }

    public function CheckStartFlow($dataPost)
    {
        $cats_id = $dataPost['cats_id'];
        $output = ['requiere_seleccion' => false, 'usuarios' => []];

        // Buscamos el flujo asociado a la subcategoría
        $flujo = $this->flujoModel->get_flujo_por_subcategoria($cats_id);
        if ($flujo) {
            // Si hay flujo, buscamos su primer paso
            // Si hay flujo, buscamos su primer paso
            $primer_paso = $this->flujoModel->get_paso_inicial_por_flujo($flujo['flujo_id']);
            if ($primer_paso && $primer_paso['requiere_seleccion_manual'] == 1) {
                // Si el primer paso requiere selección manual, preparamos la respuesta
                $output['requiere_seleccion'] = true;
                $cargo_id_necesario = $primer_paso['cargo_id_asignado'];

                // Buscamos a TODOS los usuarios con ese cargo
                // 1. Verificar si hay usuarios específicos para este paso
                $usuarios_especificos_ids = $this->flujoPasoModel->get_usuarios_especificos($primer_paso['paso_id']);

                if (!empty($usuarios_especificos_ids)) {
                    $output['usuarios'] = $this->usuarioModel->get_usuarios_por_ids($usuarios_especificos_ids);
                } else {
                    $usuarios = $this->usuarioModel->get_usuarios_por_cargo($cargo_id_necesario);
                    $output['usuarios'] = $usuarios;
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode($output);
    }
}
