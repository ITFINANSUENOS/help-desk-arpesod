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
                                <h3>Consultar ticket</h3>
                                <ol class="breadcrumb breadcrumb-simple">
                                    <li><a href="..\Home\">Home</a></li>
                                    <li class="active">Consultar ticket</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </header>
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-lg-4">
                        <div class="input-group">
                            <input type="text" class="form-control" id="custom_search" placeholder="Buscar por algo en especifico">
                            <span class="input-group-btn">
                                <button class="btn btn-primary" type="button" id="btn_search">
                                    <i class="fa fa-search"></i> Buscar
                                </button>
                            </span>
                        </div>
                    </div>
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
                                <th style="width: 5%;">Soporte</th>
                                <th style="width: 10%;">Etiquetas</th>
                                <th style="width: 10%;">Accion</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <?php require_once('../MainJs/js.php') ?>

        <script type="text/javascript" src="../ConsultarTicketAgentes/consultarticketagentes.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>


    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>