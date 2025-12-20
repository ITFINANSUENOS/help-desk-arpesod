var tabla;

function init() {
    $("#consulta_form").on("submit", function (e) {
        guardaryeditar(e);
    });
}

$(document).ready(function () {
    tabla = $('#consulta_data').dataTable({
        "aProcessing": true,
        "aServerSide": true,
        dom: 'Bfrtip',
        "searching": true,
        lengthChange: false,
        colReorder: true,
        buttons: [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5'
        ],
        "ajax": {
            url: '../../controller/consulta.php?op=listar',
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
        "autoWidth": false,
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
            "sInfo": "Mostrando un total de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando un total de 0 registros",
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

function guardaryeditar(e) {
    e.preventDefault();
    var formData = new FormData($("#consulta_form")[0]);
    $.ajax({
        url: "../../controller/consulta.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (datos) {
            $('#consulta_form')[0].reset();
            $("#modalmantenimiento").modal('hide');
            $('#consulta_data').DataTable().ajax.reload();
            swal("Correcto!", "Registrado Correctamente", "success");
        }
    });
}

function editar(cons_id) {
    $('#mdltitulo').html('Editar Consulta');
    $.post("../../controller/consulta.php?op=mostrar", { cons_id: cons_id }, function (data) {
        data = JSON.parse(data);
        $('#cons_id').val(data.cons_id);
        $('#cons_nom').val(data.cons_nom);
        $('#cons_sql').val(data.cons_sql);
    });
    $('#modalmantenimiento').modal('show');
}

function eliminar(cons_id) {
    swal({
        title: "HelpDesk",
        text: "¿Esta seguro de Eliminar el Registro?",
        type: "error",
        showCancelButton: true,
        confirmButtonClass: "btn-danger",
        confirmButtonText: "Si",
        cancelButtonText: "No",
        closeOnConfirm: false
    },
        function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/consulta.php?op=eliminar", { cons_id: cons_id }, function (data) {
                    $('#consulta_data').DataTable().ajax.reload();
                    swal("Eliminado!", "Registro Eliminado.", "success");
                });
            }
        });
}

$(document).on("click", "#btnnuevo", function () {
    $('#cons_id').val('');
    $('#mdltitulo').html('Nueva Consulta');
    $('#consulta_form')[0].reset();
    $('#modalmantenimiento').modal('show');
});

init();
