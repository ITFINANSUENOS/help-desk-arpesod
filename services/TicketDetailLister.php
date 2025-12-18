<?php

require_once('../models/Ticket.php');
require_once('../models/Usuario.php');

class TicketDetailLister
{
    private $ticketModel;

    public function __construct()
    {
        $this->ticketModel = new Ticket();
    }

    public function listTicketDetails($ticketId)
    {
        $datos = $this->ticketModel->listar_ticketdetalle_x_ticket($ticketId);
?>
        <?php
        foreach ($datos as $row) {
        ?>
            <article class="activity-line-item box-typical">
                <div class="activity-line-date">
                    <?php echo date("d/m/Y", strtotime($row['fech_crea'])) ?>
                </div>
                <header class="activity-line-item-header">
                    <div class="activity-line-item-user">
                        <div class="activity-line-item-user-photo">
                            <a href="#">
                                <img src="../../public/img/user-<?php echo $row['rol_id'] ?>.png" alt="">
                            </a>
                        </div>
                        <div class="activity-line-item-user-name"><?php echo $row['usu_nom'] . ' ' . $row['usu_ape'] ?></< /div>
                            <div class="activity-line-item-user-status">
                                <?php
                                if ($row['rol_id'] == 1) {
                                    echo 'Usuario';
                                } else {
                                    echo 'Soporte';
                                }
                                ?>
                            </div>
                        </div>
                </header>
                <div class="activity-line-action-list">
                    <section class="activity-line-action">
                        <div class="time"><?php echo date("h:i A", strtotime($row['fech_crea'])) ?></div>
                        <div class="cont">
                            <div class="cont-in summernote-content" style="margin-bottom: 8px;">
                                <p><?php echo $row['tickd_descrip'] ?></p>
                                <ul class="meta">
                                </ul>
                            </div>
                            <?php
                            if (!empty($row['det_noms'])) {
                                $docs = explode('|', $row['det_noms']);
                                foreach ($docs as $det_nom) {
                                    if (empty($det_nom)) continue;
                            ?>
                                    <div class="documentos-attachment p-3 border rounded bg-light" style="margin-top: 5px;">
                                        <p class="mb-3 text-secondary" style="margin-bottom: 0;">
                                            <i class="fa fa-paperclip"></i> Documento adjunto
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center p-2 bg-white border rounded">
                                            <a href="../../public/document/detalle/<?php echo $row['tickd_id']; ?>/<?php echo $det_nom; ?>" target="_blank" class="text-decoration-none fw-semibold text-dark">
                                                <i class="fa fa-file-text-o me-2"></i> <?php echo $det_nom; ?>
                                            </a>
                                            <a href="../../public/document/detalle/<?php echo $row['tickd_id']; ?>/<?php echo $det_nom; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-eye"></i> Ver
                                            </a>
                                        </div>
                                    </div>
                            <?php
                                }
                            }
                            ?>
                        </div>
                    </section>
                </div>
            </article>
        <?php
        }
        ?>
    <?php
    }

    public function listTicketDetailRecord($ticketId)
    {
        $datos = $this->ticketModel->listar_historial_completo($ticketId);
    ?>
        <?php
        foreach ($datos as $row) {
            $actor_name = $row['usu_nom'] . ' ' . $row['usu_ape'];
            $rol_text = '';

            if ($row['tipo'] == 'comentario') {
                $rol_text = ($row['rol_id'] == 1) ? 'Usuario' : 'Soporte';
            } elseif ($row['tipo'] == 'asignacion') {
                $rol_text = ($row['usu_nom'] == 'Sistema') ? 'Sistema' : (($row['rol_id'] == 1) ? 'Usuario' : 'Soporte');
            } elseif ($row['tipo'] == 'cierre') {
                $rol_text = 'Sistema';
            }
        ?>
            <article class="activity-line-item box-typical">
                <div class="activity-line-date">
                    <?php echo date("d/m/Y", strtotime($row['fecha_evento'])) ?>
                </div>
                <header class="activity-line-item-header">
                    <div class="activity-line-item-user">
                        <div class="activity-line-item-user-photo">
                            <a href="#">
                                <img src="../../public/img/user-<?php echo $row['rol_id'] ?>.png" alt="">
                            </a>
                        </div>
                        <div class="activity-line-item-user-name"><?php echo $actor_name; ?></div>
                        <div class="activity-line-item-user-status"><?php echo $rol_text; ?></div>
                    </div>
                </header>
                <div class="activity-line-action-list">
                    <section class="activity-line-action">
                        <div class="time"><?php echo date("h:i A", strtotime($row['fecha_evento'])) ?></div>
                        <div class="cont">
                            <div class="cont-in summernote-content" style="margin-bottom: 8px;">
                                <?php if ($row['tipo'] == 'asignacion') : ?>
                                    <p>
                                        <strong>Reasignación de Ticket:</strong><br>
                                        Ticket asignado a <b><?php echo $row['nom_receptor'] . ' ' . $row['ape_receptor']; ?></b>.
                                        <br>
                                        <i><?php echo $row['descripcion']; ?></i>
                                        <?php if (!empty($row['error_descrip'])) : ?>
                                            <br><strong>Descripción del Error:</strong> <?php echo htmlspecialchars($row['error_descrip']); ?>
                                        <?php endif; ?>
                                    </p>

                                    <?php
                                    if (!empty($row['estado_tiempo_paso']) && $row['estado_tiempo_paso'] != 'N/A') {
                                        $clase_css = ($row['estado_tiempo_paso'] == 'Atrasado') ? 'label-danger' : 'label-success';
                                    ?>
                                        <small class="text-muted">
                                            Estado del paso anterior: <span class="label <?php echo $clase_css; ?>"><?php echo $row['estado_tiempo_paso']; ?></span>
                                        </small>
                                    <?php
                                    }
                                    ?>

                                <?php elseif ($row['tipo'] == 'cierre') : ?>
                                    <p style="color:red;"><strong><?php echo $row['descripcion']; ?></strong></p>
                                <?php elseif ($row['tipo'] == 'creacion') : ?>
                                    <p><strong>Ticket Creado:</strong></p>
                                    <p><?php echo $row['descripcion']; ?></p>
                                <?php else: // Es un comentario
                                ?>
                                    <p><?php echo $row['descripcion']; ?></p>
                                <?php endif; ?>
                                <?php
                                // Muestra adjuntos para comentarios y creación
                                if (($row['tipo'] == 'comentario' || $row['tipo'] == 'creacion') && !empty($row['det_noms'])) {
                                    $docs = explode('|', $row['det_noms']);
                                    $path = ($row['tipo'] == 'creacion') ? 'ticket' : 'detalle';
                                    foreach ($docs as $det_nom) {
                                        if (empty($det_nom)) continue;
                                ?>
                                        <div class="documentos-attachment p-3 border rounded bg-light" style="margin-top: 5px;">
                                            <p class="mb-3 text-secondary" style="margin-bottom: 0;">
                                                <i class="fa fa-paperclip"></i> Documento adjunto
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center p-2 bg-white border rounded">
                                                <a href="../../public/document/<?php echo $path; ?>/<?php echo $row['tickd_id']; ?>/<?php echo $det_nom; ?>" target="_blank" class="text-decoration-none fw-semibold text-dark">
                                                    <i class="fa fa-file-text-o me-2"></i> <?php echo $det_nom; ?>
                                                </a>
                                                <a href="../../public/document/<?php echo $path; ?>/<?php echo $row['tickd_id']; ?>/<?php echo $det_nom; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fa fa-eye"></i> Ver
                                                </a>
                                            </div>
                                        </div>
                                <?php
                                    }
                                }
                                ?>
                            </div>
                    </section>
                </div>
            </article>
        <?php
        }
        ?>
<?php
    }
}
?>