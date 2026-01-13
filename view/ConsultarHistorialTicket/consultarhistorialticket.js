var tabla;

function ver(tick_id) {
    window.location.href = '/view/DetalleHistorialTicket/?ID=' + tick_id
}


$(document).ready(function () {

    const rol_id = $('#rol_real_idx').val();
    const usu_id = $('#user_idx').val();

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

    var commonOptions = {
        "processing": true,
        "serverSide": true,
        order: [[0, 'desc']],
        dom: 'Bfrtip',
        "searching": false,
        lengthChange: false,
        colReorder: true,
        "buttons": [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
        ],
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
    };


    // Inicialización de la tabla de historial
    if (rol_id == 3) {
        var options = $.extend(true, {}, commonOptions);
        options.ajax = {
            // Llamada al controlador con la operación para listar el historial
            url: '../../controller/ticket.php?op=listar_historial_tabla',
            type: 'post',
            dataType: 'json',
            data: function (d) {
                d.search_custom = $('#custom_search').val();
                d.fech_crea_start = $('#fech_crea_start').val();
                d.fech_crea_end = $('#fech_crea_end').val();
                d.tick_id = $('#tick_id').val();
                d.cats_id = $('#cats_id').val();
                d.eti_id = $('#eti_id').val();
                d.emp_id = $('#emp_id').val();
                d.usu_nom = $('#usu_nom').val();
            },
            error: function (e) {
                console.log(e.responseText);
            }
        };
        tabla = $('#historial_data').dataTable(options).DataTable();
    } else {
        var options = $.extend(true, {}, commonOptions);
        options.ajax = {
            // Llamada al controlador con la operación para listar el historial
            url: '../../controller/ticket.php?op=listar_historial_tabla_x_agente',
            type: 'post',
            dataType: 'json',
            data: function (d) {
                d.usu_id = usu_id;
                d.search_custom = $('#custom_search').val();
                d.fech_crea_start = $('#fech_crea_start').val();
                d.fech_crea_end = $('#fech_crea_end').val();
                d.tick_id = $('#tick_id').val();
                d.cats_id = $('#cats_id').val();
                d.eti_id = $('#eti_id').val();
                d.emp_id = $('#emp_id').val();
                d.usu_nom = $('#usu_nom').val();
            },
            error: function (e) {
                console.log(e.responseText);
            }
        };
        tabla = $('#historial_data').dataTable(options).DataTable();
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
        $('#tick_id').val('');
        $('#cats_id').val('').trigger('change');
        $('#eti_id').val('').trigger('change');
        $('#emp_id').val('').trigger('change');
        $('#historial_data').DataTable().ajax.reload();
    });

    // Stop Ladda on table draw
    $('#historial_data').on('draw.dt', function () {
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

$(document).on('keypress', '#usu_nom', function (e) {
    if (e.which == 13) {
        $('#btn_search').click();
    }
});


