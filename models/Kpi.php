<?php
class Kpi extends Conectar
{
    /**
     * Obtiene el número de pasos asignados a un usuario.
     * Considera asignaciones directas en th_ticket_asignacion.
     */
    public function get_pasos_asignados($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // Contamos cuántas veces el usuario aparece como 'usu_asig' en el historial,
        // PERO excluimos cuando el mismo usuario se lo asignó (ej. creación propia).
        // Se considera creación propia si how_asig es el mismo usuario.
        // Si how_asig es NULL o es otro usuario, SÍ cuenta.
        $sql = "SELECT COUNT(*) as total FROM th_ticket_asignacion WHERE usu_asig = ? AND (how_asig != ? OR how_asig IS NULL) AND est = 1";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_id);
        $stmt->bindValue(2, $usu_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'];
    }

    /**
     * Obtiene el número de pasos finalizados por un usuario.
     * Se considera finalizado si el usuario reasignó el ticket a OTRO usuario (how_asig = usu_id AND usu_asig != usu_id)
     * o si el ticket fue cerrado por el usuario.
     */
    public function get_pasos_finalizados($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // 1. Pasos MOVIDOS:
        // El usuario (how_asig) movió el ticket a otro (usu_asig != user).
        // CONDICIÓN ADICIONAL: La asignación INMEDIATAMENTE ANTERIOR (la que le dio el ticket al usuario)
        // NO debió ser una auto-asignación (how_asig != user).
        $sql_movidos = "SELECT COUNT(*) as total 
                        FROM th_ticket_asignacion t1
                        WHERE t1.how_asig = ? 
                        AND t1.usu_asig != ? 
                        AND t1.est = 1
                        AND (
                            (
                                SELECT t2.how_asig 
                                FROM th_ticket_asignacion t2 
                                WHERE t2.tick_id = t1.tick_id 
                                AND t2.usu_asig = t1.how_asig -- Debe ser el usuario actual
                                AND t2.fech_asig < t1.fech_asig
                                ORDER BY t2.fech_asig DESC 
                                LIMIT 1
                            ) != ? 
                            OR 
                            (
                                SELECT t2.how_asig 
                                FROM th_ticket_asignacion t2 
                                WHERE t2.tick_id = t1.tick_id 
                                AND t2.usu_asig = t1.how_asig 
                                AND t2.fech_asig < t1.fech_asig
                                ORDER BY t2.fech_asig DESC 
                                LIMIT 1
                            ) IS NULL
                        )";

        $stmt_movidos = $conectar->prepare($sql_movidos);
        $stmt_movidos->bindValue(1, $usu_id);
        $stmt_movidos->bindValue(2, $usu_id);
        $stmt_movidos->bindValue(3, $usu_id);
        $stmt_movidos->execute();
        $movidos = $stmt_movidos->fetch(PDO::FETCH_ASSOC);

        // 2. Tickets CERRADOS:
        // El usuario cerró el ticket.
        // CONDICIÓN: Quien se lo asignó (how_asig del ticket actual) NO debe ser él mismo.
        // NOTA: tm_ticket.how_asig guarda quién hizo la última asignación. Si fue auto-asignado, how_asig = usu_id.
        $sql_cerrados = "SELECT COUNT(*) as total 
                         FROM tm_ticket 
                         WHERE usu_asig = ? 
                         AND tick_estado = 'Cerrado' 
                         AND (how_asig != ? OR how_asig IS NULL)
                         AND est = 1";

        $stmt_cerrados = $conectar->prepare($sql_cerrados);
        $stmt_cerrados->bindValue(1, $usu_id);
        $stmt_cerrados->bindValue(2, $usu_id);
        $stmt_cerrados->execute();
        $cerrados = $stmt_cerrados->fetch(PDO::FETCH_ASSOC);

        return $movidos['total'] + $cerrados['total'];
    }


    /**
     * Calcula la MEDIANA del tiempo de respuesta del usuario.
     * Tiempo respuesta = (Fecha de acción - Fecha de asignación)
     * Acción = Reasignar (th_ticket_asignacion) o Comentar (td_ticketdetalle) o Cerrar.
     * La Mediana es mejor para descartar valores atípicos.
     */
    public function get_mediana_respuesta($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // Obtenemos todas las asignaciones al usuario
        $sql_asignaciones = "SELECT tick_id, fech_asig FROM th_ticket_asignacion WHERE usu_asig = ? AND est = 1";
        $stmt_asig = $conectar->prepare($sql_asignaciones);
        $stmt_asig->bindValue(1, $usu_id);
        $stmt_asig->execute();
        $asignaciones = $stmt_asig->fetchAll(PDO::FETCH_ASSOC);

        $tiempos = []; // Array para almacenar los tiempos en minutos

        foreach ($asignaciones as $asig) {
            $tick_id = $asig['tick_id'];
            $inicio = strtotime($asig['fech_asig']);

            // Buscar la primera acción posterior a la asignación

            // 1. Reasignación (movimiento) hecha por este usuario
            $sql_move = "SELECT fech_asig FROM th_ticket_asignacion WHERE tick_id = ? AND how_asig = ? AND fech_asig > ? ORDER BY fech_asig ASC LIMIT 1";
            $stmt_move = $conectar->prepare($sql_move);
            $stmt_move->bindValue(1, $tick_id);
            $stmt_move->bindValue(2, $usu_id);
            $stmt_move->bindValue(3, $asig['fech_asig']);
            $stmt_move->execute();
            $move = $stmt_move->fetch(PDO::FETCH_ASSOC);

            // 2. Comentario/Detalle hecho por este usuario
            $sql_comment = "SELECT fech_crea FROM td_ticketdetalle WHERE tick_id = ? AND usu_id = ? AND fech_crea > ? ORDER BY fech_crea ASC LIMIT 1";
            $stmt_comment = $conectar->prepare($sql_comment);
            $stmt_comment->bindValue(1, $tick_id);
            $stmt_comment->bindValue(2, $usu_id);
            $stmt_comment->bindValue(3, $asig['fech_asig']);
            $stmt_comment->execute();
            $comment = $stmt_comment->fetch(PDO::FETCH_ASSOC);

            // 3. Cierre (no siempre guarda historial perfecto, asumimos detalle o movimiento cubre la acción)

            $fin = null;

            if ($move && $comment) {
                $fin = min(strtotime($move['fech_asig']), strtotime($comment['fech_crea']));
            } elseif ($move) {
                $fin = strtotime($move['fech_asig']);
            } elseif ($comment) {
                $fin = strtotime($comment['fech_crea']);
            }

            if ($fin) {
                $diff_minutes = ($fin - $inicio) / 60;
                $tiempos[] = $diff_minutes;
            }
        }

        if (empty($tiempos)) {
            return 0;
        }

        // Calcular Mediana
        sort($tiempos);
        $count = count($tiempos);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            // Impar: valor del medio
            $mediana = $tiempos[$middle];
        } else {
            // Par: promedio de los dos del medio
            $low = $tiempos[$middle];
            $high = $tiempos[$middle + 1];
            $mediana = ($low + $high) / 2;
        }

        return round($mediana, 2);
    }
}
