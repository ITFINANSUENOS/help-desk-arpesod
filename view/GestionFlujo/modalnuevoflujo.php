<div class="modal fade bd-example-modal-lg"
    id="modalnuevoflujo"
    tabindex="-1"
    role="dialog"
    aria-labelledby="myLargeModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="font-icon-close-2"></i>
                </button>
                <h4 class="modal-title" id="mdltitulo"></h4>
            </div>
            <form method="post" id="flujo_form" enctype="multipart/form-data">
                <div class="modal-body">

                    <input type="hidden" id="flujo_id" name="flujo_id">
                    <fieldset class="form-group">
                        <label class="form-label semibold" for="cat_id">Categoria</label>
                        <select class="form-control" id="cat_id" name="cat_id" placeholder="Seleccione una categoria" required>
                        </select>
                    </fieldset>
                    <fieldset class="form-group">
                        <label class="form-label semibold" for="cats_id">Subcategoria</label>
                        <select class="form-control" id="cats_id" name="cats_id" placeholder="Seleccione una categoria" required>
                        </select>
                    </fieldset>
                    <fieldset class="form-group">
                        <label class="form-label semibold" for="usu_id_observador">Observador (Notificaciones)</label>
                        <select class="form-control select2" id="usu_id_observador" name="usu_id_observador[]" multiple="multiple">
                            <option value="">Ninguno</option>
                        </select>
                    </fieldset>
                    <fieldset class="form-group">
                        <label class="form-label semibold" for="flujo_nom_adjunto">Documento Plantilla (PDF) - Por Defecto</label>
                        <input type="file" class="form-control" id="flujo_nom_adjunto" name="flujo_nom_adjunto" accept=".pdf">
                        <div id="flujo_attachment_display"></div>
                    </fieldset>

                    <div class="row">
                        <div class="col-md-12">
                            <h5 class="semibold">Plantillas por Empresa (Opcional)</h5>
                            <p class="text-muted">Si se define una plantilla para una empresa, esta tendrá prioridad sobre la plantilla por defecto.</p>
                            <table class="table table-bordered table-hover" id="tabla_plantillas_empresa">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Empresa</th>
                                        <th style="width: 40%;">Plantilla (PDF)</th>
                                        <th style="width: 20%;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Filas dinámicas -->
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-success" id="btn_agregar_plantilla_empresa">
                                <i class="fa fa-plus"></i> Agregar Plantilla por Empresa
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-rounded btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" name="action" id="#" value="add" class="btn btn-rounded btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade"
    id="modalCargueMasivo"
    tabindex="-1"
    role="dialog"
    aria-labelledby="cargueMasivoLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <form action="../../cargues/cargueflujos.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="sheet_name" value="Flujos">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                        <i class="font-icon-close-2"></i>
                    </button>
                    <h4 class="modal-title" id="cargueMasivoLabel">Cargue Masivo de Flujos</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="archivo_flujos">Seleccionar Archivo Excel</label>
                        <p class="form-text text-muted">El archivo debe tener las columnas: NOMBRE_FLUJO, SUBCATEGORIA_ASOCIADA, REQUIERE_APROBACION_JEFE</p>
                        <input type="file" name="archivo_flujos" id="archivo_flujos" class="form-control" accept=".xlsx, .xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-rounded btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-rounded btn-primary">Subir Archivo</button>
                </div>
            </div>
        </form>
    </div>
</div>