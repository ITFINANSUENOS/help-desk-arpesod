<div id="modalCargarExcel" class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="font-icon-close-2"></i>
                </button>
                <h4 class="modal-title" id="mdltitulo_excel">Cargar Datos Excel para Autocompletado</h4>
            </div>
            <form method="post" id="excel_form" enctype="multipart/form-data">
                <div class="modal-body">
                    <fieldset class="form-group">
                        <label class="form-label" for="flujo_id_excel">Asociar a Flujo (Opcional)</label>
                        <select class="form-control select2" id="flujo_id_excel" name="flujo_id" style="width: 100%;">
                            <!-- Populated by JS -->
                        </select>
                    </fieldset>
                    <div class="form-group">
                        <label class="form-label" for="excel_file">Archivo Excel (.xlsx, .xls)</label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx, .xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-rounded btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" name="action" id="#" value="add" class="btn btn-rounded btn-primary">Cargar</button>
                </div>
            </form>
        </div>
    </div>
</div>