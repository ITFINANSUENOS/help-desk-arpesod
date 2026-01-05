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

        // 1. Asignaciones Reales (Base de Datos)
        $sql = "SELECT COUNT(*) as total FROM th_ticket_asignacion WHERE usu_asig = ? AND est = 1";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_id);
        $stmt->execute();
        $assigned_real = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 2. Asignaciones Implícitas (Salidas + Stock)
        // Lógica: "Si lo terminé o lo tengo abierto, entonces me fue asignado (aunque no haya log)".

        // A. Movidos (Salidas)
        $sql_mov = "SELECT COUNT(*) as total FROM th_ticket_asignacion WHERE how_asig = ? AND usu_asig != ? AND est = 1";
        $stmt_mov = $conectar->prepare($sql_mov);
        $stmt_mov->bindValue(1, $usu_id);
        $stmt_mov->bindValue(2, $usu_id);
        $stmt_mov->execute();
        $moved = $stmt_mov->fetch(PDO::FETCH_ASSOC)['total'];

        // B. Cerrados (Salidas)
        $sql_closed = "SELECT COUNT(*) as total FROM tm_ticket WHERE usu_asig = ? AND tick_estado = 'Cerrado' AND est = 1";
        $stmt_closed = $conectar->prepare($sql_closed);
        $stmt_closed->bindValue(1, $usu_id);
        $stmt_closed->execute();
        $closed = $stmt_closed->fetch(PDO::FETCH_ASSOC)['total'];

        // C. Abiertos (Stock)
        $sql_open = "SELECT COUNT(*) as total FROM tm_ticket WHERE usu_asig = ? AND tick_estado = 'Abierto' AND est = 1";
        $stmt_open = $conectar->prepare($sql_open);
        $stmt_open->bindValue(1, $usu_id);
        $stmt_open->execute();
        $open = $stmt_open->fetch(PDO::FETCH_ASSOC)['total'];

        $implied_total = $moved + $closed + $open;

        // Retornamos el MAYOR. Esto cubre los "tickets fantasmas" (ej. errores de proceso antiguos)
        return max($assigned_real, $implied_total);
    }

    /**
     * Obtiene el número de pasos finalizados por un usuario.
     * Se considera finalizado si el usuario reasignó el ticket a OTRO usuario
     * o si el ticket fue cerrado mientras estaba asignado al usuario.
     */
    public function get_pasos_finalizados($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // 1. Pasos MOVIDOS:
        // El usuario movió el ticket a otro.
        // Al contar todas las entradas (incluso auto-asignadas), cualquier salida es válida.
        $sql_movidos = "SELECT COUNT(*) as total 
                        FROM th_ticket_asignacion t1
                        WHERE t1.how_asig = ? 
                        AND t1.usu_asig != ? 
                        AND t1.est = 1";

        $stmt_movidos = $conectar->prepare($sql_movidos);
        $stmt_movidos->bindValue(1, $usu_id);
        $stmt_movidos->bindValue(2, $usu_id);
        $stmt_movidos->execute();
        $movidos = $stmt_movidos->fetch(PDO::FETCH_ASSOC);

        // 2. Tickets CERRADOS:
        // El usuario tiene el ticket asignado y está cerrado.
        $sql_cerrados = "SELECT COUNT(*) as total 
                         FROM tm_ticket 
                         WHERE usu_asig = ? 
                         AND tick_estado = 'Cerrado' 
                         AND est = 1";

        $stmt_cerrados = $conectar->prepare($sql_cerrados);
        $stmt_cerrados->bindValue(1, $usu_id);
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

    /* =========================================================
     *  NUEVA LÓGICA PARA DASHBOARD DINÁMICO & JERARQUÍA
     * ========================================================= */

    /**
     * Obtiene los IDs de usuario visibles para el usuario actual según jerarquía.
     * Admin: Ver todos (retorna 'all').
     * Jefe: Ver subordinados (recursivo) + él mismo.
     * Usuario: Ver solo él mismo.
     */
    public function get_hierarchy_scope($usu_id)
    {
        $conectar = parent::Conexion();

        // 1. Obtener Rol y Cargo del usuario
        $sql = "SELECT rol_id, car_id FROM tm_usuario WHERE usu_id = ?";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_id);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) return [$usu_id]; // Fallback

        // A. ADMIN (Rol 3) -> Ve todo
        if ($user_data['rol_id'] == 3) {
            return 'all';
        }

        // B. JEFE o SOPORTE (Depende de regla, asumimos que Soporte ve todo o solo lo suyo? 
        // El prompt dice "respete la jerarquia". Asumimos lógica de Organigrama).

        // Buscamos si es jefe de alguien
        $subordinados = $this->get_subordinates_recursive($user_data['car_id']);

        if (!empty($subordinados)) {
            // Es jefe, obtenemos usuarios con esos cargos
            // Convertimos array de cargos a string para IN
            $cargos_in = implode(',', $subordinados);
            // Agregar su propio cargo también por si acaso (o solo usuarios de esos cargos)
            // Normalmente el jefe quiere ver a su equipo. Agregamos su propio ID manual al final.

            $sql_users = "SELECT usu_id FROM tm_usuario WHERE car_id IN ($cargos_in) AND est=1";
            $stmt_u = $conectar->prepare($sql_users);
            $stmt_u->execute();
            $ids = $stmt_u->fetchAll(PDO::FETCH_COLUMN);

            // Agregarse a sí mismo
            $ids[] = $usu_id;
            return array_unique($ids);
        }

        // C. USUARIO NORMAL -> Solo se ve a sí mismo
        return [$usu_id];
    }

    /**
     * Método auxiliar para obtener IDs de Cargos subordinados recursivamente
     */
    private function get_subordinates_recursive($jefe_car_id)
    {
        $conectar = parent::Conexion();
        // Buscar cargos donde este car_id sea el jefe
        $sql = "SELECT car_id FROM tm_organigrama WHERE jefe_car_id = ? AND est = 1";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $jefe_car_id);
        $stmt->execute();
        $cargos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $resultado = $cargos;
        foreach ($cargos as $car) {
            $hijos = $this->get_subordinates_recursive($car);
            $resultado = array_merge($resultado, $hijos);
        }
        return $resultado;
    }

    /**
     * Obtiene estadísticas dinámicas (Totales y Gráficos)
     * $filters puede contener: 'dp_id', 'target_usu_id', 'cats_id' para el drill-down.
     */
    public function get_dynamic_statistics($usu_id, $filters = [])
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $conectar->exec("set names utf8");

        $scope = $this->get_hierarchy_scope($usu_id);
        $view_mode = isset($filters['view_mode']) ? $filters['view_mode'] : 'dept'; // 'dept' or 'cargo'

        // Construcción del WHERE base según Scope
        $where_scope = " t.est = 1 ";
        if ($scope !== 'all') {
            if (is_array($scope)) {
                $ids_str = implode(',', $scope);
                $where_scope .= " AND (t.usu_asig IN ($ids_str) OR t.usu_id IN ($ids_str)) ";
            } else {
                // Should not happen based on get_hierarchy_scope returning array or 'all', but primarily for safety
                $where_scope .= " AND (t.usu_asig = $usu_id OR t.usu_id = $usu_id) ";
            }
        }

        $where_filters = "";

        // Filters Drill-Down
        if (!empty($filters['dp_id'])) {
            $where_filters .= " AND u.dp_id = " . intval($filters['dp_id']);
        }
        if (!empty($filters['car_id'])) { // New Cargo Filter
            $where_filters .= " AND u.car_id = " . intval($filters['car_id']);
        }
        if (!empty($filters['target_usu_id'])) {
            $where_filters .= " AND t.usu_asig = " . intval($filters['target_usu_id']);
        }
        if (!empty($filters['cats_id'])) {
            $where_filters .= " AND t.cats_id = " . intval($filters['cats_id']);
        }
        if (!empty($filters['cat_id'])) {
            $where_filters .= " AND t.cat_id = " . intval($filters['cat_id']);
        } // End filters

        // --- 1. TOTALES ---
        $sql_totals = "SELECT 
                        SUM(CASE WHEN t.tick_estado = 'Abierto' THEN 1 ELSE 0 END) as total_abiertos,
                        SUM(CASE WHEN t.tick_estado = 'Cerrado' THEN 1 ELSE 0 END) as total_cerrados,
                        COUNT(*) as total_general
                       FROM tm_ticket t
                       INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id
                       WHERE $where_scope $where_filters";

        $stmt_t = $conectar->prepare($sql_totals);
        $stmt_t->execute();
        $totals = $stmt_t->fetch(PDO::FETCH_ASSOC);

        // --- 2. CHART DATA ---
        $groupBy = "";
        $selectLabel = "";
        $selectId = "";
        $chartLevel = "";

        $group_mode = isset($filters['group_by']) ? $filters['group_by'] : 'users';

        // Level decision logic
        if (empty($filters['target_usu_id'])) {
            // No User selected yet. Check if we have mid-level filters (Dept or Cargo)

            if ($view_mode === 'cargo') {
                // Mode CARGO
                if (empty($filters['car_id'])) {
                    // Level 0: SHOW CARGOS
                    $chartLevel = 'cargo';
                    $selectLabel = "c.car_nom as label";
                    $selectId = "c.car_id as id";
                    $joins = " INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id 
                               LEFT JOIN tm_cargo c ON u.car_id = c.car_id ";
                    $groupBy = "c.car_nom, c.car_id";
                } else {
                    // Level 1: Cargo SELECTED, show USERS
                    $chartLevel = 'user';
                    $selectLabel = "CONCAT(u.usu_nom, ' ', u.usu_ape) as label";
                    $selectId = "u.usu_id as id";
                    $joins = " INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id ";
                    $groupBy = "u.usu_id";
                }
            } else {
                // Mode DEPT (default)
                if (empty($filters['dp_id'])) {
                    // Level 0: SHOW DEPTS
                    $chartLevel = 'dept';
                    $selectLabel = "d.dp_nom as label";
                    $selectId = "d.dp_id as id";
                    $joins = " INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id 
                               LEFT JOIN tm_departamento d ON u.dp_id = d.dp_id ";
                    $groupBy = "d.dp_nom, d.dp_id";
                } else {
                    // Level 1: Dept SELECTED
                    // CHECK Group Mode
                    if ($group_mode === 'category' && empty($filters['cat_id'])) {
                        // Show CATEGORIES inside Dept
                        $chartLevel = 'category';
                        $selectLabel = "cat.cat_nom as label";
                        $selectId = "cat.cat_id as id";
                        $joins = " INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id 
                                   LEFT JOIN tm_categoria cat ON t.cat_id = cat.cat_id ";
                        $groupBy = "cat.cat_id";
                    } else {
                        // Show USERS inside Dept (or inside Category if filter set)
                        $chartLevel = 'user';
                        $selectLabel = "CONCAT(u.usu_nom, ' ', u.usu_ape) as label";
                        $selectId = "u.usu_id as id";
                        $joins = " INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id ";
                        $groupBy = "u.usu_id";
                    }
                }
            }
        } elseif (empty($filters['cats_id'])) {
            // Level 2: User Selected, show SUBCATEGORIES
            $chartLevel = 'subcat';
            $selectLabel = "s.cats_nom as label";
            $selectId = "s.cats_id as id";
            $joins = " INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id 
                       INNER JOIN tm_subcategoria s ON t.cats_id = s.cats_id ";
            $groupBy = "s.cats_id";
        } else {
            // Level 3: Subcat Selected, show STATUS
            $chartLevel = 'status';
            $selectLabel = "t.tick_estado as label";
            $selectId = "t.tick_estado as id";
            $joins = " INNER JOIN tm_usuario u ON t.usu_asig = u.usu_id ";
            $groupBy = "t.tick_estado";
        }

        $sql_chart = "SELECT 
                        $selectId,
                        $selectLabel,
                        COUNT(*) as value
                      FROM tm_ticket t
                      $joins
                      WHERE $where_scope $where_filters
                      GROUP BY $groupBy
                      ORDER BY value DESC";

        $stmt_c = $conectar->prepare($sql_chart);
        $stmt_c->execute();
        $chartData = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

        // Sanitize NULL labels
        foreach ($chartData as &$row) {
            if (empty($row['label'])) {
                if ($chartLevel === 'cargo') $row['label'] = 'Sin Cargo';
                elseif ($chartLevel === 'dept') $row['label'] = 'Sin Departamento';
                else $row['label'] = 'General / Sin nombre';
            }
            if (empty($row['id'])) $row['id'] = '0';
        }

        return [
            'totals' => $totals,
            'chartLevel' => $chartLevel,
            'chartData' => $chartData
        ];
    }
    /**
     * Obtiene métricas de flujo para una subcategoría (Promedio de tiempo por paso)
     */
    public function get_subcategory_metrics($usu_id, $subcat_name, $target_usu_id = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // 1. Buscar Subcategoría ID
        $sql_cat = "SELECT cats_id, cats_nom FROM tm_subcategoria WHERE cats_nom LIKE ? AND est = 1 LIMIT 1";
        $stmt_cat = $conectar->prepare($sql_cat);
        $stmt_cat->bindValue(1, "%" . $subcat_name . "%");
        $stmt_cat->execute();
        $subcat = $stmt_cat->fetch(PDO::FETCH_ASSOC);

        if (!$subcat) return ['found' => false];

        $cats_id = $subcat['cats_id'];

        // 1.5. CHECK PERMISSIONS
        $scope = $this->get_hierarchy_scope($usu_id);

        if ($scope !== 'all') {
            $ids_str = implode(',', $scope);
            $sql_perm = "SELECT COUNT(*) as total 
                         FROM tm_ticket t
                         INNER JOIN th_ticket_asignacion a ON t.tick_id = a.tick_id
                         WHERE t.cats_id = ? AND a.usu_asig IN ($ids_str) AND a.est=1";
            $stmt_perm = $conectar->prepare($sql_perm);
            $stmt_perm->bindValue(1, $cats_id);
            $stmt_perm->execute();
            $perm = $stmt_perm->fetch(PDO::FETCH_ASSOC);

            if ($perm['total'] == 0) {
                // Check if user is creator/assigned directly in ticket table too
                $sql_perm2 = "SELECT COUNT(*) as total FROM tm_ticket WHERE cats_id = ? AND (usu_id IN ($ids_str) OR usu_asig IN ($ids_str)) AND est=1";
                $stmt_perm2 = $conectar->prepare($sql_perm2);
                $stmt_perm2->bindValue(1, $cats_id);
                $stmt_perm2->execute();
                $perm2 = $stmt_perm2->fetch(PDO::FETCH_ASSOC);

                if ($perm2['total'] == 0) {
                    return ['found' => false, 'forbidden' => true];
                }
            }
        }

        // 2. Obtener historial de asignaciones para tickets de esta subcategoría
        // Linkeamos a tm_flujo_paso para obtener nombre y orden del paso
        // SUBQUERY: Verificar si esta asignación corresponde a una Novedad
        $sql = "SELECT 
                    a.tick_id,
                    a.paso_id,
                    a.fech_asig,
                    a.usu_asig,
                    p.paso_nombre,
                    p.paso_orden,
                    t.fech_cierre,
                    t.tick_estado,
                    (SELECT COUNT(*) FROM th_ticket_novedad n WHERE n.tick_id = a.tick_id AND n.usu_asig_novedad = a.usu_asig) as es_novedad
                FROM th_ticket_asignacion a
                INNER JOIN tm_ticket t ON a.tick_id = t.tick_id
                LEFT JOIN tm_flujo_paso p ON a.paso_id = p.paso_id
                WHERE t.cats_id = ? AND a.est = 1
                ORDER BY a.tick_id, a.fech_asig ASC";

        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $cats_id);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Procesar Duraciones
        $steps_stats = []; // [paso_id => [total_minutes => 0, count => 0, name => '']]

        // Agrupar por ticket para calcular deltas
        $tickets = [];
        foreach ($data as $row) {
            $tickets[$row['tick_id']][] = $row;
        }

        foreach ($tickets as $tick_id => $assignments) {
            $count = count($assignments);
            for ($i = 0; $i < $count; $i++) {
                $current = $assignments[$i];

                // FILTER BY TARGET USER IF SET
                if ($target_usu_id && $current['usu_asig'] != $target_usu_id) {
                    continue;
                }

                $paso_id = $current['paso_id'] ?? 0;
                $paso_nom = $current['paso_nombre'];
                $paso_orden = $current['paso_orden'] ?? 999;

                // Lógica de Naming Manual
                if (empty($paso_nom)) {
                    if ($current['es_novedad'] > 0) {
                        $paso_nom = 'Intervención / Novedad';
                        $paso_id = 'NOV'; // ID Virtual
                        $paso_orden = 900; // Al final pero antes de cierre
                    } elseif ($i == 0) {
                        $paso_nom = 'Asignación Inicial';
                        $paso_orden = 0;
                    } else {
                        $paso_nom = 'Asignación Manual';
                        $paso_orden = 950;
                    }
                }

                $start_time = strtotime($current['fech_asig']);
                $end_time = null;

                // Determinar fin del paso
                if (isset($assignments[$i + 1])) {
                    // Fin es el inicio del siguiente paso
                    $end_time = strtotime($assignments[$i + 1]['fech_asig']);
                } else {
                    // Es el último paso registrado
                    if ($current['tick_estado'] == 'Cerrado' && !empty($current['fech_cierre'])) {
                        $end_time = strtotime($current['fech_cierre']);
                    } else {
                        // Ticket abierto, paso en curso. Usamos NOW() para stats.
                        $end_time = time();
                    }
                }

                $duration_minutes = ($end_time - $start_time) / 60;

                if (!isset($steps_stats[$paso_id])) {
                    $steps_stats[$paso_id] = [
                        'name' => $paso_nom,
                        'total_minutes' => 0,
                        'count' => 0,
                        'order' => $paso_orden,
                        'is_novedad' => ($paso_id === 'NOV')
                    ];
                }

                $steps_stats[$paso_id]['total_minutes'] += $duration_minutes;
                $steps_stats[$paso_id]['count']++;
            }
        }

        // 4. Calcular Promedios y formatear para Chart
        $chart_data = [];
        foreach ($steps_stats as $pid => $stat) {
            $avg = $stat['count'] > 0 ? $stat['total_minutes'] / $stat['count'] : 0;
            $chart_data[] = [
                'step_name' => $stat['name'],
                'avg_minutes' => round($avg, 2),
                'order' => $stat['order'],
                'is_novedad' => $stat['is_novedad']
            ];
        }

        // Ordenar pasos
        usort($chart_data, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return [
            'found' => true,
            'subcat_name' => $subcat['cats_nom'],
            'chart_data' => $chart_data
        ];
    }

    public function get_novedades_details($usu_id, $subcat_name = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $cats_id = null;
        if ($subcat_name) {
            $sql_find_cat = "SELECT cats_id FROM tm_subcategoria WHERE cats_nom LIKE ? AND est=1 LIMIT 1";
            $stmt_cat = $conectar->prepare($sql_find_cat);
            $stmt_cat->bindValue(1, "%" . $subcat_name . "%");
            $stmt_cat->execute();
            $cat_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            $cats_id = $cat_row ? $cat_row['cats_id'] : null;
        }

        $join = $cats_id ? " INNER JOIN tm_ticket t ON n.tick_id = t.tick_id " : "";
        $where = $cats_id ? " AND t.cats_id = $cats_id " : "";

        $sql = "SELECT 
                    n.novedad_id,
                    n.tick_id,
                    n.descripcion_novedad,
                    n.fecha_inicio,
                    n.fecha_fin,
                    TIMESTAMPDIFF(MINUTE, n.fecha_inicio, n.fecha_fin) as duracion_min
                FROM th_ticket_novedad n
                $join
                WHERE n.usu_asig_novedad = ? 
                AND n.estado_novedad = 'Resuelta'
                $where
                ORDER BY n.fecha_inicio DESC";

        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_error_details($usu_id, $type, $subcat_name = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $conectar->exec("set names utf8"); // FORCE UTF8

        $cats_id = null;
        if ($subcat_name) {
            $sql_find_cat = "SELECT cats_id FROM tm_subcategoria WHERE cats_nom LIKE ? AND est=1 LIMIT 1";
            $stmt_cat = $conectar->prepare($sql_find_cat);
            $stmt_cat->bindValue(1, "%" . $subcat_name . "%");
            $stmt_cat->execute();
            $cat_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            $cats_id = $cat_row ? $cat_row['cats_id'] : null;
        }

        $join = $cats_id ? " INNER JOIN tm_ticket t ON e.tick_id = t.tick_id " : "";
        $where = $cats_id ? " AND t.cats_id = $cats_id " : "";

        // Filter by type: 'process' or 'info'
        $is_process = ($type === 'process') ? 1 : 0;

        $sql = "SELECT 
                    e.error_id,
                    e.tick_id,
                    e.error_descrip,
                    e.fech_crea,
                    fa.answer_nom
                FROM tm_ticket_error e
                LEFT JOIN tm_fast_answer fa ON e.answer_id = fa.answer_id
                $join
                WHERE e.usu_id_responsable = ? 
                AND e.es_error_proceso = ?
                AND e.est = 1
                $where
                ORDER BY e.fech_crea DESC";

        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_id);
        $stmt->bindValue(2, $is_process);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_performance_details($usu_id, $type, $subcat_name = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $cats_id = null;
        if ($subcat_name) {
            $sql_find_cat = "SELECT cats_id FROM tm_subcategoria WHERE cats_nom LIKE ? AND est=1 LIMIT 1";
            $stmt_cat = $conectar->prepare($sql_find_cat);
            $stmt_cat->bindValue(1, "%" . $subcat_name . "%");
            $stmt_cat->execute();
            $cat_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            $cats_id = $cat_row ? $cat_row['cats_id'] : null;
        }

        $join = $cats_id ? " INNER JOIN tm_ticket t_filter ON t.tick_id = t_filter.tick_id " : "";
        $where = $cats_id ? " AND t_filter.cats_id = $cats_id " : "";

        // Logic for types
        // 'on_time' -> 'Atiempo'
        // 'late' -> 'Atrasado' OR 'Vencido'

        $type_condition = "";
        if ($type === 'on_time') {
            $type_condition = " AND t.estado_tiempo_paso LIKE '%tiempo%' ";
        } else {
            $type_condition = " AND (t.estado_tiempo_paso LIKE '%Atrasado%' OR t.estado_tiempo_paso LIKE '%Vencido%') ";
        }

        // We join tm_ticket to get ticket title
        $sql = "SELECT 
                    t.tick_id,
                    t.fech_asig,
                    t.estado_tiempo_paso,
                    tk.tick_titulo
                FROM th_ticket_asignacion t
                INNER JOIN tm_ticket tk ON t.tick_id = tk.tick_id
                $join
                WHERE t.usu_asig = ? 
                AND t.est = 1 
                AND t.estado_tiempo_paso IS NOT NULL
                $type_condition
                $where
                ORDER BY t.fech_asig DESC";

        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene estadísticas detalladas por usuario:
     * - Gestión (A tiempo vs Atrasado)
     * - Novedades (Cantidad, Tiempo Resolución)
     * - Errores (Proceso vs Informativo)
     */
    /**
     * Obtiene estadísticas detalladas por usuario:
     * - Gestión (A tiempo vs Atrasado)
     * - Novedades (Cantidad, Tiempo Resolución)
     * - Errores (Proceso vs Informativo)
     * 
     * @param int|null $target_usu_id ID de usuario específico para filtrar
     * @param string|null $subcat_name Filtro opcional por nombre de subcategoría
     */
    public function get_detailed_user_stats($usu_id, $target_usu_id = null, $subcat_name = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $subcat_condition_join = "";
        $subcat_condition_where = "";
        $params = [];

        // 0. Preparar filtro de subcategoría si existe
        if ($subcat_name) {
            $sql_find_cat = "SELECT cats_id FROM tm_subcategoria WHERE cats_nom LIKE ? AND est=1 LIMIT 1";
            $stmt_cat = $conectar->prepare($sql_find_cat);
            $stmt_cat->bindValue(1, "%" . $subcat_name . "%");
            $stmt_cat->execute();
            $cat_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);

            if ($cat_row) {
                $cats_id = $cat_row['cats_id'];
                $subcat_condition_join = " INNER JOIN tm_ticket t_filter ON t.tick_id = t_filter.tick_id ";
                $subcat_condition_where = " AND t_filter.cats_id = $cats_id ";
            } else {
                return []; // Si no encuentra la categoría, no hay datos
            }
        }

        // 1. Obtener lista de usuarios RELEVANTES según SCOPE
        // SI hay un usuario objetivo seleccionado en el filtro ($target_usu_id)
        if ($target_usu_id) {
            $where_users = " u.usu_id = " . intval($target_usu_id) . " AND u.est=1 ";
        } else {
            $scope = $this->get_hierarchy_scope($usu_id);
            $where_users = " u.est=1 ";
            if ($scope !== 'all') {
                $ids_str = implode(',', $scope);
                $where_users .= " AND u.usu_id IN ($ids_str) ";
            } else {
                // Si es admin (all), ve a todos los agentes (rol 2 y 3)
                $where_users .= " AND u.rol_id IN (2,3) ";
            }
        }

        // Si hay filtro cats_id, refinar usuarios que han actuado en esa cats
        if ($subcat_name && isset($cats_id)) {
            $sql_users = "SELECT DISTINCT u.usu_id, u.usu_nom, u.usu_ape 
                          FROM tm_usuario u
                          INNER JOIN th_ticket_asignacion a ON u.usu_id = a.usu_asig
                          INNER JOIN tm_ticket t ON a.tick_id = t.tick_id
                          WHERE t.cats_id = ? AND $where_users";

            $stmt_u = $conectar->prepare($sql_users);
            $stmt_u->bindValue(1, $cats_id);
            $stmt_u->execute();
            $users = $stmt_u->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Todos los usuarios dentro del scope
            $sql_users = "SELECT usu_id, usu_nom, usu_ape FROM tm_usuario u WHERE $where_users";
            $stmt_u = $conectar->prepare($sql_users);
            $stmt_u->execute();
            $users = $stmt_u->fetchAll(PDO::FETCH_ASSOC);
        }

        $stats = [];
        foreach ($users as $u) {
            $stats[$u['usu_id']] = [
                'usu_id' => $u['usu_id'],
                'usu_nom' => $u['usu_nom'] . ' ' . $u['usu_ape'],
                'on_time' => 0,
                'late' => 0,
                'nov_count' => 0,
                'nov_avg_time' => 0, // Minutos
                'err_process' => 0,
                'err_info' => 0
            ];
        }

        // Si la lista de usuarios está vacía, retornar
        if (empty($stats)) return [];

        // Definir Joins comunes para filtros
        // Para asignacion: " FROM th_ticket_asignacion t " -> t es alias de tabla principal en query

        // DATOS 1: GESTIÓN (A Tiempo / Atrasado)
        $sql_gestion = "SELECT t.usu_asig, t.estado_tiempo_paso 
                        FROM th_ticket_asignacion t 
                        $subcat_condition_join
                        WHERE t.est=1 AND t.estado_tiempo_paso IS NOT NULL $subcat_condition_where";

        $stmt_g = $conectar->prepare($sql_gestion);
        $stmt_g->execute();
        while ($row = $stmt_g->fetch(PDO::FETCH_ASSOC)) {
            $uid = $row['usu_asig'];
            if (isset($stats[$uid])) {
                $est = mb_strtolower($row['estado_tiempo_paso']);
                if (strpos($est, 'tiempo') !== false) {
                    $stats[$uid]['on_time']++;
                } elseif (strpos($est, 'atrasado') !== false || strpos($est, 'vencido') !== false) {
                    $stats[$uid]['late']++;
                }
            }
        }

        // DATOS 2: NOVEDADES
        // t alias for th_ticket_novedad
        $nov_join = $subcat_name ? " INNER JOIN tm_ticket t_filter ON t.tick_id = t_filter.tick_id " : "";
        $nov_where = $subcat_name ? " AND t_filter.cats_id = $cats_id " : "";

        $sql_nov = "SELECT t.usu_asig_novedad, t.fecha_inicio, t.fecha_fin 
                    FROM th_ticket_novedad t 
                    $nov_join
                    WHERE t.estado_novedad = 'Resuelta' $nov_where";

        $stmt_n = $conectar->prepare($sql_nov);
        $stmt_n->execute();

        $nov_totals = [];

        while ($row = $stmt_n->fetch(PDO::FETCH_ASSOC)) {
            $uid = $row['usu_asig_novedad'];
            if (isset($stats[$uid])) {
                $start = strtotime($row['fecha_inicio']);
                $end = strtotime($row['fecha_fin']);
                $minutes = ($end - $start) / 60;

                if (!isset($nov_totals[$uid])) $nov_totals[$uid] = ['time' => 0, 'count' => 0];
                $nov_totals[$uid]['time'] += $minutes;
                $nov_totals[$uid]['count']++;

                $stats[$uid]['nov_count']++;
            }
        }

        // Calc Avg Novedad Time
        foreach ($nov_totals as $uid => $data) {
            if (isset($stats[$uid])) {
                $stats[$uid]['nov_avg_time'] = round($data['time'] / $data['count'], 2);
            }
        }

        // DATOS 3: ERRORES
        // t alias for tm_ticket_error
        $err_join = $subcat_name ? " INNER JOIN tm_ticket t_filter ON t.tick_id = t_filter.tick_id " : "";
        $err_where = $subcat_name ? " AND t_filter.cats_id = $cats_id " : "";

        $sql_err = "SELECT t.usu_id_responsable, t.es_error_proceso 
                    FROM tm_ticket_error t 
                    $err_join
                    WHERE t.est=1 $err_where";

        $stmt_e = $conectar->prepare($sql_err);
        $stmt_e->execute();
        while ($row = $stmt_e->fetch(PDO::FETCH_ASSOC)) {
            $uid = $row['usu_id_responsable'];
            if (isset($stats[$uid])) {
                if ($row['es_error_proceso'] == 1) {
                    $stats[$uid]['err_process']++;
                } else {
                    $stats[$uid]['err_info']++;
                }
            }
        }

        return array_values($stats);
    }
}
