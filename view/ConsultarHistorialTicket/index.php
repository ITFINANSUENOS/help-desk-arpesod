<?php
require_once('../../config/conexion.php');
if (isset($_SESSION["usu_id"])) {
?>
    <!DOCTYPE html>
    <html>
    <?php require_once('../MainHead/head.php') ?>
    <title>Consultar Tickets con Historial</title>
    </head>

    <body class="with-side-menu">

        <?php require_once('../MainHeader/header.php') ?>
        <div class="mobile-menu-left-overlay"></div>
        <?php require_once('../MainNav/nav.php') ?>

        <!-- Contenido -->
        <div class="page-content">
            <div class="container-fluid">
                <header class="section-header">
                    <div class="tbl">
                        <div class="tbl-row">
                            <div class="tbl-cell">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <h3>Tickets con Historial de Asignación</h3>
                                        <ol class="breadcrumb breadcrumb-simple">
                                            <li><a href="..\Home\">Home</a></li>
                                            <li class="active">Tickets con Historial</li>
                                        </ol>
                                    </div>
                                    <div>
                                        <a href="../../export_desempeno.php" class="btn btn-success rounded">
                                            <i class="fa fa-file-excel-o"></i> Descargar Reporte Desempeño
                                        </a>
                                    </div>
                                </div>
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
                    <table id="historial_data" class="table table-bordered table-striped table-vcenter js-dataTable-full">
                        <thead>
                            <tr>
                                <th style="width: 5%;">N° Ticket</th>
                                <th style="width: 15%;">Subategoria</th>
                                <th style="width: 40%;">Título</th>
                                <th style="width: 10%;">Estado</th>
                                <th style="width: 10%;">Fecha Creación</th>
                                <th style="width: 15%;">Asignado a</th>
                                <th style="width: 10%;">Etiquetas</th>
                                <th style="width: 10%;">Ver</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <?php require_once("../MainModal/modal_etiquetas.php"); ?>
        </div>



        <?php require_once('../MainJs/js.php') ?>
        <script type="text/javascript" src="../MainJs/etiquetas.js"></script>
        <script type="text/javascript" src="consultarhistorialticket.js"></script>

    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>