<?php
require_once('../../config/conexion.php');
if (isset($_SESSION["usu_id"])) {
?>
    <!DOCTYPE html>
    <html>
    <?php require_once('../MainHead/head.php') ?>
    <title>Detalle ticket</title>
    </head>

    <body class="with-side-menu">

        <?php require_once('../MainHeader/header.php') ?>

        <div class="mobile-menu-left-overlay"></div>

        <?php require_once('../MainNav/nav.php') ?>

        <!-- contenido -->
        <div class="page-content">
            <div class="container-fluid">
                <style>
                    /* Hide SLA info in the main detail view conversation */
                    #lbldetalle .sla-info,
                    #lbldetalle .activity-line-item .sla-info {
                        display: none !important;
                    }
                </style>
                <style media="print">
                    @media print {
                        @page {
                            margin: 1.5cm;
                        }

                        body {
                            background: #fff !important;
                            font-family: Arial, sans-serif;
                        }

                        .site-header,
                        .side-menu,
                        .mobile-menu-left-overlay,
                        .breadcrumb,
                        #btnprint,
                        #boxdetalleticket,
                        #panel_respuestas_rapidas,
                        #panel_checkbox_flujo,
                        .modal,
                        .tbl-cell .label,
                        .section-header .tbl-cell span {
                            display: none !important;
                        }

                        /* Except Title and some status info if needed */
                        .section-header {
                            padding-bottom: 10px;
                            border-bottom: 2px solid #eee;
                            margin-bottom: 20px;
                        }

                        .page-content {
                            padding: 0 !important;
                            margin: 0 !important;
                            width: 100% !important;
                            background: #fff !important;
                        }

                        .container-fluid {
                            padding: 0 !important;
                        }

                        /* Layout adjustments */
                        .box-typical {
                            border: none !important;
                            box-shadow: none !important;
                            margin-bottom: 20px !important;
                        }

                        /* Inputs as text */
                        input.form-control,
                        textarea.summernote,
                        .summernote-theme-1 {
                            border: none !important;
                            background-color: transparent !important;
                            padding: 0 !important;
                            font-size: 14px;
                            color: #000 !important;
                            height: auto !important;
                            box-shadow: none !important;
                        }

                        fieldset {
                            margin-bottom: 10px !important;
                        }

                        label.form-label {
                            font-weight: bold;
                            color: #555;
                            margin-bottom: 2px;
                        }

                        /* Hide Summernote toolbar and popovers */
                        .note-toolbar,
                        .note-statusbar,
                        .note-popover,
                        .note-control-selection,
                        .note-handle,
                        .note-resizebar {
                            display: none !important;
                        }

                        .note-editor {
                            border: none !important;
                        }

                        /* Activity feed (Conversation) */
                        .activity-line {
                            margin-top: 30px;
                        }

                        .activity-line-item {
                            border-bottom: 1px solid #eee;
                            padding-bottom: 15px;
                            margin-bottom: 15px;
                            page-break-inside: avoid;
                        }

                        .activity-line-item-user-name {
                            font-weight: bold;
                            font-size: 14px;
                            color: #333;
                        }

                        .activity-line-item-user-photo {
                            display: none;
                        }

                        /* Optional: Hide avatars for cleaner print */
                        .time {
                            color: #999;
                            font-size: 12px;
                            float: right;
                        }

                        /* Ensure full text visibility */
                        .summernote,
                        .note-editable {
                            height: auto !important;
                            overflow: visible !important;
                        }
                    }
                </style>
                <header class="section-header">
                    <div class="tbl">
                        <div class="tbl-row">
                            <div class="tbl-cell">
                                <h3 id="lblticketid"></h3>
                                <div id="print_info_header" style="display:none; margin-bottom: 10px;">
                                    <strong>Estado:</strong> <span id="print_lbltickestado"></span> |
                                    <strong>Fecha:</strong> <span id="print_lblfechacrea"></span> |
                                    <strong>Usuario:</strong> <span id="print_lblnomusuario"></span>
                                </div>
                                <span id="lbltickestado"></span>
                                <span id="lblestado_tiempo"></span>
                                <span class="label label-primary" id="lblnomusuario"></span>
                                <span class="label label-default" id="lblfechacrea"></span>
                                <span id="lblprioridad"></span>
                                <ol class="breadcrumb breadcrumb-simple">
                                    <li><a href="..\Home\">Home</a></li>
                                    <li><a href="..\ConsultarTicket\">Consultar ticket</a></li>
                                    <li class="active">Detalle ticket</li>
                                </ol>
                                <button type="button" class="btn btn-inline btn-secondary pull-right" id="btnprint" style="margin-top: -5px;">
                                    <i class="fa fa-print"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </header>

                <div id="panel_linea_tiempo" class="box-typical box-typical-padding" style="display:none;">
                    <h5 class="m-t-lg with-border">Progreso del Flujo de Trabajo</h5>
                    <div class="mermaid">

                    </div>
                </div>

                <div id="panel_guia_paso" class="alert alert-info" role="alert" style="display: none;">
                    <div class="card-body">
                        <h4 class="alert-heading" id="guia_paso_nombre"></h4>
                        <p>
                            <strong>Descripción de la Tarea:</strong> Tienes <strong id="guia_paso_tiempo"></strong> día(s) hábiles para completar este paso.
                        </p>
                        <hr>
                        <p class="mb-0">
                            A continuación, en el editor de texto, encontrarás una plantilla o guía con las instrucciones para esta tarea.
                        </p>
                        <div id="paso_attachment_display"></div>
                    </div>
                </div>

                <div class="box-typical box-typical-padding">
                    <div class="row">

                        <div class="col-lg-12">
                            <fieldset class="form-group semibold">
                                <label class="form-label" for="tick_titulo">Titulo</label>
                                <input type="text" class="form-control" id="tick_titulo" name="tick_titulo" readonly>
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="cat_id">Categoria</label>
                                <input class="form-control" id="cat_id" name="cat_id" readonly>
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="cats_id">Subcategoria</label>
                                <input class="form-control" id="cats_id" name="cats_id" readonly>
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="emp_id">Empresa</label>
                                <input class="form-control" id="emp_id" name="emp_id" readonly>
                            </fieldset>
                        </div>
                        <div class="col-lg-3">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="dp_id">Departamento</label>
                                <input class="form-control" id="dp_id" name="dp_id" readonly>
                            </fieldset>
                        </div>
                        <div class="col-lg-12">
                            <fieldset class="form-group pb-1">
                                <label class="form-label semibold" for="ticket_etiquetas">Etiquetas</label>
                                <div class="input-group">
                                    <select class="select2" id="ticket_etiquetas" name="ticket_etiquetas[]" multiple="multiple" style="width: 100%;">
                                    </select>
                                    <span class="input-group-btn" style="vertical-align: top;">
                                        <button class="btn btn-inline btn-primary-outline" type="button" id="btn_gestionar_etiquetas" title="Crear/Gestionar Etiquetas">
                                            <i class="fa fa-cog"></i>
                                        </button>
                                    </span>
                                </div>
                            </fieldset>
                        </div>
                        <div class="col-lg-12">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="tick_titulo">Documentos adicionales</label>
                                <table id="documentos_data" class="table table-bondered table-striped table-vcenter js-datatable-full">
                                    <thead>
                                        <tr>
                                            <th style="width: 90%;">Nombre</th>
                                            <th class="text-center" style="width: 10%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Los datos se llenaran mediante AJAX -->
                                    </tbody>
                                </table>
                            </fieldset>
                        </div>
                        <div class="col-lg-12">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="tick_descripusu">Descripcion</label>
                                <div class="summernote-theme-1">
                                    <textarea id="tickd_descripusu" name="tickd_descripusu" class="summernote" name="name" readonly></textarea>
                                </div>
                            </fieldset>
                        </div>
                    </div><!--.row-->

                </div>

                <section class="activity-line" id="lbldetalle">

                </section>


                <div id="boxdetalleticket" class="box-typical box-typical-padding">
                    <p>
                        Ingrese su duda o consulta
                    </p>


                    <div class="row">
                        <form method="post" id="detalle_form">
                            <div class="col-lg-12">

                                <input type="hidden" id="selected_siguiente_paso_id" name="selected_siguiente_paso_id">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <fieldset class="form-group">
                                            <label class="form-label semibold" for="cat_id">Documento adicional</label>
                                            <input type="file" name="files[]" id="fileElem" class="form-control" multiple>
                                        </fieldset>
                                    </div>
                                </div>

                                <div class="box-typical box-typical-padding" id="panel_respuestas_rapidas">
                                    <h5 class="m-t-lg with-border">Registrar Evento</h5>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="form-label" for="fast_answer_id">Tipo de Evento (Respuesta Rápida)</label>
                                                <select class="select2" id="fast_answer_id" name="fast_answer_id" data-placeholder="Seleccione un tipo de evento...">
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="form-label" for="error_descrip">Descripción Detallada del Evento</label>
                                                <textarea class="form-control" id="error_descrip" name="error_descrip" rows="4" placeholder="Añada aquí cualquier detalle relevante sobre el evento que está registrando..."></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <button type="button" id="btn_registrar_evento" class="btn btn-primary">
                                                <i class="fa fa-plus"></i> Registrar Evento en Historial
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <fieldset class="form-group semibold">
                                    <label class="form-label" for="tickd_descrip">Descripcion</label>
                                    <div class="summernote-theme-1">
                                        <textarea id="tickd_descrip" name="tickd_descrip" class="summernote" name="name"></textarea>
                                    </div>
                                </fieldset>

                                <div id="panel_campos_plantilla" class="row" style="display:none; margin-top: 15px; border-top: 1px solid #d8d8d8; padding-top: 15px;">
                                    <div class="col-lg-12">
                                        <h5 class="m-t-lg with-border">Campos Requeridos para el Siguiente Paso</h5>
                                    </div>
                                    <div id="campos_plantilla_inputs" class="col-lg-12 row">
                                        <!-- Inputs dinámicos aquí -->
                                    </div>
                                </div>

                                <div id="panel_checkbox_flujo" class="form-group" style="display: none;">
                                    <div class="checkbox-toggle">
                                        <input type="checkbox" id="checkbox_avanzar_flujo" name="avanzar_flujo">
                                        <label for="checkbox_avanzar_flujo" class="form-label semibold">
                                            Completar este paso y avanzar al siguiente flujo
                                        </label>
                                    </div>
                                </div>
                                <div id="panel_seleccion_usuario" class="box-typical box-typical-padding" style="display: none;">
                                    <h5 class="m-t-lg with-border">Seleccionar Usuario para Asignación</h5>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="form-label" for="usuario_seleccionado">Asignar a:</label>
                                                <select class="select2" id="usuario_seleccionado" name="usuario_seleccionado" data-placeholder="Buscar por correo electrónico...">
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div style="display: inline-flex;">
                            <div class="col-lg-12">
                                <button type="button" id="btnenviar" class="btn btn-inline">Enviar</button>
                            </div>
                            <div class="col-lg-12">
                                <button type="button" id="btncerrarticket" class="btn btn-danger">Cerrar ticket</button>
                            </div>
                            <div class="col-lg-12">
                                <button type="button" id="btncrearnovedad" class="btn btn-inline btn-warning">Crear Novedad</button>
                            </div>
                            <div class="col-lg-12">
                                <button type="button" id="btnresolvernovedad" class="btn btn-inline btn-success" style="display: none;">Resolver Novedad</button>
                            </div>
                            <div class="col-lg-12">
                                <button type="button" id="btn_despacho_masivo" class="btn btn-inline btn-primary" style="display: none;">Despacho Masivo</button>
                            </div>
                        </div>
                        <div class="box-typical box-typical-padding" id="panel_paralelo" style="display: none;">
                            <h5 class="m-t-lg with-border">Estado de Tareas Paralelas</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Agente</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th>Comentario</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla_paralelo_body">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="box-typical box-typical-padding" id="panel_aprobacion" style="display: none;">
                            <h5 class="m-t-lg with-border">Acción Requerida: Aprobar o Rechazar</h5>
                            <p>
                                Este paso del flujo requiere su aprobación para continuar. Puede aprobar para avanzar al siguiente paso o rechazar para devolverlo al paso anterior.
                            </p>
                            <button type="button" id="btn_aprobar_paso" class="btn btn-rounded btn-success">
                                <i class="fa fa-check"></i> Aprobar
                            </button>
                            <button type="button" id="btn_rechazar_paso" class="btn btn-rounded btn-danger">
                                <i class="fa fa-times"></i> Rechazar
                            </button>
                        </div>
                    </div><!--.row-->

                </div>


            </div>
        </div>
        <!-- Modal para seleccionar el siguiente paso -->
        <div class="modal fade" id="modal_seleccionar_paso" tabindex="-1" role="dialog" aria-labelledby="modal_seleccionar_paso_label" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal_seleccionar_paso_label">Seleccionar Siguiente Paso</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Hay múltiples opciones para el siguiente paso. Por favor, selecciona uno:</p>
                        <div class="form-group">
                            <label for="select_siguiente_paso">Paso:</label>
                            <select class="form-control" id="select_siguiente_paso" name="select_siguiente_paso">
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn_confirmar_paso_seleccionado">Seleccionar</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal para la nota de cierre -->
        <div class="modal fade" id="modal_nota_cierre" tabindex="-1" role="dialog" aria-labelledby="modal_nota_cierre_label" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal_nota_cierre_label">Añadir Nota de Cierre</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Por favor, escribe una nota para el cierre de este ticket. Esta nota será visible para el usuario creador y otros usuarios relevantes.</p>
                        <div class="summernote-theme-1">
                            <textarea id="nota_cierre_summernote" name="nota_cierre_summernote" class="summernote"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label semibold" for="cierre_files">Documentos adjuntos</label>
                            <input type="file" name="cierre_files[]" id="cierre_files" class="form-control" multiple>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn_confirmar_cierre">Confirmar Cierre</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal para crear novedad -->
        <div class="modal fade" id="modal_crear_novedad" tabindex="-1" role="dialog" aria-labelledby="modal_crear_novedad_label" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal_crear_novedad_label">Crear Novedad</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="novedad_form">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="descripcion_novedad">Descripción de la Novedad</label>
                                <textarea class="form-control" id="descripcion_novedad" name="descripcion_novedad" rows="4" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="novedad_files">Adjuntar Archivos</label>
                                <input type="file" class="form-control-file" id="novedad_files" name="files[]" multiple>
                                <small class="text-muted">Máximo 2MB por archivo. Total máximo 8MB.</small>
                            </div>
                            <div class="form-group">
                                <label for="usu_asig_novedad">Asignar a</label>
                                <select class="select2" id="usu_asig_novedad" name="usu_asig_novedad" data-placeholder="Seleccione un usuario" required>
                                    <!-- Opciones de usuario se cargarán dinámicamente -->
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Novedad</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Modal Para Gestionar Etiquetas -->
        <div class="modal fade" id="modal_crear_etiqueta" tabindex="-1" role="dialog" aria-labelledby="modal_crear_etiqueta_label" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal_crear_etiqueta_label">Gestionar Etiquetas</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="etiqueta_form">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="eti_nom">Nombre de la Etiqueta</label>
                                <input type="text" class="form-control" id="eti_nom" name="eti_nom" placeholder="Ej: Urgente, Revisión, Pendiente..." required>
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
                            <hr>
                            <h6>Etiquetas Existentes</h6>
                            <ul id="lista_etiquetas_existentes" class="list-group">
                                <!-- Loaded via JS -->
                            </ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar Nueva Etiqueta</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Modal Selección Excel -->
        <div class="modal fade" id="modal_seleccionar_excel" tabindex="-1" role="dialog" aria-labelledby="modal_seleccionar_excel_label" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal_seleccionar_excel_label">Seleccionar Archivo de Despacho</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Cargue el archivo Excel para realizar el despacho masivo:</p>
                        <div class="form-group">
                            <label for="file_despacho_masivo">Archivo Excel (.xlsx, .xls):</label>
                            <input type="file" class="form-control-file" id="file_despacho_masivo" name="file_despacho_masivo" accept=".xlsx, .xls">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn_confirmar_despacho">Procesar Despacho</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal Firma -->
        <div class="modal fade" id="modalFirma" tabindex="-1" role="dialog" aria-labelledby="modalFirmaLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalFirmaLabel">Firma Digital</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Por favor, firme en el recuadro de abajo para completar la tarea.</p>
                        <div class="wrapper" style="border: 1px solid #ccc; position: relative; height: 200px; width: 100%;">
                            <canvas id="signature-pad" class="signature-pad" style="width: 100%; height: 100%;"></canvas>
                        </div>
                        <div class="form-group mt-3">
                            <label for="upload_signature">O subir imagen de firma (PNG):</label>
                            <input type="file" class="form-control-file" id="upload_signature" accept="image/png">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info pull-left" id="btnUsarFirmaPerfil">Usar Firma del Perfil</button>
                        <button type="button" class="btn btn-secondary" id="btnLimpiarFirma">Limpiar</button>
                        <button type="button" class="btn btn-primary" id="btnGuardarFirma">Guardar y Terminar</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

        <?php require_once('../MainJs/js.php') ?>
        <script type="text/javascript" src="../DetalleTicket/detalleticket.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>


    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>