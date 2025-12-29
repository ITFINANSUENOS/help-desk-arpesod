<?php

class Documento extends Conectar
{

    public function insert_documento($tick_id, $doc_nom)
    {
        $conectar = parent::conexion();
        $sql = "INSERT INTO td_documento (doc_id, tick_id, doc_nom, fech_crea, est) VALUES (NULL, ?, ?, NOW(), '1')";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $doc_nom);
        $sql->execute();
    }

    public function get_documento_x_ticket($tick_id)
    {
        $conectar = parent::conexion();
        $sql = "SELECT * FROM td_documento WHERE tick_id = ? AND est = '1'";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert_documento_detalle($tickd_id, $det_nom)
    {
        $conectar = parent::conexion();
        $sql = "INSERT INTO td_documento_detalle (det_id, tickd_id, det_nom, fech_crea, est) VALUES (NULL, ?, ?, NOW(), '1')";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tickd_id);
        $sql->bindValue(2, $det_nom);
        $sql->execute();
    }

    public function get_documento_detalle_x_ticket($tickd_id)
    {
        $conectar = parent::conexion();
        $sql = "SELECT * FROM td_documento_detalle WHERE tickd_id = ? AND est = '1'";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tickd_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_ultimo_documento_detalle($tick_id)
    {
        $conectar = parent::conexion();
        $sql = "SELECT d.det_nom, d.tickd_id 
                    FROM td_documento_detalle d
                    INNER JOIN td_ticketdetalle t ON d.tickd_id = t.tickd_id
                    WHERE t.tick_id = ? AND d.est = '1' AND d.det_nom LIKE 'signed_%'
                    ORDER BY d.fech_crea DESC LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_documento_x_id($doc_id)
    {
        $conectar = parent::conexion();
        $sql = "SELECT * FROM td_documento WHERE doc_id = ? AND est = '1'";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $doc_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}
