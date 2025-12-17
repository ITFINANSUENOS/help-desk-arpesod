<?php
require_once dirname(__DIR__) . '/config/conexion.php';

echo "Iniciando migración de errores antiguos...\n";

$conectar = Conectar::getConexion();

$sql = "
    INSERT INTO tm_ticket_error (tick_id, usu_id_reporta, usu_id_responsable, answer_id, error_descrip, es_error_proceso, fech_crea, est)
    SELECT 
        th.tick_id,
        th.how_asig,        -- Usuario que asignó (Reporta)
        th.usu_asig,        -- Usuario asignado (Responsable/Culpable)
        th.error_code_id,   -- ID del error (Fast Answer)
        IFNULL(th.error_descrip, 'Sin descripción'), -- Descripción
        fa.es_error_proceso, -- Si es error de proceso o no
        th.fech_asig,       -- Fecha original
        1                   -- Estado activo
    FROM th_ticket_asignacion th
    JOIN tm_fast_answer fa ON th.error_code_id = fa.answer_id
    WHERE th.error_code_id IS NOT NULL 
      AND th.error_code_id > 0
      -- Evitar duplicados si se corre varias veces (Opcional, pero recomendado)
      AND NOT EXISTS (
          SELECT 1 FROM tm_ticket_error te 
          WHERE te.tick_id = th.tick_id 
            AND te.fech_crea = th.fech_asig
            AND te.answer_id = th.error_code_id
      );
";

try {
    $stmt = $conectar->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "Migración completada. Se insertaron $count registros en tm_ticket_error.\n";
} catch (Exception $e) {
    echo "Error durante la migración: " . $e->getMessage() . "\n";
}
