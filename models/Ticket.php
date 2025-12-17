<?php
class Ticket extends Conectar
{
    public function update_asignacion_y_paso($tick_id, $usu_asig, $paso_actual_id, $quien_asigno_id, $asig_comentario = 'Reasignado por avance en el flujo', $notification_message = null)
    {
        $conectar = parent::Conexion();
        // Actualiza el usuario asignado y el ID del paso actual en el ticket
        $sql = "UPDATE tm_ticket 
                SET 
                    usu_asig = ?,
                    paso_actual_id = ?
                WHERE
                    tick_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_asig);
        $sql->bindValue(2, $paso_actual_id);
        $sql->bindValue(3, $tick_id);
        $sql->execute();

        $sql2 = "INSERT INTO th_ticket_asignacion (tick_id, usu_asig, how_asig, paso_id, fech_asig, asig_comentario, est)
                VALUES (?, ?, ?, ?, NOW(), ?, 1);";
        $sql2 = $conectar->prepare($sql2);
        $sql2->bindValue(1, $tick_id);                 // El ticket afectado
        $sql2->bindValue(2, $usu_asig);                // El NUEVO usuario asignado
        $sql2->bindValue(3, $quien_asigno_id);         // El usuario que HIZO la reasignación
        $sql2->bindValue(4, $paso_actual_id);          // El ID del paso actual
        $sql2->bindValue(5, $asig_comentario);         // El comentario de la asignación
        $sql2->execute();

        if ($quien_asigno_id != $usu_asig) {
            if ($notification_message) {
                $mensaje_notificacion = $notification_message;
            } else {
                // a. Buscamos el nombre del paso para que la notificación sea más clara
                $sql_paso = "SELECT paso_nombre FROM tm_flujo_paso WHERE paso_id = ?";
                $sql_paso = $conectar->prepare($sql_paso);
                $sql_paso->bindValue(1, $paso_actual_id);
                $sql_paso->execute();
                $paso_data = $sql_paso->fetch(PDO::FETCH_ASSOC);
                $nombre_paso = $paso_data ? $paso_data['paso_nombre'] : 'un nuevo paso';

                // b. Creamos el mensaje de la notificación
                $mensaje_notificacion = "Se te ha asignado el Ticket #" . $tick_id . " para la tarea: '" . $nombre_paso . "'";
            }

            // c. Preparamos la consulta para insertar en la tabla de notificaciones
            $sql3 = "INSERT INTO tm_notificacion (usu_id, not_mensaje, tick_id, fech_not, est) VALUES (?, ?, ?, NOW(), 2)";
            $sql3 = $conectar->prepare($sql3);
            $sql3->bindValue(1, $usu_asig); // El ID del usuario a notificar
            $sql3->bindValue(2, $mensaje_notificacion); // El mensaje
            $sql3->bindValue(3, $tick_id); // El ID del ticket relacionado
            $sql3->execute();
        }

        return $sql->fetchAll();
    }

    public function cerrar_ticket_con_nota($tick_id, $usu_id, $nota_cierre, $files = [])
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // 1. Cerrar el ticket
        $sql_update = "UPDATE tm_ticket SET tick_estado = 'Cerrado', fech_cierre = NOW() WHERE tick_id = ?";
        $sql_update = $conectar->prepare($sql_update);
        $sql_update->bindValue(1, $tick_id);
        $sql_update->execute();

        // 2. Insertar la nota de cierre como un detalle del ticket
        $sql_detalle = "INSERT INTO td_ticketdetalle (tick_id, usu_id, tickd_descrip, fech_crea, est) VALUES (?, ?, ?, NOW(), '1')";
        $sql_detalle = $conectar->prepare($sql_detalle);
        $sql_detalle->bindValue(1, $tick_id);
        $sql_detalle->bindValue(2, $usu_id);
        $sql_detalle->bindValue(3, $nota_cierre);
        $sql_detalle->execute();
        $tickd_id = $conectar->lastInsertId();

        // 3. Manejar archivos adjuntos
        if (!empty($files['name'][0])) {
            require_once('DocumentoCierre.php');
            $documento_cierre = new DocumentoCierre();

            $upload_dir = '../public/document/cierre/' . $tick_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($files['name'] as $key => $name) {
                $file_tmp = $files['tmp_name'][$key];
                $file_name = $name;
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($file_tmp, $file_path)) {
                    $documento_cierre->insert_documento_cierre($tick_id, $file_name);
                }
            }
        }

        // 4. Notificar a todos los usuarios involucrados
        require_once('DocumentoCierre.php');
        $documento_cierre = new DocumentoCierre();
        $documentos = $documento_cierre->get_documentos_cierre_x_ticket($tick_id);

        $mensaje_notificacion = "El Ticket #" . $tick_id . " ha sido cerrado con la siguiente nota: " . strip_tags($nota_cierre);

        if (!empty($documentos)) {
            $mensaje_notificacion .= "<br><b>Documentos adjuntos:</b><br>";
            foreach ($documentos as $doc) {
                $url = '../../public/document/cierre/' . $tick_id . '/' . rawurlencode($doc['doc_nom']);
                $mensaje_notificacion .= "<a href=\"" . $url . "\" target=\"_blank\">" . htmlspecialchars($doc['doc_nom']) . "</a><br>";
            }
        }

        // Obtener el creador del ticket
        $sql_get_creator = "SELECT usu_id FROM tm_ticket WHERE tick_id = ?";
        $stmt_creator = $conectar->prepare($sql_get_creator);
        $stmt_creator->bindValue(1, $tick_id);
        $stmt_creator->execute();
        $creator = $stmt_creator->fetch(PDO::FETCH_ASSOC);

        // Obtener todos los usuarios asignados en el historial
        $sql_get_assigned = "SELECT DISTINCT usu_asig FROM th_ticket_asignacion WHERE tick_id = ?";
        $stmt_assigned = $conectar->prepare($sql_get_assigned);
        $stmt_assigned->bindValue(1, $tick_id);
        $stmt_assigned->execute();
        $assigned_users = $stmt_assigned->fetchAll(PDO::FETCH_COLUMN);

        $involved_users = array_unique(array_merge([$creator['usu_id']], $assigned_users));

        foreach ($involved_users as $user_id_to_notify) {
            if ($user_id_to_notify) {
                $sql_notif = "INSERT INTO tm_notificacion (usu_id, not_mensaje, tick_id, fech_not, est) VALUES (?, ?, ?, NOW(), '2')";
                $stmt_notif = $conectar->prepare($sql_notif);
                $stmt_notif->bindValue(1, $user_id_to_notify);
                $stmt_notif->bindValue(2, $mensaje_notificacion);
                $stmt_notif->bindValue(3, $tick_id);
                $stmt_notif->execute();
            }
        }
    }

    public function listar_ticket_x_usuario($usu_id, $search_term = null, $status = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
                tm_ticket.tick_id,
                tm_ticket.usu_id,
                tm_ticket.cat_id,
                tm_ticket.tick_titulo,
                tm_ticket.tick_descrip,
                tm_ticket.tick_estado,
                tm_ticket.fech_crea,     
                tm_ticket.usu_asig,
                tm_usuario.usu_nom,
                tm_usuario.usu_ape,
                tm_categoria.cat_nom,
                tm_subcategoria.cats_nom,
                pd.pd_nom as prioridad_usuario,
                pdd.pd_nom as prioridad_defecto
                FROM 
                tm_ticket
                INNER join tm_categoria on tm_ticket.cat_id = tm_categoria.cat_id
                INNER join tm_subcategoria on tm_ticket.cats_id = tm_subcategoria.cats_id 
                INNER join tm_usuario on tm_ticket.usu_id = tm_usuario.usu_id
                INNER join td_prioridad as pd on tm_ticket.pd_id = pd.pd_id
                INNER join td_prioridad as pdd on tm_subcategoria.pd_id = pdd.pd_id
                WHERE 
                tm_ticket.est = 1
                AND tm_usuario.usu_id=?";

        if (!empty($status)) {
            $sql .= " AND tm_ticket.tick_estado = ?";
        }

        if (!empty($search_term)) {
            $sql .= " AND (
                tm_ticket.tick_titulo LIKE ? 
                OR tm_ticket.tick_descrip LIKE ?
                OR EXISTS (
                    SELECT 1 FROM td_ticketdetalle 
                    WHERE td_ticketdetalle.tick_id = tm_ticket.tick_id 
                    AND td_ticketdetalle.tickd_descrip LIKE ?
                )
            )";
        }

        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);

        $paramIndex = 2;
        if (!empty($status)) {
            $sql->bindValue($paramIndex, $status);
            $paramIndex++;
        }

        if (!empty($search_term)) {
            $term = "%" . $search_term . "%";
            $sql->bindValue($paramIndex, $term);
            $paramIndex++;
            $sql->bindValue($paramIndex, $term);
            $paramIndex++;
            $sql->bindValue($paramIndex, $term);
            $paramIndex++;
        }

        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function listar_ticket_x_agente($usu_asig, $search_term = null, $status = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
                tm_ticket.tick_id,
                tm_ticket.usu_id,
                tm_ticket.cat_id,
                tm_ticket.tick_titulo,
                tm_ticket.tick_descrip,
                tm_ticket.tick_estado,
                tm_ticket.fech_crea,     
                tm_ticket.usu_asig,
                tm_usuario.usu_nom,
                tm_usuario.usu_ape,
                tm_usuario.usu_correo,
                tm_categoria.cat_nom,
                tm_subcategoria.cats_nom,
                pd.pd_nom as prioridad_usuario,
                pdd.pd_nom as prioridad_defecto
                FROM 
                tm_ticket
                INNER join tm_categoria on tm_ticket.cat_id = tm_categoria.cat_id
                INNER JOIN tm_subcategoria on tm_ticket.cats_id = tm_subcategoria.cats_id
                INNER join tm_usuario on tm_ticket.usu_id = tm_usuario.usu_id
                INNER join td_prioridad as pd on tm_ticket.pd_id = pd.pd_id
                INNER join td_prioridad as pdd on tm_subcategoria.pd_id = pdd.pd_id

                WHERE 
                tm_ticket.est = 1";

        // MÓDULO EXPANDIDO: Si es 'Cerrado', buscar también en historial.
        if ($status == 'Cerrado') {
            $sql .= " AND ( FIND_IN_SET(?, tm_ticket.usu_asig) OR EXISTS (SELECT 1 FROM th_ticket_asignacion WHERE th_ticket_asignacion.tick_id = tm_ticket.tick_id AND th_ticket_asignacion.usu_asig = ?) )";
        } else {
            $sql .= " AND FIND_IN_SET(?, tm_ticket.usu_asig)";
        }

        if (!empty($status)) {
            $sql .= " AND tm_ticket.tick_estado = ?";
        }

        if (!empty($search_term)) {
            $sql .= " AND (
                tm_ticket.tick_titulo LIKE ? 
                OR tm_ticket.tick_descrip LIKE ?
                OR EXISTS (
                    SELECT 1 FROM td_ticketdetalle 
                    WHERE td_ticketdetalle.tick_id = tm_ticket.tick_id 
                    AND td_ticketdetalle.tickd_descrip LIKE ?
                )
            )";
        }


        $sql = $conectar->prepare($sql);

        $paramIndex = 1;

        if ($status == 'Cerrado') {
            $sql->bindValue($paramIndex, $usu_asig); // FIND_IN_SET
            $paramIndex++;
            $sql->bindValue($paramIndex, $usu_asig); // EXISTS
            $paramIndex++;
        } else {
            $sql->bindValue($paramIndex, $usu_asig);
            $paramIndex++;
        }

        if (!empty($status)) {
            $sql->bindValue($paramIndex, $status);
            $paramIndex++;
        }

        if (!empty($search_term)) {
            $term = "%" . $search_term . "%";
            $sql->bindValue($paramIndex, $term);
            $paramIndex++;
            $sql->bindValue($paramIndex, $term);
            $paramIndex++;
            $sql->bindValue($paramIndex, $term);
            $paramIndex++;
        }

        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function listar_ticket($search_term = null, $status = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
                tm_ticket.tick_id,
                tm_ticket.usu_id,
                tm_ticket.cat_id,
                tm_ticket.tick_titulo,
                tm_ticket.tick_descrip,
                tm_ticket.tick_estado,
                tm_ticket.fech_crea,
                tm_ticket.usu_asig,
                tm_usuario.usu_nom,
                tm_usuario.usu_ape,
                tm_categoria.cat_nom,
                tm_subcategoria.cats_nom,
                pd.pd_nom as prioridad_usuario,
                pdd.pd_nom as prioridad_defecto
                FROM 
                tm_ticket
                INNER join tm_categoria on tm_ticket.cat_id = tm_categoria.cat_id
                INNER JOIN tm_subcategoria on tm_ticket.cats_id = tm_subcategoria.cats_id
                INNER join tm_usuario on tm_ticket.usu_id = tm_usuario.usu_id
                INNER join td_prioridad as pd on tm_ticket.pd_id = pd.pd_id
                INNER join td_prioridad as pdd on tm_subcategoria.pd_id = pdd.pd_id
                WHERE 
                tm_ticket.est = 1";

        if (!empty($status)) {
            $sql .= " AND tm_ticket.tick_estado = ?";
        }

        if (!empty($search_term)) {
            $sql .= " AND (
                tm_ticket.tick_titulo LIKE ? 
                OR tm_ticket.tick_descrip LIKE ?
                OR EXISTS (
                    SELECT 1 FROM td_ticketdetalle 
                    WHERE td_ticketdetalle.tick_id = tm_ticket.tick_id 
                    AND td_ticketdetalle.tickd_descrip LIKE ?
                )
            )";
        }

        $sql = $conectar->prepare($sql);

        if (!empty($status)) {
            $sql->bindValue(1, $status);
        }

        if (!empty($search_term)) {
            $term = "%" . $search_term . "%";
            $paramStart = !empty($status) ? 2 : 1;
            $sql->bindValue($paramStart, $term);
            $sql->bindValue($paramStart + 1, $term);
            $sql->bindValue($paramStart + 2, $term);
        }

        $sql->execute();

        return $resultado = $sql->fetchAll(pdo::FETCH_ASSOC);
    }


    public function listar_ticketdetalle_x_ticket($tick_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT
                td_ticketdetalle.tickd_id, 
                td_ticketdetalle.tickd_descrip,
                td_ticketdetalle.fech_crea,
                tm_usuario.usu_nom,
                tm_usuario.usu_ape,
                tm_usuario.rol_id,
                GROUP_CONCAT(td_documento_detalle.det_nom SEPARATOR '|') as det_noms
            FROM td_ticketdetalle 
            INNER JOIN tm_usuario ON td_ticketdetalle.usu_id = tm_usuario.usu_id
            LEFT JOIN td_documento_detalle ON td_ticketdetalle.tickd_id = td_documento_detalle.tickd_id
            WHERE td_ticketdetalle.tick_id = ? AND td_ticketdetalle.est = 1
            GROUP BY td_ticketdetalle.tickd_id
            ORDER BY td_ticketdetalle.tickd_id ASC
              ";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function listar_ticket_x_id($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT
        t.tick_id,
        t.usu_id,
        t.emp_id,
        t.cat_id,
        t.cats_id,
        t.tick_titulo,
        t.tick_descrip,
        t.tick_estado,
        t.fech_crea,
        t.usu_asig,
        t.pd_id,
        t.paso_actual_id,
        t.ruta_id,
        t.ruta_paso_orden,
        u.usu_nom,
        u.usu_ape,
        u.usu_correo,
        IFNULL(t.reg_id, u.reg_id) as reg_id,
        c.cat_nom,
        sc.cats_nom,
        p.pd_nom,
        d.dp_nom,
        e.emp_nom,
        t.usu_id_jefe_aprobador,
        paso.paso_nombre
    FROM
        tm_ticket t
        LEFT JOIN tm_categoria c ON t.cat_id = c.cat_id
        LEFT JOIN tm_usuario u ON t.usu_id = u.usu_id
        LEFT JOIN tm_departamento d ON t.dp_id = d.dp_id     
        LEFT JOIN td_empresa e ON t.emp_id = e.emp_id
        LEFT JOIN td_prioridad p ON t.pd_id = p.pd_id
        LEFT JOIN tm_subcategoria sc on t.cats_id = sc.cats_id
        LEFT JOIN tm_flujo_paso AS paso ON t.paso_actual_id = paso.paso_id
    WHERE
        t.est = 1 AND t.tick_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $resultado = $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listar_historial_completo($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "
            (SELECT
                d.fech_crea AS fecha_evento,
                'comentario' AS tipo,
                u.usu_nom,
                u.usu_ape,
                u.rol_id,
                d.tickd_descrip AS descripcion,
                NULL AS nom_receptor,
                NULL AS ape_receptor,
                GROUP_CONCAT(doc.det_nom SEPARATOR '|') AS det_noms,
                d.tickd_id,
                NULL AS estado_tiempo_paso, -- Columna de relleno para que la unión funcione
                NULL AS error_descrip -- Columna de relleno
            FROM td_ticketdetalle d
            INNER JOIN tm_usuario u ON d.usu_id = u.usu_id
            LEFT JOIN td_documento_detalle doc ON d.tickd_id = doc.tickd_id
            WHERE d.tick_id = ?
            GROUP BY d.tickd_id)

            UNION ALL

            (SELECT
                a.fech_asig AS fecha_evento,
                'asignacion' AS tipo,
                IFNULL(u_origen.usu_nom, 'Sistema') AS usu_nom,
                IFNULL(u_origen.usu_ape, '(Acción Automática)') AS usu_ape,
                IFNULL(u_origen.rol_id, 0) AS rol_id,
                a.asig_comentario AS descripcion,
                u_nuevo.usu_nom AS nom_receptor,
                u_nuevo.usu_ape AS ape_receptor,
                NULL AS det_noms,
                NULL AS tickd_id,
                a.estado_tiempo_paso, -- AÑADIDO: Seleccionamos el estado del paso
                a.error_descrip -- NUEVO: Seleccionamos la descripción del error
            FROM th_ticket_asignacion a
            LEFT JOIN tm_usuario u_origen ON a.how_asig = u_origen.usu_id
            INNER JOIN tm_usuario u_nuevo ON a.usu_asig = u_nuevo.usu_id
            WHERE a.tick_id = ?)

            UNION ALL

            (SELECT
                t.fech_cierre AS fecha_evento,
                'cierre' AS tipo,
                u_cierre.usu_nom,
                u_cierre.usu_ape,
                u_cierre.rol_id,
                'Ticket cerrado' AS descripcion,
                NULL AS nom_receptor,
                NULL AS ape_receptor,
                NULL AS det_noms,
                NULL AS tickd_id,
                NULL AS estado_tiempo_paso, -- Columna de relleno
                NULL AS error_descrip -- Columna de relleno
            FROM tm_ticket t
            LEFT JOIN tm_usuario u_cierre ON t.usu_asig = u_cierre.usu_id
            WHERE t.tick_id = ? AND t.fech_cierre IS NOT NULL)

            UNION ALL

            (SELECT
                tp.fech_crea AS fecha_evento,
                'asignacion' AS tipo,
                'Sistema' AS usu_nom,
                '(Automático)' AS usu_ape,
                0 AS rol_id,
                'Asignación Paralela' AS descripcion,
                u.usu_nom AS nom_receptor,
                u.usu_ape AS ape_receptor,
                NULL AS det_noms,
                NULL AS tickd_id,
                tp.estado_tiempo_paso AS estado_tiempo_paso,
                NULL AS error_descrip
            FROM tm_ticket_paralelo tp
            INNER JOIN tm_usuario u ON tp.usu_id = u.usu_id
            WHERE tp.tick_id = ?)

            UNION ALL

            (SELECT
                tp.fech_cierre AS fecha_evento,
                'comentario' AS tipo,
                u.usu_nom,
                u.usu_ape,
                u.rol_id,
                CONCAT('Respuesta Paralela: ', tp.estado, ' - ', IFNULL(tp.comentario, '')) AS descripcion,
                NULL AS nom_receptor,
                NULL AS ape_receptor,
                NULL AS det_nom,
                NULL AS tickd_id,
                tp.estado_tiempo_paso AS estado_tiempo_paso,
                NULL AS error_descrip
            FROM tm_ticket_paralelo tp
            INNER JOIN tm_usuario u ON tp.usu_id = u.usu_id
            WHERE tp.tick_id = ? AND tp.fech_cierre IS NOT NULL)

            ORDER BY fecha_evento ASC
        ";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $tick_id);
        $sql->bindValue(3, $tick_id);
        $sql->bindValue(4, $tick_id);
        $sql->bindValue(5, $tick_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listar_tickets_con_historial($search_term = null)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT
                    t.tick_id,
                    t.tick_titulo,
                    t.tick_estado,
                    t.fech_crea,
                    cats.cats_nom,
                    u.usu_nom,
                    u.usu_ape
                FROM
                    tm_ticket t
                INNER JOIN tm_subcategoria cats ON t.cats_id = cats.cats_id
                LEFT JOIN tm_usuario u ON t.usu_asig = u.usu_id
                WHERE
                    t.tick_id IN (SELECT tick_id FROM th_ticket_asignacion)";

        if (!empty($search_term)) {
            $sql .= " AND (
                t.tick_titulo LIKE ? 
                OR t.tick_descrip LIKE ?
                OR EXISTS (SELECT 1 FROM td_ticketdetalle d WHERE d.tick_id = t.tick_id AND d.tickd_descrip LIKE ?)
            )";
        }

        $sql .= " ORDER BY t.tick_id DESC";

        $sql = $conectar->prepare($sql);

        if (!empty($search_term)) {
            $term = "%" . $search_term . "%";
            $sql->bindValue(1, $term);
            $sql->bindValue(2, $term);
            $sql->bindValue(3, $term);
        }

        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listar_tickets_involucrados_por_usuario($usu_id, $search_term = null)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT
                    t.tick_id,
                    t.tick_titulo,
                    t.tick_estado,
                    t.fech_crea,
                    cats.cats_nom,
                    u.usu_nom,
                    u.usu_ape
                FROM
                    tm_ticket t
                INNER JOIN tm_subcategoria cats ON t.cats_id = cats.cats_id
                LEFT JOIN tm_usuario u ON t.usu_asig = u.usu_id
                WHERE (
                    t.tick_id IN (SELECT DISTINCT tick_id FROM th_ticket_asignacion WHERE usu_asig = ?)
                    OR t.how_asig = ?
                    OR t.tick_id IN (SELECT DISTINCT tick_id FROM tm_ticket_paralelo WHERE usu_id = ?)
                )";

        if (!empty($search_term)) {
            $sql .= " AND (
                t.tick_titulo LIKE ? 
                OR t.tick_descrip LIKE ?
                OR EXISTS (SELECT 1 FROM td_ticketdetalle d WHERE d.tick_id = t.tick_id AND d.tickd_descrip LIKE ?)
            )";
        }

        $sql .= " ORDER BY t.tick_id DESC";

        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->bindValue(2, $usu_id);
        $sql->bindValue(3, $usu_id);

        if (!empty($search_term)) {
            $term = "%" . $search_term . "%";
            $sql->bindValue(4, $term);
            $sql->bindValue(5, $term);
            $sql->bindValue(6, $term);
        }

        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listar_ticket_x_id_x_usuaarioasignado($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT
        tm_ticket.tick_id,
        tm_ticket.usu_id,
        tm_ticket.cat_id,
        tm_ticket.tick_titulo,
        tm_ticket.tick_descrip,
        tm_ticket.tick_estado,
        tm_ticket.fech_crea,
        tm_usuario.usu_nom,
        tm_usuario.usu_ape,
        tm_usuario.usu_correo,
        tm_categoria.cat_nom
        FROM
        tm_ticket
        INNER JOIN tm_categoria ON tm_ticket.cat_id = tm_categoria.cat_id
        INNER JOIN tm_usuario ON tm_ticket.usu_asig = tm_usuario.usu_id
        WHERE
        tm_ticket.est = 1 AND tm_ticket.tick_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function listar_ticket_x_id_x_quien_asigno($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT
        tm_ticket.tick_id,
        tm_ticket.usu_id,
        tm_ticket.cat_id,
        tm_ticket.tick_titulo,
        tm_ticket.tick_descrip,
        tm_ticket.tick_estado,
        tm_ticket.fech_crea,
        tm_usuario.usu_nom,
        tm_usuario.usu_ape,
        tm_usuario.usu_correo,
        tm_categoria.cat_nom
        FROM
        tm_ticket
        INNER JOIN tm_categoria ON tm_ticket.cat_id = tm_categoria.cat_id
        INNER JOIN tm_usuario ON tm_ticket.how_asig = tm_usuario.usu_id
        WHERE
        tm_ticket.est = 1 AND tm_ticket.tick_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function insert_ticket_detalle($tick_id, $usu_id, $tickd_descrip)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $ticket = new Ticket();

        $datos  = $ticket->listar_ticket_x_id($tick_id);
        $usu_asig = $datos['usu_asig'];
        $usu_idx = $datos['usu_id'];


        if ($_SESSION['rol_id'] == 1) {

            $mensaje1 = "El usuario te ha respondido el ticket Nro " . $tick_id;


            $sql2 = "INSERT INTO tm_notificacion(not_id,usu_id,not_mensaje,tick_id,fech_not,est) VALUES(NULL,$usu_asig,?,?,NOW(),'2');";
            $sql2 = $conectar->prepare($sql2);
            $sql2->bindValue(1, $mensaje1);
            $sql2->bindValue(2, $tick_id);

            $sql2->execute();
        } else {

            $mensaje2 = "El agente de soporte te ha respondido el ticket Nro " . $tick_id;

            $sql3 = "INSERT INTO tm_notificacion(not_id,usu_id,not_mensaje,tick_id,fech_not,est) VALUES(NULL,?,?,?,NOW(),'2');";
            $sql3 = $conectar->prepare($sql3);
            $sql3->bindValue(1, $usu_idx);
            $sql3->bindValue(2, $mensaje2);
            $sql3->bindValue(3, $tick_id);

            $sql3->execute();
        }

        $sql = "INSERT INTO td_ticketdetalle (tickd_id, tick_id, usu_id, tickd_descrip, fech_crea, est) VALUES ( NULL, ?, ?, ?, NOW(), '1')  ";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $usu_id);
        $sql->bindValue(3, $tickd_descrip);
        $sql->execute();

        $sql1 = "SELECT LAST_INSERT_ID() as tickd_id";
        $sql1 = $conectar->prepare($sql1);
        $sql1->execute();

        return $sql1->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update_ticket($tick_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // 1. Antes de cerrar, obtenemos el ID del usuario creador del ticket.
        $sql_get_user = "SELECT usu_id FROM tm_ticket WHERE tick_id = ?";
        $sql_get_user = $conectar->prepare($sql_get_user);
        $sql_get_user->bindValue(1, $tick_id);
        $sql_get_user->execute();
        $ticket_data = $sql_get_user->fetch(PDO::FETCH_ASSOC);
        $usu_id_creador = $ticket_data['usu_id'];

        // 2. Cerramos el ticket (tu lógica original).
        $sql_update = "UPDATE tm_ticket SET tick_estado = 'Cerrado', fech_cierre = NOW() WHERE tick_id = ?";
        $sql_update = $conectar->prepare($sql_update);
        $sql_update->bindValue(1, $tick_id);
        $sql_update->execute();

        // 3. Creamos y guardamos la notificación para el usuario creador.
        if ($usu_id_creador) {
            $mensaje_notificacion = "Tu Ticket #" . $tick_id . " ha sido cerrado.";

            $sql_notif = "INSERT INTO tm_notificacion (usu_id, not_mensaje, tick_id, fech_not, est) VALUES (?, ?, ?, NOW(), '2')";
            $sql_notif = $conectar->prepare($sql_notif);
            $sql_notif->bindValue(1, $usu_id_creador);
            $sql_notif->bindValue(2, $mensaje_notificacion);
            $sql_notif->bindValue(3, $tick_id);
            $sql_notif->execute();
        }
    }
    public function reabrir_ticket($tick_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_ticket SET tick_estado = 'Abierto' WHERE tm_ticket.tick_id = ? ";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    // Método para corregir el bug de "Error de Proceso":
    // Actualiza el dueño actual SIN insertar en historial (porque TicketService ya lo inserta manualmente).
    public function update_owner_silent($tick_id, $usu_asig)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_ticket SET usu_asig = ? WHERE tick_id = ?";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_asig);
        $stmt->bindValue(2, $tick_id);
        $stmt->execute();
    }

    public function update_ticket_asignacion($tick_id, $usu_asig, $how_asig)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_ticket SET usu_asig = ?, how_asig = ? WHERE tm_ticket.tick_id = ? ";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_asig);
        $sql->bindValue(2, $how_asig);
        $sql->bindValue(3, $tick_id);

        $sql->execute();

        // Crea el mensaje completo en una variable de PHP
        $mensaje = "Se le ha asignado el ticket # " . $tick_id;

        $sql1 = "INSERT INTO tm_notificacion(not_id,usu_id,not_mensaje,tick_id,fech_not,est) VALUES(NULL,?,?,?,NOW(),2);";
        $sql1 = $conectar->prepare($sql1);
        $sql1->bindValue(1, $usu_asig);
        $sql1->bindValue(2, $mensaje);
        $sql1->bindValue(3, $tick_id);

        $sql1->execute();

        $sql2 = "INSERT INTO th_ticket_asignacion (tick_id, usu_asig, how_asig, fech_asig, asig_comentario, est)
                VALUES (?, ?, ?, NOW(), 'Ticket trasladado',1)";
        $sql2 = $conectar->prepare($sql2);
        $sql2->bindValue(1, $tick_id);
        $sql2->bindValue(2, $usu_asig);
        $sql2->bindValue(3, $how_asig);

        $sql2->execute();

        return $resultado = $sql->fetchAll();
    }

    public function insert_ticket_detalle_cerrar($tick_id, $usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO td_ticketdetalle (tickd_id, tick_id, usu_id, tickd_descrip, fech_crea, est) VALUES ( NULL, ?, ?, 'Ticket cerrado', NOW(), '1')  ";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function insert_ticket_detalle_reabrir($tick_id, $usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO td_ticketdetalle (tickd_id, tick_id, usu_id, tickd_descrip, fech_crea, est) VALUES ( NULL, ?, ?, 'Ticket Re-abierto', NOW(), '1')  ";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_ticket_total()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) AS TOTAL FROM tm_ticket WHERE est = '1'";
        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_ticket_totalabierto_id()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) AS TOTAL FROM tm_ticket WHERE tick_estado = 'Abierto' and est = '1'";
        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_ticket_totalcerrado_id()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) AS TOTAL FROM tm_ticket WHERE tick_estado = 'Cerrado' and est = '1'";
        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_total_categoria()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT tm_categoria.cat_nom as nom , COUNT(*) AS total
        FROM tm_ticket JOIN tm_categoria ON tm_ticket.cat_id = tm_categoria.cat_id
        WHERE tm_ticket.est = '1'
        GROUP BY tm_categoria.cat_nom
        ORDER BY total DESC";
        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_calendar_x_asig($usu_asig)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT
                tm_ticket.tick_id as id,
                tm_ticket.tick_titulo as title,
                tm_ticket.fech_crea as start,
                tm_ticket.tick_estado as estado,
                tm_ticket.tick_descrip as descripcion,
                td_prioridad.pd_nom as prioridad,
                CONCAT(tm_usuario.usu_nom, ' ', tm_usuario.usu_ape) as nombre,
                CASE 
                    WHEN tm_ticket.tick_estado = 'Abierto' THEN 'green'   
                    WHEN tm_ticket.tick_estado = 'Cerrado' THEN 'red'  
                    ELSE 'white' 
                END as color
                FROM 
                tm_ticket
                INNER JOIN tm_usuario ON tm_ticket.usu_id = tm_usuario.usu_id
                INNER JOIN td_prioridad ON tm_ticket.pd_id = td_prioridad.pd_id
                WHERE usu_asig = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_asig);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_calendar_x_usu($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT
                tm_ticket.tick_id as id,
                tm_ticket.tick_titulo as title,
                tm_ticket.fech_crea as start,
                tm_ticket.tick_estado as estado,
                tm_ticket.tick_descrip as descripcion,
                CONCAT(usu_asignado.usu_nom, ' ', usu_asignado.usu_ape) as usuasignado,
                td_prioridad.pd_nom as prioridad,
                CASE 
                    WHEN tm_ticket.tick_estado = 'Abierto' THEN 'green'   
                    WHEN tm_ticket.tick_estado = 'Cerrado' THEN 'red'  
                    ELSE 'white' 
                END as color
                FROM 
                tm_ticket
                INNER JOIN tm_usuario as usu_creador ON tm_ticket.usu_id = usu_creador.usu_id
                INNER JOIN td_prioridad ON tm_ticket.pd_id = td_prioridad.pd_id
                LEFT JOIN tm_usuario as usu_asignado ON tm_ticket.usu_asig = usu_asignado.usu_id


                WHERE tm_ticket.usu_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_ticket_region($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        // Esta consulta busca el ticket, luego al usuario creador, y finalmente la regional de ese usuario.
        $sql = "SELECT t.reg_id
                FROM tm_ticket t
                WHERE t.tick_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['reg_id'] : null;
    }

    public function get_fecha_ultima_asignacion($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT fech_asig 
                FROM th_ticket_asignacion 
                WHERE tick_id = ? 
                ORDER BY fech_asig DESC 
                LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['fech_asig'] : null;
    }

    public function get_ultima_asignacion($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        // Añadimos t.paso_actual_id para saber en qué paso estaba
        $sql = "SELECT a.*, t.paso_actual_id
                FROM th_ticket_asignacion a
                INNER JOIN tm_ticket t ON a.tick_id = t.tick_id
                WHERE a.tick_id = ? 
                ORDER BY a.fech_asig DESC 
                LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_penultimo_historial($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        // LIMIT 1 OFFSET 1 significa "sáltate el primer resultado (el más nuevo) y dame el siguiente"
        $sql = "SELECT *
                FROM th_ticket_asignacion
                WHERE tick_id = ? 
                ORDER BY fech_asig DESC 
                LIMIT 1 OFFSET 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function update_estado_tiempo_paso($th_id, $estado)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE th_ticket_asignacion SET estado_tiempo_paso = ? WHERE th_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $estado);
        $sql->bindValue(2, $th_id);
        $sql->execute();
    }

    public function get_penultima_asignacion($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();

        // --- CORREGIDO: Ahora seleccionamos a.* para traer todas las columnas del historial ---
        $sql = "SELECT 
                    a.*, 
                    u.usu_nom, 
                    u.usu_ape
                FROM 
                    th_ticket_asignacion a
                INNER JOIN 
                    tm_usuario u ON a.usu_asig = u.usu_id
                WHERE 
                    a.tick_id = ? 
                ORDER BY 
                    a.fech_asig DESC 
                LIMIT 1 OFFSET 1";

        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_primera_asignacion($tick_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT *
                FROM th_ticket_asignacion
                WHERE tick_id = ?
                ORDER BY fech_asig ASC
                LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function update_error_proceso($tick_id, $error_code)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE tm_ticket SET error_proceso = ? WHERE tick_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $error_code);
        $sql->bindValue(2, $tick_id);
        $sql->execute();
    }

    public function update_error_code_paso($th_id, $error_code_id, $error_descrip)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE th_ticket_asignacion SET error_code_id = ?, error_descrip = ? WHERE th_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $error_code_id);
        $sql->bindValue(2, $error_descrip);
        $sql->bindValue(3, $th_id);
        $sql->execute();
    }

    public function get_th_id_by_fecha($tick_id, $fecha_evento)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT th_id FROM th_ticket_asignacion WHERE tick_id = ? AND fech_asig = ? ORDER BY th_id DESC LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $fecha_evento);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['th_id'] : null;
    }

    public function get_usuario_asignado_a_paso($tick_id, $paso_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT usu_asig 
                FROM th_ticket_asignacion 
                WHERE tick_id = ? AND paso_id = ? 
                ORDER BY fech_asig DESC 
                LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $paso_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['usu_asig'] : null;
    }

    public function get_last_forward_assignment_for_paso($tick_id, $paso_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT *
                FROM th_ticket_asignacion
                WHERE tick_id = ? 
                  AND paso_id = ?
                  AND asig_comentario != 'Ticket devuelto por error de proceso.'
                ORDER BY fech_asig DESC
                LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $paso_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }
    public function mark_as_error($tick_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_ticket SET error_proceso = 1 WHERE tick_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
    }

    public function listar_tickets_con_error()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
                tm_ticket.tick_id,
                tm_ticket.usu_id,
                tm_ticket.cat_id,
                tm_ticket.tick_titulo,
                tm_ticket.tick_descrip,
                tm_ticket.tick_estado,
                tm_ticket.fech_crea,     
                tm_ticket.usu_asig,
                tm_usuario.usu_nom,
                tm_usuario.usu_ape,
                tm_categoria.cat_nom,
                tm_subcategoria.cats_nom,
                (
                    SELECT error_descrip 
                    FROM th_ticket_asignacion 
                    WHERE th_ticket_asignacion.tick_id = tm_ticket.tick_id 
                    AND th_ticket_asignacion.error_descrip IS NOT NULL 
                    ORDER BY th_ticket_asignacion.fech_asig DESC 
                    LIMIT 1
                ) as ultimo_error
                FROM 
                tm_ticket
                INNER join tm_categoria on tm_ticket.cat_id = tm_categoria.cat_id
                INNER JOIN tm_subcategoria on tm_ticket.cats_id = tm_subcategoria.cats_id
                INNER join tm_usuario on tm_ticket.usu_id = tm_usuario.usu_id
                WHERE 
                tm_ticket.est = 1
                AND tm_ticket.error_proceso > 0";

        $sql = $conectar->prepare($sql);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }
}
