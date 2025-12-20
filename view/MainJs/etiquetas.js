var current_list_tick_id = null;
var tabla_etiquetas;

function initEtiquetas() {
    console.log("Inicializando Etiquetas JS");

    // Init DataTable for user tags
    initTablaEtiquetas();

    // Event Handler for Save/Update Tag Button
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
                    swal("Éxito", "Operación realizada correctamente", "success");
                    resetEtiquetaForm();
                    // Reload options and table
                    loadListEtiquetasDisponibles();
                    if (tabla_etiquetas) tabla_etiquetas.ajax.reload();
                } else {
                    swal("Error", "No se pudo realizar la operación", "error");
                }
            }
        });
    });

    // Cancel Edit Mode
    $(document).on('click', '#btn_cancelar_etiqueta', function (e) {
        resetEtiquetaForm();
    });

    // Initialize Select2 for Tags
    if ($('#ticket_etiquetas').length) {
        $('#ticket_etiquetas').select2({
            placeholder: "Agregar",
            tags: false,
            tokenSeparators: [','],
            templateSelection: formatEtiquetaState,
            templateResult: formatEtiquetaState,
            dropdownParent: $('#modal_crear_etiqueta')
        });

        // Select2 Select Event
        $('#ticket_etiquetas').on('select2:select', function (e) {
            var eti_id = e.params.data.id;
            var usu_id = $('#user_idx').val();
            if (!eti_id || !current_list_tick_id) return;

            $.post("../../controller/etiqueta.php?op=asignar", { tick_id: current_list_tick_id, eti_id: eti_id, usu_id: usu_id }, function (data) {
                console.log("Etiqueta asignada listado");
                reloadMainTables();
            });
        });

        // Select2 Unselect Event
        $('#ticket_etiquetas').on('select2:unselect', function (e) {
            var eti_id = e.params.data.id;
            if (!eti_id || !current_list_tick_id) return;

            $.post("../../controller/etiqueta.php?op=desligar", { tick_id: current_list_tick_id, eti_id: eti_id }, function (data) {
                console.log("Etiqueta desligada listado");
                reloadMainTables();
            });
        });
    }
}

function initTablaEtiquetas() {
    tabla_etiquetas = $('#tabla_etiquetas_usuario').DataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: 'rtip', // Simple layout
        "ajax": {
            url: "../../controller/etiqueta.php?op=listar_mis_etiquetas",
            type: "get",
            dataType: "json",
            error: function (e) {
                console.log(e.responseText);
            }
        },
        "bDestroy": true,
        "iDisplayLength": 5, // Small list
        "language": {
            "sProcessing": "Procesando...",
            "sUrl": "",
            "sZeroRecords": "No tienes etiquetas creadas",
            "sEmptyTable": "No tienes etiquetas creadas"
        }
    });
}

function gestionarEtiquetas(tick_id) {
    current_list_tick_id = tick_id;
    $('#lbl_ticket_id').text('#' + tick_id);
    $('#modal_crear_etiqueta').modal('show');
    resetEtiquetaForm();
    if (tabla_etiquetas) tabla_etiquetas.ajax.reload();

    // Load Data
    loadListEtiquetasDisponibles(function () {
        loadListEtiquetasAsignadas(current_list_tick_id);
    });
}

function editarEtiqueta(eti_id) {
    $.post("../../controller/etiqueta.php?op=mostrar", { eti_id: eti_id }, function (data) {
        data = JSON.parse(data);
        $('#eti_id').val(data.eti_id);
        $('#eti_nom').val(data.eti_nom);
        $('#eti_color').val(data.eti_color);
        $('#btn_guardar_etiqueta').text("Actualizar");
        $('#btn_cancelar_etiqueta').show();
    });
}

function eliminarEtiqueta(eti_id) {
    swal({
        title: "¿Estás seguro?",
        text: "La etiqueta se eliminará de tus opciones.",
        type: "warning",
        showCancelButton: true,
        confirmButtonClass: "btn-danger",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        closeOnConfirm: false
    },
        function () {
            $.post("../../controller/etiqueta.php?op=eliminar", { eti_id: eti_id }, function (data) {
                swal("Eliminado", "Etiqueta eliminada.", "success");
                if (tabla_etiquetas) tabla_etiquetas.ajax.reload();
                loadListEtiquetasDisponibles();
                // Also may need to refresh assignment if currently assigned? 
                // The DB soft delete should handle it (logic in listing will filter it out, but assignments?)
                // Assignments in table td_ticket_etiqueta still exist, but join to tm_etiqueta will fail if we filter by est=1 there.
                reloadMainTables();
            });
        });
}

function resetEtiquetaForm() {
    $('#etiqueta_form')[0].reset();
    $('#eti_id').val("");
    $('#btn_guardar_etiqueta').text("Crear");
    $('#btn_cancelar_etiqueta').hide();
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

function reloadMainTables() {
    if (typeof tabla !== 'undefined') {
        $('#ticket_data').DataTable().ajax.reload(null, false);
    }
    if ($('#historial_data').length) {
        $('#historial_data').DataTable().ajax.reload(null, false);
    }
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
