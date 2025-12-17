<?php
require_once(__DIR__ . '/../config/conexion.php');

class MigrationObserverColumn extends Conectar
{
    public function migrate()
    {
        $conectar = parent::Conexion();

        try {
            // Check current column type (optional, but good for safety)
            // But for now, we just run the ALTER command.
            // Converting INT to TEXT keeps the data (e.g., '123' becomes "123").

            $sql = "ALTER TABLE tm_flujo CHANGE usu_id_observador usu_id_observador TEXT NULL DEFAULT NULL";
            $stmt = $conectar->prepare($sql);
            $stmt->execute();

            echo "Migration SUCCESS: 'usu_id_observador' column changed to TEXT.\n";
        } catch (Exception $e) {
            echo "Migration FAILED: " . $e->getMessage() . "\n";
        }
    }
}

$migration = new MigrationObserverColumn();
$migration->migrate();
