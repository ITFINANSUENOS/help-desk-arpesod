<?php
require_once(__DIR__ . "/../config/conexion.php");

class Migration extends Conectar
{
    public function run()
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // Add usu_id column to tm_etiqueta
        $sql = "ALTER TABLE `tm_etiqueta` ADD COLUMN `usu_id` INT(11) NOT NULL AFTER `eti_id`;";

        try {
            $conectar->query($sql);
            echo "Columna usu_id agregada a tm_etiqueta.<br>";
        } catch (PDOException $e) {
            echo "Error (posible clave duplicada o columna existente): " . $e->getMessage() . "<br>";
        }
    }
}

$migration = new Migration();
$migration->run();
