var tabla;

function init() {
    $("#cats_form").on("submit", function (e) {
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
    var formData = new FormData($("#cats_form")[0])
    $.ajax({
        url: "../../controller/subcategoria.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (datos) {
            $("#cats_form")[0].reset();
            $('#cats_descrip').summernote('code', '');
            $('#cat_id').html('<option value="">Seleccione una Empresa y Departamento</option>');
            $("#cats_id").val('');
            $("#modalnuevasubcategoria").modal('hide');
            $("#cats_data").DataTable().ajax.reload();
            swal({
                title: "Guardado!",
                text: "Se ha guardado correctamente el nuevo registro.",
                type: "success",
                confirmButtonClass: "btn-success"
            });
        }
    })
}


$(document).ready(function () {

    $.post("../../controller/categoria.php?op=combocat", function (data) {
        $('#cat_id').html(data);
    })
    mostrarprioridad();

    descripcionSubcategoria();

    tabla = $('#cats_data').dataTable({
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
            url: '../../controller/subcategoria.php?op=listar',
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

function editar(cats_id) {
    $("#mdltitulo").html('Editar registro');

    $.post("../../controller/subcategoria.php?op=mostrar", { cats_id: cats_id }, function (data) {
        data = JSON.parse(data);
        $('#cat_id').val(data.subcategoria.cat_id).trigger('change');
        $('#cats_id').val(data.subcategoria.cats_id);
        $('#cats_nom').val(data.subcategoria.cats_nom);
        $('#pd_id').val(data.subcategoria.pd_id).trigger('change');
        $('#cats_descrip').summernote('code', data.subcategoria.cats_descrip);

    });

    $("#modalnuevasubcategoria").modal("show");
}
function eliminar(cats_id) {
    swal({
        title: "¿Estas que quieres eliminar esta subcategoria?",
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
                $.post("../../controller/subcategoria.php?op=eliminar", { cats_id: cats_id }, function (data) {
                    $('#cats_data').DataTable().ajax.reload();
                    swal({
                        title: "Eliminado!",
                        text: "subcategoria eliminada correctamente",
                        type: "success",
                        confirmButtonClass: "btn-success"
                    });
                });
            } else {
                swal({
                    title: "Cancelado",
                    text: "La subcategoria no fue eliminada",
                    type: "error",
                    confirmButtonClass: "btn-danger"

                });
            }
        });
}

function descripcionSubcategoria() {
    $('#cats_descrip').summernote({
        height: 200,
        lang: "es-ES",
        callbacks: {
            onImageUpload: function (image) {
                myimagetreat(image[0]);
            },
            onPaste: function (e) {
            }
        }
    });
}

function mostrarprioridad() {
    $.post("../../controller/prioridad.php?op=combo", function (data) {
        $('#pd_id').html(data);
    });
}

$(document).on("click", "#btnnuevasubcategoria", function () {
    // Limpiamos el formulario
    $("#cats_form")[0].reset();

    // Reseteamos los combos y el editor de texto
    $('#cat_id').val("").trigger('change');
    $('#pd_id').val("").trigger('change');
    $('#cats_descrip').summernote('code', '');

    // Limpiamos el ID oculto de la subcategoría
    $("#cats_id").val('');

    // Preparamos el modal para un nuevo registro
    $("#mdltitulo").html('Nuevo Registro');
    $("#modalnuevasubcategoria").modal("show");
});


$('#modalnuevasubcategoria').on('hidden.bs.modal', function () {
    // Al cerrar, simplemente reseteamos la selección de los combos
    $('#cat_id').val("").trigger('change');
    $('#pd_id').val("").trigger('change');
});

function myimagetreat(image) {
    // Validar tamaño de imagen (Max 2MB)
    if (image.size > 2 * 1024 * 1024) {
        swal("Error", "La imagen supera el límite de 2MB.", "error");
        return;
    }

    var data = new FormData();
    data.append("file", image);
    $.ajax({
        url: '../../controller/tmp_upload.php',
        cache: false,
        contentType: false,
        processData: false,
        data: data,
        type: "post",
        success: function (data) {
            var image = $('<img>').attr('src', data);
            $('#cats_descrip').summernote("insertNode", image[0]);
        },
        error: function (data) {
            console.log(data);
        }
    });
}

init();