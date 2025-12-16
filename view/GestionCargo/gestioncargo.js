var tabla;

function init() {
    $("#cargo_form").on("submit", function (e) {
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
    var formData = new FormData($("#cargo_form")[0]);
    $.ajax({
        url: "../../controller/cargo.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (datos) {
            $('#cargo_form')[0].reset();
            $("#modalnuevocargo").modal('hide');
            $('#cargo_data').DataTable().ajax.reload();
            swal({
                title: "¡Correcto!",
                text: "Completado.",
                type: "success",
                confirmButtonClass: "btn-success"
            });
        }
    });
}

$(document).ready(function () {
    tabla = $('#cargo_data').dataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: 'Bfrtip',
        "searching": true,
        lengthChange: false,
        colReorder: true,
        buttons: ['copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5'],
        "ajax": {
            url: '../../controller/cargo.php?op=listar',
            type: "post",
            dataType: "json",
            error: function (e) {
                console.log(e.responseText);
            }
        },
        "bDestroy": true,
        "responsive": true,
        "bInfo": true,
        "iDisplayLength": 10,
        "order": [[0, "asc"]],
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

function editar(car_id) {
    $('#mdltitulo').html('Editar Registro');
    $.post("../../controller/cargo.php?op=mostrar", { car_id: car_id }, function (data) {
        data = JSON.parse(data);
        $('#car_id').val(data.car_id);
        $('#car_nom').val(data.car_nom);
        $('#modalnuevocargo').modal('show');
    });
}

function eliminar(car_id) {
    swal({
        title: "Advertencia",
        text: "¿Está seguro de eliminar el Cargo?",
        type: "error",
        showCancelButton: true,
        confirmButtonClass: "btn-danger",
        confirmButtonText: "Si",
        cancelButtonText: "No",
        closeOnConfirm: false
    },
        function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/cargo.php?op=eliminar", { car_id: car_id }, function (data) {
                    // No es necesario procesar la respuesta, solo recargar
                });
                $('#cargo_data').DataTable().ajax.reload();
                swal({
                    title: "¡Correcto!",
                    text: "Registro Eliminado.",
                    type: "success",
                    confirmButtonClass: "btn-success"
                });
            }
        });
}

$('#btnnuevocargo').on('click', function () {
    $('#mdltitulo').html('Nuevo Registro');
    $('#cargo_form')[0].reset();
    $('#car_id').val('');
    $('#modalnuevocargo').modal('show');
});

init();