var tabla;

function ver(tick_id) {
    window.location.href = '/view/DetalleHistorialTicket/?ID=' + tick_id
}


$(document).ready(function () {

    const rol_id = $('#rol_real_idx').val();
    const usu_id = $('#user_idx').val();


    // Inicialización de la tabla de historial
    if (rol_id == 3) {
        tabla = $('#historial_data').dataTable({
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
                // Llamada al controlador con la operación para listar el historial
                url: '../../controller/ticket.php?op=listar_historial_tabla',
                type: 'post',
                dataType: 'json',
                data: function (d) {
                    d.search_custom = $('#custom_search').val();
                    d.fech_crea_start = $('#fech_crea_start').val();
                    d.fech_crea_end = $('#fech_crea_end').val();
                },
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
    } else {
        tabla = $('#historial_data').dataTable({
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
                // Llamada al controlador con la operación para listar el historial
                url: '../../controller/ticket.php?op=listar_historial_tabla_x_agente',
                type: 'post',
                dataType: 'json',
                data: function (d) {
                    d.usu_id = usu_id;
                    d.search_custom = $('#custom_search').val();
                    d.fech_crea_start = $('#fech_crea_start').val();
                    d.fech_crea_end = $('#fech_crea_end').val();
                },
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
    }

    // Event listener for custom search
    $('#btn_search').on('click', function () {
        var l = Ladda.create(document.querySelector('#btn_search'));
        l.start();
        $('#historial_data').DataTable().ajax.reload();
    });

    $('#btn_clear').on('click', function () {
        var l = Ladda.create(document.querySelector('#btn_clear'));
        l.start();
        $('#fech_crea_start').val('');
        $('#fech_crea_end').val('');
        $('#custom_search').val('');
        $('#historial_data').DataTable().ajax.reload();
    });

    // Stop Ladda on table draw
    $('#historial_data').on('draw.dt', function () {
        var l_search = Ladda.create(document.querySelector('#btn_search'));
        var l_clear = Ladda.create(document.querySelector('#btn_clear'));
        l_search.stop();
        l_clear.stop();
    });

    $('#custom_search').on('keyup', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            $('#historial_data').DataTable().ajax.reload();
        }
    });

});
