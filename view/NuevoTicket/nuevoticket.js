function init() {
    $('#ticket_form').on('submit', function (e) {
        guardaryeditar(e);
    })
}

$(document).ready(function () {

    $('#tick_descrip').summernote({
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
        },
        buttons: {
            image: function () {
                var fileInput = document.createElement('input');
                fileInput.setAttribute('type', 'file');
                fileInput.setAttribute('accept', 'image/*');
                fileInput.addEventListener('change', function () {
                    var file = this.files[0];
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var dataURL = e.target.result;
                        $('#tick_descrip').summernote('insertImage', dataURL);
                    };
                    reader.readAsDataURL(file);
                });
                fileInput.click();
            }
        }
    });


    function myimagetreat(image) {
        // Validar tamaño de imagen (Max 2MB)
        if (image.size > 2 * 1024 * 1024) {
            swal("Error", "La imagen supera el límite de 2MB.", "error");
            return;
        }

        var data = new FormData();
        data.append("file", image);
        $.ajax({
            url: '../../controller/tmp_upload.php',
            cache: false,
            contentType: false,
            processData: false,
            data: data,
            type: "post",
            success: function (data) {
                var image = $('<img>').attr('src', data);
                $('#tick_descrip').summernote("insertNode", image[0]);
            },
            error: function (data) {
                console.log(data);
            }
        });
    }

    $.post("../../controller/prioridad.php?op=combo", function (data) {
        $('#pd_id').html('<option value="Select">Seleccionar</option>' + data);
    });

    $.post("../../controller/empresa.php?op=combo", function (data) {
        $('#emp_id').html('<option value="Select">Seleccionar</option>' + data);
    });

    if ($('#es_nacional').val() == 1) {
        $('#regional_field').show();
        $.post("../../controller/regional.php?op=combo", function (data) {
            $('#reg_id').html('<option value="Select">Seleccionar</option>' + data);
        });
    }

    categoriasAnidadas();

});

categoriasAnidadas = function () {
    var user_cargo_id = 0;
    // Inicializamos los combos con Select2
    $('#emp_id, #cats_id, #usu_asig, #pd_id, #reg_id').select2();

    // Guardamos el cargo del usuario en una variable global al cargar la página
    user_cargo_id = $('#user_cargo_id').val();

    $.post("../../controller/empresa.php?op=combo", function (data) {
        $('#emp_id').html('<option value="Select">Seleccione Empresa</option>' + data);
    });

    $.post("../../controller/subcategoria.php?op=combo_filtrado", { creador_car_id: user_cargo_id }, function (data) {
        data = JSON.parse(data);
        $('#cats_id').html('<option value="Select">Seleccione Subcategoría</option>' + data.html);
    });

    // 4. Evento para Subcategoría
    $('#cats_id').on('change', function () {
        var cats_id = $(this).val();

        // Limpiamos los campos dependientes
        $('#tick_descrip').summernote('code', '');
        $('#tick_descrip').data('template', '');
        $('#panel_asignacion_manual').hide();
        $('#usu_asig').html('');

        if (cats_id) {
            // a. Llenar la descripción por defecto y obtener datos de la subcategoría
            $.post("../../controller/subcategoria.php?op=mostrar", { cats_id: cats_id }, function (data) {
                data = JSON.parse(data);

                if (data.subcategoria) {
                    var template_content = data.subcategoria.cats_descrip;
                    $('#tick_descrip').summernote('code', template_content);
                    $('#tick_descrip').data('template', template_content); // Guardar plantilla
                    $('#pd_id').val(data.subcategoria.pd_id).trigger('change');

                    // Poblar campos ocultos de categoría y departamento
                    $('#cat_id').val(data.subcategoria.cat_id);
                    if (data.departamentos && data.departamentos.length > 0) {
                        // Asumimos que el primer departamento es el correcto
                        $('#dp_id').val(data.departamentos[0]);
                    }
                }
            });

            // b. Verificar si se necesita asignación manual
            $.post("../../controller/ticket.php?op=verificar_inicio_flujo", { cats_id: cats_id }, function (data) {
                if (data.requiere_seleccion) {
                    console.log('entre seleccion');

                    var options = '<option value="Select">Seleccione un agente...</option>';
                    data.usuarios.forEach(function (user) {
                        options += `<option value="${user.usu_id}">${user.usu_nom} ${user.usu_ape} (${user.reg_nom})</option>`;
                    });
                    $('#usu_asig').html(options);
                    $('#panel_asignacion_manual').show();
                }
            });

            // NEW: Verificar condiciones de inicio (Paso 0)
            $.post("../../controller/flujopaso.php?op=get_transiciones_inicio", { cats_id: cats_id }, function (data) {
                data = JSON.parse(data);
                if (data.length > 0) {
                    var options = '<option value="">Seleccione una opción...</option>';
                    data.forEach(function (transicion) {
                        options += `<option value="${transicion.paso_destino_id}">${transicion.condicion_nombre}</option>`;
                    });
                    $('#paso_inicio_id').html(options);
                    $('#panel_condicion_inicio').show();
                } else {
                    $('#panel_condicion_inicio').hide();
                    $('#paso_inicio_id').empty();
                }
            });

            // c. Verificar campos dinámicos de plantilla
            $.post("../../controller/flujopaso.php?op=get_campos_primer_paso", { cats_id: cats_id }, function (data) {
                data = JSON.parse(data);
                if (data.requiere) {
                    $('#campos_plantilla_inputs').empty();
                    data.campos.forEach(function (campo) {
                        var inputHtml = '';
                        if (campo.campo_tipo === 'regional') {
                            inputHtml = `
                                <div class="col-md-4">
                                    <fieldset class="form-group">
                                        <label class="form-label semibold" for="campo_${campo.campo_id}">${campo.campo_nombre}</label>
                                        <select class="form-control select2" id="campo_${campo.campo_id}" name="campo_${campo.campo_id}" required>
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </fieldset>
                                </div>
                            `;
                            // Populate Regional Select
                            $.post("../../controller/regional.php?op=combo", function (data) {
                                $('#campo_' + campo.campo_id).append(data);
                            });
                        } else if (campo.campo_tipo === 'cargo') {
                            inputHtml = `
                                <div class="col-md-4">
                                    <fieldset class="form-group">
                                        <label class="form-label semibold" for="campo_${campo.campo_id}">${campo.campo_nombre}</label>
                                        <select class="form-control select2" id="campo_${campo.campo_id}" name="campo_${campo.campo_id}" required>
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </fieldset>
                                </div>
                            `;
                            // Populate Cargo Select
                            $.post("../../controller/cargo.php?op=combo", function (data) {
                                $('#campo_' + campo.campo_id).append(data);
                            });
                        } else {
                            inputHtml = `
                                <div class="col-md-4">
                                    <fieldset class="form-group">
                                        <label class="form-label semibold" for="campo_${campo.campo_id}">${campo.campo_nombre}</label>
                                        <input type="text" class="form-control" id="campo_${campo.campo_id}" name="campo_${campo.campo_id}" placeholder="${campo.campo_nombre}" required>
                                    </fieldset>
                                </div>
                            `;
                        }
                        $('#campos_plantilla_inputs').append(inputHtml);
                    });
                    $('#campos_plantilla_container').show();
                } else {
                    $('#campos_plantilla_container').hide();
                    $('#campos_plantilla_inputs').empty();
                }
            });
        } else {
            $('#campos_plantilla_container').hide();
            $('#campos_plantilla_inputs').empty();
        }
    });
}


function guardaryeditar(e) {
    e.preventDefault();
    var formData = new FormData($('#ticket_form')[0])

    if ($('#tick_titulo').val().trim() == '') {
        swal("Atención", "Debe ingresar un título", "warning");
        return false;
    } else if ($('#emp_id').val() == null || $('#emp_id').val() == '' || $('#emp_id').val() == 'Select') {
        swal("Atención", "Debe seleccionar una empresa", "warning");
        return false;

    } else if ($('#cats_id').val() == null || $('#cats_id').val() == '' || $('#cats_id').val() == 'Select') {
        swal("Atención", "Debe seleccionar una subcategoría", "warning");
        return false;
    } else if ($('#es_nacional').val() == 1 && ($('#reg_id').val() == null || $('#reg_id').val() == '' || $('#reg_id').val() == 'Select')) {
        swal("Atención", "Debe seleccionar una regional", "warning");
        return false;
    } else if ($('#panel_asignacion_manual').is(':visible') && ($('#usu_asig').val() == null || $('#usu_asig').val() == '' || $('#usu_asig').val() == 'Select')) {
        swal("Atención", "Esta subcategoría requiere que seleccione un agente para la asignación.", "warning");
        return false;

    } else if ($('#pd_id').val() == null || $('#pd_id').val() == '' || $('#pd_id').val() == 'Select') {
        swal("Atención", "Debe seleccionar una prioridad", "warning");
        return false;

    } else if ($('#tick_descrip').summernote('isEmpty')) {
        swal("Atención", "Debe ingresar una descripción", "warning");
        return false;
    }

    var template = $('#tick_descrip').data('template') || '';
    var current_content = $('#tick_descrip').val();
    var cleanTemplate = template.replace(/<[^>]*>?/gm, '').trim();
    var cleanContent = current_content.replace(/<[^>]*>?/gm, '').trim();

    if (cleanContent === cleanTemplate) {
        swal("Atención", "Debe agregar información adicional a la plantilla de descripción.", "warning");
        return false;
    }

    var form = document.getElementById('ticket_form');

    // 1) Crear un FormData vacío
    var formData = new FormData();

    // 2) Añadir todos los campos no-file (usando serializeArray para jQuery)
    var fields = $('#ticket_form').serializeArray();
    fields.forEach(function (f) {
        // serializeArray no incluye inputs file, por eso es seguro
        formData.append(f.name, f.value);
    });

    // 3) Añadir archivos manualmente desde el input #files
    var input = document.getElementById('files');
    if (input && input.files && input.files.length) {
        for (var i = 0; i < input.files.length; i++) {
            var file = input.files[i];
            // Solo añadir archivos reales (no entradas vacías)
            if (file && file.size > 0) {
                formData.append('files[]', file, file.name);
            }
        }
    }

    // --- VALIDACION DE ARCHIVOS (Max 2MB c/u, Max 8MB Total) ---
    var input = document.getElementById('files');
    var maxFileSize = 2 * 1024 * 1024; // 2MB
    var maxTotalSize = 8 * 1024 * 1024; // 8MB
    var currentTotalSize = 0;

    if (input && input.files && input.files.length > 0) {
        for (var i = 0; i < input.files.length; i++) {
            var file = input.files[i];

            // Validar tamaño individual
            if (file.size > maxFileSize) {
                swal("Error", "El archivo '" + file.name + "' supera el límite de 2MB.", "error");
                return false;
            }

            currentTotalSize += file.size;
        }

        // Validar tamaño total
        if (currentTotalSize > maxTotalSize) {
            swal("Error", "El tamaño total de los archivos supera el límite de 8MB.", "error");
            return false;
        }
    }
    // -----------------------------------------------------------

    // 4) (Opcional) Quitar entradas basura que puedan existir por error
    //    Si por alguna razón quedó una entrada "files" vacía, la borramos:
    if (formData.has && typeof formData.has === 'function') {
        // algunos navegadores no soportan FormData.has(); solo intentamos si existe
        if (formData.has('files')) formData.delete('files');
    } else {
        // alternativa segura: reconstruir for safety (no necesario si hicimos append manual)
    }



    $.ajax({
        url: "../../controller/ticket.php?op=insert",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (data) {
            // Intentamos asegurarnos de que tenemos un objeto JS
            var resp = data;
            try {
                if (typeof data === 'string' || data instanceof String) {
                    resp = JSON.parse(data);
                }
            } catch (err) {
                console.error("Respuesta no JSON:", data, err);
                swal("Error", "Respuesta inválida del servidor.", "error");
                return;
            }

            // Caso nuevo: backend devuelve { success: false, errors: [...] }
            if (resp && resp.hasOwnProperty('success') && resp.success === false) {
                var mensajes = resp.errors && resp.errors.length ? resp.errors : ["No se pudo crear el ticket."];
                // Unimos con saltos de línea para mostrar en swal
                swal("No se creó el ticket", mensajes.join("\n"), "error");
                return;
            }

            // Caso nuevo: success true con tick_id explícito
            var tickId = null;
            if (resp && resp.hasOwnProperty('tick_id')) {
                tickId = resp.tick_id;
            }
            // Caso refactor/antiguo: resp.ticket es array o resp es array
            if (!tickId && resp && resp.ticket && Array.isArray(resp.ticket) && resp.ticket.length > 0) {
                tickId = resp.ticket[0].tick_id || resp.ticket[0].tick_id;
            }
            if (!tickId && Array.isArray(resp) && resp.length > 0 && resp[0].tick_id) {
                tickId = resp[0].tick_id;
            }

            if (!tickId) {
                // si no obtenemos tick_id, intentar leer datos.ticket o asumir éxito parcial
                console.warn("No se encontró tick_id en la respuesta:", resp);
            }

            // Si todo quedó bien: enviar correos (si tenemos tickId)
            if (tickId) {
                $.post("../../controller/email.php?op=ticket_abierto", { tick_id: tickId });
                $.post("../../controller/email.php?op=ticket_asignado", { tick_id: tickId });
            }

            // Reset del formulario
            $('#cat_id').val('');
            $('#tick_titulo').val('');
            $('#files').val('');
            $('#dp_id').val('');
            $('#cats_id').val('');
            $('#pd_id').val('');
            $('#reg_id').val('');
            $('#tick_descrip').summernote('reset');
            $('#tick_descrip').data('template', '');
            $('#usu_asig').val('');
            $('#error_proceso').prop('checked', false);
            $("#error_procesodiv").addClass('hidden');

            swal("Correcto", "Registrado correctamente", "success");
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error("Error AJAX:", textStatus, errorThrown, jqXHR.responseText);
            var msg = "Ocurrió un error al comunicarse con el servidor.";
            // si el backend devolvió JSON con detalles en responseText, intentar parsearlo
            try {
                var parsed = JSON.parse(jqXHR.responseText);
                if (parsed && parsed.errors && parsed.errors.length) {
                    msg = parsed.errors.join("\n");
                } else if (parsed && parsed.message) {
                    msg = parsed.message;
                }
            } catch (e) {
                // no es JSON: usar texto plano
                if (jqXHR.responseText) msg = jqXHR.responseText;
            }
            swal("Error", msg, "error");
        }
    });
}


init();