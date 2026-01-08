<?php

namespace models\repository;

require_once(dirname(__FILE__) . '/../Cargo.php');

use Cargo;
use PDO;

class CargoRepository
{
    private $cargoModel;

    public function __construct($pdo)
    {
        // Legacy model handling
        $this->cargoModel = new Cargo();
    }

    public function getById($id)
    {
        $result = $this->cargoModel->get_cargo_por_id($id);
        if ($result && count($result) > 0) {
            return $result[0];
        }
        return null;
    }
}
