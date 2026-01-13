var tabla;
var usu_id = $('#user_idx').val()
var usu_asig = $('#user_idx').val()
var rol_id = $('#rol_idx').val()
var rol_real_id = $('#rol_real_idx').val()

function init() {
}


// Load Subcategories
$.post("../../controller/subcategoria.php?op=combo_usuario_tickets", function (data) {
    $("#cats_id").html(data);
});

// Load Tags
$.post("../../controller/etiqueta.php?op=combo", function (data) {
    $("#eti_id").html('<option value="">Seleccionar</option>' + data);
    $('#eti_id').trigger('change');
});

// Load Empresas
$.post("../../controller/empresa.php?op=combo", function (data) {
    $("#emp_id").html('<option value="">Seleccionar</option>' + data);
    $('#emp_id').trigger('change');
});

$.post("../../controller/usuario.php?op=usuariosxrol", function (data) {
    $("#usu_asig").html(data);
});

// Default configuration for all roles
var dtOptions = {
    "processing": true,
    "serverSide": true,
    order: [[0, 'desc']],
    dom: 'Bfrtip',
    "searching": false, // Disable default search
    lengthChange: false,
    colRecorder: true,
    "buttons": [
        'copyHtml5',
        'excelHtml5',
        'csvHtml5',
        'pdfHtml5',
    ],
    "ajax": {
        url: '', // To be set
        type: 'post',
        dataType: 'json',
        data: function (d) {
            // Shared data parameters
            d.search_custom = $('#custom_search').val();
            d.tick_id = $('#tick_id').val();
            d.cats_id = $('#cats_id').val();
            d.eti_id = $('#eti_id').val();
            d.emp_id = $('#emp_id').val();
            d.tick_estado = 'Abierto';
            d.fech_crea_start = $('#fech_crea_start').val();
            d.fech_crea_end = $('#fech_crea_end').val();
            d.usu_nom = $('#usu_nom').val();
            if (rol_id == 1 && rol_real_id != 3) {
                d.usu_id = usu_id;
            } else if (rol_id == 2 && rol_real_id != 3) {
                d.usu_asig = usu_asig;
            }
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
};

if (rol_id == 1 && rol_real_id != 3) {
    dtOptions.ajax.url = '../../controller/ticket.php?op=listar_x_usu';
    tabla = $('#ticket_data').dataTable(dtOptions).DataTable();
} else if (rol_id == 2 && rol_real_id != 3) {
    dtOptions.ajax.url = '../../controller/ticket.php?op=listar_x_agente';
    tabla = $('#ticket_data').dataTable(dtOptions).DataTable();
} else if (rol_id != rol_real_id) {
    dtOptions.ajax.url = '../../controller/ticket.php?op=listar';
    tabla = $('#ticket_data').dataTable(dtOptions).DataTable();
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
    $('#tick_id').val('');
    $('#cats_id').val('').trigger('change'); // If select2
    $('#eti_id').val('').trigger('change');
    $('#emp_id').val('').trigger('change');
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
// Also trigger search on Enter for tick_id
$(document).on('keypress', '#tick_id', function (e) {
    if (e.which == 13) {
        $('#btn_search').click();
    }
});

$(document).on('keypress', '#usu_nom', function (e) {
    if (e.which == 13) {
        $('#btn_search').click();
    }
});


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

