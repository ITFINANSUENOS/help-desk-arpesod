<div id="modal_crear_etiqueta" class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="font-icon-close-2"></i>
                </button>
                <h4 class="modal-title" id="mdltitulo">Gestionar Etiquetas</h4>
            </div>
            <div class="modal-body">
                <form method="post" id="etiqueta_form">
                    <input type="hidden" id="eti_id" name="eti_id">
                    <h6>Gestión de Etiquetas</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="eti_nom">Nombre</label>
                                <input type="text" class="form-control" id="eti_nom" name="eti_nom" placeholder="Ej: Urgente" required>
                            </div>
                        </div>
                        <div class="col-md-4">
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
                        </div>
                        <div class="col-md-2">
                            <div class="form-group" style="padding-top: 25px;">
                                <button type="button" id="btn_guardar_etiqueta" class="btn btn-primary btn-sm btn-block">Guardar</button>
                                <button type="button" id="btn_cancelar_etiqueta" class="btn btn-secondary btn-sm btn-block" style="display:none; margin-top:5px;">Cancelar</button>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <table id="tabla_etiquetas_usuario" class="table table-bordered table-striped table-vcenter js-dataTable-full" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 70%;">Etiqueta</th>
                                    <th style="width: 15%;"></th>
                                    <th style="width: 15%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
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