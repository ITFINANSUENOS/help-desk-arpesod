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
                    <table id="ticket_data" class="table table-bordered table-striped table-vcenter js-dataTable-full">
                        <thead>
                            <tr role="row">
                                <th style="width: 5%;">N° Ticket</th>
                                <th style="width: 10%;">Categoria</th>
                                <th style="width: 10%;">Subcategoria</th>
                                <th style="width: 15%;">Titulo</th>
                                <th style="width: 25%;">Detalle Error</th>
                                <th style="width: 10%;">Estado</th>
                                <th style="width: 10%;">Fecha Creacion</th>
                                <th style="width: 10%;">Asignado A</th>
                                <th style="width: 5%;">Accion</th>
                            </tr>
                        </thead>
                    </table>
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