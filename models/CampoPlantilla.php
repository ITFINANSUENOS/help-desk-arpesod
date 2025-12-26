<?php
class CampoPlantilla extends Conectar
{
    // --- tm_campo_plantilla methods ---

    public function insert_campo($paso_id, $campo_nombre, $campo_codigo, $coord_x, $coord_y, $pagina, $campo_tipo = 'text', $font_size = 10, $campo_trigger = 0, $campo_query = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_campo_plantilla (paso_id, campo_nombre, campo_codigo, coord_x, coord_y, pagina, campo_tipo, font_size, campo_trigger, campo_query, est, fech_crea) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->bindValue(2, $campo_nombre);
        $sql->bindValue(3, $campo_codigo);
        // Handle empty decimals
        $sql->bindValue(4, $coord_x === '' ? 0 : $coord_x);
        $sql->bindValue(5, $coord_y === '' ? 0 : $coord_y);
        $sql->bindValue(6, $pagina);
        $sql->bindValue(7, $campo_tipo);
        $sql->bindValue(8, $font_size);
        $sql->bindValue(9, $campo_trigger);
        $sql->bindValue(10, $campo_query);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function update_campo($campo_id, $campo_nombre, $campo_codigo, $coord_x, $coord_y, $pagina, $campo_tipo = 'text', $font_size = 10, $campo_trigger = 0, $campo_query = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_campo_plantilla SET campo_nombre=?, campo_codigo=?, coord_x=?, coord_y=?, pagina=?, campo_tipo=?, font_size=?, campo_trigger=?, campo_query=? WHERE campo_id=?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $campo_nombre);
        $sql->bindValue(2, $campo_codigo);
        // Handle empty decimals
        $sql->bindValue(3, $coord_x === '' ? 0 : $coord_x);
        $sql->bindValue(4, $coord_y === '' ? 0 : $coord_y);
        $sql->bindValue(5, $pagina);
        $sql->bindValue(6, $campo_tipo);
        $sql->bindValue(7, $font_size);
        $sql->bindValue(8, $campo_trigger);
        $sql->bindValue(9, $campo_query);
        $sql->bindValue(10, $campo_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function ejecutar_query_campo($campo_id, $valor)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // 1. Obtener el query del campo
        $sql = "SELECT campo_query FROM tm_campo_plantilla WHERE campo_id = ?";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $campo_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['campo_query'])) {
            $query = $row['campo_query'];

            // 2. Ejecutar el query (Preset, ID Dinámico, Excel o Manual)
            $sqlPreset = null;

            // --- Lógica Excel ---
            if (strpos($query, 'EXCEL:') === 0) {
                // Formato esperado: EXCEL:{data_id}:{columna_busqueda}
                $parts = explode(':', $query);
                if (count($parts) >= 3) {
                    $data_id = $parts[1];
                    $search_col = $parts[2];

                    // Fetch JSON data
                    require_once("ExcelData.php");
                    $excelData = new ExcelData();
                    $dataset = $excelData->get_data_by_id($data_id);

                    if ($dataset && !empty($dataset['datos_json'])) {
                        $json = json_decode($dataset['datos_json'], true);
                        if (is_array($json)) {
                            // Buscar coincidencia (Case Insensitive)
                            foreach ($json as $row) {
                                if (isset($row[$search_col]) && strcasecmp(trim($row[$search_col]), trim($valor)) === 0) {

                                    // --- RESOLUTION LOGIC ---
                                    // Resolves Names to IDs for Cargo and Regional

                                    // 1. CARGO
                                    // Check if 'Cargo' or 'Nombre Cargo' exists in the row
                                    // Or better yet, iterate through all keys and see if any value matches a known Cargo/Regional
                                    // BUT, that is expensive. Use heuristics on keys.

                                    foreach ($row as $key => $val) {
                                        $upperKey = strtoupper($key);

                                        // Detect Cargo
                                        if (strpos($upperKey, 'CARGO') !== false) {
                                            require_once(dirname(__FILE__) . "/Cargo.php");
                                            $cargo = new Cargo();
                                            $car_id = $cargo->get_id_por_nombre(trim($val));
                                            // error_log("RESOLVE CARGO: Value=['" . trim($val) . "'] Found ID: " . ($car_id ? $car_id : "FALSE"));
                                            if ($car_id) {
                                                $row[$key] = $car_id;
                                            }
                                        }

                                        // Detect Regional
                                        if (strpos($upperKey, 'REGIONAL') !== false || strpos($upperKey, 'REGION') !== false) {
                                            require_once(dirname(__FILE__) . "/Regional.php");
                                            $regional = new Regional();
                                            $reg_id = $regional->get_id_por_nombre(trim($val));
                                            if ($reg_id) {
                                                $row[$key] = $reg_id;
                                            }
                                        }
                                    }

                                    return $row; // Retorna todo el array asociativo (fila)
                                }
                            }
                        }
                    }
                }
                return null; // No encontrado en Excel
            }
            // --- Fin Lógica Excel ---

            if (strpos($query, 'PRESET_') === 0) {
                if ($query === 'PRESET_USUARIO_CEDULA') {
                    $sqlPreset = "SELECT usu_nom as nombre, usu_ape as apellido, usu_correo as correo, car_id, dp_id FROM tm_usuario WHERE usu_cedula = ?";
                } else if ($query === 'PRESET_USUARIO_CORREO') {
                    $sqlPreset = "SELECT usu_nom as nombre, usu_ape as apellido, usu_cedula as cedula FROM tm_usuario WHERE usu_correo = ?";
                }
            } elseif (is_numeric($query)) {
                // Es un ID de la tabla tm_consulta
                $sqlCons = "SELECT cons_sql FROM tm_consulta WHERE cons_id = ?";
                $stmtCons = $conectar->prepare($sqlCons);
                $stmtCons->bindValue(1, $query);
                $stmtCons->execute();
                $rowCons = $stmtCons->fetch(PDO::FETCH_ASSOC);
                if ($rowCons) {
                    $sqlPreset = $rowCons['cons_sql'];
                }
            } else {
                // Modo Manual (SQL Raw)
                $sqlPreset = $query;
            }

            if ($sqlPreset) {
                try {
                    $stmtDynamic = $conectar->prepare($sqlPreset);
                    $stmtDynamic->bindValue(1, $valor);
                    $stmtDynamic->execute();
                    return $stmtDynamic->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    return ["error" => "Error al ejecutar query dinámico: " . $e->getMessage()];
                }
            }
        }
        return null;
    }

    public function delete_campo($campo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_campo_plantilla SET est=0 WHERE campo_id=?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $campo_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function get_campos_por_paso($paso_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_campo_plantilla WHERE paso_id=? AND est=1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $paso_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_campos_por_flujo($flujo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT c.* 
                FROM tm_campo_plantilla c
                JOIN tm_flujo_paso p ON c.paso_id = p.paso_id
                WHERE p.flujo_id = ? AND c.est = 1 AND p.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $flujo_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- td_ticket_campo_valor methods ---

    public function insert_ticket_valor($tick_id, $campo_id, $valor)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO td_ticket_campo_valor (tick_id, campo_id, valor, est, fech_crea) VALUES (?, ?, ?, 1, NOW())";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $campo_id);
        $sql->bindValue(3, $valor);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function get_valores_por_ticket($tick_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT v.*, c.campo_nombre, c.campo_codigo, c.coord_x, c.coord_y, c.pagina, c.campo_tipo 
                FROM td_ticket_campo_valor v 
                INNER JOIN tm_campo_plantilla c ON v.campo_id = c.campo_id 
                WHERE v.tick_id=? AND v.est=1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->execute();
        return $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_valor_campo($tick_id, $campo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT valor FROM td_ticket_campo_valor WHERE tick_id=? AND campo_id=? AND est=1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $tick_id);
        $sql->bindValue(2, $campo_id);
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['valor'] : null;
    }
}
