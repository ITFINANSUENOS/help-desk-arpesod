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

    // Errors assigned TO the user (Mis Errores)
    public function listar_errores_recibidos($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
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
                FROM tm_ticket_error te
                INNER JOIN tm_ticket t ON te.tick_id = t.tick_id
                INNER JOIN tm_categoria cat ON t.cat_id = cat.cat_id
                INNER JOIN tm_subcategoria scat ON t.cats_id = scat.cats_id
                INNER JOIN tm_usuario u_reporta ON te.usu_id_reporta = u_reporta.usu_id
                INNER JOIN tm_fast_answer fa ON te.answer_id = fa.answer_id
                WHERE te.usu_id_responsable = ?
                AND te.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    // Errors reported BY the user (Enviados)
    public function listar_errores_enviados($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
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
                FROM tm_ticket_error te
                INNER JOIN tm_ticket t ON te.tick_id = t.tick_id
                INNER JOIN tm_categoria cat ON t.cat_id = cat.cat_id
                INNER JOIN tm_subcategoria scat ON t.cats_id = scat.cats_id
                INNER JOIN tm_usuario u_resp ON te.usu_id_responsable = u_resp.usu_id
                INNER JOIN tm_fast_answer fa ON te.answer_id = fa.answer_id
                WHERE te.usu_id_reporta = ?
                AND te.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }
}
