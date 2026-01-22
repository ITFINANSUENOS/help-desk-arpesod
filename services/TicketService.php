<?php

require_once('../models/Ticket.php');
require_once('../models/Usuario.php');
require_once('../models/Subcategoria.php');
require_once('../models/Documento.php');
require_once('../models/CampoPlantilla.php');
require_once('../models/Flujo.php');
require_once('../models/FlujoPaso.php');
require_once('../models/Organigrama.php');
require_once('../models/TicketParalelo.php');
require_once('../models/Departamento.php');
require_once('../models/DateHelper.php');
require_once('../models/FlujoTransicion.php');
require_once('../models/Ruta.php');
require_once('../models/RutaPaso.php');
require_once('../services/TicketWorkflowService.php');
require_once('../models/repository/NovedadRepository.php');
require_once('../models/CampoPlantilla.php');

use models\repository\TicketRepository;
use models\repository\NotificationRepository;
use models\repository\AssignmentRepository;
use models\repository\NovedadRepository;

require_once('../models/repository/CargoRepository.php');

use models\repository\CargoRepository;


class TicketService
{
    private $ticketModel;
    private $subcategoriaModel;
    private $usuarioModel;
    private $documentoModel;
    private $flujoModel;
    private $flujoPasoModel;
    private $departamentoModel;
    private $dateHelper;
    private $workflowService;
    private $flujoTransicionModel;
    private $rutaModel;
    private $rutaPasoModel;
    private $organigramaModel;
    private $ticketParaleloModel;
    private $campoPlantillaModel;


    private TicketRepository $ticketRepository;
    private NotificationRepository $notificationRepository;
    private AssignmentRepository $assignmentRepository;
    private NovedadRepository $novedadRepository;
    private CargoRepository $cargoRepository;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->ticketModel = new Ticket();
        $this->usuarioModel = new Usuario();
        $this->documentoModel = new Documento();
        $this->flujoModel = new Flujo();
        $this->flujoPasoModel = new FlujoPaso();
        $this->departamentoModel = new Departamento();
        $this->dateHelper = new DateHelper();
        $this->workflowService = new TicketWorkflowService();
        $this->flujoTransicionModel = new FlujoTransicion();
        $this->rutaModel = new Ruta();
        $this->rutaPasoModel = new RutaPaso();
        $this->subcategoriaModel = new Subcategoria();
        $this->organigramaModel = new Organigrama();
        $this->organigramaModel = new Organigrama();
        $this->ticketParaleloModel = new TicketParalelo();
        $this->campoPlantillaModel = new CampoPlantilla();


        $this->pdo = $pdo;
        $this->ticketRepository = new TicketRepository($pdo);
        $this->notificationRepository = new NotificationRepository($pdo);
        $this->assignmentRepository = new AssignmentRepository($pdo);
        $this->novedadRepository = new NovedadRepository($pdo);
        $this->cargoRepository = new CargoRepository($pdo);
        $this->novedadRepository = new NovedadRepository($pdo);
    }

    private function resolveCandidates($cargo_id, $reg_id, $is_national)
    {
        if ($is_national) {
            return $this->usuarioModel->get_usuarios_por_cargo($cargo_id);
        } else {
            return $this->usuarioModel->get_usuarios_por_cargo_y_regional_all($cargo_id, $reg_id);
        }
    }

    public function resolveAssigned($flujo, $usu_id_creador, $ticket_reg_id, $postData = [])
    {
        $datos_creador = $this->usuarioModel->get_usuario_x_id($usu_id_creador);
        $creador_car_id = $datos_creador['car_id'] ?? null;
        $errors = [];
        $paso_actual_id_final = null;
        $ruta_id = null;
        $ruta_paso_orden = null;

        if ($flujo) { {
                // Check if a specific start step was selected (Conditional Start)
                if (isset($postData['paso_inicio_id']) && !empty($postData['paso_inicio_id'])) {
                    error_log("Conditional Start: paso_inicio_id received = " . $postData['paso_inicio_id']);

                    // Parse the paso_inicio_id which can be "paso:X" or "ruta:Y"
                    $inicio_value = $postData['paso_inicio_id'];

                    if (strpos($inicio_value, 'ruta:') === 0) {
                        // Es una ruta - obtener el primer paso de la ruta
                        $ruta_id = substr($inicio_value, 5); // Remover "ruta:"
                        $ruta_paso_orden = 1; // Empezamos en el primer paso de la ruta
                        error_log("Conditional Start: Detected ROUTE with ID = " . $ruta_id);

                        require_once('../models/RutaPaso.php');
                        $rutaPasoModel = new RutaPaso();
                        $primer_paso_ruta = $rutaPasoModel->get_paso_por_orden($ruta_id, 1);

                        if ($primer_paso_ruta) {
                            $paso_inicial = $this->flujoPasoModel->get_paso_por_id($primer_paso_ruta['paso_id']);
                            error_log("Conditional Start: First step of route = " . json_encode($paso_inicial));
                        } else {
                            $errors[] = "La ruta seleccionada no tiene pasos definidos.";
                            $paso_inicial = null;
                        }
                    } elseif (strpos($inicio_value, 'paso:') === 0) {
                        // Es un paso directo
                        $paso_id = substr($inicio_value, 5); // Remover "paso:"
                        error_log("Conditional Start: Detected DIRECT STEP with ID = " . $paso_id);
                        $paso_inicial = $this->flujoPasoModel->get_paso_por_id($paso_id);
                    } else {
                        // Formato antiguo (solo ID numérico) - mantener compatibilidad
                        error_log("Conditional Start: Legacy format detected, treating as paso_id = " . $inicio_value);
                        $paso_inicial = $this->flujoPasoModel->get_paso_por_id($inicio_value);
                    }

                    error_log("Conditional Start: Resolved paso_inicial = " . json_encode($paso_inicial));
                } else {
                    error_log("Conditional Start: No paso_inicio_id received. Using default.");
                    $paso_inicial = $this->flujoModel->get_paso_inicial_por_flujo($flujo['flujo_id']);
                }

                $paso_actual_id_final = $paso_inicial ? $paso_inicial['paso_id'] : null;
                error_log("Conditional Start: Final paso_actual_id_final = " . $paso_actual_id_final);

                // Verificar si se proporcionó un usuario específico para la decisión inicial
                if (isset($postData['usu_asig_inicial']) && !empty($postData['usu_asig_inicial'])) {
                    $usu_asig_final = $postData['usu_asig_inicial'];
                    error_log("Conditional Start: Using manually selected user from usu_asig_inicial = " . $usu_asig_final);
                }

                if (!$paso_inicial) {
                    $errors[] = "El flujo (id: {$flujo['flujo_id']}) no tiene paso inicial definido.";
                } else {
                    if (empty($usu_asig_final)) {
                        $asignado_car_id = $paso_inicial['cargo_id_asignado'] ?? null;

                        // Lógica de Jefe Inmediato
                        if (isset($paso_inicial['necesita_aprobacion_jefe']) && $paso_inicial['necesita_aprobacion_jefe'] == 1) {

                            // Usar la lógica estándar (Jefe del Creador / Regional Seleccionada)
                            if ($datos_creador) {
                                $jefe_cargo_id = $this->organigramaModel->get_jefe_cargo_id($creador_car_id);
                                if ($jefe_cargo_id) {
                                    $jefe_info = null;

                                    // Lógica de Zona: Usar la regional del ticket (seleccionada o del usuario)
                                    if ($ticket_reg_id) {
                                        require_once("../models/Regional.php");
                                        $regionalModel = new Regional();
                                        $zona = $regionalModel->get_zona_por_regional($ticket_reg_id);

                                        if ($zona) {
                                            $jefe_info = $this->usuarioModel->get_usuario_por_cargo_y_zona($jefe_cargo_id, $zona);
                                        }
                                    }

                                    // Si no se encuentra por zona, buscar por regional exacta
                                    if (!$jefe_info && $ticket_reg_id) {
                                        $jefe_info = $this->usuarioModel->get_usuario_por_cargo_y_regional($jefe_cargo_id, $ticket_reg_id);
                                    }

                                    // Si no se encuentra por regional, buscar por cargo general (Nacional)
                                    if (!$jefe_info) {
                                        $jefe_info = $this->usuarioModel->get_usuario_nacional_por_cargo($jefe_cargo_id);
                                    }

                                    // Fallback final: cualquier usuario con ese cargo (si aplica)
                                    if (!$jefe_info) {
                                        $jefe_info = $this->usuarioModel->get_usuario_por_cargo($jefe_cargo_id);
                                    }

                                    if ($jefe_info) {
                                        $usu_asig_final = $jefe_info['usu_id'];
                                    } else {
                                        $errors[] = "Se requiere aprobación del jefe inmediato, pero no se encontró usuario para el cargo jefe (ID: $jefe_cargo_id) en la zona/regional correspondiente.";
                                    }
                                } else {
                                    $errors[] = "Se requiere aprobación del jefe inmediato, pero el creador no tiene jefe definido en el organigrama.";
                                }
                            }
                        }

                        // Si no se asignó por jefe inmediato, verificamos si es PARALELO
                        if (empty($usu_asig_final) && isset($paso_inicial['es_paralelo']) && $paso_inicial['es_paralelo'] == 1) {
                            $usuarios_destino = [];
                            // 1. Usuarios específicos
                            $usuarios_especificos = $this->flujoPasoModel->get_usuarios_especificos($paso_inicial['paso_id']);
                            if (!empty($usuarios_especificos)) {
                                $usuarios_destino = $usuarios_especificos;
                            } else {
                                // 2. Todos los usuarios del cargo
                                if (!$asignado_car_id) {
                                    $errors[] = "El paso inicial paralelo no tiene cargo asignado.";
                                } else {
                                    if (!empty($paso_inicial['es_tarea_nacional']) && $paso_inicial['es_tarea_nacional'] == 1) {
                                        $usuarios_db = $this->usuarioModel->get_usuarios_por_cargo($asignado_car_id);
                                    } else {
                                        $usuarios_db = $this->usuarioModel->get_usuarios_por_cargo_y_regional_all($asignado_car_id, $ticket_reg_id);
                                    }

                                    if ($usuarios_db) {
                                        foreach ($usuarios_db as $u) {
                                            $usuarios_destino[] = $u['usu_id'];
                                        }
                                    }
                                }
                            }

                            if (count($usuarios_destino) > 0) {
                                $usu_asig_final = implode(',', $usuarios_destino);
                            } else {
                                $errors[] = "El paso es paralelo pero no se encontraron usuarios para asignar.";
                            }
                        }

                        // Si no se asignó por jefe inmediato NI es paralelo, intentamos por cargo (asignación simple)
                        if (empty($usu_asig_final)) {
                            if (!$asignado_car_id) {
                                $errors[] = "El paso inicial no tiene cargo asignado.";
                            } else {
                                $is_national = (!empty($paso_inicial['es_tarea_nacional']) && $paso_inicial['es_tarea_nacional'] == 1);
                                $candidates = $this->resolveCandidates($asignado_car_id, $ticket_reg_id, $is_national);

                                if (count($candidates) > 1) {
                                    return [
                                        'usu_asig_final' => null,
                                        'paso_actual_id_final' => $paso_actual_id_final,
                                        'candidates' => $candidates, // Nueva clave
                                        'require_selection' => true,
                                        'errors' => []
                                    ];
                                } elseif (count($candidates) == 1) {
                                    $usu_asig_final = $candidates[0]['usu_id'];
                                } else {
                                    $errors[] = "No se encontró un usuario automático para cargo_id {$asignado_car_id} (paso inicial).";
                                }
                            }
                        }
                    }
                }
            }
        } else {
            if (empty($usu_asig_final)) {
                $errors[] = "No existe flujo para la subcategoría y no se suministró un usuario asignado manualmente.";
            }
        }

        return [
            'usu_asig_final' => $usu_asig_final ?? null,
            'paso_actual_id_final' => $paso_actual_id_final,
            'ruta_id' => $ruta_id ?? null,
            'ruta_paso_orden' => $ruta_paso_orden ?? null,
            'errors' => $errors
        ];
    }

    public function insertDocument($datos)
    {
        $output = [];

        if ($datos >= 0) {
            $output['tick_id'] = $datos;

            if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
                $countFiles = count($_FILES['files']['name']);
                $ruta = '../public/document/ticket/' . $output['tick_id'] . '/';

                if (!file_exists($ruta)) {
                    mkdir($ruta, 0777, true);
                }

                for ($i = 0; $i < $countFiles; $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $nombreArchivo = basename($_FILES['files']['name'][$i]);
                        $tmpArchivo    = $_FILES['files']['tmp_name'][$i];
                        $destino       = $ruta . $nombreArchivo;
                        $this->documentoModel->insert_documento($output['tick_id'], $nombreArchivo);
                        move_uploaded_file($tmpArchivo, $destino);
                    }
                }

                return $output;
            }
        }
    }

    public function createTicket($postData)
    {
        $this->pdo->beginTransaction();
        try {
            $usu_id_creador = $postData['usu_id'] ?? null;
            $cats_id = $postData['cats_id'] ?? null;
            $session_usu = $_SESSION['usu_id'] ?? null;
            $emp_id = $postData['emp_id'] ?? null;
            $dp_id = $postData['dp_id'] ?? null;
            $usu_asig_raw = $postData['usu_asig'] ?? null;
            $usu_asig = ($usu_asig_raw === 'Select') ? null : $usu_asig_raw;
            $usu_asig_final = null;

            $cats_nom = $this->subcategoriaModel->get_nombre_subcategoria($cats_id);

            $datos_creador = $this->usuarioModel->get_usuario_x_id($usu_id_creador);
            $ticket_reg_id = null;
            if (!empty($datos_creador['es_nacional']) && $datos_creador['es_nacional'] == 1) {
                $ticket_reg_id = $postData['reg_id'] ?? null;
            } else {
                $ticket_reg_id = $datos_creador['reg_id'] ?? null;
            }

            $errors = [];

            $flujo = $this->flujoModel->get_flujo_por_subcategoria($cats_id);

            $resolveResult = $this->resolveAssigned($flujo, $usu_id_creador, $ticket_reg_id, $postData);

            if (!empty($usu_asig)) {
                if (is_array($usu_asig)) {
                    $usu_asig_final = implode(',', $usu_asig);
                } else {
                    $usu_asig_final = $usu_asig;
                }
            } else {
                if (isset($resolveResult['require_selection']) && $resolveResult['require_selection']) {
                    $this->pdo->rollBack();
                    return [
                        "success" => false,
                        "require_selection" => true,
                        "candidates" => $resolveResult['candidates'],
                        "message" => "Varios usuarios encontrados para el paso inicial. Seleccione uno."
                    ];
                }
                $usu_asig_final = $resolveResult['usu_asig_final'];
            }

            $errors = array_merge($errors, $resolveResult['errors']);

            if (count($errors) > 0) {
                return ["success" => false, "errors" => $errors];
            }

            $datos = $this->ticketRepository->insertTicket(
                $usu_id_creador,
                $postData['cat_id'],
                $cats_id,
                $postData['pd_id'],
                $postData['tick_titulo'],
                $postData['tick_descrip'],
                $postData['error_proceso'],
                $usu_asig_final,
                $session_usu,
                $emp_id,
                $dp_id,
                $resolveResult['paso_actual_id_final'],
                $ticket_reg_id,
                $resolveResult['ruta_id'] ?? null,
                $resolveResult['ruta_paso_orden'] ?? null
            );

            // Manejar asignaciones múltiples (paralelo) o simple
            $usuarios_asignados = explode(',', $usu_asig_final);

            // Obtener info del paso para saber si es paralelo
            $paso_info = $this->flujoPasoModel->get_paso_por_id($resolveResult['paso_actual_id_final']);
            $es_paralelo = ($paso_info && isset($paso_info['es_paralelo']) && $paso_info['es_paralelo'] == 1);

            foreach ($usuarios_asignados as $uid) {
                if (!empty($uid)) {
                    $this->assignmentRepository->insertAssignment($datos, $uid, $session_usu, $resolveResult['paso_actual_id_final'], ' Ticket creado');

                    // Si es paralelo, crear registro en tm_ticket_paralelo
                    if ($es_paralelo) {
                        $this->ticketParaleloModel->insert_ticket_paralelo($datos, $resolveResult['paso_actual_id_final'], $uid);
                    }

                    if ($uid != $usu_id_creador) {
                        $mensaje_notificacion = "Se le ha asignado el ticket # {$datos} - {$cats_nom}.";
                        $this->notificationRepository->insertNotification($uid, $mensaje_notificacion, $datos);
                    }
                }
            }

            // Notify Observer
            $this->notifyObserver($datos, "Se ha abierto un nuevo ticket #{$datos} - {$cats_nom}", $cats_id);

            // Insertar documentos si los hay
            if (isset($_FILES['files'])) {
                $this->insertDocument($datos);
            }

            // Handle Dynamic Fields for PDF Template

            // FIX: If the ticket started at Step 0 but jumped to Step X (due to transition), we must also save Step 0 fields.
            $pasos_flujo = $this->flujoPasoModel->get_pasos_por_flujo($flujo['flujo_id']);
            if (count($pasos_flujo) > 0) {
                $primer_paso_id = $pasos_flujo[0]['paso_id'];
                // Only save if different (avoid double save, though handleDynamicFields is likely idempotent except for PDF generation overhead)
                if ($resolveResult['paso_actual_id_final'] != $primer_paso_id) {
                    error_log("TicketService::createTicket - Ticket jumped from Start Step $primer_paso_id to {$resolveResult['paso_actual_id_final']}. Saving Start Step fields.");
                    $this->handleDynamicFields($datos, $postData, $primer_paso_id, $usu_id_creador);
                }
            }

            $this->handleDynamicFields($datos, $postData, $resolveResult['paso_actual_id_final'], $usu_id_creador);

            $this->pdo->commit();
            return ["success" => true, "tick_id" => $datos];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ["success" => false, "errors" => [$e->getMessage()]];
        }
    }

    private function handleDynamicFields($tick_id, $postData, $paso_id, $usu_id)
    {
        require_once('../models/CampoPlantilla.php');
        require_once('../services/PdfService.php');
        require_once('../models/DocumentoFlujo.php');

        $campoModel = new CampoPlantilla();
        $pdfService = new PdfService();
        $docFlujoModel = new DocumentoFlujo();

        // Check if we have fields for this step
        $campos = $campoModel->get_campos_por_paso($paso_id);
        error_log("TicketService::handleDynamicFields - Paso ID: $paso_id. Campos found: " . count($campos));

        if (empty($campos)) {
            error_log("TicketService::handleDynamicFields - No fields configured for this step. Exiting.");
            return;
        }

        require_once('../models/Regional.php');
        require_once('../models/Cargo.php');
        $regionalModel = new Regional();
        $cargoModel = new Cargo();

        $textDataArray = [];
        $captured_cargo_id = null;
        $captured_regional_id = null;

        foreach ($campos as $campo) {
            $key = 'campo_' . $campo['campo_id'];
            if (isset($postData[$key])) {
                $valor = $postData[$key];
                $campoModel->insert_ticket_valor($tick_id, $campo['campo_id'], $valor);

                $texto_a_estampar = $valor;

                if (isset($campo['campo_tipo'])) {
                    if ($campo['campo_tipo'] == 'regional') {
                        $captured_regional_id = $valor;
                        $reg_data = $regionalModel->get_regional_x_id($valor);
                        if ($reg_data && count($reg_data) > 0) {
                            $texto_a_estampar = $reg_data[0]['reg_nom'];
                        }
                    } elseif ($campo['campo_tipo'] == 'cargo') {
                        $captured_cargo_id = $valor;
                        $cargo_data = $cargoModel->get_cargo_por_id($valor);
                        if ($cargo_data && count($cargo_data) > 0) {
                            $texto_a_estampar = $cargo_data[0]['car_nom'];
                        }
                    }
                }

                $textDataArray[] = [
                    'text' => $texto_a_estampar,
                    'x' => $campo['coord_x'],
                    'y' => $campo['coord_y'],
                    'page' => $campo['pagina'],
                    'font_size' => isset($campo['font_size']) ? $campo['font_size'] : 10
                ];
            }
        }

        // Logic to capture Jefe Inmediato User (Refactored)
        error_log("Debug Boss Lookup - Captured Cargo: " . ($captured_cargo_id ?? 'NULL') . ", Regional: " . ($captured_regional_id ?? 'NULL'));

        if ($captured_cargo_id && $captured_regional_id) {
            require_once('../models/Usuario.php');
            require_once('../models/Organigrama.php');
            $usuarioModel = new Usuario();
            $organigramaModel = new Organigrama();

            // 1. Identify the Subordinate (User identified by Template Fields)
            // We look for a user with the captured Cargo and Regional
            $subordinate_user = $usuarioModel->get_usuario_por_cargo_y_regional($captured_cargo_id, $captured_regional_id);
            error_log("Debug Boss Lookup - Subordinate User Found: " . ($subordinate_user ? 'YES (' . $subordinate_user['usu_id'] . ')' : 'NO'));

            // If not found in regional, check if there is a national user with that cargo? 
            // The requirement implies the template defines the subordinate's location. 
            // If the subordinate is national, they might not have a specific regional_id in the template or the template regional matches their record.
            // Let's assume strict match for subordinate first.

            $subordinate_car_id = null;
            $subordinate_reg_id = null;

            if ($subordinate_user) {
                $subordinate_car_id = $subordinate_user['car_id'];
                $subordinate_reg_id = $subordinate_user['reg_id'];
            } else {
                error_log("Debug Boss Lookup - Subordinate not found. Using captured IDs as fallback: Car $captured_cargo_id, Reg $captured_regional_id");
                $subordinate_car_id = $captured_cargo_id;
                $subordinate_reg_id = $captured_regional_id;
            }

            if ($subordinate_car_id && $subordinate_reg_id) {

                // 2. Find the Boss's Cargo from Organigrama
                $jefe_car_id = $organigramaModel->get_jefe_cargo_id($subordinate_car_id);
                error_log("Debug Boss Lookup - Boss Cargo ID: " . ($jefe_car_id ?? 'NULL'));

                if ($jefe_car_id) {
                    // 3. Find the Boss User(s)
                    // Priority: Boss in the same regional as Subordinate -> National Boss
                    $jefe_encontrado_id = null;

                    // Try to find boss in the same regional
                    $boss_in_regional = $usuarioModel->get_usuario_por_cargo_y_regional($jefe_car_id, $subordinate_reg_id);

                    if ($boss_in_regional) {
                        $jefe_encontrado_id = $boss_in_regional['usu_id'];
                    } else {
                        // If not found, look for a National Boss
                        $boss_national = $usuarioModel->get_usuario_nacional_por_cargo($jefe_car_id);
                        if ($boss_national) {
                            $jefe_encontrado_id = $boss_national['usu_id'];
                        }
                    }
                    error_log("Debug Boss Lookup - Boss User Found ID: " . ($jefe_encontrado_id ?? 'NO'));

                    // 4. Update tm_ticket with the Boss's ID
                    if ($jefe_encontrado_id) {
                        $sql = "UPDATE tm_ticket SET usu_id_jefe_aprobador = ? WHERE tick_id = ?";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->bindValue(1, $jefe_encontrado_id);
                        $stmt->bindValue(2, $tick_id);
                        $stmt->execute();
                        error_log("Debug Boss Lookup - Ticket Updated for tick_id: $tick_id");
                    }
                }
            }
        } else {
            error_log("Debug Boss Lookup - Skipping logic because cargo or regional is missing.");
        }

        if (empty($textDataArray)) return;

        // Prepare PDF
        $paso_info = $this->flujoPasoModel->get_paso_por_id($paso_id);
        $flujo_id = $paso_info['flujo_id'];

        // Determine source template
        $source_path = '';

        // 0. Check if there is an existing signed document (Accumulation Logic)
        $ultimo_doc = $docFlujoModel->get_ultimo_documento_flujo($tick_id);
        if ($ultimo_doc) {
            // Construct path to the latest document
            // Path format: ../public/document/flujo/{flujo_id}/{paso_id}/{usu_id}/{filename}
            $prev_path = "../public/document/flujo/{$ultimo_doc['flujo_id']}/{$ultimo_doc['paso_id']}/{$ultimo_doc['usu_id']}/{$ultimo_doc['doc_nom']}";

            error_log("TicketService::handleDynamicFields - Checking previous document: $prev_path");

            if (file_exists($prev_path)) {
                $source_path = $prev_path;
                error_log("TicketService::handleDynamicFields - Using previous document as source.");
            }
        }

        // If no previous document, look for the blank template
        if (empty($source_path)) {

            // 1. Check for Company-Specific Template
            $emp_id = isset($postData['emp_id']) ? $postData['emp_id'] : null;

            // FIX: If emp_id is not in postData (e.g. during advancement), get it from the ticket
            if (!$emp_id) {
                $ticket_info = $this->ticketModel->listar_ticket_x_id($tick_id);
                if ($ticket_info) {
                    $emp_id = $ticket_info['emp_id'];
                }
            }

            if ($emp_id) {
                $plantilla_empresa = $this->flujoModel->get_plantilla_por_empresa($flujo_id, $emp_id);
                if (!empty($plantilla_empresa)) {
                    $source_path = '../public/document/flujo/' . $plantilla_empresa;
                }
            }

            // 2. Fallback to Default Flow Template
            if (empty($source_path)) {
                $flujo_info = $this->flujoModel->get_flujo_x_id($flujo_id);
                error_log("TicketService::handleDynamicFields - Flujo Info: " . json_encode($flujo_info));
                error_log("TicketService::handleDynamicFields - Paso Info: " . json_encode($paso_info));

                if (!empty($flujo_info['flujo']['flujo_nom_adjunto'])) {
                    $source_path = '../public/document/flujo/' . $flujo_info['flujo']['flujo_nom_adjunto'];
                } elseif (!empty($paso_info['paso_nom_adjunto'])) {
                    $source_path = '../public/document/paso/' . $paso_info['paso_nom_adjunto'];
                }
            }

            error_log("TicketService::handleDynamicFields - Flujo ID: $flujo_id");
            error_log("TicketService::handleDynamicFields - Source Path Candidate: $source_path");
            if (!empty($source_path)) {
                error_log("TicketService::handleDynamicFields - File Exists? " . (file_exists($source_path) ? 'YES' : 'NO'));
            }

            if (empty($source_path) || !file_exists($source_path)) {
                error_log("TicketService::handleDynamicFields - No template found.");
                return;
            }
        } // End of template discovery block

        // Define storage path
        $storage_dir = "../public/document/flujo/{$flujo_id}/{$paso_id}/{$usu_id}/";
        if (!file_exists($storage_dir)) {
            mkdir($storage_dir, 0777, true);
        }

        $new_filename = uniqid('initial_', true) . '.pdf';
        $pdf_path = $storage_dir . $new_filename;

        if (copy($source_path, $pdf_path)) {
            // Stamp text
            if ($pdfService->estamparTexto($pdf_path, $textDataArray)) {
                // Save to tm_documento_flujo
                $docFlujoModel->insert_documento_flujo($tick_id, $flujo_id, $paso_id, $usu_id, $new_filename);
                error_log("TicketService::handleDynamicFields - Stamped PDF saved: $new_filename");
            }
        } else {
            error_log("TicketService::handleDynamicFields - Failed to copy template.");
        }
    }
    public function avanzar_ticket_en_ruta($ticket, $usu_asig = null, $manual_assignments = [])
    {
        $ruta_paso_orden_actual = $ticket["ruta_paso_orden"];
        $siguiente_orden = $ruta_paso_orden_actual + 1;
        $siguiente_paso_info = $this->rutaPasoModel->get_paso_por_orden($ticket["ruta_id"], $siguiente_orden);

        if ($siguiente_paso_info) {
            return $this->actualizar_estado_ticket($ticket['tick_id'], $siguiente_paso_info["paso_id"], $ticket["ruta_id"], $siguiente_orden, $usu_asig, $manual_assignments);
        } else {
            $this->cerrar_ticket($ticket['tick_id'], "Ruta completada.");
            return null;
        }
    }

    public function iniciar_ruta_para_ticket($ticket, $ruta_id, $usu_asig = null, $manual_assignments = [])
    {
        $primer_paso_info = $this->rutaPasoModel->get_paso_por_orden($ruta_id, 1);
        if ($primer_paso_info) {
            return $this->actualizar_estado_ticket($ticket['tick_id'], $primer_paso_info["paso_id"], $ruta_id, $primer_paso_info["paso_orden"], $usu_asig, $manual_assignments);
        } else {
            throw new Exception("La ruta seleccionada no tiene pasos definidos.");
        }
    }

    public function avanzar_ticket_con_usuario_asignado($ticket, $usu_asig)
    {
        // ... misma lógica de avanzar lineal pero forzando usuario ...
        // En realidad, esto debería llamar a actualizar_estado_ticket con el paso siguiente
        // Ojo: si es lineal, llamamos a get_siguiente_paso

        // Si el ticket tiene ruta, usamos avanzar_ticket_en_ruta?
        // No, este método se usa cuando NO se tomó decisión explícita pero se quiere avanzar (quizás reasignando)

        if (!empty($ticket['ruta_id'])) {
            // Si está en ruta, avanzar en ruta
            // Pero ojo, avanzar_ticket_en_ruta calcula el siguiente paso.
            // Si queremos reasignar el paso ACTUAL, es diferente.
            // Pero el nombre dice "avanzar". Asumimos que avanza.
            return $this->avanzar_ticket_en_ruta($ticket);
        }

        $siguiente_paso_info = $this->flujoModel->get_siguiente_paso($ticket["paso_actual_id"]);
        if ($siguiente_paso_info) {
            return $this->actualizar_estado_ticket($ticket['tick_id'], $siguiente_paso_info["paso_id"], null, null, $usu_asig);
        } else {
            $this->cerrar_ticket($ticket['tick_id'], "Flujo principal completado.");
            return null;
        }
    }

    public function avanzar_ticket_lineal($ticket, $usu_asig = null, $manual_assignments = [])
    {
        $siguiente_paso_info = $this->flujoModel->get_siguiente_paso($ticket["paso_actual_id"]);
        if ($siguiente_paso_info) {
            return $this->actualizar_estado_ticket($ticket['tick_id'], $siguiente_paso_info["paso_id"], null, null, $usu_asig, $manual_assignments);
        } else {
            $this->cerrar_ticket($ticket['tick_id'], "Flujo principal completado.");
            return null;
        }
    }

    public function actualizar_estado_ticket($ticket_id, $nuevo_paso_id, $ruta_id, $ruta_paso_orden, $usu_asig, $manual_assignments = [])
    {
        error_log("TicketService::actualizar_estado_ticket - Called. Ticket: $ticket_id, Nuevo Paso: $nuevo_paso_id");

        $ticket_info_current = $this->ticketModel->listar_ticket_x_id($ticket_id);
        if ($ticket_info_current['paso_actual_id']) {
            $current_step_info = $this->flujoPasoModel->get_paso_por_id($ticket_info_current['paso_actual_id']);
            if ($current_step_info && isset($current_step_info['cerrar_ticket_obligatorio']) && $current_step_info['cerrar_ticket_obligatorio'] == 1) {
                error_log("TicketService::actualizar_estado_ticket - TENTATIVA DE EVASIÓN: El paso actual (" . $current_step_info['paso_nombre'] . ") obliga el cierre. No se puede avanzar.");
                throw new Exception("Acción no permitida: El paso actual requiere el cierre obligatorio del ticket.");
            }
        }

        $cats_nom = $ticket_info_current['cats_nom'];

        $siguiente_paso = $this->flujoPasoModel->get_paso_por_id($nuevo_paso_id);
        if (!$siguiente_paso) {
            error_log("TicketService::actualizar_estado_ticket - Error: Siguiente paso no encontrado.");
            throw new Exception("No se encontró la información del siguiente paso (ID: $nuevo_paso_id).");
        }
        error_log("TicketService::actualizar_estado_ticket - Siguiente paso encontrado: " . $siguiente_paso['paso_nombre'] . " (ID: " . $siguiente_paso['paso_id'] . ")");
        error_log("TicketService::actualizar_estado_ticket - Es Paralelo: " . ($siguiente_paso['es_paralelo'] ?? 'NULL'));

        $siguiente_cargo_id = $siguiente_paso['cargo_id_asignado'] ?? null;
        $nuevo_asignado_info = null;

        // --- LÓGICA DE ASIGNACIÓN AL CREADOR ---
        if (isset($siguiente_paso['asignar_a_creador']) && $siguiente_paso['asignar_a_creador'] == 1) {
            $ticket_info_creator = $this->ticketModel->listar_ticket_x_id($ticket_id);
            $usu_asig = $ticket_info_creator['usu_id'];
            $nuevo_asignado_info = $this->usuarioModel->get_usuario_x_id($usu_asig);
            error_log("TicketService::actualizar_estado_ticket - Paso configurado para asignar al creador (ID: $usu_asig)");
        }

        // --- LÓGICA DE PASO PARALELO (FORK) ---
        if (isset($siguiente_paso['es_paralelo']) && $siguiente_paso['es_paralelo'] == 1 && (empty($siguiente_paso['asignar_a_creador']) || $siguiente_paso['asignar_a_creador'] == 0)) {

            // 1. Determinar usuarios destino
            $usuarios_destino = [];

            // A. Verificar si hay usuarios específicos configurados en el paso
            $usuarios_especificos = $this->flujoPasoModel->get_usuarios_especificos($siguiente_paso['paso_id']);

            // A.2 Verificar si hay CARGOS específicos configurados (Nuevo requerimiento)
            $cargos_especificos = $this->flujoPasoModel->get_cargos_especificos($siguiente_paso['paso_id']);

            // A.3 (FIX) Verificar si hay configuracion de FIRMA y agregar esos usuarios/cargos a la asignación
            $firma_config = $this->flujoPasoModel->get_firma_config($siguiente_paso['paso_id']);
            if (!empty($firma_config)) {
                foreach ($firma_config as $fc) {
                    if (!empty($fc['car_id'])) {
                        $cargos_especificos[] = $fc['car_id'];
                    }
                    if (!empty($fc['usu_id'])) {
                        $usuarios_especificos[] = $fc['usu_id'];
                    }
                }
                // Eliminar duplicados
                $cargos_especificos = array_unique($cargos_especificos);
                $usuarios_especificos = array_unique($usuarios_especificos);
            }

            error_log("TicketService::actualizar_estado_ticket - Usuarios especificos count: " . count($usuarios_especificos));
            error_log("TicketService::actualizar_estado_ticket - Cargos especificos count: " . count($cargos_especificos));
            if (!empty($cargos_especificos)) {
                error_log("TicketService::actualizar_estado_ticket - Cargos especificos: " . implode(', ', $cargos_especificos));
            }

            if (!empty($usuarios_especificos) || !empty($cargos_especificos)) {
                // Si hay configuración específica (usuarios o cargos), la usamos.

                if (!empty($usuarios_especificos)) {
                    $usuarios_destino = $usuarios_especificos;
                }

                if (!empty($cargos_especificos)) {
                    $regional_origen_id = $this->ticketModel->get_ticket_region($ticket_id);

                    // Obtener el creador del ticket para determinar su jefe
                    $ticket_data = $this->ticketModel->listar_ticket_x_id($ticket_id);
                    $creador_usu_id = $ticket_data['usu_id'];
                    $creador_data = $this->usuarioModel->get_usuario_detalle_x_id($creador_usu_id);

                    foreach ($cargos_especificos as $cargo_id) {

                        // --- CHECK MANUAL ASSIGNMENT ---
                        $manual_user_id = null;
                        if (!empty($manual_assignments)) {
                            foreach ($manual_assignments as $ma) {
                                // Convert to array if object (from json_decode)
                                $ma = (array)$ma;
                                if (isset($ma['role_key']) && $ma['role_key'] == $cargo_id) {
                                    $manual_user_id = $ma['usu_id'];
                                    break;
                                }
                            }
                        }

                        if ($manual_user_id) {
                            error_log("TicketService::actualizar_estado_ticket - Using Manual Assignment for Cargo $cargo_id: User $manual_user_id");
                            $usuarios_destino[] = $manual_user_id;
                            continue;
                        }
                        // -------------------------------

                        error_log("TicketService::actualizar_estado_ticket - Processing cargo_id: $cargo_id");
                        if ($cargo_id === 'JEFE_INMEDIATO') {
                            // New Logic: Check against usu_id_jefe_aprobador stored in tm_ticket
                            $ticket_info_check = $this->ticketModel->listar_ticket_x_id($ticket_id);

                            error_log("TicketService::actualizar_estado_ticket - Checking JEFE_INMEDIATO. Ticket ID: $ticket_id");
                            error_log("TicketService::actualizar_estado_ticket - usu_id_jefe_aprobador found: " . ($ticket_info_check['usu_id_jefe_aprobador'] ?? 'NULL'));

                            if (!empty($ticket_info_check['usu_id_jefe_aprobador'])) {
                                $usuarios_destino[] = $ticket_info_check['usu_id_jefe_aprobador'];
                                error_log("TicketService::actualizar_estado_ticket - Assigned from usu_id_jefe_aprobador.");
                            } else {
                                // Fallback Logic
                                $subordinate_car_id = null;
                                // 2. Fallback to Ticket Creator
                                if ($creador_data && !empty($creador_data['car_id'])) {
                                    $subordinate_car_id = $creador_data['car_id'];
                                }

                                // 3. Find Boss
                                if ($subordinate_car_id) {
                                    require_once('../models/Organigrama.php');
                                    $organigramaModel = new Organigrama();
                                    $jefe_car_id = $organigramaModel->get_jefe_cargo_id($subordinate_car_id);

                                    if ($jefe_car_id) {
                                        // Asignar a usuarios con ese cargo (en la misma regional o nacionales)
                                        $usuarios_jefe = $this->usuarioModel->get_usuarios_por_cargo_regional_o_nacional($jefe_car_id, $regional_origen_id);
                                        if ($usuarios_jefe) {
                                            foreach ($usuarios_jefe as $u) {
                                                $usuarios_destino[] = $u['usu_id'];
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            // Buscar usuarios de ese cargo en la regional del ticket O nacionales
                            $usuarios_cargo = $this->usuarioModel->get_usuarios_por_cargo_regional_o_nacional($cargo_id, $regional_origen_id);
                            if ($usuarios_cargo) {
                                foreach ($usuarios_cargo as $u) {
                                    $usuarios_destino[] = $u['usu_id'];
                                }
                            }
                        }
                    }
                    // Eliminar duplicados por si acaso
                    $usuarios_destino = array_unique($usuarios_destino);
                }
            } elseif (is_array($usu_asig)) {
                // B. Se seleccionaron manualmente en el paso anterior (si aplica)
                $usuarios_destino = $usu_asig;
            } else {
                // C. Todos los usuarios del cargo asignado (Fallback original)
                if ($siguiente_paso['es_tarea_nacional'] == 1) {
                    $usuarios_db = $this->usuarioModel->get_usuarios_por_cargo($siguiente_cargo_id);
                } else {
                    $regional_origen_id = $this->ticketModel->get_ticket_region($ticket_id);
                    $usuarios_db = $this->usuarioModel->get_usuarios_por_cargo_y_regional_all($siguiente_cargo_id, $regional_origen_id);
                }

                if ($usuarios_db) {
                    foreach ($usuarios_db as $u) {
                        $usuarios_destino[] = $u['usu_id'];
                    }
                }
            }

            if (count($usuarios_destino) > 0) {
                // Guardar la lista de IDs separados por coma en usu_asig
                $usuarios_ids_string = implode(',', $usuarios_destino);

                // Calcular SLA del paso anterior ANTES de cambiar el estado
                $this->computeAndUpdateEstadoPaso($ticket_id);

                $this->ticketRepository->updateTicketFlowState($ticket_id, $usuarios_ids_string, $nuevo_paso_id, $ruta_id, $ruta_paso_orden);

                foreach ($usuarios_destino as $uid) {
                    // Crear registro en tm_ticket_paralelo
                    $this->ticketParaleloModel->insert_ticket_paralelo($ticket_id, $nuevo_paso_id, $uid);

                    // Notificar a cada uno
                    $mensaje_notificacion = "Se le ha asignado una tarea paralela en el ticket #{$ticket_id} - {$cats_nom}.";
                    $this->notificationRepository->insertNotification($uid, $mensaje_notificacion, $ticket_id);
                }

                // Notify Observer (Parallel)
                $this->notifyObserver($ticket_id, "El ticket #{$ticket_id} ha avanzado a un paso paralelo.");

                return; // Terminamos aquí, no seguimos la lógica normal
            } else {
                throw new Exception("El paso es paralelo pero no se encontraron usuarios para asignar.");
            }
        }



        // --- LÓGICA DE JEFE INMEDIATO (Lineal) ---
        // Verificar si el paso requiere aprobación del jefe inmediato
        if (isset($siguiente_paso['necesita_aprobacion_jefe']) && $siguiente_paso['necesita_aprobacion_jefe'] == 1) {

            // 1. Check if we have a specific Boss identified via Template (usu_id_jefe_aprobador)
            $ticket_info_check = $this->ticketModel->listar_ticket_x_id($ticket_id);
            if (!empty($ticket_info_check['usu_id_jefe_aprobador'])) {
                $nuevo_asignado_info = $this->usuarioModel->get_usuario_x_id($ticket_info_check['usu_id_jefe_aprobador']);
                if ($nuevo_asignado_info) {
                    $usu_asig = null; // Force assignment to this boss
                }
            } else {
                // 2. Fallback to Creator's Boss (Original Logic)
                // Obtener información del creador del ticket
                $creador_id = $ticket_info_check['usu_id']; // Use info from check
                $creador_info = $this->usuarioModel->get_usuario_x_id($creador_id);

                if ($creador_info) {
                    // Obtener el cargo del jefe inmediato
                    $jefe_cargo_id = $this->organigramaModel->get_jefe_cargo_id($creador_info['car_id']);

                    if ($jefe_cargo_id) {
                        // Buscar usuario con ese cargo
                        // Priorizamos buscar en la misma regional si es posible, o usamos la búsqueda general
                        $nuevo_asignado_info = $this->usuarioModel->get_usuario_por_cargo($jefe_cargo_id);

                        if ($nuevo_asignado_info) {
                            // Si encontramos al jefe, forzamos la asignación y anulamos cualquier selección manual
                            $usu_asig = null;
                        }
                    }
                }
            }
        }


        if (!$nuevo_asignado_info) {
            if (!empty($usu_asig) || $usu_asig != null) {
                $nuevo_asignado_info = $usu_asig;
            } else {
                if ($siguiente_paso['es_tarea_nacional'] == 1) {
                    $is_national = true;
                    $regional_origen_id = null; // Ignored in national? or needed? resolveCandidates expects it but might not use it if national
                } else {
                    $is_national = false;
                    $regional_origen_id = $this->ticketModel->get_ticket_region($ticket_id);
                }

                $candidates = $this->resolveCandidates($siguiente_cargo_id, $regional_origen_id, $is_national);

                if (count($candidates) > 1) {
                    throw new Exception("REQ_SELECTION:" . json_encode($candidates));
                } elseif (count($candidates) == 1) {
                    $nuevo_asignado_info = $candidates[0];
                } else {
                    $nuevo_asignado_info = null;
                }
            }
        }

        if ($nuevo_asignado_info) {
            if (!empty($usu_asig) || $usu_asig != null) {
                $nuevo_usuario_asignado = $usu_asig;
            } else {
                // Si $nuevo_asignado_info es un array (viene de DB), usamos usu_id
                // Si es un valor escalar (viene de $usu_asig que pasamos arriba), lo usamos directo
                $nuevo_usuario_asignado = is_array($nuevo_asignado_info) ? $nuevo_asignado_info['usu_id'] : $nuevo_asignado_info;
            }

            // Calcular SLA del paso anterior ANTES de cambiar el estado y asignar al nuevo
            $this->computeAndUpdateEstadoPaso($ticket_id);

            $this->ticketRepository->updateTicketFlowState($ticket_id, $nuevo_usuario_asignado, $nuevo_paso_id, $ruta_id, $ruta_paso_orden);

            // Añadir al historial y enviar notificaciones...
            $th_id = $this->assignmentRepository->insertAssignment($ticket_id, $nuevo_usuario_asignado, $_SESSION['usu_id'] ?? null, $nuevo_paso_id, 'Ticket trasladado');

            $mensaje_notificacion = "Se le ha trasladado el ticket #{$ticket_id} - {$cats_nom}.";
            $this->notificationRepository->insertNotification($nuevo_usuario_asignado, $mensaje_notificacion, $ticket_id);

            // Notify Observer (Linear)
            $this->notifyObserver($ticket_id, "El ticket #{$ticket_id} ha sido reasignado.");

            return $nuevo_paso_id;
        } else {
            throw new Exception("No se encontró un usuario para asignar al cargo ID: $siguiente_cargo_id.");
        }
    }

    public function showTicket($tickId)
    {
        $datos = $this->ticketModel->listar_ticket_x_id($tickId);
        if (is_array($datos) == true and count($datos) > 0) {
            $row = $datos;
            $output['tick_id'] = $row['tick_id'];
            $output['usu_id'] = $row['usu_id'];
            $output['cat_id'] = $row['cat_id'];
            $output['cats_id'] = $row['cats_id'];
            $output['pd_id'] = $row['pd_id'];
            $output['usu_asig'] = $row['usu_asig'];
            $output['tick_titulo'] = $row['tick_titulo'];
            $output['tick_descrip'] = $row['tick_descrip'];
            $output['tick_estado_texto'] = $row['tick_estado'];

            if ($row['tick_estado'] == 'Abierto') {
                $output['tick_estado'] = '<span class="label label-success">Abierto</span>';
            } elseif ($row['tick_estado'] == 'Pausado') {
                $output['tick_estado'] = '<span class="label label-info">Pausado</span>';
            } else {
                $output['tick_estado'] = '<span class="label label-danger">Cerrado</span>';
            }
            $output['fech_crea'] = date("d/m/Y", strtotime($row["fech_crea"]));
            $output['usu_nom'] = $row['usu_nom'];
            $output['usu_ape'] = $row['usu_ape'];
            $output['cat_nom'] = $row['cat_nom'];
            $output['cats_nom'] = $row['cats_nom'];
            $output['emp_nom'] = $row['emp_nom'];
            $output['dp_nom'] = $row['dp_nom'];
            $output['ruta_id'] = $row['ruta_id'];
            $output['ruta_paso_orden'] = $row['ruta_paso_orden'];
            $output['paso_actual_id'] = $row['paso_actual_id'];
            $output['paso_nombre'] = $row['paso_nombre'];
            $output['novedad_abierta'] = $this->novedadRepository->getNovedadAbiertaPorTicket($tickId);

            if ($row['pd_nom'] == 'Baja') {
                $output['prioridad_usuario'] = '<span class="label label-default">Baja</span>';
            } elseif ($row['pd_nom'] == 'Media') {
                $output['pd_nom'] = '<span class="label label-warning">Media</span>';
            } else {
                $output['pd_nom'] = '<span class="label label-danger">Alta</span>';
            }

            $output["decisiones_disponibles"] = [];
            $output["siguientes_pasos_lineales"] = [];
            $output["paso_actual_info"] = []; // Valor por defecto

            if (!empty($datos["paso_actual_id"])) {
                $paso_actual_info = $this->flujoPasoModel->get_paso_actual($row["paso_actual_id"]);
                if ($paso_actual_info) {
                    // Check for signed document in tm_documento_flujo
                    require_once('../models/DocumentoFlujo.php');
                    $documentoFlujoModel = new DocumentoFlujo();
                    $signed_doc = $documentoFlujoModel->get_ultimo_documento_flujo($tickId);

                    if ($signed_doc) {
                        $paso_actual_info['documento_firmado_actual'] = [
                            'flujo_id' => $signed_doc['flujo_id'],
                            'paso_id' => $signed_doc['paso_id'],
                            'usu_id' => $signed_doc['usu_id'],
                            'det_nom' => $signed_doc['doc_nom']
                        ];
                    }
                    $output["paso_actual_info"] = $paso_actual_info;
                }
            }

            // --- NUEVA LÓGICA PARA RUTAS ---
            if (!empty($row['ruta_id'])) {
                // Si el ticket está en una ruta, el siguiente paso es el siguiente en el orden de la ruta
                $siguiente_orden = $row['ruta_paso_orden'] + 1;
                $siguiente_paso_ruta = $this->rutaPasoModel->get_paso_por_orden($row['ruta_id'], $siguiente_orden);

                if ($siguiente_paso_ruta) {
                    // Lo formateamos como un "paso lineal" para que el frontend lo entienda
                    $output["siguientes_pasos_lineales"] = [$siguiente_paso_ruta];

                    // Verificamos si requiere selección manual (igual que en el flujo principal)
                    if ($siguiente_paso_ruta['requiere_seleccion_manual'] == 1) {
                        $output['requiere_seleccion_manual'] = true;
                        $usuarios_especificos = $this->flujoPasoModel->get_usuarios_especificos($siguiente_paso_ruta['paso_id']);
                        if (count($usuarios_especificos) > 0) {
                            $output['usuarios_seleccionables'] = $this->usuarioModel->get_usuarios_por_ids($usuarios_especificos);
                        } else {
                            $output['usuarios_seleccionables'] = $this->usuarioModel->get_usuarios_por_cargo($siguiente_paso_ruta['cargo_id_asignado']);
                        }
                    }
                }

                // ADEMÁS, buscamos si hay transiciones explícitas configuradas para este paso de la ruta
                // Esto permite "salirse" de la ruta o tomar decisiones alternativas
                $transiciones_ruta = $this->flujoTransicionModel->get_transiciones_por_paso($datos["paso_actual_id"]);
                if (count($transiciones_ruta) > 0) {
                    $output["decisiones_disponibles"] = $transiciones_ruta;
                }
            } else {
                // --- LÓGICA ORIGINAL PARA FLUJO PRINCIPAL ---
                // 1. ¿Existen transiciones (decisiones) para este paso?
                $transiciones = $this->flujoTransicionModel->get_transiciones_por_paso($datos["paso_actual_id"]);

                if (count($transiciones) > 0) {
                    // Si hay decisiones, estas son las acciones principales para el usuario.
                    $output["decisiones_disponibles"] = $transiciones;
                } else {
                    // Si NO hay decisiones, buscamos el siguiente paso lineal.
                    $siguientes_pasos = $this->flujoPasoModel->get_siguientes_pasos($datos["paso_actual_id"]);
                    if ($siguientes_pasos) {
                        $output["siguientes_pasos_lineales"] = $siguientes_pasos;
                    }
                    if ($siguientes_pasos && $siguientes_pasos[0]['requiere_seleccion_manual'] == 1) {
                        $output['requiere_seleccion_manual'] = true;
                        $usuarios_especificos = $this->flujoPasoModel->get_usuarios_especificos($siguientes_pasos[0]['paso_id']);
                        if (count($usuarios_especificos) > 0) {
                            $output['usuarios_seleccionables'] = $this->usuarioModel->get_usuarios_por_ids($usuarios_especificos);
                        } else {
                            $output['usuarios_seleccionables'] = $this->usuarioModel->get_usuarios_por_cargo($paso_actual_info['cargo_id_asignado']);
                        }
                    }
                }
            }
        }

        $output["timeline_graph"] = "";
        if (!empty($row["paso_actual_id"])) {
            $flujo_id = $this->flujoPasoModel->get_flujo_id_from_paso($row["paso_actual_id"]);
            if ($flujo_id) {
                $todos_los_pasos_flujo = $this->flujoPasoModel->get_pasos_por_flujo($flujo_id);
                $paso_actual_info = $this->flujoPasoModel->get_paso_por_id($row["paso_actual_id"]);
                $orden_actual_flujo = $paso_actual_info['paso_orden'] ?? 0;
                $ruta_actual_id = !empty($row['ruta_id']) ? $row['ruta_id'] : null;

                $mermaid_string = "graph TD;\n";
                $declared_nodes = [];
                $connections = "";

                // 0. Identificar pasos que pertenecen a rutas para no duplicarlos en el flujo principal
                $pasos_en_rutas = [];
                $rutas_del_flujo = $this->rutaModel->get_rutas_por_flujo($flujo_id);
                // Si no existe get_rutas con flujo_id, podemos iterar las transiciones o buscar otra forma.
                // Por seguridad, usaremos las transiciones del flujo para descubrir las rutas activas.

                foreach ($todos_los_pasos_flujo as $p) {
                    $transiciones_p = $this->flujoTransicionModel->get_transiciones_por_paso($p["paso_id"]);
                    foreach ($transiciones_p as $t) {
                        if (!empty($t['ruta_id'])) {
                            $pasos_r = $this->rutaPasoModel->get_pasos_por_ruta($t['ruta_id']);
                            foreach ($pasos_r as $pr) {
                                $pasos_en_rutas[] = $pr['paso_id'];
                            }
                        }
                    }
                }
                $pasos_en_rutas = array_unique($pasos_en_rutas);

                // 1. Declarar nodos (filtrando los que están en rutas)
                foreach ($todos_los_pasos_flujo as $paso) {
                    // Si el paso pertenece a una ruta, NO lo declaramos en el flujo principal
                    if (in_array($paso['paso_id'], $pasos_en_rutas)) {
                        continue;
                    }

                    $paso_id_unico = "flujo_{$paso['paso_id']}";
                    if (!in_array($paso_id_unico, $declared_nodes)) {
                        $mermaid_string .= "    {$paso_id_unico}[\"{$paso['paso_nombre']}\"];\n";
                        $declared_nodes[] = $paso_id_unico;
                    }
                }

                // 2. Aplicar estilos, crear subgrafos y generar las conexiones
                $rutas_procesadas = [];
                for ($i = 0; $i < count($todos_los_pasos_flujo); $i++) {
                    $paso = $todos_los_pasos_flujo[$i];

                    // Si es un paso de ruta, lo saltamos del flujo principal
                    if (in_array($paso['paso_id'], $pasos_en_rutas)) {
                        continue;
                    }

                    $paso_id_unico = "flujo_{$paso['paso_id']}";

                    // Aplicar estilo al paso del flujo principal
                    $estado = 'pending';
                    // Lógica de estado para el flujo principal
                    if ($ruta_actual_id) {
                        // Si estamos en una ruta, los pasos anteriores al inicio de la ruta están completados
                        // Esto es una aproximación. Lo ideal sería saber en qué paso del flujo principal se disparó la ruta.
                        // Asumiremos que si el orden es menor, está completado.
                        if ($paso['paso_orden'] <= $orden_actual_flujo) $estado = 'completed';
                    } else {
                        if ($paso['paso_orden'] < $orden_actual_flujo) $estado = 'completed';
                        if ($paso['paso_id'] == $row["paso_actual_id"]) $estado = 'active';
                    }
                    $mermaid_string .= "    class {$paso_id_unico} {$estado};\n";

                    // Generar Conexiones y Subgrafos
                    $transiciones = $this->flujoTransicionModel->get_transiciones_por_paso($paso["paso_id"]);
                    $has_valid_branches = false;

                    if (count($transiciones) > 0) {
                        foreach ($transiciones as $transicion) {
                            if (!empty($transicion['ruta_id'])) {
                                $ruta_id = $transicion['ruta_id'];
                                $ruta_info = $this->rutaModel->get_ruta_por_id($ruta_id);
                                $pasos_de_la_ruta = $this->rutaPasoModel->get_pasos_por_ruta($ruta_id);

                                if ($ruta_info && $pasos_de_la_ruta) {
                                    $has_valid_branches = true;

                                    // Conectar el flujo principal al inicio de la ruta
                                    if (isset($pasos_de_la_ruta[0]['paso_id'])) {
                                        $inicio_ruta = "ruta{$ruta_id}_paso{$pasos_de_la_ruta[0]['paso_id']}";
                                        $connections .= "    {$paso_id_unico} -- \"{$transicion['condicion_nombre']}\" --> {$inicio_ruta};\n";
                                    }

                                    if (!in_array($ruta_id, $rutas_procesadas)) {
                                        $mermaid_string .= "    subgraph " . $ruta_info['ruta_nombre'] . "\n";
                                        foreach ($pasos_de_la_ruta as $paso_ruta) {
                                            if (isset($paso_ruta['paso_id'])) {
                                                $paso_ruta_id_unico = "ruta{$ruta_id}_paso{$paso_ruta['paso_id']}";
                                                $mermaid_string .= "        {$paso_ruta_id_unico}[\"{$paso_ruta['paso_nombre']}\"]\n";

                                                $estado_ruta = 'pending';

                                                // Lógica de estado para pasos DENTRO de la ruta
                                                if ($ruta_actual_id == $ruta_id) {
                                                    // Si estamos en ESTA ruta
                                                    if ($paso_ruta['paso_id'] == $row["paso_actual_id"]) {
                                                        $estado_ruta = 'active';
                                                    } elseif ($paso_ruta['orden'] < $row['ruta_paso_orden']) {
                                                        // Si el orden es menor al actual, está completado
                                                        $estado_ruta = 'completed';
                                                    }
                                                } elseif ($estado == 'completed') {
                                                    // Si el paso padre está completado, la ruta probablemente también
                                                    // $estado_ruta = 'completed'; // Descomentar si se desea
                                                }

                                                $mermaid_string .= "        class {$paso_ruta_id_unico} {$estado_ruta};\n";

                                                // Generar transiciones explícitas desde este paso de la ruta
                                                $transiciones_ruta = $this->flujoTransicionModel->get_transiciones_por_paso($paso_ruta['paso_id']);
                                                if (count($transiciones_ruta) > 0) {
                                                    foreach ($transiciones_ruta as $tr_ruta) {
                                                        if (!empty($tr_ruta['paso_destino_id'])) {
                                                            // Conexión a otro paso (puede ser fuera de la ruta)
                                                            $destino_id_unico = "flujo_{$tr_ruta['paso_destino_id']}";
                                                            // Si el destino está en una ruta, deberíamos usar el ID de nodo de ruta, 
                                                            // pero es difícil saber en QUÉ ruta está sin buscarlo.
                                                            // Por ahora asumimos que sale al flujo principal o a un paso directo.
                                                            // Si el destino es parte de ESTA misma ruta, deberíamos usar el ID de ruta.

                                                            // Verificar si el destino es parte de la misma ruta
                                                            $es_interno = false;
                                                            foreach ($pasos_de_la_ruta as $pr_check) {
                                                                if ($pr_check['paso_id'] == $tr_ruta['paso_destino_id']) {
                                                                    $destino_id_unico = "ruta{$ruta_id}_paso{$tr_ruta['paso_destino_id']}";
                                                                    $es_interno = true;
                                                                    break;
                                                                }
                                                            }

                                                            $connections .= "        {$paso_ruta_id_unico} -- \"{$tr_ruta['condicion_nombre']}\" --> {$destino_id_unico};\n";
                                                        } elseif (!empty($tr_ruta['ruta_id'])) {
                                                            // Transición a OTRA ruta desde dentro de una ruta (anidamiento o salto)
                                                            // Esto requeriría lógica recursiva o más compleja.
                                                            // Por ahora lo omitimos o lo conectamos al inicio de esa ruta si ya está declarada.
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        $mermaid_string .= "    end\n";

                                        // Conexiones internas de la ruta
                                        for ($j = 0; $j < count($pasos_de_la_ruta) - 1; $j++) {
                                            if (isset($pasos_de_la_ruta[$j]['paso_id']) && isset($pasos_de_la_ruta[$j + 1]['paso_id'])) {
                                                $origen = "ruta{$ruta_id}_paso{$pasos_de_la_ruta[$j]['paso_id']}";
                                                $destino = "ruta{$ruta_id}_paso{$pasos_de_la_ruta[$j + 1]['paso_id']}";
                                                $connections .= "        {$origen} --> {$destino};\n";
                                            }
                                        }
                                        $rutas_procesadas[] = $ruta_id;
                                    }
                                }
                            } elseif (!empty($transicion['paso_destino_id'])) {
                                // Conexión directa a otro paso
                                $destino_paso_id_unico = "flujo_{$transicion['paso_destino_id']}";
                                $connections .= "    {$paso_id_unico} -- \"{$transicion['condicion_nombre']}\" --> {$destino_paso_id_unico};\n";
                            }
                        }
                    }

                    // Si no tiene ramas válidas, es una conexión lineal
                    if (!$has_valid_branches) {
                        // Buscar el siguiente paso que NO sea de ruta
                        $siguiente_paso_index = $i + 1;
                        while (isset($todos_los_pasos_flujo[$siguiente_paso_index]) && in_array($todos_los_pasos_flujo[$siguiente_paso_index]['paso_id'], $pasos_en_rutas)) {
                            $siguiente_paso_index++;
                        }

                        if (isset($todos_los_pasos_flujo[$siguiente_paso_index])) {
                            $siguiente_paso_id = $todos_los_pasos_flujo[$siguiente_paso_index]['paso_id'];
                            $destino_id = "flujo_{$siguiente_paso_id}";
                            $connections .= "    {$paso_id_unico} --> {$destino_id};\n";
                        }
                    }
                }

                $mermaid_string .= $connections;

                // 3. Definir los estilos
                $mermaid_string .= "classDef completed fill:#dff0d8,stroke:#3c763d,stroke-width:2px;\n";
                $mermaid_string .= "classDef active fill:#d9edf7,stroke:#31708f,stroke-width:4px;\n";
                $mermaid_string .= "classDef pending fill:#f5f5f5,stroke:#ccc,stroke-width:2px;\n";
                // --- FIN DE LÓGICA ---

                $output["timeline_graph"] = $mermaid_string;
            }
        }

        $mi_ticket = $datos;
        $estado_tiempo = '<span class="label label-defa">N/A</span>';

        if ($mi_ticket['tick_estado'] == 'Abierto' && !empty($mi_ticket['paso_actual_id'])) {
            $fecha_asignacion = $this->ticketModel->get_fecha_ultima_asignacion($mi_ticket['tick_id']);
            $paso_info = $this->flujoPasoModel->get_paso_por_id($mi_ticket['paso_actual_id']);
            $dias_habiles_permitidos = $paso_info['paso_tiempo_habil'];

            if ($fecha_asignacion && $dias_habiles_permitidos > 0) {
                $fecha_limite = $this->dateHelper->calcularFechaLimiteHabil($fecha_asignacion, $dias_habiles_permitidos);
                $fecha_hoy = new DateTime();

                if ($fecha_hoy > $fecha_limite) {
                    $estado_tiempo = '<span class="label label-danger">Atrasado</span>';
                } else {
                    $estado_tiempo = '<span class="label label-success">A Tiempo</span>';
                }
            }
        }
        $output['estado_tiempo'] = $estado_tiempo;

        // Agregar campos con días transcurridos solo para tickets abiertos
        $output['campos_dias_transcurridos'] = [];
        if ($row['tick_estado'] == 'Abierto') {
            require_once('../models/CampoPlantilla.php');
            $campoPlantillaModel = new CampoPlantilla();
            $output['campos_dias_transcurridos'] = $campoPlantillaModel->get_campos_con_dias_transcurridos($tickId);
        }

        echo json_encode($output);
    }

    public function getUsuariosPorPaso($paso_id)
    {
        $paso_info = $this->flujoPasoModel->get_paso_por_id($paso_id);
        $usuarios_seleccionables = [];

        if ($paso_info) {
            $usuarios_especificos = $this->flujoPasoModel->get_usuarios_especificos($paso_id);
            if (count($usuarios_especificos) > 0) {
                $usuarios_seleccionables = $this->usuarioModel->get_usuarios_por_ids($usuarios_especificos);
            } else {
                $usuarios_seleccionables = $this->usuarioModel->get_usuarios_por_cargo($paso_info['cargo_id_asignado']);
            }
        }
        return $usuarios_seleccionables;
    }


    public function createDetailTicket($postData)
    {
        $tickId = $postData['tick_id'];
        $usuId = $postData['usu_id'];
        $tickdDescrip = $postData['tickd_descrip'];
        $usu_asig = $postData['usu_asig'] ?? null;
        $signature_data = isset($postData['signature_data']) ? $postData['signature_data'] : null;
        $manual_assignments = [];
        if (isset($postData['manual_assignments']) && !empty($postData['manual_assignments'])) {
            $decoded = json_decode($postData['manual_assignments'], true);
            if (is_array($decoded)) {
                $manual_assignments = $decoded;
            }
        }

        // 1. Guardar comentario y archivos
        $datos = $this->ticketModel->insert_ticket_detalle($tickId, $usuId, $tickdDescrip);
        if (is_array($datos) && count($datos) > 0) {
            $tickd_id = $datos[0]['tickd_id'];

            // Handle Signature
            if ($signature_data) {
                error_log("TicketService::createDetailTicket - Signature data received. Calling handleSignature.");
                $this->handleSignature($tickId, $usuId, $signature_data, $tickd_id);
            } else {
                error_log("TicketService::createDetailTicket - No signature data received.");
            }
            // Robust file handling
            if (isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
                $countfiles = count($_FILES['files']['name']);
                $ruta = '../public/document/detalle/' . $tickd_id . '/';
                if (!file_exists($ruta)) {
                    mkdir($ruta, 0777, true);
                }

                for ($index = 0; $index < $countfiles; $index++) {
                    // Skip if name is empty (e.g. empty slot)
                    if (empty($_FILES['files']['name'][$index])) {
                        continue;
                    }

                    $doc1 = $_FILES['files']['name'][$index];
                    $tmp_name = $_FILES['files']['tmp_name'][$index];
                    $destino = $ruta . $doc1;
                    $this->documentoModel->insert_documento_detalle($tickd_id, $doc1);
                    move_uploaded_file($tmp_name, $destino);
                }
            }
        }

        $this->pdo->beginTransaction();
        try {
            $ticket = $this->ticketModel->listar_ticket_x_id($tickId);

            // --- LÓGICA DE PASO PARALELO (JOIN) ---
            $paso_actual_info = $this->flujoPasoModel->get_paso_por_id($ticket['paso_actual_id']);
            if (isset($paso_actual_info['es_paralelo']) && $paso_actual_info['es_paralelo'] == 1) {
                // 1. Verificar si el usuario actual tiene una asignación paralela pendiente
                $usuId = $_SESSION['usu_id']; // Aseguramos que usuId esté definido
                $mi_paralelo = $this->ticketParaleloModel->get_ticket_paralelo_por_usuario($tickId, $ticket['paso_actual_id'], $usuId);

                if ($mi_paralelo && $mi_paralelo['estado'] == 'Pendiente') {
                    // 2. Marcar mi parte como completada
                    $comentario_paralelo = isset($_POST['tick_descrip']) ? $_POST['tick_descrip'] : 'Aprobado';

                    // Calcular SLA
                    $estado_tiempo_paso = 'N/A';
                    $dias_habiles = isset($paso_actual_info['paso_tiempo_habil']) ? $paso_actual_info['paso_tiempo_habil'] : 0;

                    if ($dias_habiles > 0) {
                        $fecha_asignacion = $mi_paralelo['fech_crea'];
                        $fecha_limite = $this->dateHelper->calcularFechaLimiteHabil($fecha_asignacion, $dias_habiles);
                        $fecha_hoy = new DateTime();
                        $estado_tiempo_paso = ($fecha_hoy > $fecha_limite) ? 'Atrasado' : 'A Tiempo';
                    }

                    $this->ticketParaleloModel->update_estado($mi_paralelo['paralelo_id'], 'Aprobado', $comentario_paralelo, $estado_tiempo_paso);

                    // 3. Verificar si TODOS han terminado
                    $todos_terminaron = $this->ticketParaleloModel->check_todos_aprobados($tickId, $ticket['paso_actual_id']);

                    if (!$todos_terminaron) {
                        // Si faltan otros, NO avanzamos el ticket. Solo guardamos el comentario y notificamos.
                        $this->pdo->commit();
                        echo json_encode(["status" => "success", "message" => "Tu parte ha sido completada. Esperando a otros usuarios.", "reassigned" => false]);
                        return;
                    }
                    // Si todos terminaron, dejamos que el flujo continúe hacia abajo (avanzar_ticket_...)
                    // El ticket avanzará al siguiente paso.
                } else {
                    // Si no tengo asignación o ya terminé, y trato de avanzar...
                    // Podría ser un error o una re-aprobación. Asumimos que si llega aquí es para avanzar.
                }
            }
            // ---------------------------------------

            // Recogemos la decisión que el usuario tomó en el frontend
            $decision_tomada = $_POST['decision_nombre'] ?? null;
            $avanzar_lineal = isset($_POST['avanzar_lineal']) && $_POST['avanzar_lineal'] === 'true';

            $reassigned = false; // Por defecto, no se reasigna

            // SOLO avanzamos si se dio una instrucción para ello
            $nuevo_paso_id_result = null;

            if ($decision_tomada || $avanzar_lineal) {

                if ($decision_tomada) {
                    // CASO 1: El usuario eligió una decisión específica.
                    $nuevo_paso_id_result = $this->iniciar_ruta_desde_decision($ticket, $decision_tomada, $usu_asig, $manual_assignments);
                } elseif (!empty($ticket["ruta_id"])) {
                    // CASO 2: El ticket YA ESTÁ en una ruta y no se tomó decisión explícita. Avanzamos en la ruta.
                    $nuevo_paso_id_result = $this->avanzar_ticket_en_ruta($ticket, $usu_asig, $manual_assignments);
                } elseif ($avanzar_lineal) {
                    // CASO 3: El usuario quiere avanzar en un flujo sin decisiones.
                    $nuevo_paso_id_result = $this->avanzar_ticket_lineal($ticket, $usu_asig, $manual_assignments);
                }
                $reassigned = true; // Si entramos en este bloque, significa que se avanzó/reasignó.
            } else {
                $nuevo_paso_id_result = $this->avanzar_ticket_con_usuario_asignado($ticket, $usu_asig);
                $reassigned = true;
            }

            // --- FIX: Procesar campos dinámicos para el nuevo paso ---
            if ($reassigned && $nuevo_paso_id_result) {
                error_log("TicketService::createDetailTicket - Using returned New Paso ID: " . $nuevo_paso_id_result);
                $this->handleDynamicFields($tickId, $postData, $nuevo_paso_id_result, $usuId);
            }
            // ---------------------------------------------------------

            $this->pdo->commit();
            echo json_encode(["status" => "success", "reassigned" => $reassigned]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $msg = $e->getMessage();
            if (strpos($msg, 'REQ_SELECTION:') === 0) {
                $json_candidates = substr($msg, 14);
                echo json_encode([
                    "status" => "require_selection",
                    "message" => "Varios usuarios encontrados para el siguiente paso. Seleccione uno.",
                    "candidates" => json_decode($json_candidates)
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => $e->getMessage()
                ]);
            }
        }
    }

    public function iniciar_ruta_desde_decision($ticket, $decision_nombre, $usu_asig = null, $manual_assignments = [])
    {
        $paso_actual_id = $ticket['paso_actual_id'];

        // 1. Buscar la transición que coincida con el paso actual y el nombre de la decisión
        $transicion = $this->flujoTransicionModel->get_transicion_por_decision($paso_actual_id, $decision_nombre);

        if ($transicion) {
            if (!empty($transicion['ruta_id'])) {
                // CASO A: La transición lleva a una RUTA
                return $this->iniciar_ruta_para_ticket($ticket, $transicion['ruta_id'], $usu_asig, $manual_assignments);
            } elseif (!empty($transicion['paso_destino_id'])) {
                // CASO B: La transición lleva DIRECTAMENTE a un PASO
                $nuevo_paso_id = $transicion['paso_destino_id'];

                // FIX: Delegar TODA la lógica de asignación a actualizar_estado_ticket
                // Este método ya maneja correctamente:
                // - Asignación al creador (asignar_a_creador)
                // - Pasos paralelos (es_paralelo)
                // - Aprobación de jefe inmediato (necesita_aprobacion_jefe)
                // - Tareas nacionales vs regionales (es_tarea_nacional)
                // - Múltiples candidatos (lanza excepción REQ_SELECTION)
                // - Usuarios específicos configurados
                // - Cargos específicos configurados

                // Ya no necesitamos hacer asignación manual aquí
                return $this->actualizar_estado_ticket(
                    $ticket['tick_id'],
                    $nuevo_paso_id,
                    null,  // ruta_id (no aplica para paso directo)
                    null,  // ruta_paso_orden (no aplica para paso directo)
                    $usu_asig,  // puede ser null, actualizar_estado_ticket lo manejará
                    $manual_assignments
                );
            } else {
                throw new Exception("La transición configurada no tiene destino (ni ruta ni paso).");
            }
        } else {
            throw new Exception("No se encontró la transición para la decisión: " . $decision_nombre);
        }
    }



    public function cerrar_ticket($ticket_id, $mensaje)
    {
        $this->ticketModel->update_ticket($ticket_id);
        $this->ticketModel->insert_ticket_detalle_cerrar($ticket_id, $_SESSION['usu_id']);

        // Insertar registro en historial de asignaciones
        $ticket_data = $this->ticketModel->listar_ticket_x_id($ticket_id);
        $usu_asig = $ticket_data['usu_asig'];
        $paso_actual = $ticket_data['paso_actual_id'];

        $this->assignmentRepository->insertAssignment($ticket_id, $usu_asig, $_SESSION['usu_id'], $paso_actual, 'Ticket cerrado');

        // Notify Observer
        $this->notifyObserver($ticket_id, "El ticket #{$ticket_id} ha sido cerrado.");
    }

    public function updateTicket($tickId)
    {
        $tick_id = $_POST['tick_id'];
        $usu_id = $_POST['usu_id'];

        $ticket = $this->ticketModel->listar_ticket_x_id($tick_id);

        // Si el ticket tiene un flujo asignado, verificamos que esté en el último paso
        if ($ticket && !empty($ticket['paso_actual_id'])) {
            $is_last_step = false;

            // Caso 1: El ticket está en una ruta específica
            if (!empty($ticket['ruta_id'])) {
                $siguiente_orden = (int)$ticket["ruta_paso_orden"] + 1;
                $siguiente_paso_info = $this->rutaPasoModel->get_paso_por_orden($ticket["ruta_id"], $siguiente_orden);
                if (!$siguiente_paso_info) {
                    $is_last_step = true;
                }
            }
            // Caso 2: El ticket está en el flujo principal
            else {
                $siguientes_pasos = $this->flujoPasoModel->get_siguientes_pasos($ticket["paso_actual_id"]);
                $transiciones = $this->flujoTransicionModel->get_transiciones_por_paso($ticket["paso_actual_id"]);

                // Un paso es final si no tiene pasos lineales siguientes NI transiciones a otras rutas
                if (empty($siguientes_pasos) && empty($transiciones)) {
                    $is_last_step = true;
                }
            }

            // Si después de las validaciones, no es el último paso, devolvemos un error
            if (!$is_last_step) {
                header('Content-Type: application/json');
                echo json_encode(["status" => "error", "message" => "El ticket no se puede cerrar porque no ha llegado al final de su flujo."]);
                return;
            }
        }

        // --- Si es el último paso o no tiene flujo, procede a cerrar ---

        $asignacion_actual = $this->ticketModel->get_ultima_asignacion($tick_id);

        if ($asignacion_actual && !empty($asignacion_actual['paso_actual_id'])) {
            $paso_actual_info = $this->flujoPasoModel->get_paso_por_id($asignacion_actual['paso_actual_id']);

            if ($paso_actual_info) {
                $estado_paso_final = 'N/A';
                $fecha_asignacion = $asignacion_actual['fech_asig'];
                $dias_habiles = $paso_actual_info['paso_tiempo_habil'];

                if ($dias_habiles > 0) {
                    $fecha_limite = $this->dateHelper->calcularFechaLimiteHabil($fecha_asignacion, $dias_habiles);
                    $fecha_hoy = new DateTime();
                    $estado_paso_final = ($fecha_hoy > $fecha_limite) ? 'Atrasado' : 'A Tiempo';
                }

                $this->ticketModel->update_estado_tiempo_paso($asignacion_actual['th_id'], $estado_paso_final);
            }
        }

        $this->ticketModel->update_ticket($tick_id);
        $this->ticketModel->insert_ticket_detalle_cerrar($tick_id, $usu_id);

        header('Content-Type: application/json');
        echo json_encode(["status" => "success", "message" => "Ticket cerrado correctamente."]);
    }

    /**
     * Registra un evento de error en un ticket.
     *
     * Este método maneja la lógica para cuando un analista registra un error en un ticket.
     * - Identifica si el error es un "error de proceso" que requiere que el ticket retroceda en el flujo.
     * - Determina el usuario responsable del error basándose en el paso anterior del flujo de trabajo definido.
     * - Sella el registro de historial de la asignación anterior con el código y la descripción del error.
     * - Añade un comentario público al historial del ticket detallando el error.
     * - Si es un error de proceso, reasigna el ticket al usuario responsable (ya sea el del paso anterior o el creador del ticket si estaba en el primer paso).
     *
     * @param array $dataPost Datos que normalmente provienen de $_POST. Debe contener:
     *                        - 'tick_id': ID del ticket.
     *                        - 'answer_id': ID de la respuesta rápida que define el error.
     *                        - 'usu_id': ID del usuario (analista) que está reportando el error.
     *                        - 'error_descrip' (opcional): Una descripción textual del error.
     * @throws Exception Si faltan parámetros o si no se encuentran datos esenciales (ticket, respuesta rápida, etc.).
     * @return void      Imprime una respuesta JSON con el estado de la operación y los datos actualizados del ticket.
     */
    public function LogErrorTicket($dataPost)
    {
        try {
            $this->pdo->beginTransaction();

            $tick_id = $dataPost['tick_id'] ?? null;
            $answer_id = $dataPost['answer_id'] ?? null;
            $usu_id_reporta = $dataPost['usu_id'] ?? null;
            $error_descrip = $dataPost['error_descrip'] ?? '';

            if (!$tick_id || !$answer_id || !$usu_id_reporta) {
                throw new Exception("Parámetros incompletos.");
            }

            require_once('../models/RespuestaRapida.php');
            $respuesta_rapida = new RespuestaRapida();
            $datos_respuesta = $respuesta_rapida->get_respuestarapida_x_id($answer_id);
            if (!$datos_respuesta) {
                throw new Exception("Respuesta rápida no encontrada.");
            }
            $nombre_respuesta = $datos_respuesta["answer_nom"] ?? 'Respuesta desconocida';
            $es_error_proceso = !empty($datos_respuesta["es_error_proceso"]);

            $ticket_data = $this->ticketModel->listar_ticket_x_id($tick_id);
            if (!$ticket_data) {
                throw new Exception("Ticket no encontrado.");
            }

            // Determinar a quién reasignar PRIMERO
            $assigned_to = null;
            $assigned_paso = null;
            $paso_actual_id = $ticket_data['paso_actual_id'];
            if ($es_error_proceso) {
                if ($paso_actual_id) {
                    $paso_anterior = $this->flujoPasoModel->get_paso_anterior($paso_actual_id);
                    if ($paso_anterior) {
                        $assigned_paso = $paso_anterior['paso_id'];
                        $assigned_to = $this->ticketModel->get_usuario_asignado_a_paso($tick_id, $assigned_paso);
                        if (!$assigned_to) {
                            throw new Exception("No se pudo determinar el usuario del paso anterior (Paso ID: $assigned_paso).");
                        }
                    } else {
                        $assigned_to = $ticket_data['usu_id']; // Creador del ticket
                        $assigned_paso = null;
                    }
                } else {
                    $assigned_to = $ticket_data['usu_id']; // Creador del ticket
                    $assigned_paso = null;
                }
            }

            // Obtener el nombre del responsable para el mensaje
            $nombre_completo_responsable = null;
            if ($es_error_proceso) {
                if ($assigned_to) {
                    $datos_responsable = $this->usuarioModel->get_usuario_x_id($assigned_to);
                    if ($datos_responsable) {
                        $nombre_completo_responsable = $datos_responsable['usu_nom'] . ' ' . $datos_responsable['usu_ape'];
                    }
                }
            } else {
                // Para errores que no son de proceso, el responsable es el asignado actual.
                if ($paso_actual_id) {
                    $paso_anterior = $this->flujoPasoModel->get_paso_anterior($paso_actual_id);
                    if ($paso_anterior) {
                        $assigned_paso = $paso_anterior['paso_id'];
                        $assigned_to = $this->ticketModel->get_usuario_asignado_a_paso($tick_id, $assigned_paso);
                        if (!$assigned_to) {
                            throw new Exception("No se pudo determinar el usuario del paso anterior (Paso ID: $assigned_paso).");
                        }
                    } else {
                        $assigned_to = $ticket_data['usu_id']; // Creador del ticket
                        $assigned_paso = null;
                    }
                } else {
                    $assigned_to = $ticket_data['usu_id']; // Creador del ticket
                    $assigned_paso = null;
                }

                if ($assigned_to) {
                    $datos_responsable = $this->usuarioModel->get_usuario_x_id($assigned_to);
                    if ($datos_responsable) {
                        $nombre_completo_responsable = $datos_responsable['usu_nom'] . ' ' . $datos_responsable['usu_ape'];
                    }
                }
            }

            // Sellar el historial
            $asignacion_a_sellar = $this->ticketModel->get_ultima_asignacion($tick_id);
            if ($asignacion_a_sellar) {
                $this->ticketModel->update_error_code_paso($asignacion_a_sellar['th_id'], $answer_id, $error_descrip);
            }

            // Preparar y guardar comentario
            // Calcular SLA actual antes de registrar el error
            $sla_state = $this->computeAndUpdateEstadoPaso($tick_id);
            $badge_class = ($sla_state === 'A Tiempo') ? 'success' : 'danger';

            $comentario = "Se registró un evento: <b>{$nombre_respuesta}</b>.";
            if (!empty($error_descrip)) $comentario .= "<br><b>Descripción:</b> " . htmlspecialchars($error_descrip);
            if ($nombre_completo_responsable) $comentario .= "<br><small class='text-muted'>Error atribuido a: <b>{$nombre_completo_responsable}</b></small>";

            // Append SLA Status
            $comentario .= "<small class='text-muted sla-info' style='display:block; margin-top: 5px;'>SLA al momento del reporte: <span class='label label-{$badge_class}'>{$sla_state}</span></small>";

            $this->ticketModel->insert_ticket_detalle($tick_id, $usu_id_reporta, $comentario);

            // NEW: Insert into relational error table
            // NEW: Insert into relational error table
            require_once('../models/TicketError.php');
            $ticketErrorModel = new TicketError();

            // Check if error of this type already exists for this ticket
            // es_error_proceso: 1 = Process, 0 = Info
            $is_process_val = $es_error_proceso ? 1 : 0;
            $existing_count = $ticketErrorModel->count_errors_by_type($tick_id, $is_process_val);

            if ($existing_count > 0) {
                $errorTypeStr = $es_error_proceso ? "de Proceso" : "Informativo";
                throw new Exception("Ya existe un Error {$errorTypeStr} registrado para este ticket. Solo se permite uno de cada tipo por ticket.");
            }
            // Determine responsible: if assigned_to is set, use it. Otherwise use Creator?
            // The existing logic sets $assigned_to perfectly for "Who caused this" (The person we are returning to, or the one responsible).
            $responsable_id = $assigned_to ?? $ticket_data['usu_id'];

            $ticketErrorModel->insert_error($tick_id, $usu_id_reporta, $responsable_id, $answer_id, $error_descrip, $es_error_proceso ? 1 : 0);


            // Realizar la reasignación si es un error de proceso
            if ($es_error_proceso && $assigned_to) {
                if ($assigned_to == $ticket_data['usu_id'] && $assigned_paso === null) {
                    $this->ticketModel->update_ticket($tick_id);
                    // FIX: Actualizar el dueño en la cabecera para que coincida con el historial (evitar fantasmas)
                    $this->ticketModel->update_owner_silent($tick_id, $assigned_to);

                    $this->ticketModel->insert_ticket_detalle_cerrar($tick_id, $usu_id_reporta);
                    $this->assignmentRepository->insertAssignment($tick_id, $assigned_to, $usu_id_reporta, null, 'Ticket cerrado por error de proceso en el primer paso.');
                    $this->notificationRepository->insertNotification($assigned_to, "El Ticket #{$tick_id} ha sido cerrado debido a un error en el proceso.", $tick_id);
                } else {
                    $comentario_reasignacion = "Ticket devuelto por error de proceso.";
                    $mensaje_notificacion = "Se te ha devuelto el Ticket #{$tick_id} por un error en el proceso.";

                    $this->ticketModel->update_asignacion_y_paso(
                        $tick_id,
                        $assigned_to,
                        $assigned_paso,
                        $usu_id_reporta,
                        $comentario_reasignacion,
                        $mensaje_notificacion
                    );
                }
            } else {
                // Si NO es error de proceso (es meramente informativo)
                // Usamos el ID del responsable para notificarle
                if ($responsable_id) {
                    $this->notificationRepository->insertNotification($responsable_id, "Se ha reportado un error informativo en Ticket #{$tick_id}: {$nombre_respuesta}", $tick_id);
                }
            }

            $this->pdo->commit();

            $ticket_data_actualizado = $this->ticketModel->listar_ticket_x_id($tick_id);
            echo json_encode([
                "status" => "success",
                "ticket" => $ticket_data_actualizado,
                "assigned_to" => $assigned_to,
                "assigned_paso" => $assigned_paso
            ]);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            echo json_encode(["status" => "error", "msg" => "Exception: " . $e->getMessage()]);
        }
    }

    public function approveStep($tickId)
    {
        $this->pdo->beginTransaction();
        try {
            $ticket = $this->ticketModel->listar_ticket_x_id($tickId);
            if (!$ticket) {
                throw new Exception("Ticket no encontrado.");
            }

            $usuId = $_SESSION['usu_id'];
            $this->ticketModel->insert_ticket_detalle($tickId, $usuId, "Aprobó el paso actual.");

            if (!empty($ticket["ruta_id"])) {
                // Si está en una ruta, avanza en la ruta.
                $this->avanzar_ticket_en_ruta($ticket);
            } else {
                // Si está en el flujo principal, avanza de forma lineal.
                $this->avanzar_ticket_lineal($ticket);
            }

            $this->pdo->commit();
            return ["status" => "success", "message" => "Paso aprobado. El ticket ha avanzado."];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            // Devolvemos el mensaje de la excepción para que el frontend lo muestre.
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function rejectStep($tickId)
    {
        $this->pdo->beginTransaction();
        try {
            $ticket = $this->ticketModel->listar_ticket_x_id($tickId);
            if (!$ticket) {
                throw new Exception("Ticket no encontrado.");
            }

            // Buscamos la penúltima asignación para saber a quién devolverle el ticket
            $asignacion_anterior = $this->ticketModel->get_penultima_asignacion($tickId);
            if (!$asignacion_anterior || empty($asignacion_anterior['usu_asig'])) {
                throw new Exception("No se encontró un paso anterior al cual devolver el ticket.");
            }

            $usuId_actual = $_SESSION['usu_id'];
            $usuId_devolver = $asignacion_anterior['usu_asig'];
            $pasoId_devolver = $asignacion_anterior['paso_id'];

            // Comentario para el historial
            $comentario_rechazo = "Se rechazó el paso actual. El ticket ha sido devuelto.";
            // Mensaje para la notificación
            $mensaje_notificacion = "Se te ha devuelto el Ticket #{$tickId} por un rechazo en el flujo.";

            // Usamos la función existente para reasignar
            $this->ticketModel->update_asignacion_y_paso(
                $tickId,
                $usuId_devolver,
                $pasoId_devolver,
                $usuId_actual,
                $comentario_rechazo,
                $mensaje_notificacion
            );

            $this->pdo->commit();
            return ["status" => "success", "message" => "Paso rechazado. El ticket ha sido devuelto."];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function computeAndUpdateEstadoPaso(int $ticketId): string
    {
        // 1) Obtener la última asignación (th_ticket_asignacion)
        $asignacion_actual = $this->ticketModel->get_ultima_asignacion($ticketId);
        if (!$asignacion_actual || empty($asignacion_actual['th_id'])) {
            return 'N/A';
        }

        $th_id = $asignacion_actual['th_id'];
        $fech_asig = $asignacion_actual['fech_asig'] ?? null;
        $paso_id = $asignacion_actual['paso_id'] ?? null;

        // 2) Obtener info del paso
        $estado = 'N/A';
        if ($paso_id) {
            $paso_info = $this->flujoPasoModel->get_paso_por_id($paso_id);
            $dias_habiles_permitidos = (int)($paso_info['paso_tiempo_habil'] ?? 0);

            if ($fech_asig && $dias_habiles_permitidos > 0) {
                // calcular fecha límite usando el DateHelper existente
                $fecha_limite = $this->dateHelper->calcularFechaLimiteHabil($fech_asig, $dias_habiles_permitidos);
                $fecha_hoy = new \DateTime();

                $estado = ($fecha_hoy > $fecha_limite) ? 'Atrasado' : 'A Tiempo';
            } else {
                // Si no hay tiempo habil definido, dejamos 'N/A'
                $estado = 'N/A';
            }
        }

        // 3) Actualizar en la tabla de historial (método ya usado en tu código)
        try {
            $this->ticketModel->update_estado_tiempo_paso($th_id, $estado);
        } catch (\Exception $e) {
            // No romper el flujo si la actualización falla — loguear si tienes logger
            // error_log("computeAndUpdateEstadoPaso error: " . $e->getMessage());
        }
        return $estado;
    }

    public function cerrarTicketConNota($tick_id, $usu_id, $nota_cierre, $files)
    {
        // Calcular SLA antes de cerrar
        $this->computeAndUpdateEstadoPaso($tick_id);

        // Llamar al modelo para cerrar
        $this->ticketModel->cerrar_ticket_con_nota($tick_id, $usu_id, $nota_cierre, $files);
    }

    public function crearNovedad(array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $tick_id = (int)$data['tick_id'];
            $usu_crea_novedad = (int)$data['usu_id'];
            $usu_asig_novedad = (int)$data['usu_asig_novedad'];
            $descripcion_novedad = $data['descripcion_novedad'];

            $ticket = $this->ticketModel->listar_ticket_x_id($tick_id);
            if (!$ticket) {
                throw new Exception("Ticket no encontrado.");
            }

            $paso_id_pausado = $ticket['paso_actual_id'];

            $this->novedadRepository->crearNovedad($tick_id, $paso_id_pausado, $usu_asig_novedad, $usu_crea_novedad, $descripcion_novedad);

            // Insertar documentos si los hay
            if (isset($_FILES['files'])) {
                $this->insertDocument($tick_id);
            }

            $this->ticketRepository->updateTicketStatus($tick_id, 'Pausado');

            // Calcular SLA actual al momento de pausar
            $sla_state = $this->computeAndUpdateEstadoPaso($tick_id);
            $badge_class = ($sla_state === 'A Tiempo') ? 'success' : 'danger';

            $comentario = "Se ha creado una novedad: " . htmlspecialchars($descripcion_novedad);

            if (isset($_FILES['files']) && count($_FILES['files']['name']) > 0) {
                $comentario .= "<br><strong>Archivos adjuntos:</strong><br>";
                $countFiles = count($_FILES['files']['name']);
                for ($i = 0; $i < $countFiles; $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $nombreArchivo = htmlspecialchars(basename($_FILES['files']['name'][$i]));
                        // Path relative to the view (view/DetalleTicket/index.php -> ../../public/...)
                        $urlArchivo = "../../public/document/ticket/{$tick_id}/{$nombreArchivo}";
                        $comentario .= "- <a href='{$urlArchivo}' target='_blank'>{$nombreArchivo}</a><br>";
                    }
                }
            }

            $comentario .= "<small class='text-muted sla-info' style='display:block; margin-top: 5px;'>SLA al momento de la novedad: <span class='label label-{$badge_class}'>{$sla_state}</span></small>";
            $this->ticketModel->insert_ticket_detalle($tick_id, $usu_crea_novedad, $comentario);

            // Registrar la asignación de la novedad en el historial principal
            $comentario_asignacion = "Novedad asignada: " . htmlspecialchars($descripcion_novedad);
            $this->assignmentRepository->insertAssignment($tick_id, $usu_asig_novedad, $usu_crea_novedad, $paso_id_pausado, $comentario_asignacion);

            $mensaje_notificacion = "Se te ha asignado una novedad para el ticket #{$tick_id}.";
            $this->notificationRepository->insertNotification($usu_asig_novedad, $mensaje_notificacion, $tick_id);

            $this->pdo->commit();
            return ["status" => "success", "message" => "Novedad creada y ticket pausado."];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function resolverNovedad(array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $tick_id = (int)$data['tick_id'];
            $usu_resuelve_novedad = (int)$data['usu_id'];

            $novedad = $this->novedadRepository->getNovedadAbiertaPorTicket($tick_id);
            if (!$novedad) {
                throw new Exception("No se encontró una novedad abierta para este ticket.");
            }

            $this->novedadRepository->resolverNovedad($novedad['novedad_id']);

            $this->ticketRepository->updateTicketStatus($tick_id, 'Abierto');

            date_default_timezone_set('America/Bogota');
            $fecha_inicio = new DateTime($novedad['fecha_inicio']);
            $fecha_fin = new DateTime();
            $duracion = $fecha_inicio->diff($fecha_fin);
            $duracion_formateada = $duracion->format('%d días, %h horas, %i minutos');

            $comentario = "Se ha resuelto la novedad: " . htmlspecialchars($novedad['descripcion_novedad']) . ".<br><b>Tiempo de resolución de la novedad:</b> " . $duracion_formateada;

            // Append user comment if provided
            if (!empty($data['tickd_descrip']) && trim(strip_tags($data['tickd_descrip'])) !== '') {
                $comentario .= "<br><br><b>Comentario de resolución:</b><br>" . $data['tickd_descrip'];
            }

            $result_detalle = $this->ticketModel->insert_ticket_detalle($tick_id, $usu_resuelve_novedad, $comentario);

            // Insert documents if provided (linked to the detail)
            if (isset($_FILES['files']) && is_array($result_detalle) && count($result_detalle) > 0) {
                $tickd_id = $result_detalle[0]['tickd_id'];

                if (isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
                    $countfiles = count($_FILES['files']['name']);
                    $ruta = '../public/document/detalle/' . $tickd_id . '/';
                    if (!file_exists($ruta)) {
                        mkdir($ruta, 0777, true);
                    }

                    for ($index = 0; $index < $countfiles; $index++) {
                        if (empty($_FILES['files']['name'][$index])) {
                            continue;
                        }

                        $doc1 = $_FILES['files']['name'][$index];
                        $tmp_name = $_FILES['files']['tmp_name'][$index];
                        $destino = $ruta . $doc1;
                        $this->documentoModel->insert_documento_detalle($tickd_id, $doc1);
                        move_uploaded_file($tmp_name, $destino);
                    }
                }
            }

            // Notificar al agente que tenía el paso pausado
            $ticket = $this->ticketModel->listar_ticket_x_id($tick_id);
            $usu_asig_original = $ticket['usu_asig'];

            // Registrar el retorno del ticket en el historial de asignaciones
            $comentario_asignacion = "Novedad resuelta. El ticket vuelve al agente original.";
            $this->assignmentRepository->insertAssignment($tick_id, $usu_asig_original, $usu_resuelve_novedad, $novedad['paso_id_pausado'], $comentario_asignacion);

            $mensaje_notificacion = "La novedad del ticket #{$tick_id} ha sido resuelta. Puedes continuar con tu trabajo.";
            $this->notificationRepository->insertNotification($usu_asig_original, $mensaje_notificacion, $tick_id);

            $this->pdo->commit();
            return ["status" => "success", "message" => "Novedad resuelta y ticket reactivado."];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    private function handleSignature($tick_id, $usu_id, $signature_data, $tickd_id)
    {
        error_log("TicketService::handleSignature - Start. TickID: $tick_id, UsuID: $usu_id");
        require_once('../services/PdfService.php');
        require_once('../models/DocumentoFlujo.php'); // Require new model
        $pdfService = new PdfService();
        $documentoFlujoModel = new DocumentoFlujo(); // Instantiate new model

        // 1. Get current step info
        $ticket = $this->ticketModel->listar_ticket_x_id($tick_id);

        // FIX: If ticket is in a route, we must get the paso_id from the route step, not the main flow step.
        if (!empty($ticket['ruta_id']) && !empty($ticket['ruta_paso_orden'])) {
            require_once('../models/RutaPaso.php');
            $rutaPasoModel = new RutaPaso();
            $ruta_paso_info = $rutaPasoModel->get_paso_por_orden($ticket['ruta_id'], $ticket['ruta_paso_orden']);

            if ($ruta_paso_info && !empty($ruta_paso_info['paso_id'])) {
                $paso_id = $ruta_paso_info['paso_id'];
                error_log("TicketService::handleSignature - Ticket in Route. Using Route Step ID: $paso_id");
            } else {
                // Fallback to main flow step if route step not found (should not happen)
                $paso_id = $ticket['paso_actual_id'];
                error_log("TicketService::handleSignature - Ticket in Route but step not found. Fallback to Main Step ID: $paso_id");
            }
        } else {
            $paso_id = $ticket['paso_actual_id'];
        }

        error_log("TicketService::handleSignature - PasoID: $paso_id");
        $paso_info = $this->flujoPasoModel->get_paso_por_id($paso_id);

        // 2. Get signature config for this user/step
        $firma_configs = $this->flujoPasoModel->get_firma_config($paso_id);
        error_log("TicketService::handleSignature - Configs found: " . count($firma_configs));
        error_log("TicketService::handleSignature - Configs data: " . json_encode($firma_configs));
        $my_config = null;

        // 2. Check Role (New Logic - Multi-Signature Support)
        $matching_configs = [];

        // Get current user info (Role, Regional, National status)
        $user_info = $this->usuarioModel->get_usuario_detalle_x_id($usu_id);
        // Get ticket info to know the regional
        $ticket_info = $this->ticketModel->listar_ticket_x_id($tick_id);

        if ($user_info && $ticket_info) {
            $ticket_regional = $ticket_info['reg_id'];

            // Check if user is a parallel assignee (bypass regional check)
            $is_parallel_assignee = $this->ticketModel->check_usuario_paralelo($tick_id, $paso_id, $usu_id);
            if ($is_parallel_assignee) {
                error_log("TicketService::handleSignature - User $usu_id is explicitly assigned in Parallel Step $paso_id. Bypassing regional check.");
            }

            foreach ($firma_configs as $config) {
                $is_match = false;

                if ($config['usu_id'] == $usu_id) {
                    $is_match = true;
                } elseif (!empty($config['car_id'])) {
                    // Check for JEFE_INMEDIATO string OR -1 (which seems to be the DB value for it)
                    if ($config['car_id'] === 'JEFE_INMEDIATO' || $config['car_id'] == -1) {
                        // New Logic: Check against usu_id_jefe_aprobador stored in tm_ticket
                        $is_jefe_match = false;

                        if (!empty($ticket_info['usu_id_jefe_aprobador'])) {
                            if ($user_info['usu_id'] == $ticket_info['usu_id_jefe_aprobador']) {
                                $is_jefe_match = true;
                            }
                        }

                        // Fallback: If not explicitly set (or mismatch), check if user is the one ASSIGNED to the ticket.
                        // If the system assigned them, we trust they are the intended actor.
                        // FIX: Removed this fallback because in parallel steps, ALL assigned users match this, causing non-bosses to sign as boss.
                        /*
                        if (!$is_jefe_match && in_array($user_info['usu_id'], explode(',', $ticket_info['usu_asig']))) {
                            $is_jefe_match = true;
                        }
                        */

                        if (!$is_jefe_match) {
                            // Fallback for legacy tickets: Use Creator's Boss logic
                            $subordinate_car_id = null;
                            $creador_usu_id = $ticket_info['usu_id'];
                            $creador_data = $this->usuarioModel->get_usuario_detalle_x_id($creador_usu_id);
                            if ($creador_data) {
                                $subordinate_car_id = $creador_data['car_id'];
                            }

                            if ($subordinate_car_id) {
                                require_once('../models/Organigrama.php');
                                $organigramaModel = new Organigrama();
                                $jefe_car_id = $organigramaModel->get_jefe_cargo_id($subordinate_car_id);

                                if ($jefe_car_id && $jefe_car_id == $user_info['car_id']) {
                                    // Check Regional or National
                                    if ($is_parallel_assignee || $user_info['es_nacional'] == 1 || $user_info['reg_id'] == $ticket_regional) {
                                        $is_jefe_match = true;
                                    }
                                }
                            }
                        }

                        if ($is_jefe_match) {
                            $is_match = true;
                        }
                    } else {
                        // Logic for specific Cargo
                        $is_cargo_match = false;

                        // 1. Check if user actually has the role
                        if ($config['car_id'] == $user_info['car_id']) {
                            if ($is_parallel_assignee || $user_info['es_nacional'] == 1 || $user_info['reg_id'] == $ticket_regional) {
                                $is_cargo_match = true;
                            }
                        }

                        // 2. Fallback: Check if user is the one ASSIGNED to the ticket and the config matches the assigned role for the step.
                        // This allows a user assigned to a step (e.g. via 'Traslado') to sign even if they don't hold the base role.
                        if (!$is_cargo_match && in_array($user_info['usu_id'], explode(',', $ticket_info['usu_asig']))) {
                            // Check if the config's car_id matches the step's assigned car_id
                            if ($config['car_id'] == $paso_info['cargo_id_asignado']) {
                                $is_cargo_match = true;
                                error_log("TicketService::handleSignature - User is assigned to ticket and config matches assigned role. Allowing signature.");
                            }
                        }

                        if ($is_cargo_match) {
                            $is_match = true;
                        }
                    }
                }

                if ($is_match) {
                    $matching_configs[] = $config;
                }
            }
        }

        if (empty($matching_configs)) {
            error_log("TicketService::handleSignature - No matching config found for user $usu_id in paso $paso_id");

            // FALLBACK: If the step requires signature but no config is found (or matches), 
            // use a default configuration to ensure the signature is applied.
            if ($paso_info['requiere_firma'] == 1) {
                error_log("TicketService::handleSignature - Using DEFAULT signature configuration.");
                $matching_configs[] = [
                    'coord_x' => 20,  // Default X
                    'coord_y' => 200, // Default Y (near bottom)
                    'pagina' => 1,
                    'car_id' => null,
                    'usu_id' => null
                ];
            } else {
                return;
            }
        }
        error_log("TicketService::handleSignature - Configs used: " . json_encode($matching_configs));

        // 3. Identify PDF
        // Use DocumentoFlujo to get the latest signed document
        $existing_doc = $documentoFlujoModel->get_ultimo_documento_flujo($tick_id);

        // Define new storage path structure: public/document/flujo/{flujo_id}/{paso_id}/{usu_id}/
        $flujo_id = $paso_info['flujo_id'];
        $storage_dir = "../public/document/flujo/{$flujo_id}/{$paso_id}/{$usu_id}/";

        if (!file_exists($storage_dir)) {
            mkdir($storage_dir, 0777, true);
        }

        $pdf_path = '';
        $new_filename = uniqid('signed_', true) . '.pdf';
        $pdf_path = $storage_dir . $new_filename;

        // Determine source file (existing signed doc or template)
        $source_path = '';

        if ($existing_doc) {
            // Existing doc found in tm_documento_flujo
            // We need to reconstruct the path.
            // The table stores: flujo_id, paso_id, usu_id, doc_nom
            // Path: ../public/document/flujo/{flujo_id}/{paso_id}/{usu_id}/{doc_nom}

            $prev_flujo_id = $existing_doc['flujo_id'];
            $prev_paso_id = $existing_doc['paso_id'];
            $prev_usu_id = $existing_doc['usu_id'];
            $prev_doc_nom = $existing_doc['doc_nom'];

            $source_path = "../public/document/flujo/{$prev_flujo_id}/{$prev_paso_id}/{$prev_usu_id}/{$prev_doc_nom}";

            if (!file_exists($source_path)) {
                error_log("TicketService::handleSignature - Could not locate previous signed document: $source_path");
                $source_path = ''; // Reset if not found
            }
        }

        if (empty($source_path)) {
            // Fallback to template

            // 1. Check for Company-Specific Template
            $emp_id = isset($ticket['emp_id']) ? $ticket['emp_id'] : null;
            if ($emp_id) {
                $plantilla_empresa = $this->flujoModel->get_plantilla_por_empresa($flujo_id, $emp_id);
                if (!empty($plantilla_empresa)) {
                    $source_path = '../public/document/flujo/' . $plantilla_empresa;
                }
            }

            // 2. Fallback to Default Flow Template
            if (empty($source_path)) {
                $flujo_info = $this->flujoModel->get_flujo_x_id($flujo_id);
                if (!empty($flujo_info['flujo']['flujo_nom_adjunto'])) {
                    $source_path = '../public/document/flujo/' . $flujo_info['flujo']['flujo_nom_adjunto'];
                } elseif (!empty($paso_info['paso_nom_adjunto'])) {
                    $source_path = '../public/document/paso/' . $paso_info['paso_nom_adjunto'];
                }
            }
        }

        if (!empty($source_path) && file_exists($source_path)) {
            if (copy($source_path, $pdf_path)) {
                error_log("TicketService::handleSignature - Copied source $source_path to $pdf_path");
            } else {
                error_log("TicketService::handleSignature - Failed to copy source.");
                return;
            }
        } else {
            error_log("TicketService::handleSignature - No source document found.");
            return;
        }

        // 4. Save signature image
        $img_parts = explode(";base64,", $signature_data);
        if (count($img_parts) < 2) {
            error_log("TicketService::handleSignature - Invalid base64 image data.");
            return;
        }
        $img_base64 = base64_decode($img_parts[1]);

        // Save signature image in the same folder
        $firma_path = $storage_dir . 'firma_' . uniqid() . '.png';
        file_put_contents($firma_path, $img_base64);
        error_log("TicketService::handleSignature - Signature image saved: $firma_path");

        // 5. Sign PDF (Iterate through all matching configs)
        // 5. Sign PDF (Batch)
        $signatures_to_apply = [];
        foreach ($matching_configs as $config) {
            $signatures_to_apply[] = [
                'x' => $config['coord_x'],
                'y' => $config['coord_y'],
                'pagina' => $config['pagina']
            ];
        }

        if (count($signatures_to_apply) > 0) {
            $result = $pdfService->firmarPdfMultiple($pdf_path, $firma_path, $signatures_to_apply);
            error_log("TicketService::handleSignature - firmarPdfMultiple result: " . ($result ? 'Success' : 'Failure'));
        }

        // 6. Cleanup
        if (file_exists($firma_path)) {
            unlink($firma_path);
        }

        // 7. Insert into tm_documento_flujo (NOT td_documento_detalle)
        $documentoFlujoModel->insert_documento_flujo($tick_id, $flujo_id, $paso_id, $usu_id, $new_filename);
    }


    private function notifyObserver($ticket_id, $message, $cats_id = null)
    {
        if (!$cats_id) {
            $ticket_data = $this->ticketModel->listar_ticket_x_id($ticket_id);
            if ($ticket_data) {
                $cats_id = $ticket_data['cats_id'];
            }
        }

        if ($cats_id) {
            $flujo = $this->flujoModel->get_flujo_por_subcategoria($cats_id);
            if ($flujo && !empty($flujo['usu_id_observador'])) {
                // Handle multiple observers (stored as CSV string)
                $observer_ids = explode(',', $flujo['usu_id_observador']);

                foreach ($observer_ids as $observer_id) {
                    if (!empty(trim($observer_id))) {
                        $this->notificationRepository->insertNotification(trim($observer_id), $message, $ticket_id);
                    }
                }
            }
        }
    }
    public function getNextStepCandidates($ticket_id)
    {
        $ticket = $this->ticketModel->listar_ticket_x_id($ticket_id);
        if (!$ticket) return [];

        $siguiente_paso = null;

        // 1. Determine Next Step
        if (!empty($ticket['ruta_id'])) {
            $ruta_paso_orden_actual = $ticket["ruta_paso_orden"];
            $siguiente_orden = $ruta_paso_orden_actual + 1;
            $siguiente_paso = $this->rutaPasoModel->get_paso_por_orden($ticket["ruta_id"], $siguiente_orden);
        } else {
            $siguiente_paso = $this->flujoModel->get_siguiente_paso($ticket["paso_actual_id"]);
        }

        if (!$siguiente_paso) {
            return []; // End of flow
        }

        $candidates_requirements = [];
        $regional_origen_id = $this->ticketModel->get_ticket_region($ticket_id);

        // --- PARALLEL LOGIC ---
        if (isset($siguiente_paso['es_paralelo']) && $siguiente_paso['es_paralelo'] == 1 && (empty($siguiente_paso['asignar_a_creador']) || $siguiente_paso['asignar_a_creador'] == 0)) {

            // Replicate logic from actualizar_estado_ticket
            $usuarios_especificos = $this->flujoPasoModel->get_usuarios_especificos($siguiente_paso['paso_id']);
            $cargos_especificos = $this->flujoPasoModel->get_cargos_especificos($siguiente_paso['paso_id']);

            $firma_config = $this->flujoPasoModel->get_firma_config($siguiente_paso['paso_id']);
            if (!empty($firma_config)) {
                foreach ($firma_config as $fc) {
                    if (!empty($fc['car_id'])) $cargos_especificos[] = $fc['car_id'];
                    if (!empty($fc['usu_id'])) $usuarios_especificos[] = $fc['usu_id'];
                }
            }
            $cargos_especificos = array_unique($cargos_especificos);
            $usuarios_especificos = array_unique($usuarios_especificos);

            // 1. Specific Users
            // If they are specific users, we assume they exist (no fallback needed usually, but could check active status)
            // For now, we focus on Cargo resolution which is dynamic.

            // 2. Specific Cargos
            foreach ($cargos_especificos as $cargo_id) {
                $role_name = "Cargo ID: " . $cargo_id;
                $candidates = [];

                if ($cargo_id === 'JEFE_INMEDIATO') {
                    $role_name = "Jefe Inmediato";
                    // Check existing boss assignment
                    $current_ticket_data = $this->ticketModel->listar_ticket_x_id($ticket_id);
                    if (!empty($current_ticket_data['usu_id_jefe_aprobador'])) {
                        // Boss is already identified
                        $boss_user = $this->usuarioModel->get_usuario_x_id($current_ticket_data['usu_id_jefe_aprobador']);
                        if ($boss_user) $candidates[] = $boss_user;
                    } else {
                        // Fallback Logic (Must match actualizar_estado_ticket)
                        $creador_usu_id = $current_ticket_data['usu_id'];
                        $creador_data = $this->usuarioModel->get_usuario_detalle_x_id($creador_usu_id);
                        $subordinate_car_id = null;
                        if ($creador_data && !empty($creador_data['car_id'])) {
                            $subordinate_car_id = $creador_data['car_id'];
                        }

                        if ($subordinate_car_id) {
                            require_once('../models/Organigrama.php');
                            $organigramaModel = new Organigrama();
                            $jefe_car_id = $organigramaModel->get_jefe_cargo_id($subordinate_car_id);
                            if ($jefe_car_id) {
                                // Asignar a usuarios con ese cargo (en la misma regional o nacionales)
                                $candidates = $this->usuarioModel->get_usuarios_por_cargo_regional_o_nacional($jefe_car_id, $regional_origen_id);
                            }
                        }
                    }
                } else {
                    // Standard Cargo
                    // Get Cargo Name for display
                    $active_cargo = $this->cargoRepository->getById($cargo_id);
                    if ($active_cargo) $role_name = $active_cargo['car_nom'];

                    // Resolve candidates for this cargo in region OR national (Match actual logic)
                    $candidates = $this->usuarioModel->get_usuarios_por_cargo_regional_o_nacional($cargo_id, $regional_origen_id);
                }

                // Add to requirements
                $candidates_requirements[] = [
                    'role_key' => $cargo_id, // Key to identify this requirement
                    'role_name' => $role_name,
                    'candidates' => $candidates
                ];
            }
        } else {
            // --- LINEAR LOGIC ---
            $siguiente_cargo_id = $siguiente_paso['cargo_id_asignado'];
            $is_national = (!empty($siguiente_paso['es_tarea_nacional']) && $siguiente_paso['es_tarea_nacional'] == 1);

            // Only relevant if cargo is defined and not assigning to creator
            if ($siguiente_cargo_id && (empty($siguiente_paso['asignar_a_creador']) || $siguiente_paso['asignar_a_creador'] == 0)) {
                $candidates = $this->resolveCandidates($siguiente_cargo_id, $regional_origen_id, $is_national);
                $role_name = "Asignado"; // Generic name for linear

                $candidates_requirements[] = [
                    'role_key' => 'linear_assign',
                    'role_name' => $role_name,
                    'candidates' => $candidates
                ];
            }
        }

        return $candidates_requirements;
    }

    public function processBulkDispatch($tick_id, $file_path)
    {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        require_once dirname(__DIR__) . '/models/Regional.php';

        $regionalModel = new Regional();

        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'Archivo físico no encontrado.'];
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al leer Excel: ' . $e->getMessage()];
        }

        if (empty($rows)) {
            return ['success' => false, 'message' => 'El archivo Excel está vacío.'];
        }

        $headers = array_map(function ($h) {
            return strtolower(trim($h));
        }, $rows[0]);
        $regional_idx = array_search('regional', $headers);
        $detalle_idx = array_search('detalle', $headers);

        if ($regional_idx === false || $detalle_idx === false) {
            return ['success' => false, 'message' => 'Faltan columnas requeridas: Regional, Detalle. Encabezados encontrados: ' . implode(', ', $headers)];
        }

        $parent_ticket = $this->ticketModel->listar_ticket_x_id($tick_id);
        if (!$parent_ticket) {
            return ['success' => false, 'message' => 'Ticket padre no encontrado.'];
        }

        $current_step_id = $parent_ticket['paso_actual_id'];

        $next_step = $this->flujoModel->get_siguiente_paso($current_step_id);
        if (!$next_step) {
            return ['success' => false, 'message' => 'No hay un paso siguiente definido para este flujo desde el paso actual (ID: ' . $current_step_id . ').'];
        }
        $target_step_id = $next_step['paso_id'];
        $target_cargo_id = $next_step['cargo_id_asignado'];

        $success_count = 0;
        $fail_count = 0;
        $errors = [];

        $cedula_idx = array_search('cedula', $headers);
        if ($cedula_idx === false) $cedula_idx = array_search('cédula', $headers);

        // Group rows by Regional + Cedula
        $groups = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Safety check for empty rows
            if (!isset($row[$regional_idx]) || empty($row[$regional_idx])) continue;

            $reg_name = trim($row[$regional_idx]);
            $cedula = ($cedula_idx !== false && isset($row[$cedula_idx])) ? trim($row[$cedula_idx]) : 'N/A';

            // Unique key for grouping
            $key = strtolower($reg_name) . '|' . strtolower($cedula);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'reg_name' => $reg_name,
                    'cedula' => $cedula,
                    'rows' => []
                ];
            }
            $groups[$key]['rows'][] = $row;
        }

        foreach ($groups as $group) {
            $reg_name = $group['reg_name'];
            $group_rows = $group['rows'];

            // Build HTML Table for Description with MULTIPLE ROWS
            $table_html = '<table class="table table-bordered table-striped" style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">';
            $table_html .= '<thead><tr style="background-color: #f2f2f2;">';
            foreach ($rows[0] as $header_cell) {
                // Ensure header value is not null even if extremely rare
                $h_val = isset($header_cell) ? htmlspecialchars($header_cell) : '';
                $table_html .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . $h_val . '</th>';
            }
            $table_html .= '</tr></thead>';
            $table_html .= '<tbody>';

            foreach ($group_rows as $g_row) {
                $table_html .= '<tr>';
                foreach ($rows[0] as $k => $h) { // Use header keys to iterate to ensure alignment
                    $cell_value = isset($g_row[$k]) ? $g_row[$k] : '';
                    $val = htmlspecialchars($cell_value);
                    $table_html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $val . '</td>';
                }
                $table_html .= '</tr>';
            }

            $table_html .= '</tbody></table>';
            $detalle = $table_html;

            $reg_id = $regionalModel->get_id_por_nombre($reg_name);
            if (!$reg_id) {
                $fail_count += count($group_rows);
                $errors[] = "Grupo Regional '$reg_name': Regional no encontrada. (" . count($group_rows) . " filas omitidas)";
                continue;
            }

            $candidates = $this->usuarioModel->get_usuarios_por_cargo_y_regional_all($target_cargo_id, $reg_id);

            if (empty($candidates)) {
                $fail_count += count($group_rows);
                $errors[] = "Grupo Regional '$reg_name': No hay usuarios con cargo '$target_cargo_id'. (" . count($group_rows) . " filas omitidas)";
                continue;
            }

            $assigned_user_id = $candidates[0]['usu_id'];

            try {
                $new_tick_id = $this->ticketRepository->insertTicket(
                    $parent_ticket['usu_id'],
                    $parent_ticket['cat_id'],
                    $parent_ticket['cats_id'],
                    $parent_ticket['pd_id'],
                    $parent_ticket['tick_titulo'] . " - " . $reg_name,
                    $detalle,
                    0,
                    $assigned_user_id,
                    $_SESSION['usu_id'],
                    $parent_ticket['emp_id'],
                    $parent_ticket['dp_id'],
                    $target_step_id,
                    $reg_id
                );

                $this->assignmentRepository->insertAssignment($new_tick_id, $assigned_user_id, $_SESSION['usu_id'], $target_step_id, 'Despacho Masivo desde Ticket #' . $tick_id);

                $mensaje_notificacion = "Se le ha asignado un nuevo ticket (Despacho Masivo) # {$new_tick_id}.";
                $this->notificationRepository->insertNotification($assigned_user_id, $mensaje_notificacion, $new_tick_id);

                $success_count += count($group_rows); // Count rows as successful
            } catch (\Exception $e) {
                $fail_count += count($group_rows);
                $errors[] = "Error creando ticket para grupo '$reg_name': " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'processed' => $success_count,
            'failed' => $fail_count,
            'errors' => $errors
        ];
    }
}
