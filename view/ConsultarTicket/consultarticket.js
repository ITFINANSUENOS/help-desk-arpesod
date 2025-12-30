var tabla;
var usu_id = $('#user_idx').val()
var usu_asig = $('#user_idx').val()
var rol_id = $('#rol_idx').val()
var rol_real_id = $('#rol_real_idx').val()

function init() {
}


$(document).ready(function () {

    $.post("../../controller/usuario.php?op=usuariosxrol", function (data) {
        $("#usu_asig").html(data);
    });


    if (rol_id == 1 && rol_real_id != 3) {

        $('#lblusucrea').remove();

        tabla = $('#ticket_data').dataTable({
            "aProcessing": true,
            "aServerSide": true,
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
                url: '../../controller/ticket.php?op=listar_x_usu',
                type: 'post',
                dataType: 'json',
                data: function (d) {
                    d.usu_id = usu_id;
                    d.tick_estado = 'Abierto';
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
            "isDisplayLength": 10,
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
    } else if (rol_id == 2 && rol_real_id != 3) {

        $("#lblusertable").html('Usuario')
        $('#lblusucrea').remove();

        tabla = $('#ticket_data').dataTable({
            "aProcessing": true,
            "aServerSide": true,
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
                url: '../../controller/ticket.php?op=listar_x_agente',
                type: 'post',
                data: function (d) {
                    d.usu_asig = usu_asig;
                    d.search_custom = $('#custom_search').val();
                    d.tick_estado = 'Abierto';
                    d.fech_crea_start = $('#fech_crea_start').val();
                    d.fech_crea_end = $('#fech_crea_end').val();
                },
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
    } else if (rol_id != rol_real_id) {
        tabla = $('#ticket_data').dataTable({
            "aProcessing": true,
            "aServerSide": true,
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
                url: '../../controller/ticket.php?op=listar',
                type: 'post',
                dataType: 'json',
                data: function (d) {
                    d.search_custom = $('#custom_search').val();
                    d.tick_estado = 'Abierto';
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
            "isDisplayLength": 10,
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

    // Custom Search Handlers
    $(document).on('click', '#btn_search', function () {
        var l = Ladda.create(document.querySelector('#btn_search'));
        l.start();
        $('#ticket_data').DataTable().ajax.reload();
    });

    $(document).on('click', '#btn_clear', function () {
        var l = Ladda.create(document.querySelector('#btn_clear'));
        l.start();
        $('#fech_crea_start').val('');
        $('#fech_crea_end').val('');
        $('#custom_search').val('');
        $('#ticket_data').DataTable().ajax.reload();
    });

    // Stop Ladda on table draw
    $('#ticket_data').on('draw.dt', function () {
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

})

function ver(tick_id) {
    window.location.href = '/view/DetalleTicket/?ID=' + tick_id
}


function cambiarEstado(tick_id) {
    swal({
        title: "Re-abrir ticket",
        text: "¿Estas que quieres re abrir el ticket?",
        type: "warning",
        showCancelButton: true,
        confirmButtonClass: "btn-warning",
        confirmButtonText: "Si, re-abir!",
        cancelButtonText: "No, cancelar!",
        closeOnConfirm: false,
        closeOnCancel: false
    },
        function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/ticket.php?op=reabrir", { tick_id: tick_id, usu_id: usu_id }, function (data) {
                    $('#ticket_data').DataTable().ajax.reload();
                    swal({
                        title: "Ticket!",
                        text: "Ticket nuevamente abierto",
                        type: "success",
                        confirmButtonClass: "btn-success"
                    });
                });
            } else {
                swal({
                    title: "Cancelado",
                    text: "El ticket no se abrio.",
                    type: "error",
                    confirmButtonClass: "btn-danger"

                });
            }
        });
}


init();

