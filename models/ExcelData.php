<?php
class ExcelData extends Conectar
{
    public function insert_data($flujo_id, $nombre_archivo, $datos_json)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_data_excel (flujo_id, nombre_archivo, datos_json, fech_carga, est) VALUES (?, ?, ?, NOW(), 1)";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->bindValue(2, $nombre_archivo);
        $sql->bindValue(3, $datos_json);
        $sql->execute();
        return $conectar->lastInsertId();
    }

    public function get_data_by_flow($flujo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT data_id, nombre_archivo, fech_carga FROM tm_data_excel WHERE flujo_id = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_data_by_id($data_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_data_excel WHERE data_id = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $data_id);
        $sql->execute();
        return $resultado = $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function delete_data($data_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_data_excel SET est = 0 WHERE data_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $data_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }
}
