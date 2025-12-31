var tabla_recibidos;
var tabla_enviados;
var usu_id = $('#user_idx').val()
var rol_id = $('#rol_idx').val()

function init() {
}

$(document).ready(function () {

    // Load Subcategories
    $.post("../../controller/subcategoria.php?op=combo_usuario_tickets", function (data) {
        $("#cats_id").html(data);
    });

    // Load Tags
    $.post("../../controller/etiqueta.php?op=combo", function (data) {
        $("#eti_id").html('<option value="">Seleccionar</option>' + data);
        $('#eti_id').trigger('change');
    });

    // Configuration Common for Datatables
    var commonOptions = {
        "processing": true,
        "serverSide": true,
        order: [[0, 'desc']],
        dom: 'Bfrtip',
        "searching": false,
        lengthChange: false,
        colRecorder: true,
        "buttons": [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
        ],
        "bDestroy": true,
        "responsive": true,
        "bInfo": true,
        "isDisplayLength": 10,
        "autoWidth": false,
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
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
    };

    // TABLA 1: MIS ERRORES (RECIBIDOS)
    var optionsRecibidos = $.extend(true, {}, commonOptions);
    optionsRecibidos.language.sEmptyTable = "No tienes errores asignados.";
    optionsRecibidos.ajax = {
        url: '../../controller/ticket.php?op=listar_error_recibido',
        type: 'post',
        data: function (d) {
            d.usu_id = usu_id;
            d.search_custom = $('#custom_search').val();
            d.fech_crea_start = $('#fech_crea_start').val();
            d.fech_crea_end = $('#fech_crea_end').val();
            d.tick_id = $('#tick_id').val();
            d.cats_id = $('#cats_id').val();
            d.eti_id = $('#eti_id').val();
        },
        dataType: 'json',
        error: function (e) {
            console.log(e.responseText);
        }
    };
    tabla_recibidos = $('#ticket_data_recibidos').dataTable(optionsRecibidos).DataTable();

    // TABLA 2: ERRORES REPORTADOS (ENVIADOS)
    var optionsEnviados = $.extend(true, {}, commonOptions);
    optionsEnviados.language.sEmptyTable = "No has reportado errores.";
    optionsEnviados.ajax = {
        url: '../../controller/ticket.php?op=listar_error_enviado',
        type: 'post',
        data: function (d) {
            d.usu_id = usu_id;
            d.search_custom = $('#custom_search').val();
            d.fech_crea_start = $('#fech_crea_start').val();
            d.fech_crea_end = $('#fech_crea_end').val();
            d.tick_id = $('#tick_id').val();
            d.cats_id = $('#cats_id').val();
            d.eti_id = $('#eti_id').val();
        },
        dataType: 'json',
        error: function (e) {
            console.log(e.responseText);
        }
    };
    tabla_enviados = $('#ticket_data_enviados').dataTable(optionsEnviados).DataTable();

    // Reajustar tablas al cambiar de pestaña para evitar error responsive
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable()
            .columns.adjust()
            .responsive.recalc();
    });

    // Custom Search Handlers
    $(document).on('click', '#btn_search', function () {
        var l = Ladda.create(document.querySelector('#btn_search'));
        l.start();
        $('#ticket_data_recibidos').DataTable().ajax.reload();
        $('#ticket_data_enviados').DataTable().ajax.reload();
        // We probably don't need to stop ladda manually significantly later, draw callback handles it
    });

    $(document).on('click', '#btn_clear', function () {
        var l = Ladda.create(document.querySelector('#btn_clear'));
        l.start();
        $('#fech_crea_start').val('');
        $('#fech_crea_end').val('');
        $('#custom_search').val('');
        $('#tick_id').val('');
        $('#cats_id').val('').trigger('change');
        $('#eti_id').val('').trigger('change');
        $('#ticket_data_recibidos').DataTable().ajax.reload();
        $('#ticket_data_enviados').DataTable().ajax.reload();
    });

    // Stop Ladda on table draw (Recibidos)
    $('#ticket_data_recibidos').on('draw.dt', function () {
        var l_search = Ladda.create(document.querySelector('#btn_search'));
        var l_clear = Ladda.create(document.querySelector('#btn_clear'));
        l_search.stop();
        l_clear.stop();
    });

    // Stop Ladda on table draw (Enviados) - in case one finishes later
    $('#ticket_data_enviados').on('draw.dt', function () {
        var l_search = Ladda.create(document.querySelector('#btn_search'));
        var l_clear = Ladda.create(document.querySelector('#btn_clear'));
        l_search.stop();
        l_clear.stop();
    });

    $(document).on('keypress', '#custom_search', function (e) {
        if (e.which == 13) {
            $('#btn_search').click();
        }
    });

    $(document).on('keypress', '#tick_id', function (e) {
        if (e.which == 13) {
            $('#btn_search').click();
        }
    });

});
function ver(tick_id) {
    window.location.href = '/view/DetalleTicket/?ID=' + tick_id
}

init();
