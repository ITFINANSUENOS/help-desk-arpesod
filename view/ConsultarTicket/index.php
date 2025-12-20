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
                                <th id="lblusertable" style="width: 5%;">Soporte</th>
                                <th id="lblusucrea" style="width: 5%;">Usuario</th>
                                <th style="width: 10%;">Etiquetas</th>
                                <th style="width: 10%;">Accion</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <!-- Modal Para Gestionar Etiquetas (Reutilizado) -->
            <div class="modal fade" id="modal_crear_etiqueta" tabindex="-1" role="dialog" aria-labelledby="modal_crear_etiqueta_label" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modal_crear_etiqueta_label">Gestionar Etiquetas</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <!-- Modal Body for Select/Create -->
                        <div class="modal-body">
                            <form id="etiqueta_form">
                                <h6>Nueva Etiqueta</h6>
                                <div class="form-group">
                                    <label for="eti_nom">Nombre</label>
                                    <input type="text" class="form-control" id="eti_nom" name="eti_nom" placeholder="Ej: Urgente" required>
                                </div>
                                <div class="form-group">
                                    <label for="eti_color">Color</label>
                                    <select class="form-control" id="eti_color" name="eti_color" required>
                                        <option value="primary">Azul (Primary)</option>
                                        <option value="secondary">Gris (Secondary)</option>
                                        <option value="success">Verde (Success)</option>
                                        <option value="danger">Rojo (Danger)</option>
                                        <option value="warning">Amarillo (Warning)</option>
                                        <option value="info">Celeste (Info)</option>
                                        <option value="dark">Oscuro (Dark)</option>
                                    </select>
                                </div>
                                <button type="button" id="btn_guardar_etiqueta" class="btn btn-primary btn-sm">Crear</button>
                            </form>
                            <hr>
                            <h6>Asignar Etiquetas al Ticket <span id="lbl_ticket_id"></span></h6>
                            <div class="form-group pb-1">
                                <div class="input-group">
                                    <select class="select2" id="ticket_etiquetas" name="ticket_etiquetas[]" multiple="multiple" style="width: 100%;">
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                /* Override Select2 Styling for Tags */
                .select2-selection__choice {
                    background-color: transparent !important;
                    border: none !important;
                    position: relative !important;
                    padding: 0 !important;
                    margin-right: 5px !important;
                }

                /* The Label (Text) */
                .select2-selection__choice .label {
                    font-size: 100% !important;
                    display: inline-block !important;
                    padding: 6px 15px 6px 8px !important;
                    /* Right padding space for X */
                    position: relative !important;
                    border-radius: 4px !important;
                }

                /* The X (Remove Button) - Top Right & Small */
                .select2-selection__choice__remove {
                    position: absolute !important;
                    top: 1px !important;
                    right: 2px !important;
                    z-index: 10 !important;
                    color: rgba(255, 255, 255, 0.7) !important;
                    font-size: 10px !important;
                    /* Smaller */
                    font-weight: bold !important;
                    width: auto !important;
                    height: auto !important;
                    line-height: 1 !important;
                    margin: 0 !important;
                    float: none !important;
                    background-color: transparent !important;
                }

                .select2-selection__choice__remove:hover {
                    color: #fff !important;
                    cursor: pointer !important;
                }
            </style>

        </div>
        <?php require_once('../MainJs/js.php') ?>

        <script type="text/javascript" src="../ConsultarTicket/consultarticket.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>


    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>