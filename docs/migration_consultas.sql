CREATE TABLE tm_consulta (
    cons_id INT AUTO_INCREMENT PRIMARY KEY,
    cons_nom VARCHAR(150) NOT NULL,
    cons_sql TEXT NOT NULL,
    est INT DEFAULT 1,
    fech_crea DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert existing presets as initial data (Optional but helpful logic migration)
INSERT INTO
    tm_consulta (cons_nom, cons_sql)
VALUES (
        'Usuario por Cédula',
        'SELECT usu_nom as nombre, usu_ape as apellido, usu_correo as correo, car_id, dp_id FROM tm_usuario WHERE usu_cedula = ?'
    ),
    (
        'Usuario por Correo',
        'SELECT usu_nom as nombre, usu_ape as apellido, usu_cedula as cedula FROM tm_usuario WHERE usu_correo = ?'
    );