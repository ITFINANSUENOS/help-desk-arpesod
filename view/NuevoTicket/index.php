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

        <div class="page-content">
            <div class="container-fluid">

                <header class="section-header">
                    <div class="tbl">
                        <div class="tbl-row">
                            <div class="tbl-cell">
                                <h3>Nuevo ticket</h3>
                                <ol class="breadcrumb breadcrumb-simple">
                                    <li><a href="#">Home</a></li>
                                    <li class="active">Nuevo ticket</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </header>
            </div>

            <div class="box-typical box-typical-padding">
                <p>
                    Desde esta ventana podras crear nuevos tickets.
                </p>


                <div class="row">
                    <form method="post" id="ticket_form" enctype="multipart/form-data">
                        <input type="hidden" id="usu_id" name="usu_id" value="<?php echo $_SESSION["usu_id"] ?>">
                        <input type="hidden" id="user_cargo_id" value="<?php echo $_SESSION['car_id']; ?>">
                        <input type="hidden" id="es_nacional" value="<?php echo $_SESSION['es_nacional']; ?>">
                        <div class="col-lg-3">
                            <fieldset class="form-group semibold">
                                <label class="form-label" for="tick_titulo">Titulo</label>
                                <input type="text" class="form-control" id="tick_titulo" name="tick_titulo" placeholder="Titulo del ticket">
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="dp_id">Departamento</label>
                                <select class="form-control" id="dp_id" name="dp_id" placeholder="Seleccione un departamento">
                                </select>
                            </fieldset>
                        </div>
                        <input type="hidden" id="cat_id" name="cat_id">
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="cats_id">Subcategoria</label>
                                <select class="form-control" id="cats_id" name="cats_id" placeholder="Seleccione una subcategoria">
                                </select>
                            </fieldset>
                        </div>
                        <div class="col-lg-3" id="panel_condicion_inicio" style="display: none;">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="paso_inicio_id">Condición de Inicio</label>
                                <select class="form-control" id="paso_inicio_id" name="paso_inicio_id">
                                </select>
                            </fieldset>
                        </div>
                        <div class="col-lg-3" id="panel_asignacion_manual" style="display: none;">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="usu_asig">Asignar Ticket a:</label>
                                <select id="usu_asig" name="usu_asig" class="form-control"></select>
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="emp_id">Empresa</label>
                                <select class="form-control" id="emp_id" name="emp_id" placeholder="Seleccione una empresa">
                                </select>
                            </fieldset>
                        </div>
                        <div class="col-lg-3" id="regional_field" style="display: none;">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="reg_id">Regional</label>
                                <select class="form-control" id="reg_id" name="reg_id" placeholder="Seleccione una regional">
                                </select>
                            </fieldset>
                        </div>
                        <div class="col-lg-12" id="campos_plantilla_container" style="display: none;">
                            <fieldset class="form-group">
                                <label class="form-label semibold">Campos de Plantilla</label>
                                <div class="row" id="campos_plantilla_inputs">
                                    <!-- Inputs added dynamically -->
                                </div>
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="cat_id">Documento adicional</label>
                                <input type="file" name="files[]" id="files" class="form-control" multiple>
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="pd_id">Pioridad</label>
                                <select class="form-control" id="pd_id" name="pd_id" placeholder="Seleccione la prioridad">
                                </select>
                            </fieldset>
                        </div>
                        <div class="col-lg-12">
                            <fieldset class="form-group semibold">
                                <label class="form-label" for="tick_descrip">Descripcion</label>
                                <div class="summernote-theme-1">
                                    <textarea id="tick_descrip" name="tick_descrip" class="summernote" name="name"></textarea>
                                </div>
                            </fieldset>
                        </div>

                        <div class="col-lg-5">
                            <div class="d-flex align-items-end">
                                <div id="error_procesodiv" class="form-group mr-3 hidden">
                                    <div class="checkbox-toggle">
                                        <input type="hidden" name="error_proceso" value="0">
                                        <input type="checkbox" id="error_proceso" name="error_proceso" value="1">
                                        <label for="error_proceso" class="form-label semibold">Error de Proceso</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="action" value="add" id="btnguardar" class="btn btn-inline">Guardar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>


        </div>
        <?php require_once('../MainJs/js.php') ?>

        <script type="text/javascript" src="..//NuevoTicket/nuevoticket.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>


    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>