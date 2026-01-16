<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Usuario extends Conectar
{

    public function login()
    {
        $conectar = parent::Conexion();
        parent::set_names();

        if (isset($_POST["enviar"])) {
            $correo = $_POST["usu_correo"];
            $password = $_POST["usu_pass"];
            $rol_solicitado = (int)$_POST["rol_id"];

            if (empty($correo) || empty($password)) {
                header("Location: " . Conectar::ruta() . "index.php?m=2");
                exit();
            } else {
                $sql = "SELECT u.*, c.car_nom, r.reg_nom 
                        FROM tm_usuario u
                        LEFT JOIN tm_cargo c ON u.car_id = c.car_id
                        LEFT JOIN tm_regional r ON u.reg_id = r.reg_id
                        WHERE u.usu_correo = ? AND u.est = 1";
                $stmt = $conectar->prepare($sql);
                $stmt->bindValue(1, $correo);
                $stmt->execute();
                $resultado = $stmt->fetch();

                if (is_array($resultado) and count($resultado) > 0 and password_verify($password, $resultado["usu_pass"])) {
                    $rol_real_del_usuario = $resultado["rol_id"];
                    $rol_de_administrador = 3;

                    $acceso_permitido = ($rol_real_del_usuario == $rol_de_administrador) || ($rol_real_del_usuario == $rol_solicitado);

                    if ($acceso_permitido) {
                        require_once(dirname(__FILE__) . '/../models/Organigrama.php');
                        $organigrama = new Organigrama();

                        // Verificar si el usuario es jefe usando el organigrama
                        $es_jefe = $organigrama->es_jefe($resultado['car_id']);
                        $_SESSION["is_jefe"] = $es_jefe;

                        // Guardar datos en la sesión
                        $_SESSION["usu_id"] = $resultado["usu_id"];
                        $_SESSION["usu_nom"] = $resultado["usu_nom"];
                        $_SESSION["usu_ape"] = $resultado["usu_ape"];
                        $_SESSION["rol_id"] = $rol_solicitado;
                        $_SESSION["rol_id_real"] = $rol_real_del_usuario;
                        $_SESSION["dp_id"] = $resultado["dp_id"]; // Se mantiene el depto al que pertenece
                        $_SESSION["car_id"] = $resultado["car_id"];
                        $_SESSION["car_nom"] = $resultado["car_nom"];
                        $_SESSION["reg_nom"] = $resultado["reg_nom"];
                        $_SESSION["es_nacional"] = $resultado["es_nacional"];

                        header("Location: " . Conectar::ruta() . "view/Home/");
                        exit();
                    } else {
                        header("Location: " . Conectar::ruta() . "index.php?m=1");
                        exit();
                    }
                } else {
                    header("Location: " . Conectar::ruta() . "index.php?m=1");
                    exit();
                }
            }
        }
    }
    public function insert_usuario($usu_nom, $usu_ape, $usu_correo, $usu_pass, $rol_id, $dp_id, $es_nacional, $reg_id, $car_id, $usu_cedula = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $hashed_pass = password_hash($usu_pass, PASSWORD_BCRYPT);

        $sql = "INSERT INTO tm_usuario (usu_id, usu_nom, usu_ape, usu_correo, usu_pass, rol_id, reg_id, car_id, dp_id, es_nacional, usu_cedula, fech_crea, fech_modi, fech_elim, est) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL, NULL, '1')";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_nom);
        $sql->bindValue(2, $usu_ape);
        $sql->bindValue(3, $usu_correo);
        $sql->bindValue(4, $hashed_pass);
        $sql->bindValue(5, $rol_id);
        $sql->bindValue(6, $reg_id);
        $sql->bindValue(7, $car_id);

        // dp_id puede ser NULL
        if (empty($dp_id)) {
            $sql->bindValue(8, null, PDO::PARAM_NULL);
        } else {
            $sql->bindValue(8, $dp_id, PDO::PARAM_INT);
        }

        $sql->bindValue(9, $es_nacional);
        $sql->bindValue(10, $usu_cedula);

        $sql->execute();

        return $conectar->lastInsertId();
    }

    public function update_usuario($usu_id, $usu_nom, $usu_ape, $usu_correo, $usu_pass, $rol_id, $dp_id, $es_nacional, $reg_id, $car_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        if (!empty($_POST['usu_pass'])) {
            $hashed_pass = password_hash($usu_pass, PASSWORD_BCRYPT);

            $sql = "UPDATE tm_usuario SET
                    usu_nom = ?,
                    usu_ape = ?,
                    usu_correo = ?,
                    usu_pass = ?,
                    rol_id = ?,
                    reg_id = ?,
                    car_id = ?,
                    dp_id = ?,
                    es_nacional = ?
                    WHERE usu_id = ?";
            $sql = $conectar->prepare($sql);
            $sql->bindValue(1, $usu_nom);
            $sql->bindValue(2, $usu_ape);
            $sql->bindValue(3, $usu_correo);
            $sql->bindValue(4, $hashed_pass);
            $sql->bindValue(5, $rol_id);
            $sql->bindValue(6, $reg_id);
            $sql->bindValue(7, $car_id);

            // dp_id puede ser NULL
            if (empty($dp_id)) {
                $sql->bindValue(8, null, PDO::PARAM_NULL);
            } else {
                $sql->bindValue(8, $dp_id, PDO::PARAM_INT);
            }

            $sql->bindValue(9, $es_nacional);

            $sql->bindValue(10, $usu_id);

            $sql->execute();
        } else {
            $sql = "UPDATE tm_usuario SET
                    usu_nom = ?,
                    usu_ape = ?,
                    usu_correo = ?,
                    rol_id = ?,
                    reg_id = ?,
                    car_id = ?,
                    dp_id = ?,
                    es_nacional = ?
                    WHERE usu_id = ?";
            $sql = $conectar->prepare($sql);
            $sql->bindValue(1, $usu_nom);
            $sql->bindValue(2, $usu_ape);
            $sql->bindValue(3, $usu_correo);
            $sql->bindValue(4, $rol_id);
            $sql->bindValue(5, $reg_id);
            $sql->bindValue(6, $car_id);
            // dp_id puede ser NULL
            if (empty($dp_id)) {
                $sql->bindValue(7, null, PDO::PARAM_NULL);
            } else {
                $sql->bindValue(7, $dp_id, PDO::PARAM_INT);
            }
            $sql->bindValue(8, $es_nacional);

            $sql->bindValue(9, $usu_id);

            $sql->execute();
        }



        return $usu_id;
    }

    public function delete_usuario($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_usuario SET est = '0', fech_elim = NOW() WHERE usu_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_usuario_por_correo($usu_correo)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_usuario WHERE usu_correo = ? AND est = 1";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_correo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function get_usuario()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "call sp_l_usuario_01()";
        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_usuarios_por_cargo($car_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT u.usu_id, u.usu_nom, u.usu_ape, r.reg_nom
                FROM tm_usuario u
                LEFT JOIN tm_regional r ON u.reg_id = r.reg_id
                WHERE u.car_id = ? AND u.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $car_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_usuario_x_rol()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_usuario WHERE rol_id = '2' AND est = '1'";
        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_usuario_x_departamento($dp_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        if (is_null($dp_id)) {
            $sql = "SELECT * FROM tm_usuario WHERE dp_id IS NULL AND est = '1'";
            $sql = $conectar->prepare($sql);
        } else {
            $sql = "SELECT * FROM tm_usuario WHERE dp_id = ? AND est = '1'";
            $sql = $conectar->prepare($sql);
            $sql->bindValue(1, $dp_id, PDO::PARAM_INT);
            $sql->execute();
        }

        return $resultado = $sql->fetchAll();
    }

    public function update_firma($usu_id, $usu_firma)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_usuario SET usu_firma = ? WHERE usu_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_firma);
        $sql->bindValue(2, $usu_id);
        $sql->execute();
        return $resultado = $sql->fetchAll();
    }

    public function get_usuario_x_id($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT tm_usuario.usu_id, tm_usuario.usu_nom, tm_usuario.usu_ape, tm_usuario.usu_correo, tm_usuario.usu_pass, tm_usuario.rol_id, tm_usuario.dp_id, tm_usuario.reg_id, tm_usuario.car_id, tm_usuario.es_nacional, tm_usuario.usu_firma, GROUP_CONCAT(empresa_usuario.emp_id) as emp_ids
                FROM tm_usuario
                LEFT JOIN empresa_usuario ON tm_usuario.usu_id = empresa_usuario.usu_id
                WHERE tm_usuario.usu_id = ?
                GROUP BY tm_usuario.usu_id";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();
        return $resultado = $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_usuario_detalle_x_id($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_usuario WHERE usu_id = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    //no se utiliza
    public function get_usuario_total_id($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) AS TOTAL FROM tm_ticket where usu_id = ? and est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    //no se utiliza
    public function get_usuario_totalabierto_id($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) AS TOTAL FROM tm_ticket where usu_asig = ? and tick_estado = 'Abierto' and est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    //no se utiliza
    public function get_usuario_totalcerrado_id($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT COUNT(*) AS TOTAL FROM tm_ticket where usu_id = ? and tick_estado = 'Cerrado' and est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    //no se utiliza
    public function get_total_categoria_usuario($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT tm_categoria.cat_nom as nom , COUNT(*) AS total
        FROM tm_ticket JOIN tm_categoria ON tm_ticket.cat_id = tm_categoria.cat_id
        WHERE tm_ticket.est = '1'
        AND usu_id = ?
        GROUP BY tm_categoria.cat_nom
        ORDER BY total DESC";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_usuario_por_cargo_y_regional($car_id, $reg_id)
    {
        $conectar = parent::Conexion();
        $sql = "SELECT * FROM tm_usuario WHERE car_id = ? AND reg_id = ? AND est = 1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $car_id);
        $sql->bindValue(2, $reg_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_usuario_por_cargo($car_id)
    {
        $conectar = parent::Conexion();
        $sql = "SELECT * FROM tm_usuario WHERE car_id = ? AND est = 1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $car_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }
    public function get_usuario_nacional_por_cargo($cargo_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_usuario 
            WHERE car_id = ? AND es_nacional = 1 AND est = 1 
            LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cargo_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    //no se utiliza
    public function get_usuario_por_cargo_y_departamento($car_id, $dp_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_usuario WHERE car_id = ? AND dp_id = ? AND est = 1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $car_id);
        $sql->bindValue(2, $dp_id);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_usuarios_por_ids($user_ids)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $inQuery = implode(',', array_fill(0, count($user_ids), '?'));
        $sql = "SELECT u.usu_id, u.usu_nom, u.usu_ape, u.usu_correo, r.reg_nom 
                FROM tm_usuario u
                LEFT JOIN tm_regional r ON u.reg_id = r.reg_id
                WHERE u.usu_id IN ($inQuery) AND u.est = 1";
        $stmt = $conectar->prepare($sql);
        foreach ($user_ids as $k => $id) {
            $stmt->bindValue(($k + 1), $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_usuarios_por_cargo_y_regional_all($car_id, $reg_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_usuario WHERE car_id = ? AND reg_id = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $car_id);
        $sql->bindValue(2, $reg_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_usuarios_por_cargo_regional_o_nacional($car_id, $reg_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        // Selecciona usuarios del cargo que sean de la regional O que sean nacionales
        $sql = "SELECT * FROM tm_usuario 
                WHERE car_id = ? 
                AND (reg_id = ? OR es_nacional = 1) 
                AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $car_id);
        $sql->bindValue(2, $reg_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
    public function get_usuario_por_cargo_y_zona($car_id, $zona)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT u.* 
                FROM tm_usuario u
                INNER JOIN tm_regional r ON u.reg_id = r.reg_id
                INNER JOIN tm_zona z ON r.zona_id = z.zona_id
                WHERE u.car_id = ? AND z.zona_nom = ? AND u.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $car_id);
        $sql->bindValue(2, $zona);
        $sql->execute();
        return $resultado = $sql->fetch(PDO::FETCH_ASSOC);
    }
    public function generar_token_recuperacion($usu_correo)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $usu_token_recuperacion = bin2hex(random_bytes(32));
        $usu_token_expiracion = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $sql = "UPDATE tm_usuario SET usu_token_recuperacion = ?, usu_token_expiracion = ? WHERE usu_correo = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_token_recuperacion);
        $sql->bindValue(2, $usu_token_expiracion);
        $sql->bindValue(3, $usu_correo);
        $sql->execute();

        // Check if any row was updated (user exists)
        if ($sql->rowCount() > 0) {
            return $usu_token_recuperacion;
        } else {
            return false;
        }
    }

    public function validar_token_recuperacion($usu_token_recuperacion)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $now = date("Y-m-d H:i:s");
        $sql = "SELECT * FROM tm_usuario WHERE usu_token_recuperacion = ? AND usu_token_expiracion > ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_token_recuperacion);
        $sql->bindValue(2, $now);
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function restablecer_contrasena($usu_token_recuperacion, $usu_pass)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $hashed_pass = password_hash($usu_pass, PASSWORD_BCRYPT);

        $sql = "UPDATE tm_usuario SET usu_pass = ?, usu_token_recuperacion = NULL, usu_token_expiracion = NULL WHERE usu_token_recuperacion = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $hashed_pass);
        $sql->bindValue(2, $usu_token_recuperacion);
        $sql->execute();
        return $sql->rowCount() > 0;
    }
    public function insert_usuario_perfil($usu_id, $per_ids)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        // 1. Delete existing profiles for this user
        $sqlDelete = "DELETE FROM tm_usuario_perfiles WHERE usu_id = ?";
        $stmtDelete = $conectar->prepare($sqlDelete);
        $stmtDelete->bindValue(1, $usu_id);
        $stmtDelete->execute();

        // 2. Insert new profiles
        if (is_array($per_ids) && count($per_ids) > 0) {
            foreach ($per_ids as $per_id) {
                if (!empty($per_id)) {
                    $sql = "INSERT INTO tm_usuario_perfiles (usu_id, per_id, est) VALUES (?, ?, 1)";
                    $stmt = $conectar->prepare($sql);
                    $stmt->bindValue(1, $usu_id);
                    $stmt->bindValue(2, $per_id);
                    $stmt->execute();
                }
            }
        }
    }

    public function get_perfiles_por_usuario($usu_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT p.per_id, p.per_nom 
                FROM tm_usuario_perfiles up
                JOIN tm_perfil p ON up.per_id = p.per_id
                WHERE up.usu_id = ? AND up.est = 1";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $usu_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
