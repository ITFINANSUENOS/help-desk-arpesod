<?php
class Subcategoria extends Conectar
{
    public function get_subcategoria($cat_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_subcategoria WHERE cat_id = ? and est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cat_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_subcategoriatodo()
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "SELECT 
            tm_subcategoria.cat_id,
            tm_subcategoria.cats_id,
            tm_subcategoria.cats_nom,
            tm_categoria.cat_nom,
            td_prioridad.pd_nom 
            FROM tm_subcategoria
            INNER JOIN tm_categoria ON tm_subcategoria.cat_id = tm_categoria.cat_id
            INNER JOIN td_prioridad ON tm_subcategoria.pd_id = td_prioridad.pd_id
            WHERE tm_subcategoria.est = 1";
        $sql = $conectar->prepare($sql);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }


    public function insert_subcategoria($cat_id, $pd_id, $cats_nom, $cats_descrip)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "INSERT INTO tm_subcategoria (cats_id, cat_id, pd_id, cats_nom, cats_descrip, est) VALUES (NULL,?,?,?,?,1)";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cat_id);
        $sql->bindValue(2, $pd_id);
        $sql->bindValue(3, $cats_nom);
        $sql->bindValue(4, $cats_descrip);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function delete_subcategoria($cats_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_subcategoria SET est = 0 WHERE cats_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cats_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function update_subcategoria($cats_id, $cat_id, $pd_id, $cats_nom, $cats_descrip)
    {
        $conectar = parent::Conexion();
        parent::set_names();
        $sql = "UPDATE tm_subcategoria SET cat_id = ?, pd_id = ?, cats_nom = ?, cats_descrip = ? WHERE cats_id = ?";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cat_id);
        $sql->bindValue(2, $pd_id);
        $sql->bindValue(3, $cats_nom);
        $sql->bindValue(4, $cats_descrip);
        $sql->bindValue(5, $cats_id);
        $sql->execute();

        return $resultado = $sql->fetchAll();
    }

    public function get_subcategoria_x_id($cats_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $output = array();

        // 1. Obtener los datos básicos de la subcategoría
        $sql_subcat = "SELECT * FROM tm_subcategoria WHERE cats_id = ? AND est = 1";
        $sql_subcat = $conectar->prepare($sql_subcat);
        $sql_subcat->bindValue(1, $cats_id);
        $sql_subcat->execute();
        $subcategoria_data = $sql_subcat->fetch(PDO::FETCH_ASSOC);

        // Si encontramos la subcategoría, buscamos sus relaciones
        if ($subcategoria_data) {
            $output['subcategoria'] = $subcategoria_data;
            $cat_id = $subcategoria_data['cat_id']; // ID de la categoría padre

            // 2. Obtener la lista de IDs de empresas asociadas a la categoría padre
            $sql_emp = "SELECT emp_id FROM categoria_empresa WHERE cat_id = ?";
            $sql_emp = $conectar->prepare($sql_emp);
            $sql_emp->bindValue(1, $cat_id);
            $sql_emp->execute();
            // array_column crea un array simple con solo los IDs. Ej: [10, 11]
            $output['empresas'] = array_column($sql_emp->fetchAll(PDO::FETCH_ASSOC), 'emp_id');

            // 3. Obtener la lista de IDs de departamentos asociados a la categoría padre
            $sql_dp = "SELECT dp_id FROM categoria_departamento WHERE cat_id = ?";
            $sql_dp = $conectar->prepare($sql_dp);
            $sql_dp->bindValue(1, $cat_id);
            $sql_dp->execute();
            $output['departamentos'] = array_column($sql_dp->fetchAll(PDO::FETCH_ASSOC), 'dp_id');
        }

        return $output;
    }

    public function get_subcategoria_por_nombre($cats_nom)
    {
        $conectar = parent::conexion();
        parent::set_names();
        $sql = "SELECT * FROM tm_subcategoria WHERE cats_nom = ? AND est = 1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, trim($cats_nom));
        $sql->execute();
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function get_nombre_subcategoria($cats_id)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $sql = "SELECT cats_nom FROM tm_subcategoria WHERE cats_id = ? AND est = 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $cats_id);
        $sql->execute();
        $row = $sql->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['cats_nom'] : null;
    }


    public function get_id_por_nombre($cats_nom)
    {
        $conectar = parent::conexion();
        $sql = "SELECT cats_id FROM tm_subcategoria WHERE UPPER(cats_nom) = UPPER(?) AND est = 1 LIMIT 1";
        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, trim($cats_nom));
        $sql->execute();
        $resultado = $sql->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['cats_id'] : null;
    }
    public function get_subcategorias_filtradas($creador_car_id, $creador_per_ids = [], $dp_id = null)
    {
        $conectar = parent::Conexion();
        parent::set_names();

        $sql = "SELECT DISTINCT
                s.cats_id,
                s.cats_nom
            FROM 
                tm_subcategoria s
            INNER JOIN 
                tm_regla_mapeo rm ON s.cats_id = rm.cats_id
            LEFT JOIN
                regla_creadores rc ON rm.regla_id = rc.regla_id
            LEFT JOIN
                regla_creadores_perfil rcp ON rm.regla_id = rcp.regla_id";

        if ($dp_id) {
            $sql .= " INNER JOIN categoria_departamento cd ON s.cat_id = cd.cat_id";
        }

        $sql .= " WHERE 
                s.est = 1 AND rm.est = 1 ";

        if ($dp_id) {
            $sql .= " AND cd.dp_id = ? ";
        }

        $sql .= " AND (
                    rc.creador_car_id = ?";

        if (!empty($creador_per_ids)) {
            $placeholders = implode(',', array_fill(0, count($creador_per_ids), '?'));
            $sql .= " OR (rcp.creator_per_id IN ($placeholders) AND rcp.est = 1)";
        }

        $sql .= ") ORDER BY s.cats_nom ASC";

        $sql = $conectar->prepare($sql);

        $bindIndex = 1;

        if ($dp_id) {
            $sql->bindValue($bindIndex, $dp_id);
            $bindIndex++;
        }

        $sql->bindValue($bindIndex, $creador_car_id);
        $bindIndex++;

        if (!empty($creador_per_ids)) {
            foreach ($creador_per_ids as $per_id) {
                $sql->bindValue($bindIndex, $per_id);
                $bindIndex++;
            }
        }

        $sql->execute();
        return $resultado = $sql->fetchAll();
    }
    public function get_subcategorias_por_usu_ticket($usu_id, $rol_id)
    {
        $conectar = parent::conexion();
        parent::set_names();

        $sql = "SELECT DISTINCT s.cats_id, s.cats_nom 
                FROM tm_subcategoria s 
                INNER JOIN tm_ticket t ON s.cats_id = t.cats_id 
                WHERE s.est = 1 AND t.est = 1";

        if ($rol_id == 1) { // Usuario
            $sql .= " AND t.usu_id = ?";
        } else { // Soporte/Admin (viendo sus asignados)
            // Nota: Para admin podría ser diferente si quiere ver todo, pero la solicitud fue restrictiva.
            // Asumimos que Admin filtra por asignados también si filtra por "mis subcategorias"
            $sql .= " AND t.usu_asig = ?";
        }

        $sql .= " ORDER BY s.cats_nom ASC";

        $sql = $conectar->prepare($sql);
        $sql->bindValue(1, $usu_id);
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}
