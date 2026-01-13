<?php
class FlujoPaso extends Conectar
{


    public function get_flujopaso()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_flujo_paso WHERE est = 1";

        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_pasos_por_flujo($flujo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT tm_flujo_paso.*,
            tm_cargo.car_nom
            FROM tm_flujo_paso 
            LEFT JOIN tm_cargo ON tm_flujo_paso.cargo_id_asignado = tm_cargo.car_id   
            WHERE flujo_id = ? AND tm_flujo_paso.est = 1 ORDER BY paso_orden ASC";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }


    public function insert_paso($flujo_id, $paso_orden, $paso_nombre, $cargo_id_asignado, $paso_tiempo_habil, $paso_descripcion, $requiere_seleccion_manual, $es_tarea_nacional, $es_aprobacion, $paso_nom_adjunto, $permite_cerrar, $necesita_aprobacion_jefe, $es_paralelo, $requiere_firma, $requiere_campos_plantilla, $campo_id_referencia_jefe = null, $asignar_a_creador = 0, $cerrar_ticket_obligatorio = 0, $permite_despacho_masivo = 0)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_flujo_paso (flujo_id, paso_orden, paso_nombre, cargo_id_asignado, paso_tiempo_habil, paso_descripcion, requiere_seleccion_manual, es_tarea_nacional, es_aprobacion, paso_nom_adjunto, permite_cerrar, necesita_aprobacion_jefe, es_paralelo, requiere_firma, requiere_campos_plantilla, est, campo_id_referencia_jefe, asignar_a_creador, cerrar_ticket_obligatorio, permite_despacho_masivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->bindValue(2, $paso_orden);
        $sql->bindValue(3, $paso_nombre);
        $sql->bindValue(4, $cargo_id_asignado);
        $sql->bindValue(5, $paso_tiempo_habil);
        $sql->bindValue(6, $paso_descripcion);
        $sql->bindValue(7, $requiere_seleccion_manual);
        $sql->bindValue(8, $es_tarea_nacional);
        $sql->bindValue(9, $es_aprobacion);
        $sql->bindValue(10, $paso_nom_adjunto);
        $sql->bindValue(11, $permite_cerrar);
        $sql->bindValue(12, $necesita_aprobacion_jefe);
        $sql->bindValue(13, $es_paralelo);
        $sql->bindValue(14, $requiere_firma);
        $sql->bindValue(15, $requiere_campos_plantilla);
        // Handle null for campo_id_referencia_jefe
        if (empty($campo_id_referencia_jefe)) {
            $sql->bindValue(16, null, PDO::PARAM_NULL);
        } else {
            $sql->bindValue(16, $campo_id_referencia_jefe);
        }
        $sql->bindValue(17, $asignar_a_creador);
        $sql->bindValue(18, $cerrar_ticket_obligatorio);
        $sql->bindValue(19, $permite_despacho_masivo);
        $sql->execute();
        return $conectar->lastInsertId();
    }

    public function delete_paso($paso_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_flujo_paso SET est = 0 WHERE paso_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function delete_pasos_por_flujo($flujo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_flujo_paso SET est = 0 WHERE flujo_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->execute();

        // También eliminar transiciones asociadas a los pasos de ese flujo (para limpieza profunda)
        // Esto requiere un query más complejo o hacerlo paso por paso, pero est=0 en pasos debería ocultarlo.
        // Por seguridad, desactivamos transiciones donde el origen sea de este flujo.
        $sql_trans = "UPDATE tm_flujo_transiciones 
                      SET est = 0 
                      WHERE paso_origen_id IN (SELECT paso_id FROM tm_flujo_paso WHERE flujo_id = ?)";
        $sql_trans = $conectar->prepare($sql_trans);
        $sql_trans->bindValue(1, $flujo_id);
        $sql_trans->execute();
    }

    public function update_paso($paso_id, $paso_orden, $paso_nombre, $cargo_id_asignado, $paso_tiempo_habil, $paso_descripcion, $requiere_seleccion_manual, $es_tarea_nacional, $es_aprobacion, $paso_nom_adjunto, $permite_cerrar, $necesita_aprobacion_jefe, $es_paralelo, $requiere_firma, $requiere_campos_plantilla, $campo_id_referencia_jefe = null, $asignar_a_creador = 0, $cerrar_ticket_obligatorio = 0, $permite_despacho_masivo = 0)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "UPDATE tm_flujo_paso SET paso_orden=?, paso_nombre=?, cargo_id_asignado=?, paso_tiempo_habil=?, paso_descripcion=?, requiere_seleccion_manual=?, es_tarea_nacional=?, es_aprobacion=?, paso_nom_adjunto=?, permite_cerrar=?, necesita_aprobacion_jefe=?, es_paralelo=?, requiere_firma=?, requiere_campos_plantilla=?, campo_id_referencia_jefe=?, asignar_a_creador=?, cerrar_ticket_obligatorio=?, permite_despacho_masivo=? WHERE paso_id=?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_orden);
        $sql->bindValue(2, $paso_nombre);
        $sql->bindValue(3, $cargo_id_asignado);
        $sql->bindValue(4, $paso_tiempo_habil);
        $sql->bindValue(5, $paso_descripcion);
        $sql->bindValue(6, $requiere_seleccion_manual);
        $sql->bindValue(7, $es_tarea_nacional);
        $sql->bindValue(8, $es_aprobacion);
        $sql->bindValue(9, $paso_nom_adjunto);
        $sql->bindValue(10, $permite_cerrar);
        $sql->bindValue(11, $necesita_aprobacion_jefe);
        $sql->bindValue(12, $es_paralelo);
        $sql->bindValue(13, $requiere_firma);
        $sql->bindValue(14, $requiere_campos_plantilla);
        // Handle null for campo_id_referencia_jefe
        if (empty($campo_id_referencia_jefe)) {
            $sql->bindValue(15, null, PDO::PARAM_NULL);
        } else {
            $sql->bindValue(15, $campo_id_referencia_jefe);
        }
        $sql->bindValue(16, $asignar_a_creador);
        $sql->bindValue(17, $cerrar_ticket_obligatorio);
        $sql->bindValue(18, $permite_despacho_masivo);
        $sql->bindValue(19, $paso_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function get_paso_x_id($emp_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT tm_flujo_paso.*,
            tm_usuario.car_id
            FROM tm_flujo_paso 
            INNER JOIN tm_usuario ON tm_flujo_paso.cargo_id_asignado = tm_usuario.car_id 
            WHERE paso_id = ? AND tm_flujo_paso.est = 1;";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $emp_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_flujo_id_from_paso($paso_id)
    {
        $conectar = parent::Conexion();
        $sql = "SELECT flujo_id FROM tm_flujo_paso WHERE paso_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['flujo_id'] : null;
    }

    public function get_paso_por_id($paso_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_flujo_paso WHERE paso_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);

        if ($resultado) {
            $resultado['usuarios_especificos'] = $this->get_usuarios_especificos($paso_id);
            $resultado['usuarios_especificos_data'] = $this->get_usuarios_especificos_data($paso_id);
            $resultado['firma_config'] = $this->get_firma_config($paso_id);

            require_once("CampoPlantilla.php");
            $campoModel = new CampoPlantilla();
            $resultado['campos_plantilla_config'] = $campoModel->get_campos_por_paso($paso_id);
        }

        return $resultado;
    }

    public function set_campos_plantilla($paso_id, $campos)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // Desactivar campos existentes
        $sql = "UPDATE tm_campo_plantilla SET est=0 WHERE paso_id=?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();

        require_once("CampoPlantilla.php");
        $campoModel = new CampoPlantilla();

        if (is_array($campos)) {
            foreach ($campos as $campo) {
                // Default font size to 10 if not present
                $font_size = isset($campo['font_size']) ? $campo['font_size'] : 10;
                $campo_trigger = isset($campo['campo_trigger']) ? $campo['campo_trigger'] : 0;
                $campo_query = isset($campo['campo_query']) ? $campo['campo_query'] : null;
                $mostrar_dias_transcurridos = isset($campo['mostrar_dias_transcurridos']) ? $campo['mostrar_dias_transcurridos'] : 0;

                $campoModel->insert_campo($paso_id, $campo['campo_nombre'], $campo['campo_codigo'], $campo['coord_x'], $campo['coord_y'], $campo['pagina'], $campo['campo_tipo'], $font_size, $campo_trigger, $campo_query, $mostrar_dias_transcurridos);
            }
        }
    }

    public function get_siguiente_paso_transicion($paso_actual_id, $condicion_clave, $condicion_nombre)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT paso_destino_id FROM tm_flujo_transiciones WHERE paso_origen_id = ? AND condicion_clave = ? AND condicion_nombre = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_actual_id);
        $sql->bindValue(2, $condicion_clave);
        $sql->bindValue(3, $condicion_nombre);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);

        if ($resultado && $resultado['paso_destino_id']) {
            // Devolvemos los detalles completos del paso destino
            return $this->get_paso_por_id($resultado['paso_destino_id']);
        }
        return null; // No se encontró transición o es el fin del flujo
    }

    public function get_transiciones_por_paso($paso_origen_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
                    t.condicion_clave, 
                    t.condicion_nombre,
                    t.paso_destino_id,
                    t.ruta_id,
                    p_dest.requiere_seleccion_manual AS manual_paso,
                    p_ruta.requiere_seleccion_manual AS manual_ruta,
                    rp.paso_id AS ruta_first_step_id
                FROM tm_flujo_transiciones t
                LEFT JOIN tm_flujo_paso p_dest ON t.paso_destino_id = p_dest.paso_id
                LEFT JOIN tm_ruta_paso rp ON t.ruta_id = rp.ruta_id AND rp.orden = 1
                LEFT JOIN tm_flujo_paso p_ruta ON rp.paso_id = p_ruta.paso_id
                WHERE t.paso_origen_id = ? AND t.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_origen_id);
        $sql->execute();
        $results = $sql->fetchAll(PDO::FETCH_ASSOC);

        // Process results to unify the flag and target step
        foreach ($results as &$row) {
            $row['requiere_seleccion_manual'] = 0;
            $row['target_step_id'] = null;

            if (!empty($row['ruta_id'])) {
                $row['target_step_id'] = $row['ruta_first_step_id'];
                if ($row['manual_ruta'] == 1) {
                    $row['requiere_seleccion_manual'] = 1;
                }
            } elseif (!empty($row['paso_destino_id'])) {
                $row['target_step_id'] = $row['paso_destino_id'];
                if ($row['manual_paso'] == 1) {
                    $row['requiere_seleccion_manual'] = 1;
                }
            }
            // Clean up temporary columns if desired, or leave them
            unset($row['manual_paso']);
            unset($row['manual_ruta']);
            unset($row['ruta_first_step_id']);
        }

        return $results;
    }

    public function get_siguientes_pasos($paso_actual_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_flujo_paso 
                WHERE 
                    flujo_id = (SELECT flujo_id FROM tm_flujo_paso WHERE paso_id = ?) 
                    AND 
                    paso_orden = (SELECT paso_orden FROM tm_flujo_paso WHERE paso_id = ?) + 1
                    AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_actual_id);
        $sql->bindValue(2, $paso_actual_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_paso_actual($paso_actual_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT *, paso_nom_adjunto FROM tm_flujo_paso WHERE paso_id = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_actual_id);
        $sql->execute();
        // Usamos fetch() para obtener solo una fila (o false si no hay siguiente paso)
        return $resultado = $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_regla_aprobacion($creador_cargo_id_asignado)
    {
        $conectar = parent::Conexion();
        $sql = "SELECT * FROM tm_regla_aprobacion WHERE aprobador_usu_id = ? AND est = 1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $creador_cargo_id_asignado);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_regla_mapeo($cats_id, $creador_car_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $sql = "SELECT
                ra.asignado_car_id
            FROM
                tm_regla_mapeo rm
            INNER JOIN
                regla_creadores rc ON rm.regla_id = rc.regla_id
            INNER JOIN
                regla_asignados ra ON rm.regla_id = ra.regla_id
            WHERE
                rm.cats_id = ? AND rc.creador_car_id = ? AND rm.est = 1
            LIMIT 1"; // Obtenemos solo el primer resultado

        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cats_id);
        $sql->bindValue(2, $creador_car_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);

        // Devolvemos solo el ID, o null si no se encontró nada
        return $resultado ? $resultado['asignado_car_id'] : null;
    }

    public function verificar_orden_existente($flujo_id, $paso_orden)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) as total FROM tm_flujo_paso WHERE flujo_id = ? AND paso_orden = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->bindValue(2, $paso_orden);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado['total'] > 0;
    }

    public function set_usuarios_especificos($paso_id, $user_ids, $cargo_ids = [])
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "DELETE FROM tm_flujo_paso_usuarios WHERE paso_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();

        if (is_array($user_ids)) {
            foreach ($user_ids as $user_id) {
                if (!empty($user_id)) {
                    $sql = "INSERT INTO tm_flujo_paso_usuarios (paso_id, usu_id) VALUES (?, ?)";
                    $sql = $conectar->prepare($sql);
                    $sql->bindValue(1, $paso_id);
                    $sql->bindValue(2, $user_id);
                    $sql->execute();
                }
            }
        }

        if (is_array($cargo_ids)) {
            foreach ($cargo_ids as $cargo_id) {
                if (!empty($cargo_id)) {
                    $cargo_id_to_save = $cargo_id;
                    if ($cargo_id === 'JEFE_INMEDIATO') {
                        $cargo_id_to_save = -1;
                    }
                    $sql = "INSERT INTO tm_flujo_paso_usuarios (paso_id, car_id) VALUES (?, ?)";
                    $sql = $conectar->prepare($sql);
                    $sql->bindValue(1, $paso_id);
                    $sql->bindValue(2, $cargo_id_to_save);
                    $sql->execute();
                }
            }
        }
    }

    public function get_usuarios_especificos($paso_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT usu_id FROM tm_flujo_paso_usuarios WHERE paso_id = ? AND usu_id IS NOT NULL";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function get_cargos_especificos($paso_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT car_id FROM tm_flujo_paso_usuarios WHERE paso_id = ? AND car_id IS NOT NULL";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        $results = $sql->fetchAll(PDO::FETCH_COLUMN, 0);

        // Convert sentinel value back
        foreach ($results as &$car_id) {
            if ($car_id == -1) {
                $car_id = 'JEFE_INMEDIATO';
            }
        }
        return $results;
    }

    public function get_usuarios_especificos_data($paso_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $data = [];

        // Obtener usuarios
        $sql = "SELECT tm_usuario.usu_id, tm_usuario.usu_nom, tm_usuario.usu_ape 
                FROM tm_flujo_paso_usuarios 
                INNER JOIN tm_usuario ON tm_flujo_paso_usuarios.usu_id = tm_usuario.usu_id
                WHERE paso_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        $usuarios = $sql->fetchAll(PDO::FETCH_ASSOC);

        foreach ($usuarios as $u) {
            $data[] = [
                'tipo' => 'usuario',
                'id' => $u['usu_id'],
                'nombre' => $u['usu_nom'] . ' ' . $u['usu_ape']
            ];
        }

        // Obtener cargos
        $sql = "SELECT tm_cargo.car_id, tm_cargo.car_nom 
                FROM tm_flujo_paso_usuarios 
                INNER JOIN tm_cargo ON tm_flujo_paso_usuarios.car_id = tm_cargo.car_id
                WHERE paso_id = ?";
        $sql = "SELECT tpfu.car_id, tc.car_nom 
                FROM tm_flujo_paso_usuarios tpfu
                LEFT JOIN tm_cargo tc ON tpfu.car_id = tc.car_id
                WHERE tpfu.paso_id = ? AND tpfu.car_id IS NOT NULL";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        $cargos = $sql->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cargos as $cargo) {
            if ($cargo['car_id'] == -1) {
                $data[] = [
                    'id' => 'JEFE_INMEDIATO',
                    'nombre' => 'Jefe Inmediato',
                    'tipo' => 'cargo'
                ];
            } else {
                $data[] = [
                    'id' => $cargo['car_id'],
                    'nombre' => 'Cargo: ' . $cargo['car_nom'],
                    'tipo' => 'cargo'
                ];
            }
        }

        return $data;
    }

    public function get_paso_anterior($paso_actual_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // Primero, obtener el orden y el flujo_id del paso actual
        $sql_current = "SELECT flujo_id, paso_orden FROM tm_flujo_paso WHERE paso_id = ? AND est = 1";
        $stmt_current = $conectar->prepare($sql_current);
        $stmt_current->bindValue(1, $paso_actual_id);
        $stmt_current->execute();
        $paso_actual = $stmt_current->fetch(PDO::FETCH_ASSOC);

        if (!$paso_actual) {
            return null; // El paso actual no existe o está inactivo
        }

        $orden_actual = (int)$paso_actual['paso_orden'];
        $flujo_id = $paso_actual['flujo_id'];

        if ($orden_actual <= 1) {
            return null; // No hay paso anterior si es el primero o el orden es inválido
        }

        $orden_anterior = $orden_actual - 1;

        // Ahora, buscar el paso con el orden anterior en el mismo flujo
        $sql_prev = "SELECT * FROM tm_flujo_paso WHERE flujo_id = ? AND paso_orden = ? AND est = 1";
        $stmt_prev = $conectar->prepare($sql_prev);
        $stmt_prev->bindValue(1, $flujo_id);
        $stmt_prev->bindValue(2, $orden_anterior);
        $stmt_prev->execute();

        return $stmt_prev->fetch(PDO::FETCH_ASSOC);
    }
    public function set_firma_config($paso_id, $configuraciones)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // Primero eliminamos configuraciones existentes para este paso
        $sql = "DELETE FROM tm_flujo_paso_firma WHERE paso_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();

        // Insertamos las nuevas
        if (is_array($configuraciones)) {
            foreach ($configuraciones as $config) {
                $car_id_to_save = !empty($config['car_id']) ? $config['car_id'] : null;
                if ($car_id_to_save === 'JEFE_INMEDIATO') {
                    $car_id_to_save = -1; // Sentinel value for JEFE_INMEDIATO
                }

                $sql = "INSERT INTO tm_flujo_paso_firma (paso_id, usu_id, car_id, coord_x, coord_y, pagina, est) VALUES (?, ?, ?, ?, ?, ?, 1)";
                $sql = $conectar->prepare($sql);
                $sql->bindValue(1, $paso_id);
                $sql->bindValue(2, !empty($config['usu_id']) ? $config['usu_id'] : null);
                $sql->bindValue(3, $car_id_to_save);
                $sql->bindValue(4, $config['coord_x']);
                $sql->bindValue(5, $config['coord_y']);
                $sql->bindValue(6, !empty($config['pagina']) ? $config['pagina'] : 1);
                $sql->execute();
            }
        }
    }

    public function get_firma_config($paso_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT tm_flujo_paso_firma.*, tm_cargo.car_nom 
                FROM tm_flujo_paso_firma 
                LEFT JOIN tm_cargo ON tm_flujo_paso_firma.car_id = tm_cargo.car_id
                WHERE paso_id = ? AND tm_flujo_paso_firma.est = 1
                ORDER BY CASE WHEN tm_flujo_paso_firma.car_id = -1 THEN 1 ELSE 0 END ASC, tm_flujo_paso_firma.firma_id ASC";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        $results = $sql->fetchAll(PDO::FETCH_ASSOC);

        // Convert sentinel value back to string
        foreach ($results as &$row) {
            if ($row['car_id'] == -1) {
                $row['car_id'] = 'JEFE_INMEDIATO';
                $row['car_nom'] = 'Jefe Inmediato';
            }
        }
        return $results;
    }

    public function get_transiciones_inicio($flujo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
                    t.condicion_clave, 
                    t.condicion_nombre,
                    t.paso_destino_id
                FROM tm_flujo_transiciones t
                INNER JOIN tm_flujo_paso p_origen ON t.paso_origen_id = p_origen.paso_id
                WHERE p_origen.flujo_id = ? AND p_origen.paso_orden = 0 AND t.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}
