<?php

require_once('../models/Ticket.php');
require_once('../models/Usuario.php');
require_once('../models/Etiqueta.php');

class TicketLister
{
    private $ticketModel;
    private $usuarioModel;
    private $etiquetaModel;

    public function __construct()
    {
        $this->ticketModel = new Ticket();
        $this->usuarioModel = new Usuario();
        $this->etiquetaModel = new Etiqueta();
    }

    public function listTicketsByUser($userId, $status = null)
    {
        // Prioritize custom search if not empty, otherwise fallback to DataTables search
        $search = !empty($_POST['search_custom']) ? $_POST['search_custom'] : (isset($_POST['search']['value']) ? $_POST['search']['value'] : null);

        $datos = $this->ticketModel->listar_ticket_x_usuario($userId, $search, $status);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cat_nom'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            if ($row['tick_estado'] == 'Abierto') {
                $sub_array[] = '<span class="label label-success">Abierto</span>';
            } else {
                $sub_array[] = '<a onClick="cambiarEstado(' . $row['tick_id'] . ')" ><span class="label label-danger">Cerrado</span></a>';
            }

            if ($row['prioridad_usuario'] == 'Baja') {
                $sub_array[] = '<span class="label label-default">Baja</span>';
            } elseif ($row['prioridad_usuario'] == 'Media') {
                $sub_array[] = '<span class="label label-warning">Media</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Alta</span>';
            }

            if ($row['prioridad_defecto'] == 'Baja') {
                $sub_array[] = '<span class="label label-default">Baja</span>';
            } elseif ($row['prioridad_defecto'] == 'Media') {
                $sub_array[] = '<span class="label label-warning">Media</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Alta</span>';
            }


            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            if ($row['usu_asig'] == null) {
                $sub_array[] = '<span class="label label-danger">Sin asignar</span>';
            } else {
                $sub_array[] = $this->getFormattedUserNames($row['usu_asig'], 'label-success');
            }

            // Etiquetas
            $tags = $this->etiquetaModel->listar_etiquetas_x_ticket($row['tick_id'], $userId);
            $html_tags = '';
            foreach ($tags as $tag) {
                $color = $tag['eti_color'];
                $labelClass = "label label-" . $color;
                if ($color == "secondary") $labelClass = "label label-default";
                if ($color == "dark") $labelClass = "label label-primary";
                $html_tags .= '<span class="' . $labelClass . '" style="margin-right: 2px;">' . $tag['eti_nom'] . '</span> ';
            }
            // Removed icon from here
            $sub_array[] = $html_tags;

            $action_buttons = '<div style="white-space: nowrap;">';
            $action_buttons .= '<a href="javascript:void(0);" onClick="gestionarEtiquetas(' . $row['tick_id'] . ')" title="Gestionar etiquetas" class="btn btn-inline btn-default btn-sm ladda-button"><i class="fa fa-tag"></i></a>';
            $action_buttons .= ' <a href="/view/DetalleTicket/?ID=' . $row['tick_id'] . '" target="_blank" id="' . $row['tick_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></a>';
            $action_buttons .= '</div>';
            $sub_array[] = $action_buttons;
            $data[] = $sub_array;
        }
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

        return [
            "sEcho" => $draw,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }

    public function listTicketsByAgent($agentId, $status = null)
    {
        // Prioritize custom search if not empty, otherwise fallback to DataTables search
        $search = !empty($_POST['search_custom']) ? $_POST['search_custom'] : (isset($_POST['search']['value']) ? $_POST['search']['value'] : null);

        $datos = $this->ticketModel->listar_ticket_x_agente($agentId, $search, $status);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cat_nom'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            if ($row['tick_estado'] == 'Abierto') {
                $sub_array[] = '<span class="label label-success">Abierto</span>';
            } else {
                $sub_array[] = '<a onClick="cambiarEstado(' . $row['tick_id'] . ')" ><span class="label label-danger">Cerrado</span></a>';
            }

            if ($row['prioridad_usuario'] == 'Baja') {
                $sub_array[] = '<span class="label label-default">Baja</span>';
            } elseif ($row['prioridad_usuario'] == 'Media') {
                $sub_array[] = '<span class="label label-warning">Media</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Alta</span>';
            }

            if ($row['prioridad_defecto'] == 'Baja') {
                $sub_array[] = '<span class="label label-default">Baja</span>';
            } elseif ($row['prioridad_defecto'] == 'Media') {
                $sub_array[] = '<span class="label label-warning">Media</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Alta</span>';
            }

            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            if ($row['usu_id'] == null) {
                $sub_array[] = '<span class="label label-danger">Sin asignar</span>';
            } else {
                $sub_array[] = $this->getFormattedUserNames($row['usu_id'], 'label-primary');
            }

            // Etiquetas (Agent Viewer)
            $tags = $this->etiquetaModel->listar_etiquetas_x_ticket($row['tick_id'], $agentId);
            $html_tags = '';
            foreach ($tags as $tag) {
                $color = $tag['eti_color'];
                $labelClass = "label label-" . $color;
                if ($color == "secondary") $labelClass = "label label-default";
                if ($color == "dark") $labelClass = "label label-primary";
                $html_tags .= '<span class="' . $labelClass . '" style="margin-right: 2px;">' . $tag['eti_nom'] . '</span> ';
            }
            // Removed icon from here
            $sub_array[] = $html_tags;

            $action_buttons = '<div style="white-space: nowrap;">';
            $action_buttons .= '<a href="javascript:void(0);" onClick="gestionarEtiquetas(' . $row['tick_id'] . ')" title="Gestionar etiquetas" class="btn btn-inline btn-default btn-sm ladda-button"><i class="fa fa-tag"></i></a>';
            $action_buttons .= ' <a href="/view/DetalleTicket/?ID=' . $row['tick_id'] . '" target="_blank" id="' . $row['tick_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></a>';
            $action_buttons .= '</div>';
            $sub_array[] = $action_buttons;

            $data[] = $sub_array;
        }

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

        return [
            "sEcho" => $draw,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }

    public function listAllTickets($status = null, $viewerId = null)
    {
        // Prioritize custom search if not empty, otherwise fallback to DataTables search
        $search = !empty($_POST['search_custom']) ? $_POST['search_custom'] : (isset($_POST['search']['value']) ? $_POST['search']['value'] : null);
        $datos = $this->ticketModel->listar_ticket($search, $status);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cat_nom'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            if ($row['tick_estado'] == 'Abierto') {
                $sub_array[] = '<span class="label label-success">Abierto</span>';
            } else {
                $sub_array[] = '<a onClick="cambiarEstado(' . $row['tick_id'] . ')" ><span class="label label-danger">Cerrado</span></a>';
            }

            if ($row['prioridad_usuario'] == 'Baja') {
                $sub_array[] = '<span class="label label-default">Baja</span>';
            } elseif ($row['prioridad_usuario'] == 'Media') {
                $sub_array[] = '<span class="label label-warning">Media</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Alta</span>';
            }

            if ($row['prioridad_defecto'] == 'Baja') {
                $sub_array[] = '<span class="label label-default">Baja</span>';
            } elseif ($row['prioridad_defecto'] == 'Media') {
                $sub_array[] = '<span class="label label-warning">Media</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Alta</span>';
            }


            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            if ($row['usu_asig'] == null) {
                $sub_array[] = '<a><span class="label label-danger">Sin asignar</span></a>';
            } else {
                $sub_array[] = '<a onClick="asignar(' . $row['tick_id'] . ')" >' . $this->getFormattedUserNames($row['usu_asig'], 'label-success') . '</a> ';
            }
            if ($row['usu_id'] == null) {
                $sub_array[] = '<a><span class="label label-danger">Sin asignar</span></a>';
            } else {
                $sub_array[] = '<a onClick="asignar(' . $row['tick_id'] . ')" >' . $this->getFormattedUserNames($row['usu_id'], 'label-success') . '</a> ';
            }

            // Etiquetas (Generic Viewer)
            $html_tags = '';
            if ($viewerId) {
                $tags = $this->etiquetaModel->listar_etiquetas_x_ticket($row['tick_id'], $viewerId);
                foreach ($tags as $tag) {
                    $color = $tag['eti_color'];
                    $labelClass = "label label-" . $color;
                    if ($color == "secondary") $labelClass = "label label-default";
                    if ($color == "dark") $labelClass = "label label-primary";
                    $html_tags .= '<span class="' . $labelClass . '" style="margin-right: 2px;">' . $tag['eti_nom'] . '</span> ';
                }
            }
            // Removed icon from here
            $sub_array[] = $html_tags;

            $action_buttons = '<div style="white-space: nowrap;">';
            $action_buttons .= '<a href="javascript:void(0);" onClick="gestionarEtiquetas(' . $row['tick_id'] . ')" title="Gestionar etiquetas" class="btn btn-inline btn-default btn-sm ladda-button"><i class="fa fa-tag"></i></a>';
            $action_buttons .= ' <a href="/view/DetalleTicket/?ID=' . $row['tick_id'] . '" target="_blank" id="' . $row['tick_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></a>';
            $action_buttons .= '</div>';
            $sub_array[] = $action_buttons;
            $data[] = $sub_array;
        }

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

        return [
            "sEcho" => $draw,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }

    public function listTicketsRecordByAgent($usuId)
    {
        // Prioritize custom search if not empty, otherwise fallback to DataTables search
        $search = !empty($_POST['search_custom']) ? $_POST['search_custom'] : (isset($_POST['search']['value']) ? $_POST['search']['value'] : null);
        $datos = $this->ticketModel->listar_tickets_involucrados_por_usuario($usuId, $search);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            if ($row['tick_estado'] == 'Abierto') {
                $sub_array[] = '<span class="label label-success">Abierto</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Cerrado</span>';
            }

            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            if ($row['usu_nom'] === null) {
                $sub_array[] = '<span class="label label-default">Sin Asignar</span>';
            } else {
                $sub_array[] = $row['usu_nom'] . ' ' . $row['usu_ape'];
            }

            // Etiquetas (Record Agent)
            $tags = $this->etiquetaModel->listar_etiquetas_x_ticket($row['tick_id'], $usuId);
            $html_tags = '';
            foreach ($tags as $tag) {
                $color = $tag['eti_color'];
                $labelClass = "label label-" . $color;
                if ($color == "secondary") $labelClass = "label label-default";
                if ($color == "dark") $labelClass = "label label-primary";
                $html_tags .= '<span class="' . $labelClass . '" style="margin-right: 2px;">' . $tag['eti_nom'] . '</span> ';
            }
            // Removed icon from here
            $sub_array[] = $html_tags;

            $action_buttons = '<div style="white-space: nowrap;">';
            $action_buttons .= '<a href="javascript:void(0);" onClick="gestionarEtiquetas(' . $row['tick_id'] . ')" title="Gestionar etiquetas" class="btn btn-inline btn-default btn-sm ladda-button"><i class="fa fa-tag"></i></a>';
            $action_buttons .= ' <a href="/view/DetalleHistorialTicket/?ID=' . $row['tick_id'] . '" target="_blank" class="btn btn-inline btn-primary btn-sm ladda-button" title="Ver Historial Detallado"><i class="fa fa-eye"></i></a>';
            $action_buttons .= '</div>';
            $sub_array[] = $action_buttons;

            $data[] = $sub_array;
        }

        return [
            "sEcho" => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }

    public function listAllTicketsRecord($viewerId = null)
    {
        // Prioritize custom search if not empty, otherwise fallback to DataTables search
        $search = !empty($_POST['search_custom']) ? $_POST['search_custom'] : (isset($_POST['search']['value']) ? $_POST['search']['value'] : null);
        $datos = $this->ticketModel->listar_tickets_con_historial($search);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            if ($row['tick_estado'] == 'Abierto') {
                $sub_array[] = '<span class="label label-success">Abierto</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Cerrado</span>';
            }

            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            if ($row['usu_nom'] === null) {
                $sub_array[] = '<span class="label label-default">Sin Asignar</span>';
            } else {
                $sub_array[] = $row['usu_nom'] . ' ' . $row['usu_ape'];
            }

            // Etiquetas (Record All)
            $html_tags = '';
            if ($viewerId) {
                $tags = $this->etiquetaModel->listar_etiquetas_x_ticket($row['tick_id'], $viewerId);
                foreach ($tags as $tag) {
                    $color = $tag['eti_color'];
                    $labelClass = "label label-" . $color;
                    if ($color == "secondary") $labelClass = "label label-default";
                    if ($color == "dark") $labelClass = "label label-primary";
                    $html_tags .= '<span class="' . $labelClass . '" style="margin-right: 2px;">' . $tag['eti_nom'] . '</span> ';
                }
            }
            // Removed icon from here
            $sub_array[] = $html_tags;

            $action_buttons = '<div style="white-space: nowrap;">';
            $action_buttons .= '<a href="javascript:void(0);" onClick="gestionarEtiquetas(' . $row['tick_id'] . ')" title="Gestionar etiquetas" class="btn btn-inline btn-default btn-sm ladda-button"><i class="fa fa-tag"></i></a>';
            $action_buttons .= ' <a href="/view/DetalleHistorialTicket/?ID=' . $row['tick_id'] . '" target="_blank" class="btn btn-inline btn-primary btn-sm ladda-button" title="Ver Historial Detallado"><i class="fa fa-eye"></i></a>';
            $action_buttons .= '</div>';
            $sub_array[] = $action_buttons;

            $data[] = $sub_array;
        }

        return [
            "sEcho" => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }

    private function getFormattedUserNames($ids, $labelClass = 'label-success')
    {
        if (empty($ids)) return '';

        if (strpos($ids, ',') !== false) {
            $idArray = explode(',', $ids);
            $names = [];
            foreach ($idArray as $id) {
                $u = $this->usuarioModel->get_usuario_x_id(trim($id));
                if ($u) {
                    $names[] = $u['usu_nom'] . ' ' . $u['usu_ape'];
                }
            }
            return '<span class="label ' . $labelClass . '">' . implode(', ', $names) . '</span>';
        } else {
            $u = $this->usuarioModel->get_usuario_x_id($ids);
            if ($u) {
                return '<span class="label ' . $labelClass . '">' . $u['usu_nom'] . ' ' . $u['usu_ape'] . '</span>';
            }
        }
        return '';
    }

    public function listTicketsWithError()
    {
        $datos = $this->ticketModel->listar_tickets_con_error();
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cat_nom'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            // Columna de DETALLE ERROR
            $sub_array[] = '<span style="color:red; font-weight:bold;">' . ($row['ultimo_error'] ?? 'Sin detalle') . '</span>';

            if ($row['tick_estado'] == 'Abierto') {
                $sub_array[] = '<span class="label label-success">Abierto</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Cerrado</span>';
            }

            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            if ($row['usu_asig'] == null) {
                $sub_array[] = '<span class="label label-danger">Sin asignar</span>';
            } else {
                $sub_array[] = $this->getFormattedUserNames($row['usu_asig'], 'label-warning');
            }

            $sub_array[] = '<a href="/view/DetalleTicket/?ID=' . $row['tick_id'] . '" target="_blank" class="btn btn-inline btn-primary btn-sm ladda-button" title="Ver Ticket"><i class="fa fa-eye"></i></a>';

            $data[] = $sub_array;
        }

        return [
            "sEcho" => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }
    public function listReceivedErrors($usuId)
    {
        require_once('../models/TicketError.php');
        $ticketErrorModel = new TicketError();
        $datos = $ticketErrorModel->listar_errores_recibidos($usuId);
        return $this->formatErrorList($datos);
    }

    public function listReportedErrors($usuId)
    {
        require_once('../models/TicketError.php');
        $ticketErrorModel = new TicketError();
        $datos = $ticketErrorModel->listar_errores_enviados($usuId);
        return $this->formatErrorList($datos, true);
    }

    private function formatErrorList($datos, $isReported = false)
    {
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cat_nom'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            // Detalle del Error
            $sub_array[] = '<span style="color:red; font-weight:bold;">' . $row['answer_nom'] . '</span><br>' . ($row['error_descrip'] ?? '');

            // Si es lista de reportados, mostramos QUIEN es el responsable. Si es recibidos, mostramos QUIEN reportó.
            if ($isReported) {
                // "Responsable" (quien cometió el error)
                $sub_array[] = $row['resp_nom'] . ' ' . $row['resp_ape'];
            } else {
                // "Reportado Por" (quien me asignó el error)
                $sub_array[] = $row['reporta_nom'] . ' ' . $row['reporta_ape'];
            }

            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            $sub_array[] = '<a href="/view/DetalleTicket/?ID=' . $row['tick_id'] . '" target="_blank" class="btn btn-inline btn-primary btn-sm ladda-button" title="Ver Ticket"><i class="fa fa-eye"></i></a>';

            $data[] = $sub_array;
        }

        return [
            "sEcho" => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }

    public function listTicketsByObserver($userId)
    {
        $datos = $this->ticketModel->listar_tickets_observados($userId);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();
            $sub_array[] = $row['tick_id'];
            $sub_array[] = $row['cat_nom'];
            $sub_array[] = $row['cats_nom'];
            $sub_array[] = $row['tick_titulo'];

            if ($row['tick_estado'] == 'Abierto') {
                $sub_array[] = '<span class="label label-success">Abierto</span>';
            } else {
                $sub_array[] = '<span class="label label-danger">Cerrado</span>';
            }

            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

            if ($row['usu_asig'] == null || $row['usu_asig'] == '') {
                $sub_array[] = '<span class="label label-danger">Sin asignar</span>';
            } else {
                $sub_array[] = $this->getFormattedUserNames($row['usu_asig'], 'label-success');
            }

            $sub_array[] = '<a href="/view/DetalleTicket/?ID=' . $row['tick_id'] . '" target="_blank" id="' . $row['tick_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></a>';
            $data[] = $sub_array;
        }

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

        return [
            "sEcho" => $draw,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }
}
