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
                data: { usu_id: usu_id, tick_estado: 'Abierto' },
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
        $('#ticket_data').DataTable().ajax.reload();
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
// --- LOGICA DE ETIQUETAS (LISTADO) ---
var current_list_tick_id = null;

function gestionarEtiquetas(tick_id) {
    current_list_tick_id = tick_id;
    $('#lbl_ticket_id').text('#' + tick_id);
    $('#modal_crear_etiqueta').modal('show');

    // Init Select2 if not initialized
    if (!$('#ticket_etiquetas').data('select2')) {
        $('#ticket_etiquetas').select2({
            placeholder: "Agregar etiqueta...",
            tags: false,
            tokenSeparators: [','],
            templateSelection: formatEtiquetaState,
            templateResult: formatEtiquetaState,
            dropdownParent: $('#modal_crear_etiqueta')
        });

        // Event Listeners (Solo una vez)
        $('#ticket_etiquetas').on('select2:select', function (e) {
            var eti_id = e.params.data.id;
            var usu_id = $('#user_idx').val();
            if (!eti_id || !current_list_tick_id) return;

            $.post("../../controller/etiqueta.php?op=asignar", { tick_id: current_list_tick_id, eti_id: eti_id, usu_id: usu_id }, function (data) {
                console.log("Etiqueta asignada listado");
                $('#ticket_data').DataTable().ajax.reload(null, false); // Reload table logic without reset paging
            });
        });

        $('#ticket_etiquetas').on('select2:unselect', function (e) {
            var eti_id = e.params.data.id;
            if (!eti_id || !current_list_tick_id) return;

            $.post("../../controller/etiqueta.php?op=desligar", { tick_id: current_list_tick_id, eti_id: eti_id }, function (data) {
                console.log("Etiqueta desligada listado");
                $('#ticket_data').DataTable().ajax.reload(null, false);
            });
        });

        // Form Create Tag
        $('#etiqueta_form').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                url: "../../controller/etiqueta.php?op=guardar",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    if (response == "1") {
                        swal("Éxito", "Etiqueta creada", "success");
                        $('#etiqueta_form')[0].reset();
                        // Reload options
                        loadListEtiquetasDisponibles();
                    } else {
                        swal("Error", "No se pudo crear", "error");
                    }
                }
            });
        });
    }

    // Load Data
    loadListEtiquetasDisponibles(function () {
        loadListEtiquetasAsignadas(current_list_tick_id);
    });
}

function loadListEtiquetasDisponibles(callback) {
    $.post("../../controller/etiqueta.php?op=combo", function (data) {
        $('#ticket_etiquetas').html(data);
        $('#ticket_etiquetas').trigger('change.select2');
        if (callback) callback();
    });
}

function loadListEtiquetasAsignadas(tick_id) {
    $.post("../../controller/etiqueta.php?op=listar_x_ticket", { tick_id: tick_id }, function (data) {
        var assigned = JSON.parse(data);
        var selectedIds = [];
        assigned.forEach(function (tag) {
            selectedIds.push(tag.eti_id);
        });
        $('#ticket_etiquetas').val(selectedIds).trigger('change');
    });
}

function formatEtiquetaState(state) {
    if (!state.id) { return state.text; }
    var color = $(state.element).attr('data-color');
    if (!color) color = 'secondary';

    var labelClass = "label label-" + color;
    if (color == "secondary") labelClass = "label label-default";
    if (color == "dark") labelClass = "label label-primary";

    var $state = $(
        '<span class="' + labelClass + '">' + state.text + '</span>'
    );
    return $state;
}
