<?php
class Regional extends Conectar
{
    public function insert_regional($reg_nom, $zona_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_regional (reg_nom, zona_id, est) VALUES (?, ?, '1');";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $reg_nom);
        $sql->bindValue(2, $zona_id);
        $sql->execute();
        return $conectar->lastInsertId();
    }

    public function update_regional($reg_id, $reg_nom, $zona_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_regional SET reg_nom = ?, zona_id = ? WHERE reg_id = ?;";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $reg_nom);
        $sql->bindValue(2, $zona_id);
        $sql->bindValue(3, $reg_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function delete_regional($reg_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_regional SET est = '0' WHERE reg_id = ?;";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $reg_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function get_regionales()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT r.*, z.zona_nom 
                FROM tm_regional r 
                LEFT JOIN tm_zona z ON r.zona_id = z.zona_id 
                WHERE r.est = '1';";
        $sql = $conectar->prepare($sql);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function get_regional_x_id($reg_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_regional WHERE reg_id = ?;";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $reg_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function get_zona_por_regional($reg_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT z.zona_nom 
                FROM tm_regional r
                INNER JOIN tm_zona z ON r.zona_id = z.zona_id
                WHERE r.reg_id = ? AND r.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $reg_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['zona_nom'] : null;
    }

    public function get_id_por_nombre($reg_nom)
    {
        $conectar = parent::Conexion();
        $sql = "SELECT reg_id FROM tm_regional WHERE UPPER(reg_nom) = UPPER(?) AND est = 1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, trim($reg_nom));
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['reg_id'] : null;
    }
}
