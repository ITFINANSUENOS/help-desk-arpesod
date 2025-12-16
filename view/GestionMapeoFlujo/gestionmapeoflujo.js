var tabla;

function init() {
    $("#flujomapeo_form").on("submit", function (e) {
        guardaryeditar(e);
    });

    // Validar Cargue Masivo
    $('#modalCargueMasivo form').on('submit', function (e) {
        var input = $(this).find('input[type="file"]');
        var maxFileSize = 2 * 1024 * 1024; // 2MB

        if (input.length > 0 && input[0].files.length > 0) {
            if (input[0].files[0].size > maxFileSize) {
                swal("Error", "El archivo supera el límite de 2MB.", "error");
                e.preventDefault();
                return false;
            }
        }
    });
}

function guardaryeditar(e) {
    e.preventDefault();
    var formData = new FormData($("#flujomapeo_form")[0]);
    $.ajax({
        url: "../../controller/flujomapeo.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function () {
            $('#flujomapeo_form')[0].reset();
            // Reseteamos todos los combos de Select2
            $('#cat_id').val(null).trigger('change');
            $('#cats_id').val(null).trigger('change');
            $('#cats_id').val(null).trigger('change');
            $('#creador_car_ids').val(null).trigger('change');
            $('#creador_per_ids').val(null).trigger('change');
            $('#asignado_car_ids').val(null).trigger('change');
            $("#modalnuevoflujomapeo").modal('hide');
            $('#flujomapeo_data').DataTable().ajax.reload();
            swal("¡Correcto!", "Regla guardada exitosamente.", "success");
        }
    });
}

$(document).ready(function () {
    // --- 1. Inicializar Select2 ---
    // --- 1. Inicializar Select2 ---
    $('#cat_id, #cats_id').select2({ dropdownParent: $('#modalnuevoflujomapeo'), placeholder: "Seleccione" });
    $('#creador_car_ids, #asignado_car_ids, #creador_per_ids').select2({ dropdownParent: $('#modalnuevoflujomapeo'), placeholder: "Seleccione uno o varios", multiple: true });

    // --- 2. Cargar combos iniciales ---
    // Carga la lista de categorías padre
    $.post("../../controller/categoria.php?op=combocat", function (data) {
        $('#cat_id').html(data);
    });

    // Carga la lista de todos los cargos
    $.post("../../controller/flujomapeo.php?op=combo_cargos", function (data) {
        $('#creador_car_ids').html(data);
        $('#asignado_car_ids').html(data);
    });

    // Carga la lista de perfiles
    $.post("../../controller/perfil.php?op=combo", function (data) {
        $('#creador_per_ids').html(data);
    });

    // --- 3. Lógica para combos dependientes ---
    $('#cat_id').on('change', function () {
        var cat_id = $(this).val();
        if (cat_id) {
            // Si se selecciona una categoría, se actualiza el combo de subcategorías
            $.post("../../controller/subcategoria.php?op=combo", { cat_id: cat_id }, function (data) {
                $('#cats_id').html(data);
            });
        } else {
            // Si no hay categoría seleccionada, se limpia el combo de subcategorías
            $('#cats_id').html('<option value="">Seleccione una categoría primero</option>');
        }
    });

    // --- 4. Lógica para botones de seleccionar/deseleccionar todos ---
    $('#select_all_creador').on('click', function () {
        $("#creador_car_ids > option").prop("selected", "selected");
        $("#creador_car_ids").trigger("change");
    });

    $('#deselect_all_creador').on('click', function () {
        $("#creador_car_ids").val(null).trigger("change");
    });

    $('#select_all_asignado').on('click', function () {
        $("#asignado_car_ids > option").prop("selected", "selected");
        $("#asignado_car_ids").trigger("change");
    });

    $('#deselect_all_asignado').on('click', function () {
        $("#asignado_car_ids").val(null).trigger("change");
    });


    // --- 5. Inicializar DataTable ---
    tabla = $('#flujomapeo_data').dataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: 'Bfrtip',
        "searching": true,
        lengthChange: false,
        colReorder: true,
        "buttons": [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
        ],
        "ajax": {
            url: '../../controller/flujomapeo.php?op=listar',
            type: 'post',
            dataType: 'json',
            error: function (e) {
                console.log(e.responseText);
            }
        },
        "bDestroy": true,
        "responsive": true,
        "bInfo": true,
        "iDisplayLength": 10,
        "autoWidth": false,
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
            "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sInfoPostFix": "",
            "sSearch": "Buscar:",
            "sUrl": "",
            "sInfoThousands": ",",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
                "sFirst": "Primero",
                "sLast": "Último",
                "sNext": "Siguiente",
                "sPrevious": "Anterior"
            },
            "oAria": {
                "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
                "sSortDescending": ": Activar para ordenar la columna de manera descendente"
            }
        }
    }).DataTable();

});

function editar(regla_id) {
    $('#mdltitulo').html('Editar Regla');
    $.post("../../controller/flujomapeo.php?op=mostrar", { regla_id: regla_id }, function (data) {
        data = JSON.parse(data);

        // --- LÓGICA DE EDICIÓN CORREGIDA ---
        if (data && data.regla) {
            $('#regla_id').val(data.regla.regla_id);

            // Asignamos los arrays de IDs a los combos de selección múltiple
            $('#creador_car_ids').val(data.creadores).trigger('change');
            $('#creador_per_ids').val(data.creadores_perfiles).trigger('change');
            $('#asignado_car_ids').val(data.asignados).trigger('change');

            // Para los combos dependientes, cargamos en cascada
            // 1. Seleccionamos la categoría padre
            $('#cat_id').val(data.regla.cat_id);

            // 2. Cargamos las subcategorías que le corresponden
            $.post("../../controller/subcategoria.php?op=combo", { cat_id: data.regla.cat_id }, function (subcat_data) {
                $('#cats_id').html(subcat_data);

                // 3. Una vez cargadas, seleccionamos la subcategoría correcta
                $('#cats_id').val(data.regla.cats_id).trigger('change');
            });
        }
        $('#modalnuevoflujomapeo').modal('show');
    });
}

function eliminar(regla_id) {
    swal({
        title: "Advertencia",
        text: "¿Está seguro de eliminar esta regla?",
        type: "warning",
        showCancelButton: true,
        confirmButtonClass: "btn-danger",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "No, cancelar",
        closeOnConfirm: false
    },
        function (isConfirm) {
            if (isConfirm) {
                // --- MODIFICADO: Se envía 'regla_id' en lugar de 'map_id' ---
                $.post("../../controller/flujomapeo.php?op=eliminar", { regla_id: regla_id }, function () {
                    $('#flujomapeo_data').DataTable().ajax.reload();
                    swal("¡Eliminado!", "La regla ha sido eliminada.", "success");
                });
            }
        });
}

$('#btnnuevoflujomapeo').on('click', function () {
    $('#mdltitulo').html('Nueva Regla');
    $('#flujomapeo_form')[0].reset();
    $('#regla_id').val('');
    // Reseteamos todos los combos de Select2
    $('#cat_id').val(null).trigger('change');
    $('#cats_id').html('<option value="">Seleccione una categoría primero</option>').trigger('change');
    $('#cats_id').html('<option value="">Seleccione una categoría primero</option>').trigger('change');
    $('#creador_car_ids').val(null).trigger('change');
    $('#creador_per_ids').val(null).trigger('change');
    $('#asignado_car_ids').val(null).trigger('change');
    $('#modalnuevoflujomapeo').modal('show');
});

init();