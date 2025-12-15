<?php

require_once('../models/Ticket.php');
require_once('../models/Usuario.php');

class TicketLister
{
    private $ticketModel;
    private $usuarioModel;

    public function __construct()
    {
        $this->ticketModel = new Ticket();
        $this->usuarioModel = new Usuario();
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

            $sub_array[] = '<button type="button" onClick="ver(' . $row['tick_id'] . ');" id="' . $row['tick_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';
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


            $sub_array[] = '<button type="button" onClick="ver(' . $row['tick_id'] . ');" id="' . $row['tick_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';

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

    public function listAllTickets($status = null)
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
            $sub_array[] = '<button type="button" onClick="ver(' . $row['tick_id'] . ');" id="' . $row['tick_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';
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

            $sub_array[] = '<button type="button" onClick="ver(' . $row['tick_id'] . ');" class="btn btn-inline btn-primary btn-sm ladda-button" title="Ver Historial Detallado"><i class="fa fa-eye"></i></button>';

            $data[] = $sub_array;
        }

        return [
            "sEcho" => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];
    }

    public function listAllTicketsRecord()
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

            $sub_array[] = '<button type="button" onClick="ver(' . $row['tick_id'] . ');" class="btn btn-inline btn-primary btn-sm ladda-button" title="Ver Historial Detallado"><i class="fa fa-eye"></i></button>';

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
}
