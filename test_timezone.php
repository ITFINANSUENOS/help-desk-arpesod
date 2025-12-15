<?php
// Incluimos la configuración principal donde definimos la zona horaria
require_once 'config/conexion.php';

echo "--- Verificación de Configuración de Zona Horaria ---\n\n";

// 1. Verificar configuración de PHP
$zona_actual = date_default_timezone_get();
$fecha_php = date('Y-m-d H:i:s');
$offset = date('P'); // diferencia con GMT (ej: -05:00)

echo "1. CONFIGURACIÓN PHP:\n";
echo "   Zona Horaria Activa: " . $zona_actual . "\n";
echo "   Hora Actual PHP:     " . $fecha_php . "\n";
echo "   Diferencia UTC:      " . $offset . "\n";

if ($zona_actual === 'America/Bogota' || $offset === '-05:00') {
    echo "   [OK] La zona horaria parece correcta para Colombia/Perú/Ecuador.\n";
} else {
    echo "   [WARNING] La zona horaria podría no ser la esperada.\n";
}
echo "\n";

// 2. Verificar hora de la Base de Datos
echo "2. CONFIGURACIÓN BASE DE DATOS (MySQL):\n";
try {
    $conectar = Conectar::getConexion();
    $stmt = $conectar->prepare("SELECT NOW() as db_time, @@global.time_zone as global_tz, @@session.time_zone as session_tz");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "   Hora Actual DB:      " . $row['db_time'] . "\n";
    echo "   Timezone Global:     " . $row['global_tz'] . "\n";
    echo "   Timezone Sesión:     " . $row['session_tz'] . "\n";

    // Comparación simple
    $php_ts = strtotime($fecha_php);
    $db_ts = strtotime($row['db_time']);
    $diff = abs($php_ts - $db_ts);

    if ($diff < 60) {
        echo "   [OK] La hora de PHP y la DB están sincronizadas (Diferencia: {$diff}s).\n";
    } else {
        echo "   [WARNING] Hay una diferencia significativa entre PHP y DB ({$diff}s).\n";
    }
} catch (Exception $e) {
    echo "   [ERROR] No se pudo conectar a la base de datos: " . $e->getMessage() . "\n";
}
echo "\n-----------------------------------------------------\n";
