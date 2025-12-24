<div id="modalImportarV2" class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="font-icon-close-2"></i>
                </button>
                <h4 class="modal-title" id="mdltitulo">Importar Reporte de Flujos (V2)</h4>
            </div>
            <form method="post" id="importar_v2_form" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <strong>Advertencia:</strong> Esta importación es <b>destructiva</b> para los flujos existentes en el archivo. Si un flujo ya existe, se borrarán todos sus pasos y se recrearán según el Excel.
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-2 form-control-label" for="archivo_flujos">Archivo Excel</label>
                        <div class="col-sm-10">
                            <input type="file" id="archivo_flujos" name="archivo_flujos" class="form-control" required />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-rounded btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" name="action" class="btn btn-rounded btn-primary">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>