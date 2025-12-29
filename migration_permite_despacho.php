<?php
require_once("config/conexion.php");

class Migration extends Conectar
{
    public function run()
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $sql = "SHOW COLUMNS FROM tm_flujo_paso LIKE 'permite_despacho_masivo'";
        $stmt = $conectar->prepare($sql);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE tm_flujo_paso ADD COLUMN permite_despacho_masivo TINYINT(1) DEFAULT 0 AFTER cerrar_ticket_obligatorio";
            $stmt = $conectar->prepare($sql);
            $stmt->execute();
            echo "Columna 'permite_despacho_masivo' creada correctamente.\n";
        } else {
            echo "La columna 'permite_despacho_masivo' ya existe.\n";
        }
    }
}

$migration = new Migration();
$migration->run();
