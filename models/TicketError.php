<?php
class TicketError extends Conectar
{

    public function insert_error($tick_id, $usu_id_reporta, $usu_id_responsable, $answer_id, $error_descrip, $es_error_proceso)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_ticket_error (tick_id, usu_id_reporta, usu_id_responsable, answer_id, error_descrip, es_error_proceso, fech_crea, est) VALUES (?,?,?,?,?,?,NOW(),1)";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $usu_id_reporta);
        $sql->bindValue(3, $usu_id_responsable);
        $sql->bindValue(4, $answer_id);
        $sql->bindValue(5, $error_descrip);
        $sql->bindValue(6, $es_error_proceso);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function count_errors_by_type($tick_id, $es_error_proceso)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) as count FROM tm_ticket_error WHERE tick_id = ? AND es_error_proceso = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $es_error_proceso);
        $sql->execute();
        $result = $sql->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['count'] : 0;
    }

    // Errors assigned TO the user (Mis Errores)
    public function listar_errores_recibidos($usu_id, $search_term = null, $fech_crea_start = null, $fech_crea_end = null, $tick_id = null, $cats_id = null, $eti_id = null, $start = 0, $length = 10, $order_column = null, $order_dir = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $conditions = "te.usu_id_responsable = :usu_id AND te.est = 1";
        $params = [':usu_id' => $usu_id];

        if (!empty($tick_id)) {
            $conditions .= " AND te.tick_id = :tick_id";
            $params[':tick_id'] = $tick_id;
        }

        if (!empty($cats_id)) {
            $conditions .= " AND t.cats_id = :cats_id";
            $params[':cats_id'] = $cats_id;
        }

        if (!empty($eti_id)) {
            $conditions .= " AND EXISTS (SELECT 1 FROM td_ticket_etiqueta et WHERE et.tick_id = te.tick_id AND et.eti_id = :eti_id AND et.est=1)";
            $params[':eti_id'] = $eti_id;
        }

        if (!empty($fech_crea_start) && !empty($fech_crea_end)) {
            $conditions .= " AND te.fech_crea BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $fech_crea_start . " 00:00:00";
            $params[':end_date'] = $fech_crea_end . " 23:59:59";
        }

        if (!empty($search_term)) {
            $conditions .= " AND (
                t.tick_titulo LIKE :search_term 
                OR te.error_descrip LIKE :search_term
                OR fa.answer_nom LIKE :search_term
            )";
            $params[':search_term'] = "%" . $search_term . "%";
        }

        // Shared JOINS for consistency
        $from_joins = " FROM tm_ticket_error te
                INNER JOIN tm_ticket t ON te.tick_id = t.tick_id
                INNER JOIN tm_categoria cat ON t.cat_id = cat.cat_id
                INNER JOIN tm_subcategoria scat ON t.cats_id = scat.cats_id
                INNER JOIN tm_usuario u_reporta ON te.usu_id_reporta = u_reporta.usu_id
                INNER JOIN tm_fast_answer fa ON te.answer_id = fa.answer_id";

        // 1. Total Records (for this user)
        // Note: We use the same joins to ensure we only count errors for existing tickets/users
        $sql_total = "SELECT COUNT(*) $from_joins WHERE te.usu_id_responsable = :usu_id AND te.est = 1";
        $stmt_total = $conectar->prepare($sql_total);
        $stmt_total->bindValue(':usu_id', $usu_id);
        $stmt_total->execute();
        $result = $stmt_total->fetchAll();
        $recordsTotal = isset($result[0]) ? $result[0][0] : 0;

        // 2. Filtered Records
        $sql_filtered = "SELECT COUNT(*) $from_joins WHERE $conditions";

        $stmt_filtered = $conectar->prepare($sql_filtered);
        foreach ($params as $key => $value) {
            $stmt_filtered->bindValue($key, $value);
        }
        $stmt_filtered->execute();
        $result = $stmt_filtered->fetchAll();
        $recordsFiltered = isset($result[0]) ? $result[0][0] : 0;


        // 3. Data Query
        $sql_data = "SELECT 
                te.error_id,
                te.tick_id,
                te.error_descrip,
                te.fech_crea,
                t.tick_titulo,
                t.tick_estado,
                cat.cat_nom,
                scat.cats_nom,
                u_reporta.usu_nom as reporta_nom,
                u_reporta.usu_ape as reporta_ape,
                fa.answer_nom
                $from_joins
                WHERE $conditions";

        // Ordering
        if ($order_column !== null && $order_dir !== null) {
            $columns = [
                0 => 'te.tick_id',
                1 => 'cat.cat_nom',
                2 => 'scat.cats_nom',
                3 => 't.tick_titulo',
                4 => 'fa.answer_nom',
                5 => 'u_reporta.usu_nom',
                6 => 'te.fech_crea'
            ];
            $colName = isset($columns[$order_column]) ? $columns[$order_column] : 'te.tick_id';
            $dir = strtoupper($order_dir) === 'ASC' ? 'ASC' : 'DESC';
            $sql_data .= " ORDER BY $colName $dir";
        } else {
            $sql_data .= " ORDER BY te.tick_id DESC";
        }

        // Pagination
        if ($length != -1) {
            $sql_data .= " LIMIT :start, :length";
        }

        $stmt_data = $conectar->prepare($sql_data);
        foreach ($params as $key => $value) {
            $stmt_data->bindValue($key, $value);
        }
        if ($length != -1) {
            $stmt_data->bindValue(':start', (int)$start, PDO::PARAM_INT);
            $stmt_data->bindValue(':length', (int)$length, PDO::PARAM_INT);
        }
        $stmt_data->execute();
        $data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered
        ];
    }

    // Errors reported BY the user (Enviados)
    public function listar_errores_enviados($usu_id, $search_term = null, $fech_crea_start = null, $fech_crea_end = null, $tick_id = null, $cats_id = null, $eti_id = null, $start = 0, $length = 10, $order_column = null, $order_dir = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $conditions = "te.usu_id_reporta = :usu_id AND te.est = 1";
        $params = [':usu_id' => $usu_id];

        if (!empty($tick_id)) {
            $conditions .= " AND te.tick_id = :tick_id";
            $params[':tick_id'] = $tick_id;
        }

        if (!empty($cats_id)) {
            $conditions .= " AND t.cats_id = :cats_id";
            $params[':cats_id'] = $cats_id;
        }

        if (!empty($eti_id)) {
            $conditions .= " AND EXISTS (SELECT 1 FROM td_ticket_etiqueta et WHERE et.tick_id = te.tick_id AND et.eti_id = :eti_id AND et.est=1)";
            $params[':eti_id'] = $eti_id;
        }

        if (!empty($fech_crea_start) && !empty($fech_crea_end)) {
            $conditions .= " AND te.fech_crea BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $fech_crea_start . " 00:00:00";
            $params[':end_date'] = $fech_crea_end . " 23:59:59";
        }

        if (!empty($search_term)) {
            $conditions .= " AND (
                t.tick_titulo LIKE :search_term 
                OR te.error_descrip LIKE :search_term
                OR fa.answer_nom LIKE :search_term
            )";
            $params[':search_term'] = "%" . $search_term . "%";
        }

        // Shared JOINS
        $from_joins = " FROM tm_ticket_error te
                INNER JOIN tm_ticket t ON te.tick_id = t.tick_id
                INNER JOIN tm_categoria cat ON t.cat_id = cat.cat_id
                INNER JOIN tm_subcategoria scat ON t.cats_id = scat.cats_id
                INNER JOIN tm_usuario u_resp ON te.usu_id_responsable = u_resp.usu_id
                INNER JOIN tm_fast_answer fa ON te.answer_id = fa.answer_id";

        // 1. Total Records
        $sql_total = "SELECT COUNT(*) $from_joins WHERE te.usu_id_reporta = :usu_id AND te.est = 1";
        $stmt_total = $conectar->prepare($sql_total);
        $stmt_total->bindValue(':usu_id', $usu_id);
        $stmt_total->execute();
        $result = $stmt_total->fetchAll();
        $recordsTotal = isset($result[0]) ? $result[0][0] : 0;

        // 2. Filtered Records
        $sql_filtered = "SELECT COUNT(*) $from_joins WHERE $conditions";

        $stmt_filtered = $conectar->prepare($sql_filtered);
        foreach ($params as $key => $value) {
            $stmt_filtered->bindValue($key, $value);
        }
        $stmt_filtered->execute();
        $result = $stmt_filtered->fetchAll();
        $recordsFiltered = isset($result[0]) ? $result[0][0] : 0;

        // 3. Data Query
        $sql_data = "SELECT 
                te.error_id,
                te.tick_id,
                te.error_descrip,
                te.fech_crea,
                t.tick_titulo,
                t.tick_estado,
                cat.cat_nom,
                scat.cats_nom,
                u_resp.usu_nom as resp_nom,
                u_resp.usu_ape as resp_ape,
                fa.answer_nom
                $from_joins
                WHERE $conditions";

        // Ordering
        if ($order_column !== null && $order_dir !== null) {
            $columns = [
                0 => 'te.tick_id',
                1 => 'cat.cat_nom',
                2 => 'scat.cats_nom',
                3 => 't.tick_titulo',
                4 => 'fa.answer_nom',
                5 => 'u_resp.usu_nom',
                6 => 'te.fech_crea'
            ];
            $colName = isset($columns[$order_column]) ? $columns[$order_column] : 'te.tick_id';
            $dir = strtoupper($order_dir) === 'ASC' ? 'ASC' : 'DESC';
            $sql_data .= " ORDER BY $colName $dir";
        } else {
            $sql_data .= " ORDER BY te.tick_id DESC";
        }

        // Pagination
        if ($length != -1) {
            $sql_data .= " LIMIT :start, :length";
        }

        $stmt_data = $conectar->prepare($sql_data);
        foreach ($params as $key => $value) {
            $stmt_data->bindValue($key, $value);
        }
        if ($length != -1) {
            $stmt_data->bindValue(':start', (int)$start, PDO::PARAM_INT);
            $stmt_data->bindValue(':length', (int)$length, PDO::PARAM_INT);
        }
        $stmt_data->execute();
        $data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered
        ];
    }
}
