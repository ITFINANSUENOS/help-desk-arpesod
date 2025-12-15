<?php

session_start();


// Requerir el autoload de Composer para poder usar las librerías instaladas
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar las variables de entorno desde el archivo .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

class Conectar
{
    protected $dbh;

    protected function Conexion()
    {
        try {
            // Ahora leemos las variables de entorno con $_ENV
            $host = $_ENV['DB_HOST'];
            $dbname = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];

            $conectar = $this->dbh = new PDO("mysql:host={$host};dbname={$dbname}", $user, $pass);
            return $conectar;
        } catch (Exception $e) {
            print "ERROR DB" . $e->getMessage() . "<br/>";
            die();
        }
    }

    public function set_names()
    {
        return $this->dbh->query("SET NAMES 'utf8'");
    }

    public static function ruta()
    {
        // La ruta también viene del archivo .env
        return $_ENV['APP_URL'];
    }

    public static function getConexion()
    {
        $instance = new self();
        return $instance->Conexion();
    }
}
