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

                <div class="box-typical box-typical-padding" style="margin-bottom: 15px;">
                    <form id="ticket_search_form">
                        <div class="row">
                            <div class="col-lg-3">
                                <fieldset class="form-group">
                                    <label class="form-label" for="cats_id">Subcategoria</label>
                                    <select class="select2" id="cats_id" style="width:100%;">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </fieldset>
                            </div>
                            <div class="col-lg-3">
                                <fieldset class="form-group">
                                    <label class="form-label" for="eti_id">Etiqueta</label>
                                    <select class="select2" id="eti_id" style="width:100%;">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </fieldset>
                            </div>
                            <div class="col-lg-2">
                                <fieldset class="form-group">
                                    <label class="form-label" for="fech_crea_start">Fecha Inicio</label>
                                    <input type="date" class="form-control" id="fech_crea_start">
                                </fieldset>
                            </div>
                            <div class="col-lg-2">
                                <fieldset class="form-group">
                                    <label class="form-label" for="fech_crea_end">Fecha Fin</label>
                                    <input type="date" class="form-control" id="fech_crea_end">
                                </fieldset>
                            </div>
                            <div class="col-lg-2">
                                <fieldset class="form-group">
                                    <label class="form-label" for="tick_id">N° Ticket</label>
                                    <input type="text" class="form-control" id="tick_id" placeholder="ID">
                                </fieldset>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12">
                                <fieldset class="form-group">
                                    <label class="form-label" for="custom_search">Busqueda Mensajes</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="custom_search" placeholder="Buscar en mensajes...">
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary ladda-button" data-style="expand-left" type="button" id="btn_search">
                                                <i class="fa fa-search"></i>
                                            </button>
                                            <button class="btn btn-secondary ladda-button" data-style="expand-left" type="button" id="btn_clear">
                                                <i class="fa fa-eraser"></i>
                                            </button>
                                        </span>
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="box-typical">
                    <section class="tabs-section">
                        <div class="tabs-section-nav tabs-section-nav-inline" style="padding: 15px; padding-bottom: 5px;">
                            <ul class="nav" role="tablist" style="padding: 15px; padding-bottom: 15px;">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#tabs-4-tab-1" role="tab" data-toggle="tab">
                                        <span class="nav-link-in" style="padding: 5px;">
                                            Mis Errores (Recibidos)
                                            <span class="label label-danger" id="lbl_count_recibidos"></span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#tabs-4-tab-2" role="tab" data-toggle="tab">
                                        <span class="nav-link-in" style="padding: 5px;">
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
                                <div class="box-typical-padding" style="padding-top: 0;">
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
                                </div>
                            </div><!--.tab-pane-->

                            <!-- TAB 2: Errores Reportados (Enviados) -->
                            <div role="tabpanel" class="tab-pane fade" id="tabs-4-tab-2">
                                <div class="box-typical-padding" style="padding-top: 0;">
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
                                </div>
                            </div><!--.tab-pane-->
                        </div><!--.tab-content-->
                    </section><!--.tabs-section-->
                </div><!--.box-typical-->
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