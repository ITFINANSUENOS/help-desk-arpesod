var tabla;

function init() {
    $("#flujo_form").on("submit", function (e) {
        guardaryeditar(e);
    })
}

function guardaryeditar(e) {
    e.preventDefault();
    var formData = new FormData($("#flujo_form")[0])
    $.ajax({
        url: "../../controller/flujo.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (datos) {
            $("#flujo_form")[0].reset();
            $("#flujo_nom").html('');
            $("#flujo_id").val('');
            $("#cats_id").val('');
            $('#cat_id').val('');
            $('#usu_id_observador').val(null).trigger('change');
            $('#flujo_attachment_display').html('');
            $('#flujo_attachment_display').html('');
            $("#modalnuevoflujo").modal('hide');
            $("#flujo_data").DataTable().ajax.reload();
            swal({
                title: "Guardado!",
                text: "Se ha guardado correctamente el nuevo registro.",
                type: "success",
                confirmButtonClass: "btn-success"
            });
        }
    })
}

function ver(flujo_id) {
    window.location.href = '/view/PasoFlujo/?ID=' + flujo_id
}


$(document).ready(function () {
    // Initialize Select2
    $('.select2').select2({
        placeholder: "Seleccionar",
        allowClear: true,
        dropdownParent: $('#modalnuevoflujo')
    });

    $.post("../../controller/categoria.php?op=combocat", function (data) {
        $('#cat_id').html('<option value="">Seleccionar</option>' + data);
        $("#cat_id").val(data.cat_id);
    });

    // Cargar usuarios para observador
    $.post("../../controller/usuario.php?op=combo", function (data) {
        $('#usu_id_observador').html('<option value="">Ninguno</option>' + data);
    });

    $("#cat_id").off('change').on('change', function () {
        var cat_id = $(this).val();
        $.post("../../controller/subcategoria.php?op=combo", { cat_id: cat_id }, function (data) {
            $('#cats_id').html('<option value="">Seleccionar</option>' + data);
        })
    });



    tabla = $('#flujo_data').dataTable({
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
            url: '../../controller/flujo.php?op=listar',
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


})

function editar(flujo_id) {
    $("#mdltitulo").html('Editar registro');

    $.post("../../controller/flujo.php?op=mostrar", { flujo_id: flujo_id }, function (data) {
        data = JSON.parse(data);
        $('#flujo_id').val(data.flujo.flujo_id);
        $('#flujo_nom').val(data.flujo.flujo_nom);
        $("#cat_id").val(data.flujo.cat_id);

        // Set Observer (Multi-select)
        if (data.flujo.usu_id_observador) {
            // Check if it contains commas
            var observers = String(data.flujo.usu_id_observador).split(',');
            $("#usu_id_observador").val(observers).trigger('change');
        } else {
            $("#usu_id_observador").val([]).trigger('change');
        }

        $.post("../../controller/subcategoria.php?op=combo", { cat_id: data.flujo.cat_id }, function (subcategoriadata) {
            $('#cats_id').html('<option value="">Seleccionar</option>' + subcategoriadata);
            $("#cats_id").val(data.flujo.cats_id);
        });


        if (data.flujo.flujo_nom_adjunto) {
            var fileLink = '<a href="../../public/document/flujo/' + data.flujo.flujo_nom_adjunto + '" target="_blank">Ver archivo actual: ' + data.flujo.flujo_nom_adjunto + '</a>';
            $('#flujo_attachment_display').html(fileLink);
        } else {
            $('#flujo_attachment_display').html('');
        }

    });

    // Cargar plantillas por empresa
    $.post("../../controller/flujo.php?op=listar_plantillas_empresa", { flujo_id: flujo_id }, function (data) {
        var plantillas = JSON.parse(data);
        $('#tabla_plantillas_empresa tbody').empty();
        if (plantillas && plantillas.length > 0) {
            plantillas.forEach(function (p) {
                agregarFilaPlantillaEmpresa(p.emp_id, p.plantilla_nom);
            });
        }
    });

    $("#modalnuevoflujo").modal("show");
}
function eliminar(flujo_id) {
    swal({
        title: "¿Estas que quieres eliminar esta flujo?",
        text: "Una vez eliminado no podrás volver a recuperarlo",
        type: "warning",
        showCancelButton: true,
        confirmButtonClass: "btn-danger",
        confirmButtonText: "Si, eliminar!",
        cancelButtonText: "No, cancelar!",
        closeOnConfirm: false,
        closeOnCancel: false
    },
        function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/flujo.php?op=eliminar", { flujo_id: flujo_id }, function (data) {
                    $('#flujo_data').DataTable().ajax.reload();
                    swal({
                        title: "Eliminado!",
                        text: "flujo eliminada correctamente",
                        type: "success",
                        confirmButtonClass: "btn-success"
                    });
                });
            } else {
                swal({
                    title: "Cancelado",
                    text: "La flujo no fue eliminada",
                    type: "error",
                    confirmButtonClass: "btn-danger"

                });
            }
        });
}

$(document).on("click", "#btnnuevoflujo", function () {
    $("#mdltitulo").html('Nuevo registro');
    $("#flujo_form")[0].reset();
    $("#modalnuevoflujo").modal("show");
});


$('#modalnuevoflujo').on('hidden.bs.modal', function () {
    $("#flujo_form")[0].reset();
    $("#flujo_nom").html('');
    $("#flujo_id").val('');
    $("#cats_id").val('');
    $('#cat_id').val('');
    $('#usu_id_observador').val(null).trigger('change');
    $('#tabla_plantillas_empresa tbody').empty();
});

var empresasOptions = "";

$(document).ready(function () {
    // Cargar opciones de empresa una sola vez
    $.post("../../controller/empresa.php?op=combo", function (data) {
        empresasOptions = data;
    });

    // ... (resto del código existente) ...
});

$('#btn_agregar_plantilla_empresa').on('click', function () {
    agregarFilaPlantillaEmpresa();
});

function agregarFilaPlantillaEmpresa(emp_id = '', plantilla_nom = '') {
    var html = '<tr>';
    html += '<td><select class="form-control" name="plantilla_empresa_emp_id[]">';
    html += '<option value="">Seleccionar Empresa</option>' + empresasOptions;
    html += '</select></td>';

    html += '<td>';
    if (plantilla_nom) {
        html += '<p class="text-muted mb-1">Actual: <a href="../../public/document/flujo/' + plantilla_nom + '" target="_blank">' + plantilla_nom + '</a></p>';
    }
    html += '<input type="hidden" name="plantilla_empresa_actual[]" value="' + (plantilla_nom || '') + '">';
    html += '<input type="file" class="form-control" name="plantilla_empresa_file[]" accept=".pdf"></td>';

    html += '<td><button type="button" class="btn btn-danger btn-sm btn-delete-row"><i class="fa fa-trash"></i></button></td>';
    html += '</tr>';

    var row = $(html);
    if (emp_id) {
        row.find('select').val(emp_id);
    }

    $('#tabla_plantillas_empresa tbody').append(row);

    // Asignar Select2 al select recién creado
    row.find('select').select2();
}

$(document).on('click', '.btn-delete-row', function () {
    $(this).closest('tr').remove();
});

init();