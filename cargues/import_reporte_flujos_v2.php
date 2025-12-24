<?php
// Requerir la librería para leer Excel
require dirname(__FILE__) . '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Incluir los modelos necesarios
require_once(__DIR__ . '/../config/conexion.php');
require_once(__DIR__ . '/../models/Flujo.php');
require_once(__DIR__ . '/../models/FlujoPaso.php');
require_once(__DIR__ . '/../models/Subcategoria.php');
require_once(__DIR__ . '/../models/Categoria.php'); // Added
require_once(__DIR__ . '/../models/Prioridad.php'); // Added
require_once(__DIR__ . '/../models/Cargo.php');
require_once(__DIR__ . '/../models/Usuario.php');

$flujo_model = new Flujo();
$flujo_paso_model = new FlujoPaso();
$subcategoria_model = new Subcategoria();
$cargo_model = new Cargo();
$usuario_model = new Usuario();

// Conexión para queries manuales de transición
$conectar = Conectar::getConexion();

if (!isset($_FILES['archivo_flujos']) || $_FILES['archivo_flujos']['error'] != UPLOAD_ERR_OK) {
    die("<h3 style='color:red;'>Error: No se subió el archivo o hubo un problema en la subida.</h3>");
}

$nombre_temporal = $_FILES['archivo_flujos']['tmp_name'];

try {
    echo "<h1>Iniciando Importación V2 (Reverse Import)...</h1>";
    $spreadsheet = IOFactory::load($nombre_temporal);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Asumimos encabezados en fila 1
    array_shift($rows);

    $mapa_pasos = []; // Estructura: [flujo_id][paso_orden] => paso_id_bd
    $transiciones_pendientes = []; // Array para Segunda Pasada
    $stats = ['flujos_creados' => 0, 'pasos_creados' => 0, 'transiciones_creadas' => 0, 'errores' => 0];

    echo "<h3>Fase 1: Creación de Estructura y Pasos</h3>";

    // --- FASE 1: Estructura y Pasos ---
    $skipped_log = [];

    // Variables para manejar Celdas Combinadas (Merged Cells)
    $last_subcat_nom = '';
    $last_flujo_nom = '';

    // --- FASE 1: Estructura y Pasos ---
    foreach ($rows as $index => $row) {
        $real_row_num = $index + 2; // +2 porque $index 0 es fila 2 (despues de shift)

        // Índices basados en export_flujos.php ACTUALIZADO (0-indexed desde col A)
        // A: Cat, B: Subcat, C: Prioridad, D: Flujo, ... H: Orden, I: Paso, J: Resp, K: Desc, L: Tipo, M: Cond, N: Accion, O: Detalle

        $cat_nom = isset($row[0]) ? trim($row[0]) : ''; // Necesario para crear subcat
        $subcat_raw = isset($row[1]) ? trim($row[1]) : '';
        $prioridad_nom = isset($row[2]) ? trim($row[2]) : ''; // NUEVO
        $flujo_raw = isset($row[3]) ? trim($row[3]) : '';

        // Validar si Flujo viene vacío pero Subcategoría tiene dato
        if (empty($flujo_raw) && !empty($subcat_raw)) {
            $flujo_raw = $subcat_raw;
        }

        // Manejo de Merged Cells
        if (!empty($subcat_raw)) {
            $last_subcat_nom = $subcat_raw;
        }
        if (!empty($flujo_raw)) {
            $last_flujo_nom = $flujo_raw;
        }

        $subcat_nom = $last_subcat_nom;
        $flujo_nom = $last_flujo_nom;

        // Indices desplazados por la nueva columna Prioridad
        $paso_orden = isset($row[7]) ? trim($row[7]) : '';
        $paso_nombre = isset($row[8]) ? trim($row[8]) : '';
        $responsables_raw = isset($row[9]) ? trim($row[9]) : '';
        $descripcion = isset($row[10]) ? trim($row[10]) : '';
        $tipo_paso = isset($row[11]) ? trim($row[11]) : '';

        // Columnas de transición (para Fase 2)
        $condicion = isset($row[12]) ? trim($row[12]) : '';
        $accion = isset($row[13]) ? trim($row[13]) : '';
        $detalle = isset($row[14]) ? trim($row[14]) : '';

        if (empty($subcat_nom) || empty($flujo_nom)) {
            $skipped_log[] = "Fila $real_row_num: Subcategoría o Flujo vacíos.";
            continue;
        }

        // Ignorar "Pasos de Ruta" (Simulaciones visuales)
        if ($accion == 'Pasos de Ruta') {
            $skipped_log[] = "Fila $real_row_num: Fila informativa 'Pasos de Ruta'.";
            continue;
        }

        // Validar Orden (Ojo: empty('0') es true en PHP)
        if ($paso_orden === '' || $paso_orden === null || $paso_orden == '-') {
            $skipped_log[] = "Fila $real_row_num: Orden de paso vacío o guión (-). (Probablemente flujo vacío)";
            continue;
        }
        if ($paso_orden === '0' || $paso_orden === 0) {
            continue;
        }

        // 1. Obtener/Verificar Subcategoría
        $cats_id = $subcategoria_model->get_id_por_nombre($subcat_nom);

        // --- AUTO-CREATE LOGIC ---
        if (!$cats_id) {
            // Si no existe, intentamos crearla si tenemos Categoría y Prioridad
            if (!empty($cat_nom) && !empty($prioridad_nom)) {
                // Buscar ID de categoría padre
                $cat_model = new Categoria();
                $cat_id = $cat_model->get_id_por_nombre($cat_nom);

                // Buscar ID de prioridad
                $prioridad_model = new Prioridad(); // Asumiendo que existe el modelo y fue incluido/autocargado
                // Si no está incluido arriba, asegurar incluirlo
                if (!class_exists('Prioridad')) {
                    require_once(__DIR__ . '/../models/Prioridad.php');
                    $prioridad_model = new Prioridad();
                }
                $pd_id = $prioridad_model->get_id_por_nombre($prioridad_nom);

                if ($cat_id && $pd_id) {
                    // Crear Subcategoría
                    $subcategoria_model->insert_subcategoria($cat_id, $pd_id, $subcat_nom, "Creada por Importación V2");
                    $cats_id = $subcategoria_model->get_id_por_nombre($subcat_nom);
                    echo "<p style='color:green;'><strong>Subcategoría creada:</strong> '$subcat_nom' (Categoria: $cat_nom, Prioridad: $prioridad_nom)</p>";
                } else {
                    echo "<p style='color:orange;'>Fila $real_row_num: No se pudo crear Subcategoría '$subcat_nom'. Categoria '$cat_nom' o Prioridad '$prioridad_nom' no encontrados.</p>";
                    $stats['errores']++;
                    continue;
                }
            } else {
                echo "<p style='color:orange;'>Fila $real_row_num: Subcategoría '$subcat_nom' no encontrada y faltan datos (Categoria/Prioridad) para crearla. Omitiendo.</p>";
                $stats['errores']++;
                continue;
            }
        }

        // 2. Gestionar Flujo
        $flujo_data = $flujo_model->get_flujo_por_subcategoria($cats_id);
        $flujo_id = null;

        if ($flujo_data) {
            $flujo_id = $flujo_data['flujo_id'];
            // Si es la primera vez que tocamos este flujo en este script, limpiamos sus pasos anteriores
            if (!isset($mapa_pasos[$flujo_id])) {
                $flujo_paso_model->delete_pasos_por_flujo($flujo_id);
                echo "<p style='color:blue;'>Limpiando flujo existente: '$flujo_nom' (ID: $flujo_id)</p>";
                $mapa_pasos[$flujo_id] = [];
                $stats['flujos_creados']++; // Contamos como procesado
            }
        } else {
            // Crear flujo
            $flujo_model->insert_flujo($flujo_nom, $cats_id);
            // Recuperar el ID recién creado (hacky si insert_flujo no retorna ID, buscamos de nuevo)
            $flujo_data_new = $flujo_model->get_flujo_por_subcategoria($cats_id);
            $flujo_id = $flujo_data_new['flujo_id'];
            $mapa_pasos[$flujo_id] = [];
            $stats['flujos_creados']++;
            echo "<p style='color:green;'>Flujo creado: '$flujo_nom'</p>";
        }

        // 3. Parsear Responsables
        $cargo_id_asignado = null; // Principal
        $otros_cargos = [];

        $lines = explode("\n", $responsables_raw);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'Rol:') === 0) {
                $rol_nombre = trim(substr($line, 4));
                if ($rol_nombre == 'Jefe Inmediato') {
                    // Jefe Inmediato suele ser secundario o asignado especial, 
                    // pero si es el único, podría ser el principal.
                    // Asumiremos que si hay Jefe Inmediato, se agrega a "otros_cargos" con ID -1
                    // O si no hay principal aun, ponemos null y lo manejamos.
                    // En lógica actual, cargo_id_asignado es ID de tm_cargo. Jefe Inmediato no tiene ID en tm_cargo (es dinámico).
                    // Por tanto, debe ir a tm_flujo_paso_usuarios.
                    $otros_cargos[] = 'JEFE_INMEDIATO';
                } else {
                    $c_id = $cargo_model->get_id_por_nombre($rol_nombre);
                    if ($c_id) {
                        if (!$cargo_id_asignado) {
                            $cargo_id_asignado = $c_id;
                        } else {
                            $otros_cargos[] = $c_id;
                        }
                    }
                }
            } elseif (strpos($line, 'Rol Add:') === 0) {
                $rol_nombre = trim(substr($line, 8));
                if ($rol_nombre == 'Jefe Inmediato') {
                    $otros_cargos[] = 'JEFE_INMEDIATO';
                } else {
                    $c_id = $cargo_model->get_id_por_nombre($rol_nombre);
                    if ($c_id) $otros_cargos[] = $c_id;
                }
            }
        }

        // 4. Crear Paso
        // Valores por defecto
        $paso_tiempo_habil = 1;
        $req_seleccion_manual = 0;
        $es_tarea_nacional = 0;
        $es_aprobacion = 0;
        $paso_nom_adjunto = null;
        $permite_cerrar = ($tipo_paso == 'Cierre') ? 1 : 0;
        $necesita_aprobacion_jefe = 0; // Se podría deducir si Jefe Inmediato está en responsables
        $es_paralelo = 0;
        $requiere_firma = 0;
        $requiere_campos_plantilla = 0;

        // Inserción
        $nuevo_paso_id = $flujo_paso_model->insert_paso(
            $flujo_id,
            $paso_orden,
            $paso_nombre,
            $cargo_id_asignado,
            $paso_tiempo_habil,
            $descripcion,
            $req_seleccion_manual,
            $es_tarea_nacional,
            $es_aprobacion,
            $paso_nom_adjunto,
            $permite_cerrar,
            $necesita_aprobacion_jefe,
            $es_paralelo,
            $requiere_firma,
            $requiere_campos_plantilla
        );

        if ($nuevo_paso_id) {
            $stats['pasos_creados']++;
            $mapa_pasos[$flujo_id][$paso_orden] = $nuevo_paso_id;

            // Asignar responsables adicionales
            if (!empty($otros_cargos)) {
                $flujo_paso_model->set_usuarios_especificos($nuevo_paso_id, [], $otros_cargos);
            }

            // Guardar info para fase 2
            if (!empty($accion) && !empty($condicion) && $condicion != 'N/A') {
                $transiciones_pendientes[] = [
                    'flujo_id' => $flujo_id,
                    'paso_origen_id' => $nuevo_paso_id,
                    'condicion' => $condicion,
                    'accion' => $accion,
                    'detalle' => $detalle
                ];
            }
        } else {
            $skipped_log[] = "Fila $real_row_num: Error al insertar paso en BD.";
            $stats['errores']++;
        }
    }

    echo "<h3>Fase 2: Enlazado de Transiciones</h3>";

    // --- FASE 2: Transiciones ---
    foreach ($transiciones_pendientes as $trans) {
        $flujo_id = $trans['flujo_id'];
        $paso_origen_id = $trans['paso_origen_id'];
        $condicion = $trans['condicion']; // Texto de la condición (Label)
        $condicion_clave = $condicion; // Asumiremos Clave = Nombre por simplicidad en importación
        $accion = $trans['accion'];
        $detalle = $trans['detalle'];

        $paso_destino_id = null;
        $ruta_destino_id = null;

        if ($accion == 'Ir a Paso') {
            // detalle: "Paso 5: Nombre..."
            if (preg_match('/Paso (\d+):/', $detalle, $matches)) {
                $orden_destino = $matches[1];
                if (isset($mapa_pasos[$flujo_id][$orden_destino])) {
                    $paso_destino_id = $mapa_pasos[$flujo_id][$orden_destino];
                }
            }
        } elseif ($accion == 'Ir a Ruta') {
            // detalle: "Ruta: NombreRuta"
            if (preg_match('/Ruta: (.*)/', $detalle, $matches)) {
                $ruta_nombre = trim($matches[1]);
                // Buscar ID ruta
                // Necesitamos un modelo o query ad-hoc para buscar ruta por nombre
                $sql_ruta = "SELECT ruta_id FROM tm_ruta WHERE ruta_nombre = ? AND est = 1 LIMIT 1";
                $stmt_ruta = $conectar->prepare($sql_ruta);
                $stmt_ruta->execute([$ruta_nombre]);
                $ruta_row = $stmt_ruta->fetch(PDO::FETCH_ASSOC);
                if ($ruta_row) {
                    $ruta_destino_id = $ruta_row['ruta_id'];
                }
            }
        } elseif ($accion == 'Fin' || $accion == 'Terminar' || $accion == 'Finalizar') {
            // Es una transición explícita a Fin (útil para ramas de decisiones)
            // Dejamos ids en null, pero permitimos que entre al if de inserción
            // Hack: Forzamos una bandera para insertar
            $paso_destino_id = -1; // Marcador temporal
        }

        if ($paso_destino_id || $ruta_destino_id) {

            $dest_final = ($paso_destino_id == -1) ? null : $paso_destino_id;

            // Insertar transición
            $sql_ins = "INSERT INTO tm_flujo_transiciones (paso_origen_id, condicion_clave, condicion_nombre, paso_destino_id, ruta_id, est) VALUES (?, ?, ?, ?, ?, 1)";
            $stmt_ins = $conectar->prepare($sql_ins);
            $stmt_ins->bindValue(1, $paso_origen_id);
            $stmt_ins->bindValue(2, $condicion_clave);
            $stmt_ins->bindValue(3, $condicion);
            $stmt_ins->bindValue(4, $dest_final); // Nullable
            $stmt_ins->bindValue(5, $ruta_destino_id); // Nullable
            $stmt_ins->execute();
            $stats['transiciones_creadas']++;
        }
    }

    echo "<h3>¡Importación Finalizada!</h3>";
    echo "<ul>";
    echo "<li>Flujos procesados/creados: {$stats['flujos_creados']}</li>";
    echo "<li>Pasos creados: {$stats['pasos_creados']}</li>";
    echo "<li>Transiciones creadas: {$stats['transiciones_creadas']}</li>";
    echo "<li>Errores Críticos: {$stats['errores']}</li>";
    echo "</ul>";

    // Mostrar detalles de filas omitidas si no se procesó nada o si hay muchas
    if ($stats['pasos_creados'] == 0 || !empty($skipped_log)) {
        echo "<div style='max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;'>";
        echo "<h4>Detalle de Filas Omitidas (" . count($skipped_log) . ")</h4>";
        if (count($skipped_log) > 0) {
            echo "<ul>";
            foreach ($skipped_log as $log) {
                echo "<li><small>$log</small></li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No hubo filas omitidas explícitamente, revise si el archivo está vacío o tiene formato incorrecto.</p>";
        }
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<h3 style='color:red;'>Error Crítico: " . $e->getMessage() . "</h3>";
}
