<?php
class Etiqueta extends Conectar
{
    // Insertar nueva etiqueta (Personal)
    public function insert_etiqueta($usu_id, $eti_nom, $eti_color)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_etiqueta (eti_id, usu_id, eti_nom, eti_color, fech_crea, est) VALUES (NULL, ?, ?, ?, NOW(), 1);";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->bindValue(2, $eti_nom);
        $sql->bindValue(3, $eti_color);
        $sql->execute();
        return $conectar->lastInsertId();
    }

    // Actualizar etiqueta
    public function update_etiqueta($eti_id, $eti_nom, $eti_color)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE tm_etiqueta SET eti_nom = ?, eti_color = ? WHERE eti_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $eti_nom);
        $sql->bindValue(2, $eti_color);
        $sql->bindValue(3, $eti_id);
        $sql->execute();
        return $sql->rowCount();
    }

    // Obtener etiqueta por ID
    public function get_etiqueta_x_id($eti_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_etiqueta WHERE eti_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $eti_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    // Listar todas las etiquetas activas del usuario
    public function listar_etiquetas($usu_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_etiqueta WHERE est = 1 AND usu_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    // Listar todas las etiquetas activas (para Agentes)
    public function listar_etiquetas_total()
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT tm_etiqueta.*, tm_usuario.usu_nom, tm_usuario.usu_ape 
                FROM tm_etiqueta 
                LEFT JOIN tm_usuario ON tm_etiqueta.usu_id = tm_usuario.usu_id
                WHERE tm_etiqueta.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    // Eliminar (soft delete) etiqueta
    public function delete_etiqueta($eti_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE tm_etiqueta SET est = 0 WHERE eti_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $eti_id);
        $sql->execute();
        return $sql->rowCount();
    }

    // LISTAR ETIQUETAS POR TICKET (Solo las propias del usuario)
    public function listar_etiquetas_x_ticket($tick_id, $usu_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT 
                    te.tick_eti_id,
                    te.tick_id,
                    e.eti_id,
                    e.eti_nom,
                    e.eti_color
                FROM td_ticket_etiqueta te
                INNER JOIN tm_etiqueta e ON te.eti_id = e.eti_id
                WHERE te.tick_id = ? AND te.est = 1 AND e.est = 1 AND e.usu_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $usu_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    // ASIGNAR ETIQUETA A TICKET
    public function asignar_etiqueta_ticket($tick_id, $eti_id, $usu_id)
    {
        $conectar = parent::conexion();
        parent::set_names();

        // Verificar si ya existe
        $sql_check = "SELECT * FROM td_ticket_etiqueta WHERE tick_id = ? AND eti_id = ? AND est = 1";
        $stmt = $conectar->prepare($sql_check);
        $stmt->bindValue(1, $tick_id);
        $stmt->bindValue(2, $eti_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return false; // Ya existe
        }

        $sql = "INSERT INTO td_ticket_etiqueta (tick_eti_id, tick_id, eti_id, usu_id, fech_crea, est) VALUES (NULL, ?, ?, ?, NOW(), 1)";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $eti_id);
        $sql->bindValue(3, $usu_id);
        $sql->execute();
        return true;
    }

    // ELIMINAR ETIQUETA DE TICKET (Por ID Relación)
    public function eliminar_etiqueta_ticket($tick_eti_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE td_ticket_etiqueta SET est = 0 WHERE tick_eti_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_eti_id);
        $sql->execute();
    }

    // DESLIGAR ETIQUETA DE TICKET (Por IDs)
    public function desligar_etiqueta_ticket($tick_id, $eti_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE td_ticket_etiqueta SET est = 0 WHERE tick_id = ? AND eti_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $eti_id);
        $sql->execute();
    }
}
