var tabla;

function init() {
    $("#paso_form").on("submit", function (e) {
        guardaryeditar(e);
    });

    // Nuevo: Submit para el form dentro del modal de transiciones
    $("#transicion_form").on("submit", function (e) {
        e.preventDefault();
        var formData = new FormData($("#transicion_form")[0]);
        $.ajax({
            url: "../../controller/flujotransicion.php?op=guardaryeditar",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function (datos) {
                $('#transicion_form')[0].reset();
                // Recargar la lista de transiciones en el modal
                abrirModalTransiciones(formData.get('paso_origen_id'), $("#nombre_paso_origen").text());
                swal("Correcto!", "Transición guardada.", "success");
            }
        });
    });
}

var getUrlParameter = function getUrlParameter(sParam) {
    var sPageURL = decodeURIComponent(window.location.search.substring(1)),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : sParameterName[1];
        }
    }
}

function guardaryeditar(e) {
    e.preventDefault();
    var formData = new FormData($("#paso_form")[0]);

    // Serializar configuración de firmas
    var firmas = [];
    $('#tabla_firmas tbody tr').each(function () {
        var row = $(this);
        var usu_id = row.find('.select2-firma-user').val();
        var car_id = row.find('.select2-firma-cargo').val();
        var pagina = row.find('td:eq(2) input').val();
        var coord_x = row.find('td:eq(3) input').val();
        var coord_y = row.find('td:eq(4) input').val();
        if (coord_x && coord_y) {
            firmas.push({ usu_id: usu_id, car_id: car_id, pagina: pagina, coord_x: coord_x, coord_y: coord_y });
        }
    });
    formData.append('firma_config', JSON.stringify(firmas));

    // Serializar configuración de campos plantilla
    var camposPlantilla = [];
    $('#tabla_campos_plantilla tbody tr').each(function () {
        var row = $(this);
        var campo_nombre = row.find('td:eq(0) input').val();
        var campo_codigo = row.find('td:eq(1) input').val();
        var campo_tipo = row.find('.campo_tipo').val();
        var font_size = row.find('.campo_font_size').val();
        var pagina = row.find('.campo_pagina').val();
        var coord_x = row.find('.campo_x').val();
        var coord_y = row.find('.campo_y').val();
        var campo_trigger = row.find('.campo_trigger').val();
        var campo_query = row.find('.campo_query').val();
        var mostrar_dias_transcurridos = row.find('.mostrar_dias_transcurridos').val();

        if (campo_nombre && campo_codigo) {
            camposPlantilla.push({
                campo_nombre: campo_nombre,
                campo_codigo: campo_codigo,
                campo_tipo: campo_tipo,
                font_size: font_size,
                pagina: pagina,
                coord_x: coord_x,
                coord_y: coord_y,
                campo_trigger: campo_trigger,
                campo_query: campo_query,
                mostrar_dias_transcurridos: mostrar_dias_transcurridos
            });
        }
    });
    formData.append('campos_plantilla_config', JSON.stringify(camposPlantilla));

    $.ajax({
        url: "../../controller/flujopaso.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (datos) {
            $("#paso_form")[0].reset();
            $("#modalnuevopaso").modal('hide');
            $("#paso_data").DataTable().ajax.reload();
            swal("Guardado!", "Se ha guardado correctamente el registro.", "success");
        }
    })
}

// ==================================================================
// == LÓGICA PARA GESTIONAR LOS PASOS DE UNA RUTA ==
// ==================================================================

$(document).ready(function () {
    // Inicialmente, el botón de gestionar pasos está deshabilitado
    $('#btnGestionarPasos').prop('disabled', true);

    // Cuando el usuario selecciona una ruta en el dropdown...
    $('#ruta_id_modal').on('change', function () {
        var rutaSeleccionada = $(this).val();
        // Si se ha seleccionado una ruta válida, habilita el botón. Si no, lo deshabilita.
        if (rutaSeleccionada && rutaSeleccionada !== '') {
            $('#btnGestionarPasos').prop('disabled', false);
        } else {
            $('#btnGestionarPasos').prop('disabled', true);
        }
    });

    // Cuando el usuario hace clic en el botón de gestionar pasos...
    $(document).on('click', '#btnGestionarPasos', function () {
        var ruta_id = $('#ruta_id_modal').val();
        // Obtenemos el texto de la opción seleccionada
        var ruta_nombre = $('#ruta_id_modal option:selected').text();

        if (ruta_id && ruta_id !== '') {
            // Ocultamos el modal actual
            $('#modalGestionTransiciones').modal('hide');

            // Llamamos a la función que abre el segundo modal (esta función la creamos en el mensaje anterior)
            gestionarPasosRuta(ruta_id, ruta_nombre);
        } else {
            swal("Atención", "Por favor, seleccione una ruta primero.", "warning");
        }
    });

    // Cuando el modal de gestionar pasos se cierre, volvemos a mostrar el de transiciones
    $('#modalGestionPasosRuta').on('hidden.bs.modal', function () {
        $('#modalGestionTransiciones').modal('show');
    });

});

function gestionarPasosRuta(ruta_id, ruta_nombre) {
    // 1. Llenar datos del modal
    $('#gestion_ruta_id').val(ruta_id);
    $('#gestion_ruta_nombre').text(ruta_nombre);

    // 2. Llenar el dropdown con todos los pasos del flujo actual
    //    Necesitamos una nueva operación 'combo' en el controlador de FlujoPaso
    $.post("../../controller/flujopaso.php?op=combo_por_flujo", { flujo_id: flujo_id }, function (data) {
        $('#paso_id_para_ruta').html(data);
        // Opcional: Refrescar si usas un plugin
        // $('#paso_id_para_ruta').selectpicker('refresh'); 
    });

    // 3. Cargar la tabla con los pasos que ya están en la ruta
    cargarTablaRutaPasos(ruta_id);

    // 4. Mostrar el modal
    $('#modalGestionPasosRuta').modal('show');
}

// Función para recargar la tabla de pasos de la ruta
function cargarTablaRutaPasos(ruta_id) {
    $('#rutapasos_data tbody').html(''); // Limpiar tabla
    $.post("../../controller/rutapaso.php?op=listar", { ruta_id: ruta_id }, function (data) {
        var pasos = JSON.parse(data);
        if (pasos.length > 0) {
            pasos.forEach(function (paso) {
                var fila = '<tr>';
                fila += '<td><span class="badge badge-pill badge-primary">' + paso.orden + '</span></td>';
                fila += '<td>' + paso.paso_nombre + '</td>';
                fila += '<td><button type="button" onClick="eliminarPasoDeRuta(' + paso.ruta_paso_id + ')" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button></td>';
                fila += '</tr>';
                $('#rutapasos_data tbody').append(fila);
            });
        } else {
            // Mensaje si no hay pasos
            var fila = '<tr><td colspan="3" class="text-center">Aún no se han añadido pasos a esta ruta.</td></tr>';
            $('#rutapasos_data tbody').append(fila);
        }
    });
}

// Handler para el formulario de añadir paso a la ruta
$(document).on('submit', '#rutapaso_form', function (e) {
    e.preventDefault();
    var ruta_id = $('#gestion_ruta_id').val();

    $.ajax({
        url: "../../controller/rutapaso.php?op=guardar",
        type: "POST",
        data: {
            ruta_id: ruta_id,
            paso_id: $('#paso_id_para_ruta').val(),
            orden: $('#orden_del_paso').val()
        },
        success: function () {
            // Limpiar formulario y recargar tabla
            $('#orden_del_paso').val('');
            cargarTablaRutaPasos(ruta_id);
            swal("¡Éxito!", "Paso añadido a la ruta.", "success");
        },
        error: function () {
            swal("Error", "No se pudo añadir el paso.", "error");
        }
    });
});

// Función para eliminar un paso de la ruta
function eliminarPasoDeRuta(ruta_paso_id) {
    swal({
        title: "Confirmar",
        text: "¿Está seguro de quitar este paso de la ruta?",
        type: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, quitar",
        cancelButtonText: "No",
    }, function (isConfirm) {
        if (isConfirm) {
            $.post("../../controller/rutapaso.php?op=eliminar", { ruta_paso_id: ruta_paso_id }, function () {
                // Recargamos la tabla para ver el cambio
                cargarTablaRutaPasos($('#gestion_ruta_id').val());
                swal("Eliminado", "El paso ha sido quitado de la ruta.", "success");
            });
        }
    });
}

// ==================================================================
// == CÓDIGO FINAL Y CONSOLIDADO PARA MODAL DE TRANSICIONES ==
// ==================================================================
flujo_id = getUrlParameter('ID'); // Asegúrate de que esta variable esté disponible globalmente en tu script.

function abrirModalTransiciones(paso_id, paso_nombre) {
    // 1. Preparamos el contenido estático del modal.
    $('#modalGestionTransicionesLabel').html('Gestionar Transiciones para: ' + paso_nombre);
    $('#paso_origen_id_modal').val(paso_id);
    $('#nombre_paso_origen').text(paso_nombre);
    $('#areaNuevaRuta').hide();
    $('#nueva_ruta_nombre').val('');

    // 2. Cargamos la tabla de transiciones existentes.
    cargarTablaTransiciones(paso_id);

    // 3. Cargamos las rutas y LUEGO mostramos el modal.
    cargarYReconstruirSelect().then(function () {
        // Una vez que el select está listo y reconstruido, mostramos el modal.
        $('#modalGestionTransiciones').modal('show');
    });
}

/**
 * Función centralizada y segura para actualizar el select de rutas.
 * Devuelve una Promesa para saber cuándo ha terminado.
 */
function cargarYReconstruirSelect(ruta_a_seleccionar) {
    var d = $.Deferred(); // Creamos una promesa para controlar el flujo asíncrono.
    var select = $('#ruta_id_modal');

    // Hacemos la llamada AJAX para obtener las rutas.
    $.post("../../controller/ruta.php?op=listar_para_select", { flujo_id: flujo_id }, function (data) {

        // Actualizamos el HTML del select.
        select.html(data);

        // Si se nos pasó una ruta para seleccionar, la marcamos como seleccionada.
        if (ruta_a_seleccionar) {
            select.val(ruta_a_seleccionar); // .val() es seguro aquí porque el plugin está destruido.
        }
        // Resolvemos la promesa para indicar que hemos terminado.
        d.resolve();
    });

    return d.promise();
}


// Botón para mostrar/ocultar el área de nueva ruta
$(document).on("click", "#btnNuevaRuta", function () {
    $('#areaNuevaRuta').toggle();
});


// Handler para GUARDAR LA NUEVA RUTA (CORREGIDO)
$(document).on("click", "#btnGuardarRuta", function () {
    var nombreRuta = $('#nueva_ruta_nombre').val();
    if (nombreRuta.trim() === '') {
        swal("Error", "El nombre de la ruta no puede estar vacío.", "error");
        return;
    }

    $.ajax({
        url: "../../controller/ruta.php?op=guardaryeditar",
        type: "POST",
        data: {
            flujo_id: flujo_id,
            ruta_nombre: nombreRuta
        },
        success: function (new_ruta_id) {
            if (new_ruta_id) {
                $('#nueva_ruta_nombre').val('');
                $('#areaNuevaRuta').hide();
                swal("Correcto!", "Ruta creada exitosamente.", "success");

                // Usamos la nueva función centralizada para recargar el select
                // y pre-seleccionar la ruta que acabamos de crear.
                cargarYReconstruirSelect(new_ruta_id);
            } else {
                swal("Error", "No se pudo crear la ruta.", "error");
            }
        }
    });
});

// El resto de las funciones (cargarTablaTransiciones, submit del formulario, etc.)
// pueden permanecer como en la respuesta anterior, ya que no tocan el select.

function cargarTablaTransiciones(paso_id) {
    $('#transiciones_data tbody').html('');
    $.post("../../controller/flujotransicion.php?op=listar_por_paso", { paso_origen_id: paso_id }, function (data) {
        var response = JSON.parse(data);
        if (response.aaData) {
            response.aaData.forEach(function (row) {
                var fila = '<tr>';
                fila += '<td>' + row[0] + '</td>'; // ruta_nombre
                fila += '<td>' + row[1] + '</td>'; // condicion_clave
                fila += '<td>' + row[2] + '</td>'; // condicion_nombre
                fila += '<td>' + row[3] + ' ' + row[4] + '</td>';
                fila += '</tr>';
                $('#transiciones_data tbody').append(fila);
            });
        }
    });
}

$(document).ready(function () {

    descripcionPaso();
    cargarUsuarios();
    cargarTodosLosUsuarios();

    $('#usuarios_especificos').select2();

    $('#requiere_seleccion_manual').on('change', function () {
        if ($(this).is(':checked')) {
            $('#usuarios_especificos_container').show();
            // Deshabilitar y limpiar cargo cuando se activa selección manual
            $('#cargo_id_asignado').val('').prop('disabled', true).trigger('change');
        } else {
            $('#usuarios_especificos_container').hide();
            // Rehabilitar cargo si no hay otros checkboxes que lo deshabiliten
            if (!$('#necesita_aprobacion_jefe').is(':checked') && !$('#asignar_a_creador').is(':checked') && !$('#es_paralelo').is(':checked')) {
                $('#cargo_id_asignado').prop('disabled', false);
            }
        }
    });

    $('#flujo_id').val(getUrlParameter('ID'));

    tabla = $('#paso_data').dataTable({
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
            url: '../../controller/flujopaso.php?op=listar',
            type: 'post',
            type: "post",
            data: { flujo_id: flujo_id },
            dataType: "json",
            "dataSrc": function (json) {
                // Añadir el botón de transiciones a cada fila
                json.aaData.forEach(function (row) {
                    var paso_id = row[6].match(/editar\((\d+)\)/)[1];
                    var paso_nombre = row[1];
                    row.splice(6, 0, '<button type="button" onClick="abrirModalTransiciones(' + paso_id + ',\'' + paso_nombre + '\');" class="btn btn-inline btn-info btn-sm"><i class="fa fa-eye"></i></button>');
                });
                return json.aaData;
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
        "order": [[0, "asc"]]
    }).DataTable();

    // Listener para el checkbox de aprobación de jefe
    $('#necesita_aprobacion_jefe').change(function () {
        if ($(this).is(":checked")) {
            $('#cargo_id_asignado').prop('disabled', true);
            $('#cargo_id_asignado').val('').trigger('change'); // Limpiar selección
        } else {
            if (!$('#asignar_a_creador').is(':checked') && !$('#es_paralelo').is(':checked')) {
                $('#cargo_id_asignado').prop('disabled', false);
            }
        }
    });

    $('#asignar_a_creador').change(function () {
        if ($(this).is(":checked")) {
            $('#cargo_id_asignado').prop('disabled', true);
            $('#cargo_id_asignado').val('').trigger('change');
        } else {
            if (!$('#necesita_aprobacion_jefe').is(':checked') && !$('#es_paralelo').is(':checked')) {
                $('#cargo_id_asignado').prop('disabled', false);
            }
        }
    });

    $.post("../../controller/cargo.php?op=combo", function (data, status) {
        $('#cargo_id_asignado').html(data);
    });

    $('#usuarios_especificos').select2({
        width: '100%',
        ajax: {
            url: "../../controller/usuario.php?op=combo_usuarios_select2",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // search term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 1
    });

    $('#cargos_especificos').select2({
        width: '100%',
        ajax: {
            url: "../../controller/cargo.php?op=combo_select2", // Need to create this endpoint or use existing combo
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 0 // Allow loading all on click
    });

    $('#requiere_seleccion_manual').change(function () {
        if ($(this).is(':checked') || $('#es_paralelo').is(':checked')) {
            $('#usuarios_especificos_container').show();
        } else {
            $('#usuarios_especificos_container').hide();
            $('#usuarios_especificos').val(null).trigger('change');
        }
    });

    $('#es_paralelo').change(function () {
        if ($(this).is(':checked')) {
            $('#cargo_id_asignado').prop('disabled', true);
            $('#cargo_id_asignado').val('').trigger('change'); // Limpiar selección
        } else {
            // Solo habilitar si no está marcado "necesita aprobación jefe" NI "asignar a creador"
            if (!$('#necesita_aprobacion_jefe').is(':checked') && !$('#asignar_a_creador').is(':checked')) {
                $('#cargo_id_asignado').prop('disabled', false);
            }
        }

        if ($(this).is(':checked') || $('#requiere_seleccion_manual').is(':checked')) {
            $('#usuarios_especificos_container').show();
        } else {
            $('#usuarios_especificos_container').hide();
            $('#usuarios_especificos').val(null).trigger('change');
        }
    });

});

// Lógica para Firma Digital
$('#requiere_firma').change(function () {
    if ($(this).is(":checked")) {
        $('#firma_config_container').show();
    } else {
        $('#firma_config_container').hide();
    }
});

$('#btn_add_firma').click(function () {
    addFirmaRow();
});

$(document).on('click', '.btn-remove-firma', function () {
    $(this).closest('tr').remove();
});

function editar(paso_id) {
    $('#mdltitulo').html('Editar Paso');
    $.post("../../controller/flujopaso.php?op=mostrar", { paso_id: paso_id }, function (data) {
        data = JSON.parse(data);
        $('#paso_id').val(data.paso_id);
        $('#flujo_id').val(data.flujo_id);
        $('#paso_orden').val(data.paso_orden);
        $('#paso_nombre').val(data.paso_nombre);
        $('#cargo_id_asignado').val(data.cargo_id_asignado).trigger('change');
        $('#paso_tiempo_habil').val(data.paso_tiempo_habil);
        $('#paso_descripcion').summernote('code', data.paso_descripcion);
        $('#current_paso_nom_adjunto').val(data.paso_nom_adjunto);

        if (data.requiere_seleccion_manual == 1) {
            $('#requiere_seleccion_manual').prop('checked', true);
            $('#usuarios_especificos_container').show();
            cargarUsuariosEspecificos(data.usuarios_especificos_data);
            // Deshabilitar cargo si tiene selección manual
            $('#cargo_id_asignado').prop('disabled', true);
        } else {
            $('#requiere_seleccion_manual').prop('checked', false);
            $('#usuarios_especificos_container').hide();
            $('#usuarios_especificos').val(null).trigger('change');
        }

        if (data.es_tarea_nacional == 1) {
            $('#es_tarea_nacional').prop('checked', true);
        } else {
            $('#es_tarea_nacional').prop('checked', false);
        }

        if (data.es_aprobacion == 1) {
            $('#es_aprobacion').prop('checked', true);
        } else {
            $('#es_aprobacion').prop('checked', false);
        }

        if (data.permite_cerrar == 1) {
            $('#permite_cerrar').prop('checked', true);
        } else {
            $('#permite_cerrar').prop('checked', false);
        }

        if (data.necesita_aprobacion_jefe == 1) {
            $('#necesita_aprobacion_jefe').prop('checked', true);
            $('#cargo_id_asignado').prop('disabled', true);
        } else {
            $('#necesita_aprobacion_jefe').prop('checked', false);
            $('#cargo_id_asignado').prop('disabled', false);
        }

        if (data.asignar_a_creador == 1) {
            $('#asignar_a_creador').prop('checked', true);
            $('#cargo_id_asignado').prop('disabled', true);
        } else {
            $('#asignar_a_creador').prop('checked', false);
        }

        if (data.cerrar_ticket_obligatorio == 1) {
            $('#cerrar_ticket_obligatorio').prop('checked', true);
        } else {
            $('#cerrar_ticket_obligatorio').prop('checked', false);
        }

        if (data.permite_despacho_masivo == 1) {
            $('#permite_despacho_masivo').prop('checked', true);
        } else {
            $('#permite_despacho_masivo').prop('checked', false);
        }

        if (data.es_paralelo == 1) {
            $('#es_paralelo').prop('checked', true);
            $('#usuarios_especificos_container').show();
            cargarUsuariosEspecificos(data.usuarios_especificos_data);
            $('#cargo_id_asignado').prop('disabled', true);
        } else {
            $('#es_paralelo').prop('checked', false);
            if (data.requiere_seleccion_manual != 1) {
                $('#usuarios_especificos_container').hide();
                $('#usuarios_especificos').val(null).trigger('change');
            }
        }

        if (data.requiere_firma == 1) {
            $('#requiere_firma').prop('checked', true);
            $('#firma_config_container').show();
            $('#tabla_firmas tbody').empty();
            if (data.firma_config) {
                data.firma_config.forEach(function (conf) {
                    addFirmaRow(conf);
                });
            }
        } else {
            $('#requiere_firma').prop('checked', false);
            $('#firma_config_container').hide();
            $('#tabla_firmas tbody').empty();
        }

        if (data.requiere_campos_plantilla == 1) {
            $('#requiere_campos_plantilla').prop('checked', true);
            $('#campos_plantilla_container').show();
            $('#tabla_campos_plantilla tbody').empty();
            if (data.campos_plantilla_config) {
                data.campos_plantilla_config.forEach(function (conf) {
                    addCampoPlantillaRow(conf);
                });
            }
        } else {
            $('#requiere_campos_plantilla').prop('checked', false);
            $('#campos_plantilla_container').hide();
            $('#tabla_campos_plantilla tbody').empty();
        }

        // Solo habilitar si no está marcado "necesita aprobación jefe"
        if (data.necesita_aprobacion_jefe != 1) {
            $('#cargo_id_asignado').prop('disabled', false);
        }

        if (data.paso_nom_adjunto) {
            var fileLink = '<a href="../../public/document/paso/' + data.paso_nom_adjunto + '" target="_blank">Ver archivo actual: ' + data.paso_nom_adjunto + '</a>';
            $('#paso_attachment_display').html(fileLink);
        } else {
            $('#paso_attachment_display').html('');
        }

        if (data.campo_id_referencia_jefe) {
            cargarCamposFlujo(data.flujo_id, data.campo_id_referencia_jefe);
        } else {
            cargarCamposFlujo(data.flujo_id);
        }

        $('#modalnuevopaso').modal('show');
    });
}

function nuevo() {
    $('#mdltitulo').html('Nuevo Paso');
    $('#paso_form')[0].reset();
    $('#flujo_id').val(flujo_id);
    $('#paso_descripcion').summernote('code', '');
    $('#usuarios_especificos_container').hide();
    $('#usuarios_especificos').val(null).trigger('change');
    $('#paso_attachment_display').html('');
    $('#necesita_aprobacion_jefe').prop('checked', false);
    $('#cargo_id_asignado').prop('disabled', false);
    $('#requiere_campos_plantilla').prop('checked', false);
    $('#campos_plantilla_container').hide();
    $('#tabla_campos_plantilla tbody').empty();
    $('#cerrar_ticket_obligatorio').prop('checked', false);
    $('#permite_despacho_masivo').prop('checked', false);
    $('#campo_id_referencia_jefe').val('');

    $('#modalnuevopaso').modal('show');
    cargarCamposFlujo(flujo_id);
}
function eliminar(paso_id) {
    swal({
        title: "¿Estas seguro que quieres eliminar este paso?",
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
                $.post("../../controller/flujopaso.php?op=eliminar", { paso_id: paso_id }, function (data) {
                    $('#paso_data').DataTable().ajax.reload();
                    swal({
                        title: "Eliminado!",
                        text: "Paso eliminado correctamente",
                        type: "success",
                        confirmButtonClass: "btn-success"
                    });
                });
            } else {
                swal({
                    title: "Cancelado",
                    text: "El paso no fue eliminado",
                    type: "error",
                    confirmButtonClass: "btn-danger"

                });
            }
        });
}

$(document).on("click", "#btnnuevopaso", function () {
    $("#mdltitulo").html('Nuevo registro');
    $("#paso_form")[0].reset();
    $("#paso_form")[0].reset();
    $('#requiere_seleccion_manual').prop('checked', false);
    $('#es_tarea_nacional').prop('checked', false);
    $('#es_aprobacion').prop('checked', false);
    $('#permite_cerrar').prop('checked', false);
    $('#necesita_aprobacion_jefe').prop('checked', false);
    $('#asignar_a_creador').prop('checked', false);
    $('#cerrar_ticket_obligatorio').prop('checked', false);
    $('#permite_despacho_masivo').prop('checked', false);
    $('#es_paralelo').prop('checked', false);
    $('#usuarios_especificos_container').hide();
    $('#usuarios_especificos').val(null).trigger('change');
    $('#paso_nom_adjunto').val('');
    $('#paso_attachment_display').html('');
    $("#modalnuevopaso").modal("show");
});

function cargarUsuarios() {
    $.post("../../controller/cargo.php?op=combo", function (data) {
        $('#cargo_id_asignado').html('<option value="">Seleccionar un cargo</option>' + data);
    });

}

function cargarTodosLosUsuarios() {
    $.post("../../controller/usuario.php?op=combo", function (data) {
        $('#usuarios_especificos').html(data);
    });
}

function descripcionPaso() {
    $('#paso_descripcion').summernote({
        height: 200,
        lang: "es-ES",
        callbacks: {
            onImageUpload: function (image) {
                console.log("Image detect...");
                myimagetreat(image[0]);
            },
            onPaste: function (e) {
                console.log("Text detect...");
            }
        }
    });
}



$('#modalnuevopaso').on('hidden.bs.modal', function () {
    $("#paso_form")[0].reset();
    $("#paso_id").val('');
    $("#paso_orden").val('');
    $('#paso_nombre').val('');
    $('#cargo_id_asignado').val('');
    $('#cargo_id_asignado').val('');
    $('#requiere_seleccion_manual').prop('checked', false);
    $('#es_tarea_nacional').prop('checked', false);
    $('#es_aprobacion').prop('checked', false);
    $('#permite_cerrar').prop('checked', false);
    $('#necesita_aprobacion_jefe').prop('checked', false);
    $('#asignar_a_creador').prop('checked', false);
    $('#cerrar_ticket_obligatorio').prop('checked', false);
    $('#permite_despacho_masivo').prop('checked', false);
    $('#es_paralelo').prop('checked', false);
    $('#usuarios_especificos_container').hide();
    $('#usuarios_especificos').val(null).trigger('change');
    $('#paso_tiempo_habil').val('');
    $('#paso_descripcion').summernote('code', '');
    $('#paso_nom_adjunto').val('');
    $('#paso_attachment_display').html('');

});

// Lógica para cambiar entre Ruta y Paso Directo en el modal de transiciones
$(document).on('change', 'input[name="tipo_destino"]', function () {
    // Este evento se dispara cuando el input cambia de valor
    handleTipoDestinoChange(this.value);
});

// También escuchamos el click en los labels para asegurar compatibilidad con Bootstrap
$(document).on('click', '.btn-group-toggle .btn', function () {
    var input = $(this).find('input');
    if (input.length > 0) {
        // Usamos setTimeout para esperar a que Bootstrap actualice el estado
        setTimeout(function () {
            handleTipoDestinoChange(input.val());
        }, 50);
    }
});

function handleTipoDestinoChange(value) {
    if (value === 'ruta') {
        $('#container_ruta_destino').show();
        $('#container_paso_destino').hide();
        $('#ruta_id_modal').prop('required', true);
        $('#paso_destino_id_modal').prop('required', false).val('');
    } else {
        $('#container_ruta_destino').hide();
        $('#container_paso_destino').show();
        $('#ruta_id_modal').prop('required', false).val('');
        $('#paso_destino_id_modal').prop('required', true);
    }
}

// Cargar pasos para el selector de destino
function cargarPasosParaDestino() {
    var flujo_id = getUrlParameter('ID');
    $.post("../../controller/flujotransicion.php?op=combo_pasos", { flujo_id: flujo_id }, function (data) {
        $('#paso_destino_id_modal').html(data);
    });
}

// Llamar a cargarPasosParaDestino cuando se abre el modal
$('#modalGestionTransiciones').on('show.bs.modal', function (e) {
    cargarPasosParaDestino();
    // Resetear estado si es una nueva transición (no edición)
    if (!$('#transicion_id').val()) {
        $('#tipo_destino_ruta').prop('checked', true).trigger('change');
        $('#tipo_destino_ruta').parent().addClass('active');
        $('#tipo_destino_paso').parent().removeClass('active');
        $('#transicion_form')[0].reset();
        $('#paso_origen_id_modal').val($('#paso_origen_id_modal').val()); // Mantener el ID del paso origen
    }
});

function eliminarTransicion(transicion_id, paso_origen_id, paso_origen_nombre_encoded) {
    swal({
        title: "Confirmar",
        text: "¿Está seguro de eliminar esta transición?",
        type: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "No",
        closeOnConfirm: false
    }, function (isConfirm) {
        if (isConfirm) {
            $.post("../../controller/flujotransicion.php?op=eliminar", { transicion_id: transicion_id }, function () {
                swal("Eliminado!", "La transición ha sido eliminada.", "success");
                cargarTablaTransiciones(paso_origen_id);
            });
        }
    });
}
function cargarUsuariosEspecificos(data) {
    var selectUsuarios = $('#usuarios_especificos');
    var selectCargos = $('#cargos_especificos');

    selectUsuarios.empty();
    selectCargos.empty();

    if (data && data.length > 0) {
        data.forEach(function (item) {
            if (item.tipo === 'usuario') {
                var option = new Option(item.nombre, item.id, true, true);
                selectUsuarios.append(option);
            } else if (item.tipo === 'cargo') {
                var option = new Option(item.nombre, item.id, true, true);
                selectCargos.append(option);
            }
        });
        selectUsuarios.trigger('change');
        selectCargos.trigger('change');
    }
}
function editarTransicion(transicion_id) {
    $.post("../../controller/flujotransicion.php?op=mostrar", { transicion_id: transicion_id }, function (data) {
        data = JSON.parse(data);
        $('#transicion_id').val(data.transicion_id);
        $('#paso_origen_id_modal').val(data.paso_origen_id);
        $('#condicion_clave_modal').val(data.condicion_clave);
        $('#condicion_nombre_modal').val(data.condicion_nombre);

        if (data.ruta_id) {
            $('input[name="tipo_destino"][value="ruta"]').prop('checked', true).trigger('change');
            $('input[name="tipo_destino"][value="ruta"]').parent().addClass('active');
            $('input[name="tipo_destino"][value="paso"]').parent().removeClass('active');

            $('#ruta_id_modal').val(data.ruta_id);
        } else if (data.paso_destino_id) {
            $('input[name="tipo_destino"][value="paso"]').prop('checked', true).trigger('change');
            $('input[name="tipo_destino"][value="paso"]').parent().addClass('active');
            $('input[name="tipo_destino"][value="ruta"]').parent().removeClass('active');

            $('#paso_destino_id_modal').val(data.paso_destino_id);
        }
    });
}

function addFirmaRow(data = null) {
    var usu_id = data ? data.usu_id : '';
    var car_id = data ? data.car_id : '';
    var pagina = data ? data.pagina : 1;
    var coord_x = data ? data.coord_x : '';
    var coord_y = data ? data.coord_y : '';

    var row = `<tr>
        <td><select class="form-control select2-firma-user" style="width:100%"></select></td>
        <td><select class="form-control select2-firma-cargo" style="width:100%"></select></td>
        <td><input type="number" class="form-control input-pagina" value="${pagina}"></td>
        <td><input type="number" class="form-control input-x" step="0.01" value="${coord_x}" style="width:70px; display:inline-block;"></td>
        <td>
            <input type="number" class="form-control input-y" step="0.01" value="${coord_y}" style="width:70px; display:inline-block;">
            <button type="button" class="btn btn-sm btn-secondary btn-selector" title="Seleccionar en PDF"><i class="fa fa-crosshairs"></i></button>
        </td>
        <td><button type="button" class="btn btn-danger btn-sm btn-remove-firma"><i class="fa fa-trash"></i></button></td>
    </tr>`;
    var $row = $(row);
    $('#tabla_firmas tbody').append($row);

    var $selectUser = $row.find('.select2-firma-user');
    var $selectCargo = $row.find('.select2-firma-cargo');

    $selectUser.select2({
        placeholder: "Seleccione un usuario (Opcional)",
        allowClear: true,
        ajax: {
            url: '../../controller/usuario.php?op=combo_usuarios_select2',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        }
    });

    $selectCargo.select2({
        placeholder: "Seleccione un cargo (Opcional)",
        allowClear: true,
        ajax: {
            url: '../../controller/cargo.php?op=combo_select2',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        }
    });

    // Pre-select values if editing
    if (usu_id) {
        $.ajax({
            type: 'GET',
            url: '../../controller/usuario.php?op=mostrar',
            data: { usu_id: usu_id },
            success: function (response) {
                var user = JSON.parse(response);
                var option = new Option(user.usu_nom + ' ' + user.usu_ape, user.usu_id, true, true);
                $selectUser.append(option).trigger('change');
            }
        });
    }

    if (car_id) {
        if (car_id === 'JEFE_INMEDIATO') {
            var option = new Option("Jefe Inmediato", "JEFE_INMEDIATO", true, true);
            $selectCargo.append(option).trigger('change');
        } else {
            $.ajax({
                type: 'POST',
                url: '../../controller/cargo.php?op=mostrar',
                data: { car_id: car_id },
                success: function (response) {
                    var cargo = JSON.parse(response);
                    var option = new Option(cargo.car_nom, cargo.car_id, true, true);
                    $selectCargo.append(option).trigger('change');
                }
            });
        }
    }

    // Attach click handler for selector
    $row.find('.btn-selector').click(function () {
        abrirSelectorCoordenadas(this);
    });
}

// Logic for Dynamic PDF Fields
$('#requiere_campos_plantilla').change(function () {
    if ($(this).is(":checked")) {
        $('#campos_plantilla_container').show();
    } else {
        $('#campos_plantilla_container').hide();
    }
});

$('#btn_add_campo_plantilla').click(function () {
    addCampoPlantillaRow();
});

$(document).on('click', '.btn-remove-campo', function () {
    $(this).closest('tr').remove();
});

function addCampoPlantillaRow(campo = null) {
    var campo_nombre = campo ? campo.campo_nombre : '';
    var campo_codigo = campo ? campo.campo_codigo : '';
    var pagina = campo ? campo.pagina : 1;
    var coord_x = campo ? campo.coord_x : '';
    var coord_y = campo ? campo.coord_y : '';
    var font_size = campo ? campo.font_size : 10;
    var campo_trigger = campo ? campo.campo_trigger : 0;
    // Ensure campo_query is a string to prevent startsWith errors
    var campo_query = campo && campo.campo_query ? String(campo.campo_query) : '';
    var mostrar_dias_transcurridos = campo ? campo.mostrar_dias_transcurridos : 0;

    var newRow = `
        <tr>
            <td><input type="text" class="form-control form-control-sm" value="${campo_nombre || ''}" placeholder="Nombre" required></td>
            <td><input type="text" class="form-control form-control-sm" value="${campo_codigo || ''}" placeholder="COD" required></td>
            <td>
                <select class="form-control form-control-sm campo_tipo">
                    <option value="text" ${campo && campo.campo_tipo == 'text' ? 'selected' : ''}>Texto</option>
                    <option value="textarea" ${campo && campo.campo_tipo == 'textarea' ? 'selected' : ''}>Area de Texto</option>
                    <option value="date" ${campo && campo.campo_tipo == 'date' ? 'selected' : ''}>Fecha</option>
                    <option value="number" ${campo && campo.campo_tipo == 'number' ? 'selected' : ''}>Número</option>
                    <option value="email" ${campo && campo.campo_tipo == 'email' ? 'selected' : ''}>Email</option>
                    <option value="select" ${campo && campo.campo_tipo == 'select' ? 'selected' : ''}>Lista</option>
                    <option value="regional" ${campo && campo.campo_tipo == 'regional' ? 'selected' : ''}>Regional</option>
                    <option value="cargo" ${campo && campo.campo_tipo == 'cargo' ? 'selected' : ''}>Cargo</option>
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm campo_font_size" value="${font_size || 10}" style="width: 100%;"></td>
            <td><input type="number" class="form-control form-control-sm campo_pagina input-pagina" value="${pagina || 1}" style="width: 100%;"></td>
            <td><input type="text" class="form-control form-control-sm campo_x input-x" value="${coord_x || ''}" placeholder="X"></td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control campo_y input-y" value="${coord_y || ''}" placeholder="Y">
                    <span class="input-group-btn">
                        <button class="btn btn-default btn-selector" type="button"><i class="fa fa-crosshairs"></i></button>
                    </span>
                </div>
            </td>
            <td>
                <select class="form-control form-control-sm campo_trigger">
                    <option value="0" ${campo_trigger == 0 ? 'selected' : ''}>No</option>
                    <option value="1" ${campo_trigger == 1 ? 'selected' : ''}>Si</option>
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm campo_fuente" style="width: 100%;">
                    <option value="">Cargando...</option>
                </select>
                <input type="text" class="form-control form-control-sm campo_query_manual" value="${(campo_query && !campo_query.startsWith('PRESET_') && !campo_query.startsWith('EXCEL:') && isNaN(campo_query)) ? campo_query.replace(/"/g, '&quot;') : ''}" placeholder="SELECT..." style="display: none; margin-top: 5px;">
                
                <div class="excel-config-container" style="display: none; margin-top: 5px;">
                    <select class="form-control form-control-sm excel-file-select" style="margin-bottom: 3px;">
                        <option value="">Cargando Archivos...</option>
                    </select>
                    <select class="form-control form-control-sm excel-col-select">
                        <option value="">Seleccione Archivo...</option>
                    </select>
                </div>

                <input type="hidden" class="form-control form-control-sm campo_query" value="${campo_query ? campo_query.replace(/"/g, '&quot;') : ''}">
            </td>
            <td>
                <select class="form-control form-control-sm mostrar_dias_transcurridos">
                    <option value="0" ${mostrar_dias_transcurridos == 0 ? 'selected' : ''}>No</option>
                    <option value="1" ${mostrar_dias_transcurridos == 1 ? 'selected' : ''}>Si</option>
                </select>
            </td>
            <td><button type="button" class="btn btn-sm btn-danger btn-remove-campo"><i class="fa fa-trash"></i></button></td>
        </tr>
    `;
    var $row = $(newRow);

    // Load options dynamically
    $.post("../../controller/consulta.php?op=combo", function (data) {
        var $select = $row.find('.campo_fuente');

        // Inject Options: SQL Queries + "Excel Data" + "Manual SQL" + "Fecha Actual"
        var baseOptions = data; // Assumes data contains <option>s
        var finalOptions = baseOptions + '<option value="PRESET_FECHA_ACTUAL">Preset: Fecha Actual (Automática)</option><option value="EXCEL">Datos Excel</option><option value="CUSTOM">Consulta Manual (SQL)</option>';

        $select.html(finalOptions);

        // Legacy Support & Pre-selection
        if (campo_query.startsWith('PRESET_')) {
            if ($select.find("option[value='" + campo_query + "']").length == 0) {
                var label = (campo_query == 'PRESET_USUARIO_CEDULA') ? 'Usuario por Cédula (Legacy)' :
                    (campo_query == 'PRESET_USUARIO_CORREO') ? 'Usuario por Correo (Legacy)' : campo_query;
                $select.append('<option value="' + campo_query + '">' + label + '</option>');
            }
            $select.val(campo_query);
        } else if (campo_query.startsWith('EXCEL:')) {
            $select.val('EXCEL').trigger('change');
        } else if (campo_query && !isNaN(campo_query)) {
            // It's an ID from tm_consulta
            $select.val(campo_query);
        } else if (campo_query.length > 0) {
            // Custom SQL
            $select.val('CUSTOM').trigger('change');
        }
    });

    $('#tabla_campos_plantilla tbody').append($row);

    // Attach handler for source change
    $row.find('.campo_fuente').change(function () {
        var val = $(this).val();
        var manualInput = $(this).siblings('.campo_query_manual');
        var hiddenInput = $(this).siblings('.campo_query');
        var excelContainer = $(this).siblings('.excel-config-container');

        // Hide everything first
        manualInput.hide();
        excelContainer.hide();

        if (val === 'CUSTOM') {
            manualInput.show();
            hiddenInput.val(manualInput.val());
        } else if (val === 'EXCEL') {
            excelContainer.show();
            // Load Excel Files for this Flow
            var flujo_id = $('#flujo_id').val();
            var $excelSelect = excelContainer.find('.excel-file-select');
            $.post("../../controller/exceldata.php?op=combo", { flujo_id: flujo_id }, function (data) {
                $excelSelect.html('<option value="">Seleccionar Archivo...</option>' + data);

                // If we are editing and have a selected excel
                var currentQuery = hiddenInput.val(); // EXCEL:{id}:{col}
                if (currentQuery.startsWith('EXCEL:')) {
                    var parts = currentQuery.split(':');
                    if (parts.length >= 3) {
                        $excelSelect.val(parts[1]).trigger('change');
                        // We need to wait for columns to load... logic in change handler
                    }
                }
            });
        } else {
            hiddenInput.val(val);
        }
    });

    // Handler for Excel File Change
    $row.on('change', '.excel-file-select', function () {
        var data_id = $(this).val();
        var $colSelect = $(this).siblings('.excel-col-select');
        var hiddenInput = $(this).closest('td').find('.campo_query');

        if (data_id) {
            $.post("../../controller/exceldata.php?op=get_columns", { data_id: data_id }, function (data) {
                $colSelect.html(data);

                // If editing, select column
                var currentQuery = hiddenInput.val();
                if (currentQuery.startsWith('EXCEL:') && currentQuery.includes(':' + data_id + ':')) {
                    var parts = currentQuery.split(':');
                    if (parts[2]) {
                        setTimeout(function () { $colSelect.val(parts[2]); }, 100);
                    }
                }
            });
        } else {
            $colSelect.html('<option value="">Seleccione Archivo...</option>');
            hiddenInput.val('');
        }
    });

    // Handler for Excel Column Change
    $row.on('change', '.excel-col-select', function () {
        var data_id = $(this).siblings('.excel-file-select').val();
        var col = $(this).val();
        var hiddenInput = $(this).closest('td').find('.campo_query');

        if (data_id && col) {
            hiddenInput.val('EXCEL:' + data_id + ':' + col);
        }
    });

    // Attach handler for manual input change
    $row.find('.campo_query_manual').on('input', function () {
        $(this).siblings('.campo_query').val($(this).val());
    });

    // Attach click handler for selector
    $row.find('.btn-selector').click(function () {
        abrirSelectorCoordenadas(this);
    });
}

function cargarCamposFlujo(flujo_id, selected_id = null) {
    $.post("../../controller/campoplantilla.php?op=combo_flujo", { flujo_id: flujo_id }, function (data) {
        $("#campo_id_referencia_jefe").html(data);
        if (selected_id) {
            $("#campo_id_referencia_jefe").val(selected_id);
        }
    });
}

// --- PDF Visual Selector Logic ---

var pdfDoc = null,
    pageNum = 1,
    pageRendering = false,
    pageNumPending = null,
    scale = 1.5,
    canvas = document.getElementById('pdf-render'),
    ctx = canvas.getContext('2d'),
    currentInputX = null,
    currentInputY = null,
    currentInputPage = null;

function renderPage(num) {
    pageRendering = true;
    // Fetch page
    pdfDoc.getPage(num).then(function (page) {
        var viewport = page.getViewport({ scale: scale });
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        // Render PDF page into canvas context
        var renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        var renderTask = page.render(renderContext);

        // Wait for render to finish
        renderTask.promise.then(function () {
            pageRendering = false;
            if (pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
            }
        });
    });

    // Update page counters
    document.getElementById('page-num').textContent = num;
}

function queueRenderPage(num) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num);
    }
}

function onPrevPage() {
    if (pageNum <= 1) {
        return;
    }
    pageNum--;
    queueRenderPage(pageNum);
}
document.getElementById('prev-page').addEventListener('click', onPrevPage);

function onNextPage() {
    if (pageNum >= pdfDoc.numPages) {
        return;
    }
    pageNum++;
    queueRenderPage(pageNum);
}
document.getElementById('next-page').addEventListener('click', onNextPage);

function abrirSelectorCoordenadas(btn) {
    var $row = $(btn).closest('tr');
    currentInputX = $row.find('.input-x');
    currentInputY = $row.find('.input-y');
    currentInputPage = $row.find('.input-pagina');

    var paso_id = $('#paso_id').val();
    var flujo_id = $('#flujo_id').val();

    // Get PDF Path
    $.post("../../controller/flujopaso.php?op=get_pdf_path", { paso_id: paso_id, flujo_id: flujo_id }, function (data) {
        var response = JSON.parse(data);
        if (response.status === 'success') {
            var url = response.path;

            // Load PDF
            pdfjsLib.getDocument(url).promise.then(function (pdfDoc_) {
                pdfDoc = pdfDoc_;
                document.getElementById('page-count').textContent = pdfDoc.numPages;

                // Initial/Current page
                pageNum = parseInt(currentInputPage.val()) || 1;
                if (pageNum < 1) pageNum = 1;
                if (pageNum > pdfDoc.numPages) pageNum = pdfDoc.numPages;

                renderPage(pageNum);
                $('#modalPDFSelector').modal('show');
            });
        } else {
            swal("Error", response.message, "error");
        }
    });
}

// Canvas Click Handler
canvas.addEventListener('click', function (event) {
    var rect = canvas.getBoundingClientRect();
    var x = event.clientX - rect.left;
    var y = event.clientY - rect.top;

    // Convert pixels to mm
    // PDF.js uses 72 DPI by default for viewport calculations (points)
    // mm = points * (25.4 / 72)
    // x_points = x / scale

    var x_mm = (x / scale) * (25.4 / 72);
    var y_mm = (y / scale) * (25.4 / 72);

    if (currentInputX && currentInputY) {
        currentInputX.val(x_mm.toFixed(2));
        currentInputY.val(y_mm.toFixed(2));
        if (currentInputPage) {
            currentInputPage.val(pageNum);
        }
        $('#modalPDFSelector').modal('hide');
        swal("Coordenadas Capturadas", "X: " + x_mm.toFixed(2) + ", Y: " + y_mm.toFixed(2) + ", Pág: " + pageNum, "success");
    }
});

// Fix for nested modals scrolling issue
$('#modalPDFSelector').on('hidden.bs.modal', function () {
    if ($('#modalnuevopaso').hasClass('show') || $('#modalnuevopaso').hasClass('in')) {
        $('body').addClass('modal-open');
    }
});

init();

