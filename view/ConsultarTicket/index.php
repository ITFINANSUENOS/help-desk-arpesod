<?php
require_once('../../config/conexion.php');
if (isset($_SESSION["usu_id"])) {
?>
    <!DOCTYPE html>
    <html>
    <?php require_once('../MainHead/head.php') ?>
    <title>Home</title>
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
                                <h3>Consultar ticket Abiertos</h3>
                                <ol class="breadcrumb breadcrumb-simple">
                                    <li><a href="..\Home\">Home</a></li>
                                    <li class="active">Consultar ticket</li>
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
                                    <label class="form-label" for="emp_id">Empresa</label>
                                    <select class="select2" id="emp_id" style="width:100%;">
                                        <option value="">Seleccionar</option>
                                    </select>
                                </fieldset>
                            </div>
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
                            <div class="col-lg-3">
                                <fieldset class="form-group">
                                    <label class="form-label" for="tick_id">N° Ticket</label>
                                    <input type="text" class="form-control" id="tick_id" placeholder="ID">
                                </fieldset>
                            </div>
                        </div>
                        <div class="row">
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
                                    <label class="form-label" for="usu_nom">Usuario</label>
                                    <input type="text" class="form-control" id="usu_nom" placeholder="Usuario">
                                </fieldset>
                            </div>
                            <div class="col-lg-6">
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

                <div class="box-typical box-typical-padding">
                    <table id="ticket_data" class="table table-bordered table-striped table-vcenter js-dataTable-full">
                        <thead>
                            <tr role="row">
                                <th style="width: 5%;">N° Ticket</th>
                                <th style="width: 15%;">Categoria</th>
                                <th style="width: 15%;">Subcategoria</th>
                                <th style="width: 40%;">Titulo</th>
                                <th style="width: 5%;">Estado</th>
                                <th style="width: 5%;">Prioridad Usuario</th>
                                <th style="width: 5%;">Prioridad Defecto</th>
                                <th style="width: 10%;">Fecha creacion</th>

                                <th id="lblusucrea" style="width: 5%;">Usuario</th>
                                <th style="width: 10%;">Etiquetas</th>
                                <th style="width: 10%;">Accion</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <?php require_once("../MainModal/modal_etiquetas.php"); ?>
        </div>
        <?php require_once('../MainJs/js.php') ?>

        <script type="text/javascript" src="../ConsultarTicket/consultarticket.js"></script>
        <script type="text/javascript" src="../MainJs/etiquetas.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>


    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>