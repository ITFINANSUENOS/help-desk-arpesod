<?php
require_once('../config/conexion.php');
require_once('../models/Notificacion.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$notificacion = new Notificacion();

switch ($_GET["op"]) {

    case "mostrar":
        $datos = $notificacion->get_notificacion_x_usu($_POST['usu_id']);
        if (is_array($datos) and count($datos) > 0) {
            foreach ($datos as $row) {
                $output['not_id'] = $row['not_id'];
                $output['usu_id'] = $row['usu_id'];
                $output['not_mensaje'] = $row['not_mensaje'] . ' ' . $row['tick_id'];
                $output['tick_id'] = $row['tick_id'];
            }
            echo json_encode($output);
        }



        break;

    case "notificacionespendientes":
        date_default_timezone_set('America/Bogota');
        $datos = $notificacion->get_notificacion_x_usu_todas($_POST['usu_id']);
        $conectar = new Conectar();
        if (is_array($datos) and count($datos) > 0) {
            foreach ($datos as $row) {

                $fech_not = new DateTime($row['fech_not']);

                $ahora = new DateTime('now', new DateTimeZone('America/Bogota'));

                $intervalo = $fech_not->diff($ahora);

                // Generar texto legible:
                if ($intervalo->y > 0) {
                    $tiempo = $intervalo->y . ' año(s)';
                } elseif ($intervalo->m > 0) {
                    $tiempo = $intervalo->m . ' mes(es)';
                } elseif ($intervalo->d > 0) {
                    $tiempo = $intervalo->d . ' día(s)';
                } elseif ($intervalo->h > 0) {
                    $tiempo = $intervalo->h . ' hora(s)';
                } elseif ($intervalo->i > 0) {
                    $tiempo = $intervalo->i . ' minuto(s)';
                } else {
                    $tiempo = 'hace unos segundos';
                }

?>
                <div class="dropdown-menu-notif-item">
                    <div class="photo">
                        <img src="" alt="">
                    </div>
                    <?php
                    if ($row['est'] != 0) {
                    ?>
                        <a onclick="verNotificacion(<?php echo $row['not_id'] ?>)" href="<?php echo $conectar->ruta() ?>view/DetalleTicket/?ID=<?php echo $row['tick_id'] ?>">Nueva notificacion </a>
                        <div><?php echo  $row['not_mensaje'] ?></div>
                        <div class="color-blue-grey-lighter"><?php echo $tiempo ?></div>
                    <?php } ?>
                </div>
<?php
            }
        }
        break;


    case "actualizar":
        $notificacion->update_notificacion_estado($_POST["not_id"]);
        break;

    case "leido";
        $notificacion->update_notificacion_estado_leido($_POST["not_id"]);
        break;

    case "contar";
        $datos = $notificacion->contar_notificaciones_x_usu($_POST["usu_id"]);
        foreach ($datos as $row) {
            $output['totalnotificaciones'] = $row['totalnotificaciones'];
        }
        echo json_encode($output);
        break;

    case "listar_historial":
        $datos = $notificacion->get_historial_notificaciones($_POST['usu_id']);
        $data = array();
        foreach ($datos as $row) {
            $sub_array = array();

            // Mensaje
            $sub_array[] = $row['not_mensaje'];

            // Fecha
            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_not"]));

            // Estado (Leído/No Leído)
            if ($row['est'] == 1) {
                $sub_array[] = '<span class="label label-warning">No Leído</span>';
            } else {
                $sub_array[] = '<span class="label label-default">Leído</span>';
            }

            // Acción (Ver Ticket)
            $sub_array[] = '<button type="button" onClick="verNotificacion(' . $row['not_id'] . ', ' . $row['tick_id'] . ');" id="' . $row['not_id'] . '" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';

            $data[] = $sub_array;
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case "listar_historial_feed":
        $datos = $notificacion->get_historial_notificaciones($_POST['usu_id']);
        // Return raw data for custom frontend rendering
        echo json_encode($datos);
        break;

    case "leido_todas":
        $notificacion->update_notificacion_estado_leido_todas($_POST["usu_id"]);
        break;

    case "leido_varios":
        $notificacion->update_notificacion_estado_varios($_POST["not_ids"], 0); // 0 = Leído
        break;

    case "no_leido_varios":
        $notificacion->update_notificacion_estado_varios($_POST["not_ids"], 1); // 1 = No Leído
        break;
}
?>