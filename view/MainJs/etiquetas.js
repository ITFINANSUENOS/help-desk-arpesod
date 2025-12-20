var current_list_tick_id = null;

function initEtiquetas() {
    console.log("Inicializando Etiquetas JS");

    // Event Handler for Create Tag Button
    $(document).on('click', '#btn_guardar_etiqueta', function (e) {
        e.preventDefault();
        var formData = new FormData($('#etiqueta_form')[0]);
        $.ajax({
            url: "../../controller/etiqueta.php?op=guardar",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response == "1") {
                    swal("Éxito", "Etiqueta creada", "success");
                    $('#etiqueta_form')[0].reset();
                    // Reload options
                    loadListEtiquetasDisponibles();
                } else {
                    swal("Error", "No se pudo crear", "error");
                }
            }
        });
    });

    // Initialize Select2 for Tags
    if ($('#ticket_etiquetas').length) {
        $('#ticket_etiquetas').select2({
            placeholder: "Agregar etiqueta...",
            tags: false,
            tokenSeparators: [','],
            templateSelection: formatEtiquetaState,
            templateResult: formatEtiquetaState,
            dropdownParent: $('#modal_crear_etiqueta')
        });

        // Select2 Select Event
        $('#ticket_etiquetas').on('select2:select', function (e) {
            var eti_id = e.params.data.id;
            var usu_id = $('#user_idx').val(); // Assuming this hidden field exists in all views
            if (!usu_id) { console.error("user_idx not found"); return; }
            if (!eti_id || !current_list_tick_id) return;

            $.post("../../controller/etiqueta.php?op=asignar", { tick_id: current_list_tick_id, eti_id: eti_id, usu_id: usu_id }, function (data) {
                console.log("Etiqueta asignada listado");
                // Reload current table if exists
                if (typeof tabla !== 'undefined') {
                    $('#ticket_data').DataTable().ajax.reload(null, false);
                }
                if ($('#historial_data').length) {
                    $('#historial_data').DataTable().ajax.reload(null, false);
                }
            });
        });

        // Select2 Unselect Event
        $('#ticket_etiquetas').on('select2:unselect', function (e) {
            var eti_id = e.params.data.id;
            if (!eti_id || !current_list_tick_id) return;

            $.post("../../controller/etiqueta.php?op=desligar", { tick_id: current_list_tick_id, eti_id: eti_id }, function (data) {
                console.log("Etiqueta desligada listado");
                if (typeof tabla !== 'undefined') {
                    $('#ticket_data').DataTable().ajax.reload(null, false);
                }
                if ($('#historial_data').length) {
                    $('#historial_data').DataTable().ajax.reload(null, false);
                }
            });
        });
    }
}

function gestionarEtiquetas(tick_id) {
    current_list_tick_id = tick_id;
    $('#lbl_ticket_id').text('#' + tick_id);
    $('#modal_crear_etiqueta').modal('show');

    // Load Data
    loadListEtiquetasDisponibles(function () {
        loadListEtiquetasAsignadas(current_list_tick_id);
    });
}

function loadListEtiquetasDisponibles(callback) {
    $.post("../../controller/etiqueta.php?op=combo", function (data) {
        $('#ticket_etiquetas').html(data);
        $('#ticket_etiquetas').trigger('change.select2');
        if (callback) callback();
    });
}

function loadListEtiquetasAsignadas(tick_id) {
    $.post("../../controller/etiqueta.php?op=listar_x_ticket", { tick_id: tick_id }, function (data) {
        var assigned = JSON.parse(data);
        var selectedIds = [];
        assigned.forEach(function (tag) {
            selectedIds.push(tag.eti_id);
        });
        $('#ticket_etiquetas').val(selectedIds).trigger('change');
    });
}

function formatEtiquetaState(state) {
    if (!state.id) { return state.text; }
    var color = $(state.element).attr('data-color');
    if (!color) color = 'secondary';

    var labelClass = "label label-" + color;
    if (color == "secondary") labelClass = "label label-default";
    if (color == "dark") labelClass = "label label-primary";

    var $state = $(
        '<span class="' + labelClass + '">' + state.text + '</span>'
    );
    return $state;
}

$(document).ready(function () {
    initEtiquetas();
});
