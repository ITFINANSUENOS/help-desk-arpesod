<?php
require_once("../config/conexion.php");
require_once("../models/ExcelData.php");
require_once("../vendor/autoload.php"); // Ensure PhpSpreadsheet is loaded

use PhpOffice\PhpSpreadsheet\IOFactory;

$excelData = new ExcelData();

switch ($_GET["op"]) {

    case "upload":
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
            $flujo_id = $_POST['flujo_id'];
            $file_name = $_FILES['excel_file']['name'];
            $tmp_name = $_FILES['excel_file']['tmp_name'];

            try {
                // Load Excel file using PhpSpreadsheet
                $spreadsheet = IOFactory::load($tmp_name);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Get headers from first row
                $headers = array_shift($rows);
                if (!$headers) {
                    echo "Error: Archivo vacío o sin cabeceras.";
                    exit;
                }

                // Normalize headers (trim, lowercase?) - preserving original for now but trimming
                $headers = array_map('trim', $headers);

                $data = [];
                foreach ($rows as $row) {
                    // Combine headers with row data
                    // Ensure row has same length as headers
                    if (count($row) == count($headers)) {
                        $data[] = array_combine($headers, $row);
                    } else {
                        // Handle mismatch if needed, or skip
                        // Try to slice or pad
                        //$data[] = array_combine($headers, array_slice($row, 0, count($headers)));
                    }
                }

                // Convert to JSON
                $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);

                // Save to DB
                $excelData->insert_data($flujo_id, $file_name, $json_data);
                echo "1"; // Success

            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            }
        } else {
            echo "Error: No se ha subido ningún archivo.";
        }
        break;

    case "combo":
        $flujo_id = $_POST['flujo_id'];
        $datos = $excelData->get_data_by_flow($flujo_id);
        if (is_array($datos) && count($datos) > 0) {
            $html = "";
            foreach ($datos as $row) {
                $html .= "<option value='" . $row['data_id'] . "'>" . $row['nombre_archivo'] . " (" . $row['fech_carga'] . ")</option>";
            }
            echo $html;
        } else {
            echo "";
        }
        break;

    case "get_columns":
        $data_id = $_POST['data_id'];
        $dataset = $excelData->get_data_by_id($data_id);
        if ($dataset && !empty($dataset['datos_json'])) {
            $json = json_decode($dataset['datos_json'], true);
            if (is_array($json) && count($json) > 0) {
                // Get keys from first element
                $columns = array_keys($json[0]);
                $html = "<option value=''>Seleccionar Columna...</option>";
                foreach ($columns as $col) {
                    $html .= "<option value='" . $col . "'>" . $col . "</option>";
                }
                echo $html;
            } else {
                echo "<option value=''>Sin datos</option>";
            }
        }
        break;
}
