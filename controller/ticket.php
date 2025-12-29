<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('../config/conexion.php');
require_once('../services/TicketService.php');
require_once('../services/TicketWorkflowService.php');
require_once('../services/TicketLister.php');
require_once('../services/TicketDetailLister.php');
require_once('../models/repository/TicketRepository.php');
require_once('../models/repository/NotificationRepository.php');
require_once('../models/repository/AssignmentRepository.php');
require_once('../models/repository/NovedadRepository.php');

$pdo = Conectar::getConexion();

$ticket = new Ticket();
$ticketService = new TicketService($pdo);
$workflowService = new TicketWorkflowService();
$lister = new TicketLister();
$detailLister = new TicketDetailLister();

switch ($_GET["op"]) {

    case "insert":
        $result = $ticketService->createTicket($_POST);
        echo json_encode($result);
        break;

    case "listar_x_usu":
        $status = isset($_POST['tick_estado']) ? $_POST['tick_estado'] : null;
        $result = $lister->listTicketsByUser($_POST['usu_id'], $status);
        echo json_encode($result);
        break;

    case "listar_x_agente":
        $status = isset($_POST['tick_estado']) ? $_POST['tick_estado'] : null;
        $result = $lister->listTicketsByAgent($_POST['usu_asig'], $status);
        echo json_encode($result);
        break;

    case "listar":
        $status = isset($_POST['tick_estado']) ? $_POST['tick_estado'] : null;
        $result = $lister->listAllTickets($status, $_SESSION['usu_id']);
        echo json_encode($result);
        break;

    case "listar_error_recibido":
        $result = $lister->listReceivedErrors($_POST['usu_id']);
        echo json_encode($result);
        break;

    case "listar_error_enviado":
        $result = $lister->listReportedErrors($_POST['usu_id']);
        echo json_encode($result);
        break;

    case "listar_historial_tabla_x_agente":
        $result = $lister->listTicketsRecordByAgent($_POST['usu_id']);
        echo json_encode($result);
        break;

    case "listar_observados":
        $result = $lister->listTicketsByObserver($_POST['usu_id']);
        echo json_encode($result);
        break;

    case "listardetalle":
        $detailLister->listTicketDetails($_POST['tick_id']);
        break;

    case "listarhistorial":
        $detailLister->listTicketDetailRecord($_POST['tick_id']);
        break;

    case "listar_historial_tabla":
        $result = $lister->listAllTicketsRecord($_SESSION['usu_id']);
        echo json_encode($result);
        break;

    case "mostrar":
        $result = $ticketService->showTicket($_POST['tick_id']);
        break;

    case "get_transiciones":
        require_once('../models/FlujoPaso.php');
        $flujoPaso = new FlujoPaso();
        $transiciones = $flujoPaso->get_transiciones_por_paso($_POST["paso_id"]);
        echo json_encode($transiciones);
        break;

    case "insertdetalle":
        $result = $ticketService->createDetailTicket($_POST);
        break;

    case "update":
        $ticketService->cerrar_ticket($_POST["tick_id"], "Ticket cerrado manualmente");
        break;

    case 'cerrar_con_nota':
        $files = isset($_FILES['cierre_files']) ? $_FILES['cierre_files'] : [];
        $ticketService->cerrarTicketConNota($_POST["tick_id"], $_POST["usu_id"], $_POST["nota_cierre"], $files);
        echo json_encode(["success" => true]);
        break;

    case "reabrir":
        $ticket->reabrir_ticket($_POST['tick_id']);
        // $correo->ticket_cerrado($_POST['tick_id']);
        $ticket->insert_ticket_detalle_reabrir($_POST['tick_id'], $_POST['usu_id']);
        break;

    case "updateasignacion":
        $ticket->update_ticket_asignacion($_POST['tick_id'], $_POST['usu_asig'], $_POST['how_asig']);
        break;

    case "calendario_x_usu_asig":
        $datos = $ticket->get_calendar_x_asig($_POST['usu_asig']);
        echo json_encode($datos);
        break;

    case "calendario_x_usu":
        $datos = $ticket->get_calendar_x_usu($_POST['usu_id']);
        echo json_encode($datos);
        break;

    case "aprobar_flujo":
        $result = $workflowService->ApproveFlow($_POST, $_SESSION);
        break;

    case "registrar_error":
        $result = $ticketService->LogErrorTicket($_POST);
        break;

    case "verificar_inicio_flujo":
        $result = $workflowService->CheckStartFlow($_POST);
        break;

    case "aprobar_paso":
        $resultado = $ticketService->approveStep($_POST['tick_id']);
        echo json_encode($resultado);
        break;

    case "rechazar_paso":
        $resultado = $ticketService->rejectStep($_POST['tick_id']);
        echo json_encode($resultado);
        break;

    case "crear_novedad":
        $result = $ticketService->crearNovedad($_POST);
        echo json_encode($result);
        break;

    case "resolver_novedad":
        $result = $ticketService->resolverNovedad($_POST);
        echo json_encode($result);
        break;

    case "get_novedad_abierta":
        $novedadRepository = new \models\repository\NovedadRepository($pdo);
        $novedad = $novedadRepository->getNovedadAbiertaPorTicket($_POST['tick_id']);
        echo json_encode($novedad);
        break;

    case "listar_novedades_x_usu":
        $novedadRepository = new \models\repository\NovedadRepository($pdo);
        $novedades = $novedadRepository->getNovedadesAbiertasPorUsuario($_POST['usu_id']);
        $data = array();
        foreach ($novedades as $row) {
            $sub_array = array();
            $sub_array[] = $row["tick_id"];
            $sub_array[] = $row["descripcion_novedad"];
            $sub_array[] = $row["fecha_inicio"];
            $sub_array[] = $row["usu_crea_novedad"];
            $sub_array[] = '<button type="button" onClick="ver(' . $row["tick_id"] . ');"  id="' . $row["tick_id"] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';
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

    case "listar_paralelo":
        require_once('../models/TicketParalelo.php');
        $ticketParalelo = new TicketParalelo();
        $datos = $ticketParalelo->get_ticket_paralelo_por_ticket_y_paso($_POST['tick_id'], $_POST['paso_id']);
        echo json_encode($datos);
        break;

    case "get_usuarios_paso":
        $usuarios = $ticketService->getUsuariosPorPaso($_POST['paso_id']);
        echo json_encode($usuarios);
        break;

    case "dispatch_uploaded_excel":
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
            exit;
        }
        $file_path = $_FILES['excel_file']['tmp_name'];
        $result = $ticketService->processBulkDispatch($_POST['tick_id'], $file_path);
        echo json_encode($result);
        break;

    case "check_next_step_candidates":
        $result = $ticketService->getNextStepCandidates($_POST['tick_id']);
        echo json_encode($result);
        break;
}
