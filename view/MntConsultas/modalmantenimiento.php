<div id="modalmantenimiento" class="modal fade bd-example-modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="font-icon-close-2"></i>
                </button>
                <h4 class="modal-title" id="mdltitulo"></h4>
            </div>
            <form method="post" id="consulta_form">
                <div class="modal-body">
                    <input type="hidden" id="cons_id" name="cons_id">

                    <div class="form-group">
                        <label class="form-label" for="cons_nom">Nombre</label>
                        <input type="text" class="form-control" id="cons_nom" name="cons_nom" placeholder="Ej: Usuario por Cédula" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="cons_sql">Consulta SQL</label>
                        <p class="text-muted small">Use <code>?</code> como marcador de posición para el valor del campo disparador.<br>
                            Ejemplo: <code>SELECT usu_nom as nombre FROM tm_usuario WHERE usu_cedula = ?</code></p>
                        <textarea class="form-control" rows="4" id="cons_sql" name="cons_sql" placeholder="SELECT..." required></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-rounded btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" name="action" id="#" value="add" class="btn btn-rounded btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>