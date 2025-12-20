<?php
require_once(__DIR__ . "/../config/conexion.php");

class Migration extends Conectar
{
    public function run()
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // Tabla tm_etiqueta
        $sql1 = "CREATE TABLE IF NOT EXISTS `tm_etiqueta` (
            `eti_id` int(11) NOT NULL AUTO_INCREMENT,
            `eti_nom` varchar(150) COLLATE utf8_spanish_ci NOT NULL,
            `eti_color` varchar(50) COLLATE utf8_spanish_ci NOT NULL COMMENT 'Bootstrap class suffix or hex',
            `fech_crea` datetime DEFAULT NULL,
            `est` int(11) NOT NULL DEFAULT '1',
            PRIMARY KEY (`eti_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;";

        // Tabla td_ticket_etiqueta
        $sql2 = "CREATE TABLE IF NOT EXISTS `td_ticket_etiqueta` (
            `tick_eti_id` int(11) NOT NULL AUTO_INCREMENT,
            `tick_id` int(11) NOT NULL,
            `eti_id` int(11) NOT NULL,
            `usu_id` int(11) NOT NULL,
            `fech_crea` datetime DEFAULT NULL,
            `est` int(11) NOT NULL DEFAULT '1',
            PRIMARY KEY (`tick_eti_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;";

        try {
            $conectar->query($sql1);
            echo "Tabla tm_etiqueta creada o ya existe.<br>";
            $conectar->query($sql2);
            echo "Tabla td_ticket_etiqueta creada o ya existe.<br>";
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "<br>";
        }
    }
}

$migration = new Migration();
$migration->run();
