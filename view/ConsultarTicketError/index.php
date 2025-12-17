<?php
require_once('../../config/conexion.php');
if (isset($_SESSION["usu_id"])) {
?>
    <!DOCTYPE html>
    <html>
    <?php require_once('../MainHead/head.php') ?>
    <title>Tickets con Error</title>
    </head>

    <body class="with-side-menu">

        <?php require_once('../MainHeader/header.php') ?>

        <div class="mobile-menu-left-overlay"></div>

        <?php require_once('../MainNav/nav.php') ?>

        <!-- contenido -->
        <div class="page-content">
            <div class="container-fluid">
                <header class="section-header">
                    <div class="tbl">
                        <div class="tbl-row">
                            <div class="tbl-cell">
                                <h3>Tickets con Error de Proceso/Información</h3>
                                <ol class="breadcrumb breadcrumb-simple">
                                    <li><a href="..\Home\">Home</a></li>
                                    <li class="active">Tickets con Error</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="box-typical box-typical-padding">
                    <section class="tabs-section">
                        <div class="tabs-section-nav tabs-section-nav-inline">
                            <ul class="nav" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#tabs-4-tab-1" role="tab" data-toggle="tab">
                                        <span class="nav-link-in">
                                            Mis Errores (Recibidos)
                                            <span class="label label-danger" id="lbl_count_recibidos"></span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#tabs-4-tab-2" role="tab" data-toggle="tab">
                                        <span class="nav-link-in">
                                            Errores Reportados (Enviados)
                                            <span class="label label-info" id="lbl_count_enviados"></span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </div><!--.tabs-section-nav-->

                        <div class="tab-content">
                            <!-- TAB 1: Mis Errores (Recibidos) -->
                            <div role="tabpanel" class="tab-pane fade in active" id="tabs-4-tab-1">
                                <table id="ticket_data_recibidos" class="table table-bordered table-striped table-vcenter js-dataTable-full">
                                    <thead>
                                        <tr role="row">
                                            <th style="width: 5%;">N° Ticket</th>
                                            <th style="width: 10%;">Categoria</th>
                                            <th style="width: 10%;">Subcategoria</th>
                                            <th style="width: 15%;">Titulo</th>
                                            <th style="width: 25%;">Detalle Error</th>
                                            <th style="width: 15%;">Reportado Por</th>
                                            <th style="width: 10%;">Fecha Reporte</th>
                                            <th style="width: 5%;">Accion</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div><!--.tab-pane-->

                            <!-- TAB 2: Errores Reportados (Enviados) -->
                            <div role="tabpanel" class="tab-pane fade" id="tabs-4-tab-2">
                                <table id="ticket_data_enviados" class="table table-bordered table-striped table-vcenter js-dataTable-full">
                                    <thead>
                                        <tr role="row">
                                            <th style="width: 5%;">N° Ticket</th>
                                            <th style="width: 10%;">Categoria</th>
                                            <th style="width: 10%;">Subcategoria</th>
                                            <th style="width: 15%;">Titulo</th>
                                            <th style="width: 25%;">Detalle Error</th>
                                            <th style="width: 15%;">Responsable (Culpable)</th>
                                            <th style="width: 10%;">Fecha Reporte</th>
                                            <th style="width: 5%;">Accion</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div><!--.tab-pane-->
                        </div><!--.tab-content-->
                    </section><!--.tabs-section-->
                </div>
            </div>
        </div>
        <?php require_once('../MainJs/js.php') ?>

        <script type="text/javascript" src="../ConsultarTicketError/consultarticketerror.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>


    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>