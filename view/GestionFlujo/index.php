<?php
require_once('../../config/conexion.php');
if (isset($_SESSION["usu_id"])) {
?>
    <!DOCTYPE html>
    <html>
    <?php require_once('../MainHead/head.php') ?>
    <title>Gestion de flujo</title>
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
                                <h3>Gestion de flujo</h3>
                                <ol class="breadcrumb breadcrumb-simple">
                                    <li><a href="..\Home\">Home</a></li>
                                    <li><a href="#">Gestion</a></li>
                                    <li><a href="#">Gestion flujos</a></li>
                                    <li class="active">Flujos</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </header>
                <div class="box-typical box-typical-padding">
                    <button type="button" id="btn_cargue_masivo" class="btn btn-inline btn-success" data-toggle="modal" data-target="#modalCargueMasivo">
                        <i class="fa fa-upload"></i> Cargue Masivo
                    </button>
                    <a href="../../export_flujos.php" target="_blank" class="btn btn-inline btn-secondary">
                        <i class="fa fa-download"></i> Descargar Reporte
                    </a>
                    <button type="button" id="btn_importar_v2" class="btn btn-inline btn-warning-outline" data-toggle="modal" data-target="#modalImportarV2">
                        <i class="fa fa-upload"></i> Importar (V2)
                    </button>
                    <button type="button" id="btnnuevoflujo" class="btn btn-inline btn-primary">Nuevo flujo</button>
                    <table id="flujo_data" class="table table-bordered table-striped table-vcenter js-dataTable-full">
                        <thead>
                            <tr role="row">
                                <th style="width: 25%;">Subcategoria</th>
                                <th style="width: 2%;">Editar</th>
                                <th style="width: 2%;">Eliminar</th>
                                <th style="width: 2%;">Ver</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <?php require_once('../GestionFlujo/modalnuevoflujo.php') ?>
        <?php require_once('../GestionFlujo/modalimportar_v2.php') ?>
        <?php require_once('../MainJs/js.php') ?>

        <script type="text/javascript" src="../GestionFlujo/gestionflujo.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>


    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>