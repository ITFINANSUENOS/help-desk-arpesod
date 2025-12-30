var tabla_recibidos;
var tabla_enviados;
var usu_id = $('#user_idx').val()
var rol_id = $('#rol_idx').val()

function init() {
}

$(document).ready(function () {

    // TABLA 1: MIS ERRORES (RECIBIDOS)
    tabla_recibidos = $('#ticket_data_recibidos').dataTable({
        "processing": true,
        "serverSide": true,
        order: [[0, 'desc']],
        dom: 'Bfrtip',
        "searching": true,
        lengthChange: false,
        colRecorder: true,
        "buttons": [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
        ],
        "ajax": {
            url: '../../controller/ticket.php?op=listar_error_recibido',
            type: 'post',
            data: { usu_id: usu_id },
            dataType: 'json',
            error: function (e) {
                console.log(e.responseText);
            }
        },
        "bDestroy": true,
        "responsive": true,
        "bInfo": true,
        "isDisplayLength": 10,
        "autoWidth": false,
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "No tienes errores asignados.",
            "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty": "0 registros",
            "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sSearch": "Buscar:",
            "oPaginate": {
                "sFirst": "Primero",
                "sLast": "Último",
                "sNext": "Siguiente",
                "sPrevious": "Anterior"
            }
        }
    }).DataTable();

    // TABLA 2: ERRORES REPORTADOS (ENVIADOS)
    tabla_enviados = $('#ticket_data_enviados').dataTable({
        "processing": true,
        "serverSide": true,
        order: [[0, 'desc']],
        dom: 'Bfrtip',
        "searching": true,
        lengthChange: false,
        colRecorder: true,
        "buttons": [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
        ],
        "ajax": {
            url: '../../controller/ticket.php?op=listar_error_enviado',
            type: 'post',
            data: { usu_id: usu_id },
            dataType: 'json',
            error: function (e) {
                console.log(e.responseText);
            }
        },
        "bDestroy": true,
        "responsive": true,
        "bInfo": true,
        "isDisplayLength": 10,
        "autoWidth": false,
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "No has reportado errores.",
            "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty": "0 registros",
            "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sSearch": "Buscar:",
            "oPaginate": {
                "sFirst": "Primero",
                "sLast": "Último",
                "sNext": "Siguiente",
                "sPrevious": "Anterior"
            }
        }
    }).DataTable();

    // Reajustar tablas al cambiar de pestaña para evitar error responsive
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable()
            .columns.adjust()
            .responsive.recalc();
    });

});
function ver(tick_id) {
    window.location.href = '/view/DetalleTicket/?ID=' + tick_id
}

init();
