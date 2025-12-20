<?php
class Consulta extends Conectar
{
    public function get_consulta()
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_consulta WHERE est=1";
        $sql = $conectar->prepare($sql);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_consulta_x_id($cons_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_consulta WHERE cons_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cons_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete_consulta($cons_id)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE tm_consulta SET est=0 WHERE cons_id=?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cons_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert_consulta($cons_nom, $cons_sql)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_consulta (cons_nom, cons_sql, est, fech_crea) VALUES (?, ?, 1, NOW())";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cons_nom);
        $sql->bindValue(2, $cons_sql);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update_consulta($cons_id, $cons_nom, $cons_sql)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE tm_consulta SET cons_nom=?, cons_sql=? WHERE cons_id=?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cons_nom);
        $sql->bindValue(2, $cons_sql);
        $sql->bindValue(3, $cons_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}
