<?php
require_once(__DIR__ . "/../config/conexion.php");

class Migration extends Conectar
{
    public function up()
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $sql_check = "SHOW COLUMNS FROM tm_flujo LIKE 'usu_id_observador'";
        $stmt_check = $conectar->prepare($sql_check);
        $stmt_check->execute();

        if ($stmt_check->rowCount() == 0) {
            $sql = "ALTER TABLE tm_flujo ADD COLUMN usu_id_observador INT NULL DEFAULT NULL AFTER cats_id";
            $stmt = $conectar->prepare($sql);
            $stmt->execute();
            echo "Columna 'usu_id_observador' agregada exitosamente.\n";
        } else {
            echo "La columna 'usu_id_observador' ya existe.\n";
        }
    }
}

$migration = new Migration();
$migration->up();
