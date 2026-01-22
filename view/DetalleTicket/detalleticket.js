var selected_condicion_clave = null;
var selected_condicion_nombre = null;
var decisionSeleccionada = null; // Asegúrate de que esta variable esté definida globalmente
var signaturePad;
var currentStepInfo = null;

function init() {

}

$(document).ready(function () {

    var rol_id = $("#rol_idx").val();
    var tick_id = getUrlParameter('ID');

    $('#usuario_seleccionado').on('change', updateEnviarButtonState);

    // Pre-emptive check for assignees when "Avanzar Flujo" is checked
    $(document).on('change', '#checkbox_avanzar_flujo', function () {
        var isChecked = $(this).is(':checked');
        updateEnviarButtonState();

        if (isChecked) {
            var tick_id = getUrlParameter('ID');
            $.post("../../controller/ticket.php?op=check_next_step_candidates", { tick_id: tick_id }, function (data) {
                var requirements = [];
                try {
                    requirements = JSON.parse(data);
                } catch (e) {
                    console.error("Invalid JSON response", data);
                }

                // Logic:
                // 1. If multiple ROLES (Parallel) -> Hide Inline Panel (Modal will handle manual assignment if needed).
                // 2. If single ROLE but multiple CANDIDATES -> Show Inline Panel (User must pick one).
                // 3. Else -> Hide Inline Panel.

                var $select = $('#usuario_seleccionado');
                $select.empty();

                if (requirements && Array.isArray(requirements) && requirements.length === 1) {
                    // Single Role case
                    var req = requirements[0];
                    if (req.candidates && req.candidates.length > 1) {
                        // Ambiguity in linear step -> Show Inline Panel
                        $select.append('<option value="">Seleccionar...</option>');
                        $.each(req.candidates, function (i, user) {
                            $select.append('<option value="' + user.usu_id + '">' + user.usu_nom + ' ' + user.usu_ape + '</option>');
                        });
                        $('#panel_seleccion_usuario').show();
                    } else {
                        // 0 or 1 candidate -> Auto handled
                        $('#panel_seleccion_usuario').hide();
                    }
                } else {
                    // Parallel (length > 1) or Error (length 0) -> Hide Inline Panel
                    // If Parallel logic requires manual assignment, the MODAL logic (triggered elsewhere) will handle it.
                    $('#panel_seleccion_usuario').hide();
                }

                updateEnviarButtonState();
            });
        } else {
            $('#panel_seleccion_usuario').hide();
            $('#usuario_seleccionado').empty();
            updateEnviarButtonState();
        }
    });

    listarDetalle(tick_id);

    $('#tickd_descrip').summernote({
        height: 200,
        lang: "es-ES",
        callbacks: {
            onImageUpload: function (image) {
                myimagetreat(image[0]);
            },
            onPaste: function (e) {
            }
        }
    });

    $('#tickd_descripusu').summernote({
        height: 200,
        lang: 'es-ES'
    });

    $('#tickd_descripusu').summernote('disable');

    // Inicializar Summernote para la nota de cierre en el modal
    $('#nota_cierre_summernote').summernote({
        height: 150,
        lang: "es-ES",
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough', 'superscript', 'subscript']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['height', ['height']]
        ]
    });

    // Inicializar SignaturePad
    var canvas = document.getElementById('signature-pad');
    if (canvas) {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)'
        });

        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }
        window.addEventListener("resize", resizeCanvas);
        $('#modalFirma').on('shown.bs.modal', resizeCanvas);

        var isProfileSignatureLoaded = false;

        $('#btnLimpiarFirma').click(function () {
            signaturePad.clear();
            $('#upload_signature').val(''); // Clear file input
            isProfileSignatureLoaded = false;
        });

        // When user starts drawing, clear the flag (though signaturePad.onBegin handles clearing empty state)
        signaturePad.onBegin = function () {
            isProfileSignatureLoaded = false;
        };

        $('#upload_signature').change(function (e) {
            var file = e.target.files[0];
            if (file) {
                // Validar tamaño (Max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    swal("Error", "La imagen de firma supera el límite de 2MB.", "error");
                    $(this).val(''); // Limpiar input
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (evt) {
                    var img = new Image();
                    img.onload = function () {
                        // Draw image on canvas
                        var ctx = canvas.getContext('2d');
                        // Clear canvas first
                        signaturePad.clear();

                        // Calculate aspect ratio to fit
                        // FIX: Use offsetWidth/offsetHeight (CSS pixels) instead of width/height (Physical pixels)
                        // because the context is already scaled by devicePixelRatio.
                        var hRatio = canvas.offsetWidth / img.width;
                        var vRatio = canvas.offsetHeight / img.height;
                        var ratio = Math.min(hRatio, vRatio);
                        var centerShift_x = (canvas.offsetWidth - img.width * ratio) / 2;
                        var centerShift_y = (canvas.offsetHeight - img.height * ratio) / 2;

                        ctx.drawImage(img, 0, 0, img.width, img.height, centerShift_x, centerShift_y, img.width * ratio, img.height * ratio);

                        // We treat uploaded file same as profile signature for validation purposes
                        // But actually the validation check includes !$('#upload_signature').val()
                        // So we don't strictly need the flag here, but let's be consistent.
                        isProfileSignatureLoaded = true;
                    }
                    img.src = evt.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        $('#btnGuardarFirma').click(function () {
            if (signaturePad.isEmpty() && !$('#upload_signature').val() && !isProfileSignatureLoaded) {
                swal("Atención", "Por favor, proporcione su firma.", "warning");
                return;
            }
            // If we drew an image, toDataURL will capture it.
            var signatureData = document.getElementById('signature-pad').toDataURL();
            $('#modalFirma').modal('hide');
            enviarDetalle(signatureData);
        });

        $('#btnUsarFirmaPerfil').click(function () {
            var usu_id = $('#user_idx').val();
            $.post('../../controller/usuario.php?op=obtener_firma', { usu_id: usu_id }, function (response) {
                var data = JSON.parse(response);
                if (data.status === 'success') {
                    var img = new Image();
                    img.onload = function () {
                        var ctx = canvas.getContext('2d');
                        signaturePad.clear();

                        // Use same scaling logic as file upload
                        var hRatio = canvas.offsetWidth / img.width;
                        var vRatio = canvas.offsetHeight / img.height;
                        var ratio = Math.min(hRatio, vRatio);
                        var centerShift_x = (canvas.offsetWidth - img.width * ratio) / 2;
                        var centerShift_y = (canvas.offsetHeight - img.height * ratio) / 2;

                        ctx.drawImage(img, 0, 0, img.width, img.height, centerShift_x, centerShift_y, img.width * ratio, img.height * ratio);
                        isProfileSignatureLoaded = true;
                    }
                    img.src = '../../public/img/firmas/' + usu_id + '/' + data.firma;
                } else {
                    swal("Atención", "No tiene una firma guardada en su perfil.", "warning");
                }
            });
        });
    }

    tabla = $('#documentos_data').dataTable({
        "aProcessing": true,
        "aServerSide": true,
        "searching": false,
        "info": false,
        lengthChange: false,
        colReorder: true,
        "buttons": [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
        ],
        "ajax": {
            url: '../../controller/documento.php?op=listar',
            type: 'post',
            data: { tick_id: tick_id },
            dataType: 'json',
            error: function (e) {
                console.log(e.responseText);
            }
        },
        "bDestroy": true,
        "responsive": true,
        "bInfo": true,
        "iDisplayLength": 3,
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

    getRespuestasRapidas();

    // Cargar usuarios para el modal de novedad
    $.post("../../controller/usuario.php?op=combo", function (data) {
        $('#usu_asig_novedad').html('<option value="">Seleccione un usuario...</option>' + data);
    });

    // Evento para abrir el modal de crear novedad
    $(document).on('click', '#btncrearnovedad', function () {
        $('#modal_crear_novedad').modal('show');
    });

    // Fix Select2 focus issue in Modal
    $('#modal_crear_novedad').on('shown.bs.modal', function () {
        $('#usu_asig_novedad').select2({
            dropdownParent: $('#modal_crear_novedad')
        });
    });

    // Evento para guardar la novedad
    $('#novedad_form').on('submit', function (e) {
        e.preventDefault();

        var selectedUser = $('#usu_asig_novedad').val();
        if (!selectedUser) {
            swal("Error", "Por favor, seleccione un usuario para la novedad.", "warning");
            return;
        }

        // File Validation
        var fileInput = document.getElementById('novedad_files');
        var maxFileSize = 2 * 1024 * 1024; // 2MB
        var maxTotalSize = 8 * 1024 * 1024; // 8MB
        var currentTotalSize = 0;

        if (fileInput && fileInput.files.length > 0) {
            for (var i = 0; i < fileInput.files.length; i++) {
                var file = fileInput.files[i];
                if (file.size > maxFileSize) {
                    swal("Error", "El archivo '" + file.name + "' supera el límite de 2MB.", "error");
                    return;
                }
                currentTotalSize += file.size;
            }
        }

        if (currentTotalSize > maxTotalSize) {
            swal("Error", "El tamaño total de los archivos supera el límite de 8MB.", "error");
            return;
        }

        var selectedUserName = $('#usu_asig_novedad option:selected').text();
        // Remove extra info in parens if any
        selectedUserName = selectedUserName.split('(')[0].trim();

        swal({
            title: "¿Estás seguro?",
            text: "¿Deseas enviar la novedad a " + selectedUserName + "?",
            type: "warning",
            showCancelButton: true,
            confirmButtonClass: "btn-primary",
            confirmButtonText: "Sí, enviar",
            cancelButtonText: "Cancelar",
            closeOnConfirm: false
        }, function (isConfirm) {
            if (isConfirm) {
                var formData = new FormData($('#novedad_form')[0]); // Use modal form directly
                formData.append('tick_id', getUrlParameter('ID'));
                formData.append('usu_id', $('#user_idx').val());

                // Manually append files if not caught by form constructor (safeguard)
                if (fileInput && fileInput.files.length > 0) {
                    // Clean existing key to avoid duplicates if browser handles it differently
                    formData.delete('files[]');
                    for (var i = 0; i < fileInput.files.length; i++) {
                        formData.append('files[]', fileInput.files[i]);
                    }
                }

                $.ajax({
                    url: '../../controller/ticket.php?op=crear_novedad',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function (response) {
                        var data = JSON.parse(response);
                        if (data.status === 'success') {
                            $('#modal_crear_novedad').modal('hide');

                            swal({
                                title: "¡Éxito!",
                                text: "Novedad creada y ticket pausado. Redirigiendo a la lista...",
                                type: "success",
                                timer: 1600,
                                showConfirmButton: false
                            });
                            setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);

                        } else {
                            swal("Error", data.message, "error");
                        }
                    },
                    error: function () {
                        swal("Error", "No se pudo crear la novedad.", "error");
                    }
                });
            }
        });
    });

    // Evento para resolver la novedad
    $(document).on('click', '#btnresolvernovedad', function () {
        swal({
            title: "¿Estás seguro?",
            text: "Se resolverá la novedad y el ticket volverá a su estado anterior.",
            type: "warning",
            showCancelButton: true,
            confirmButtonClass: "btn-success",
            confirmButtonText: "Sí, resolver",
            cancelButtonText: "Cancelar",
            closeOnConfirm: false
        }, function (isConfirm) {
            if (isConfirm) {
                // Prepare FormData to include text and files
                var formData = new FormData();
                formData.append('tick_id', getUrlParameter('ID'));
                formData.append('usu_id', $('#user_idx').val());

                // Get description from Summernote
                var description = $('#tickd_descrip').summernote('code');
                formData.append('tickd_descrip', description);

                // Get files and validate size
                var fileInput = document.getElementById('fileElem');
                var totalSize = 0;
                var maxFileSize = 2 * 1024 * 1024; // 2MB

                if (fileInput && fileInput.files.length > 0) {
                    for (var i = 0; i < fileInput.files.length; i++) {
                        // Validar archivo individual
                        if (fileInput.files[i].size > maxFileSize) {
                            swal("Error", "El archivo '" + fileInput.files[i].name + "' supera el límite de 2MB.", "error");
                            return;
                        }
                        totalSize += fileInput.files[i].size;
                        formData.append('files[]', fileInput.files[i]);
                    }
                }

                if (totalSize > 8 * 1024 * 1024) { // 8MB limit
                    swal("Error", "El tamaño total de los archivos excede el límite permitido de 8MB. Por favor reduzca el tamaño.", "error");
                    return;
                }

                $.ajax({
                    url: "../../controller/ticket.php?op=resolver_novedad",
                    type: "POST",
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function (response) {
                        var data = JSON.parse(response);
                        if (data.status === 'success') {

                            // MODIFICADO: Redirigir después de resolver la novedad
                            swal({
                                title: "¡Éxito!",
                                text: data.message + " Redirigiendo a la lista...",
                                type: "success",
                                timer: 1600,
                                showConfirmButton: false
                            });
                            setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);

                        } else {
                            swal("Error", data.message, "error");
                        }
                    },
                    error: function () {
                        swal("Error", "No se pudo resolver la novedad.", "error");
                    }
                });
            }
        });
    });

    // Evento para imprimir
    $(document).on('click', '#btnprint', function () {
        // Copiar valores a los campos de impresión
        $('#print_lbltickestado').text($('#lbltickestado').text());
        $('#print_lblfechacrea').text($('#lblfechacrea').text());
        $('#print_lblnomusuario').text($('#lblnomusuario').text());

        // Show specific print header for printing
        $('#print_info_header').show();

        window.print();

        // Hide it back (setTimeout to ensure print dialog captures it)
        setTimeout(function () {
            $('#print_info_header').hide();
        }, 1000);
    });

});

function updateEnviarButtonState() {
    // El botón solo está habilitado si el checkbox está realmente marcado 	
    var panelVisible = $('#panel_seleccion_usuario').is(':visible');
    var userSelected = !!$('#usuario_seleccionado').val();
    var enabledCheckbox = $('#checkbox_avanzar_flujo').is(':checked');

    // Check if it's a parallel task (button text changed)
    var isParallelAction = $('#btnenviar').html() === 'Terminar mi parte';

    var enabled = false;

    if (isParallelAction) {
        enabled = true;
    } else if (panelVisible) {
        // If manual selection is active, we MUST have a user selected.
        enabled = userSelected;
    } else {
        // If no manual selection, we depend on the checkbox
        enabled = enabledCheckbox;
    }

    if ($('#btnenviar').data('processing')) {
        enabled = false;
    }

    $('#btnenviar').prop('disabled', !enabled);
}

// al terminar de inicializar (por ejemplo justo después de getRespuestasRapidas();)
$('#btnenviar').prop('disabled', true); // deshabilitado por defecto
updateEnviarButtonState();


function getRespuestasRapidas() {

    $.post("../../controller/respuestarapida.php?op=combo", function (data) {
        $('#fast_answer_id').html('<option value="">Seleccionar</option>' + data);
    });

}

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
            $('#tickd_descrip').summernote("insertNode", image[0]);
        },
        error: function (data) {
            console.log(data);
        }
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

// Helper para quitar HTML y caracteres invisibles y dejar solo texto legible
function stripHtml(html) {
    if (!html) return '';
    // quitar etiquetas html
    var tmp = html.replace(/<[^>]*>/g, '');
    // reemplazar &nbsp; y otros espacios invisibles
    tmp = tmp.replace(/\u00A0/g, ' ').replace(/\u200B/g, '').trim();
    // colapsar múltiples espacios y saltos
    tmp = tmp.replace(/\s+/g, ' ').trim();
    return tmp;
}

function htmlToPlainText(html) {
    // usa tu función stripHtml para normalizar
    return stripHtml(html || '');
}

function enviarDetalle(signatureData = null) {
    // Helper para restaurar botón
    function restoreButton() {
        var $btn = $('#btnenviar');
        var originalText = $btn.data('original-text');
        if (originalText) $btn.html(originalText);
        $btn.data('processing', false);
        updateEnviarButtonState();
    }

    if ($('#tickd_descrip').summernote('isEmpty')) {
        swal("Atención", "Debe ingresar una respuesta o comentario.", "warning");
        restoreButton();
        return false;
    }

    // 2) Validar plantilla vs contenido real (usar summernote('code'))
    var templateHtml = $('#tickd_descrip').data('template') || '';
    var currentHtml = $('#tickd_descrip').summernote ? $('#tickd_descrip').summernote('code') : $('#tickd_descrip').val() || '';

    var cleanTemplate = htmlToPlainText(templateHtml);
    var cleanContent = htmlToPlainText(currentHtml);

    // Opcional: console.log para debug (quita en producción)
    console.log("cleanTemplate:", JSON.stringify(cleanTemplate));
    console.log("cleanContent:", JSON.stringify(cleanContent));

    if (!cleanContent || cleanContent.length === 0) {
        swal("Atención", "Debe ingresar una respuesta o comentario.", "warning");
        restoreButton();
        return false;
    }

    if (cleanContent === cleanTemplate) {
        swal("Atención", "Debe agregar información adicional a la plantilla de descripción.", "warning");
        restoreButton();
        return false;
    }

    // --- PARALLEL STEP VALIDATION ---
    var isAdvancing = $('#checkbox_avanzar_flujo').is(':checked');
    // Check if we already have manual assignments (from a previous validation pass)
    var manualAssignments = window.currentManualAssignments || null;

    if (isAdvancing && !manualAssignments) {
        var tick_id = getUrlParameter('ID');
        var isValid = true; // Sync flag (hacky but needed if we want to block)
        // We must switch to Async flow.

        // Block UI
        $('#btnenviar').prop('disabled', true);

        $.ajax({
            url: "../../controller/ticket.php?op=check_next_step_candidates",
            type: "POST",
            data: { tick_id: tick_id },
            async: false, // We need to block to validate before creating FormData
            success: function (response) {
                try {
                    var requirements = JSON.parse(response);
                    var missingRoles = [];

                    if (Array.isArray(requirements)) {
                        requirements.forEach(function (req) {
                            if (req.candidates.length === 0) {
                                missingRoles.push(req);
                            }
                        });
                    }


                    if (missingRoles.length > 0) {
                        isValid = false;
                        // Trigger Manual Selection UI
                        showManualAssignmentModal(missingRoles);
                    }
                } catch (e) {
                    console.error("Error parsing candidates", e);
                }
            }
        });

        if (!isValid) {
            restoreButton();
            return false; // Stop submission
        }
    }
    // -------------------------------

    var formData = new FormData($('#detalle_form')[0]);
    formData.append("tick_id", getUrlParameter('ID'));
    formData.append("usu_id", $('#user_idx').val());
    formData.append("tickd_descrip", $('#tickd_descrip').summernote('code'));

    // Append Manual Assignments if they exist
    if (manualAssignments) {
        formData.append("manual_assignments", JSON.stringify(manualAssignments));
        // Clear them after use? Maybe not in case of failure.
        window.currentManualAssignments = null;
    }

    // Explicitly handle file upload to ensure it works
    var fileInput = document.getElementById('fileElem');
    var maxFileSize = 2 * 1024 * 1024; // 2MB
    var maxTotalSize = 8 * 1024 * 1024; // 8MB
    var currentTotalSize = 0;

    if (fileInput && fileInput.files.length > 0) {
        for (var i = 0; i < fileInput.files.length; i++) {
            var file = fileInput.files[i];

            // Validar individual
            if (file.size > maxFileSize) {
                swal("Error", "El archivo '" + file.name + "' supera el límite de 2MB.", "error");
                restoreButton();
                return false;
            }

            currentTotalSize += file.size;
            formData.append('files[]', file);
        }
    }

    // Validar total
    if (currentTotalSize > maxTotalSize) {
        swal("Error", "El tamaño total de los archivos supera el límite de 8MB.", "error");
        restoreButton();
        return false;
    }

    // FIX: Si hay selección manual de usuario, también debemos avanzar el flujo
    if ($('#panel_seleccion_usuario').is(':visible')) {
        var usu_asig = $('#usuario_seleccionado').val();
        console.log("Manual user selection:", usu_asig);

        if (!usu_asig) {
            swal("Atención", "Por favor, selecciona un usuario para asignar antes de enviar.", "warning");
            restoreButton();
            return false;
        }
        formData.append('usu_asig', usu_asig);
        formData.append('assign_on_send', 'true');

        // CRÍTICO: Cuando hay selección manual, también debemos avanzar el flujo
        // El checkbox está oculto pero necesitamos enviar avanzar_lineal
        if (!decisionSeleccionada) {
            formData.append("avanzar_lineal", "true");
            console.log("Manual selection: adding avanzar_lineal=true");
        }
    } else if ($('#checkbox_avanzar_flujo').is(':checked')) {
        // Solo procesar checkbox si NO hay selección manual
        if (decisionSeleccionada) {
            formData.append("decision_nombre", decisionSeleccionada);
        } else {
            formData.append("avanzar_lineal", "true");
        }
    }

    // Recopilar campos dinámicos de plantilla
    if ($('#panel_campos_plantilla').is(':visible')) {
        var camposFaltantes = [];
        $('#campos_plantilla_inputs input, #campos_plantilla_inputs select').each(function () {
            var input = $(this);
            if (input.prop('required') && !input.val()) {
                camposFaltantes.push(input.prev('label').text());
            }
            formData.append(input.attr('name'), input.val());
        });

        if (camposFaltantes.length > 0) {
            swal("Atención", "Por favor complete los siguientes campos requeridos: " + camposFaltantes.join(', '), "warning");
            restoreButton();
            return false;
        }
    }

    if (signatureData) {
        formData.append('signature_data', signatureData);
    }

    $.ajax({
        url: "../../controller/ticket.php?op=insertdetalle",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
            var data;
            try { data = JSON.parse(response); } catch (e) {
                console.error("Respuesta no válida del servidor:", response);
                swal("Error", "El servidor devolvió una respuesta inesperada.", "error");
                return;
            }

            if (data.status === 'success') {
                swal({
                    title: "¡Éxito!",
                    text: "La acción se ha completado correctamente.",
                    type: "success",
                    timer: 1500,
                    showConfirmButton: false
                });

                // Esta lógica de redirección ya es la que quieres, así que se mantiene
                if (data.reassigned) {
                    setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1600);
                } else {
                    setTimeout(function () { location.reload(); }, 1600);
                }
            } else if (data.status === 'require_selection') {
                swal("Atención", data.message, "warning");

                var $select = $('#usuario_seleccionado');
                $select.empty();
                $select.append('<option value=""></option>'); // Vacío para placeholder

                if (data.candidates && data.candidates.length > 0) {
                    $.each(data.candidates, function (i, user) {
                        $select.append('<option value="' + user.usu_id + '">' + user.usu_nom + ' ' + user.usu_ape + '</option>');
                    });
                }

                $('#panel_seleccion_usuario').show();
                // Asegurarse de que el botón se actualice
                updateEnviarButtonState();
            } else {
                swal("Error", data.message || "No se pudo procesar la acción.", "error");
            }
        },
        error: function (jqXHR) {
            swal("Error Fatal", "Ocurrió un error con el servidor. Revisa la consola.", "error");
            console.error(jqXHR.responseText);
        },
        complete: function () {
            // Siempre liberar el bloqueo del botón cuando la petición termine
            restoreButton();
        }
    });
}


$(document).on('click', '#btnenviar', function () {
    var $btn = $(this);

    // Protección contra múltiples clicks: si ya está en procesamiento, salir
    if ($btn.data('processing')) return;

    // Si el checkbox está visible, exigir que esté marcado
    // MODIFICADO: Esta validación es ahora más compleja
    var panelFlujoVisible = $('#panel_checkbox_flujo').is(':visible');
    var panelUsuarioVisible = $('#panel_seleccion_usuario').is(':visible');
    var isParallelAction = $btn.html() === 'Terminar mi parte';

    if (!isParallelAction && panelFlujoVisible && !$('#checkbox_avanzar_flujo').is(':checked')) {
        swal("Atención", "Debe marcar la opción para avanzar el flujo antes de enviar.", "warning");
        return false;
    }

    if (panelUsuarioVisible && !$('#usuario_seleccionado').val()) {
        swal("Atención", "Debe seleccionar un usuario para asignar el ticket antes de enviar.", "warning");
        return false;
    }

    // Marcar en procesamiento y deshabilitar el botón
    var originalText = $btn.html();
    $btn.data('original-text', originalText);
    $btn.data('processing', true).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Enviando...');

    selected_condicion_clave = null; // No hay clave de condición si se usa el botón de enviar normal

    // Verificar si requiere firma
    if (currentStepInfo && currentStepInfo.requiere_firma == 1) {
        $('#modalFirma').modal('show');
        // Revertir estado visual si cancela o para permitir firmar
        // PERO: si abre el modal, aún no ha enviado AJAX. 
        // El usuario debe firmar y luego dar click en "Guardar y Terminar" del modal.
        // Ese botón llama a enviarDetalle(signatureData).
        // Así que aquí DEBEMOS revertir el estado del botón principal porque NO se está enviando todavía.
        $btn.data('processing', false).prop('disabled', false).html(originalText);
        return;
    }

    enviarDetalle(); // enviarDetalle ahora liberará el botón en el complete del ajax
});

$(document).on('click', '#btn_registrar_evento', function () {
    var tick_id = getUrlParameter('ID');
    var answer_id = $('#fast_answer_id').val();
    var usu_id = $('#user_idx').val();
    var answer_text = $('#fast_answer_id option:selected').text();

    if (!answer_id) {
        swal("Atención", "Por favor, seleccione una respuesta rápida para registrar.", "warning");
        return;
    }

    if (answer_text.toLowerCase().trim() === "cierre forzoso") {
        swal({
            title: "¿Estás seguro?",
            text: "Se registrará este evento y se cerrará el ticket.",
            type: "warning",
            showCancelButton: true,
            confirmButtonClass: "btn-danger",
            confirmButtonText: "Sí, cerrar ticket",
            cancelButtonText: "Cancelar",
            closeOnConfirm: false
        }, function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/ticket.php?op=registrar_error", { tick_id: tick_id, answer_id: answer_id, usu_id: usu_id, error_descrip: $('#error_descrip').val() })
                    .done(function (response) {
                        try {
                            var data = (typeof response === 'string') ? JSON.parse(response) : response;
                            if (data.status === 'error') {
                                swal("Error", data.msg, "error");
                            } else {
                                updateTicket(tick_id, usu_id);
                                $('#fast_answer_id').val('');
                            }
                        } catch (e) {
                            console.error("Invalid JSON", response);
                            updateTicket(tick_id, usu_id); // Fallback to behave as before if JSON fails
                        }
                    })
                    .fail(function () {
                        swal("Error", "No se pudo registrar el evento.", "error");
                    });
            }
        });
    } else {
        swal({
            title: "¿Estás seguro?",
            text: "Se registrará este evento en el historial del ticket y se marcará el ticket con este estado.",
            type: "warning",
            showCancelButton: true,
            confirmButtonClass: "btn-primary",
            confirmButtonText: "Sí, registrar ahora",
            cancelButtonText: "Cancelar",
            closeOnConfirm: false
        }, function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/ticket.php?op=registrar_error", { tick_id: tick_id, answer_id: answer_id, usu_id: usu_id, error_descrip: $('#error_descrip').val() })
                    .done(function (response) {
                        // MODIFICADO: Verificar si la respuesta indica reasignación
                        var data;
                        try { data = JSON.parse(response); } catch (e) { data = {}; }

                        if (data.status === 'error') {
                            swal("Error", data.msg, "error");
                            return;
                        }

                        if (data.reassigned) {
                            swal({
                                title: "¡Registrado!",
                                text: "El evento se registró y el ticket fue reasignado. Redirigiendo a la lista...",
                                type: "success",
                                timer: 1600,
                                showConfirmButton: false
                            });
                            setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);
                        } else {
                            // Si no hay reasignación, solo recargar
                            swal("¡Registrado!", "El evento ha sido añadido al historial del ticket.", "success");
                            listarDetalle(tick_id);
                        }
                        $('#fast_answer_id').val('');
                    })
                    .fail(function () {
                        swal("Error", "No se pudo registrar el evento.", "error");
                    });
            }
        });
    }
});


$(document).on('click', '#btncerrarticket', function () {
    // Abre el modal para la nota de cierre
    $('#modal_nota_cierre').modal('show');
});

$(document).on('click', '#btn_confirmar_cierre', function () {
    var tick_id = getUrlParameter('ID');
    var usu_id = $('#user_idx').val();
    var nota_cierre = $('#nota_cierre_summernote').summernote('code');

    if ($('#nota_cierre_summernote').summernote('isEmpty')) {
        swal("Atención", "Debe ingresar una nota de cierre.", "warning");
        return;
    }

    var formData = new FormData();
    formData.append('tick_id', tick_id);
    formData.append('usu_id', usu_id);
    formData.append('nota_cierre', nota_cierre);

    var files = $('#cierre_files')[0].files;
    var maxFileSize = 2 * 1024 * 1024; // 2MB
    var maxTotalSize = 8 * 1024 * 1024; // 8MB
    var currentTotalSize = 0;

    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        if (file.size > maxFileSize) {
            swal("Error", "El archivo '" + file.name + "' supera el límite de 2MB.", "error");
            return;
        }
        currentTotalSize += file.size;
        formData.append('cierre_files[]', files[i]);
    }

    if (currentTotalSize > maxTotalSize) {
        swal("Error", "El tamaño total de los archivos supera el límite de 8MB.", "error");
        return;
    }

    $.ajax({
        url: '../../controller/ticket.php?op=cerrar_con_nota',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
            $('#modal_nota_cierre').modal('hide');
            $('#nota_cierre_summernote').summernote('reset');
            $('#cierre_files').val('');

            // MODIFICADO: Redirigir después de cerrar
            swal({
                title: "¡Cerrado!",
                text: "El ticket ha sido cerrado correctamente. Redirigiendo a la lista...",
                type: "success",
                timer: 1600,
                showConfirmButton: false
            });
            setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);
        },
        error: function () {
            swal("Error", "No se pudo cerrar el ticket.", "error");
        }
    });
});

$(document).on("click", "#btn_aprobar_paso", function () {
    var tick_id = getUrlParameter('ID');
    swal({
        title: "¿Estás seguro de aprobar este paso?",
        text: "Una vez aprobado, el ticket avanzará al siguiente paso del flujo.",
        type: "warning",
        showCancelButton: true,
        confirmButtonClass: "btn-success",
        confirmButtonText: "Sí, ¡Aprobar!",
        cancelButtonText: "No, cancelar",
        closeOnConfirm: false
    },
        function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/ticket.php?op=aprobar_paso", { tick_id: tick_id }, function (data) {

                    // MODIFICADO: Redirigir después de aprobar
                    swal({
                        title: "¡Aprobado!",
                        text: "El ticket ha sido aprobado. Redirigiendo a la lista...",
                        type: "success",
                        timer: 1600,
                        showConfirmButton: false
                    });
                    setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);

                }).fail(function (jqXHR) {
                    swal("Error", "No se pudo completar la aprobación. Detalle: " + jqXHR.responseText, "error");
                });
            }
        });
});

$(document).on("click", "#btn_rechazar_paso", function () {
    var tick_id = getUrlParameter('ID');
    swal({
        title: "¿Estás seguro de rechazar este paso?",
        text: "Una vez rechazado, el ticket será devuelto al paso anterior.",
        type: "warning",
        showCancelButton: true,
        confirmButtonClass: "btn-danger",
        confirmButtonText: "Sí, ¡Rechazar!",
        cancelButtonText: "No, cancelar",
        closeOnConfirm: false
    },
        function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/ticket.php?op=rechazar_paso", { tick_id: tick_id }, function (data) {

                    // MODIFICADO: Redirigir después de rechazar
                    swal({
                        title: "¡Rechazado!",
                        text: "El ticket ha sido devuelto. Redirigiendo a la lista...",
                        type: "success",
                        timer: 1600,
                        showConfirmButton: false
                    });
                    setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);

                }).fail(function (jqXHR) {
                    swal("Error", "No se pudo completar el rechazo. Detalle: " + jqXHR.responseText, "error");
                });
            }
        });
});


function updateTicket(tick_id, usu_id) {
    // MODIFICADO: Esta función ahora maneja el swal y la redirección
    $.post("../../controller/ticket.php?op=update", { tick_id: tick_id, usu_id: usu_id }, function (data) {
        swal({
            title: "¡Cerrado!",
            text: "El ticket ha sido cerrado correctamente. Redirigiendo a la lista...",
            type: "success",
            timer: 1600,
            showConfirmButton: false
        });
        setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);
    });
}

function listarDetalle(tick_id) {
    // Carga el historial del ticket
    $.post("../../controller/ticket.php?op=listardetalle", { tick_id: tick_id }, function (data) {
        $('#lbldetalle').html(data);
    });

    // Carga los datos principales del ticket y define las acciones
    $.post("../../controller/ticket.php?op=mostrar", { tick_id: tick_id }, function (data) {
        var ticketData = JSON.parse(data);
        currentStepInfo = ticketData.paso_actual_info;
        console.log(ticketData);

        if (ticketData.paso_actual_id) {
            checkParallelStatus(tick_id, ticketData.paso_actual_id);
        }

        // --- Asignación de datos a la vista (tu código original) ---
        $('#lbltickestado').html(ticketData.tick_estado);
        $('#lblprioridad').html(ticketData.pd_nom);
        $('#lblnomusuario').html(ticketData.usu_nom + ' ' + ticketData.usu_ape);
        $('#lblestado_tiempo').html(ticketData.estado_tiempo);
        $('#lblfechacrea').html(ticketData.fech_crea);
        $('#lblticketid').html("Detalle del ticket #" + ticketData.tick_id + " - " + ticketData.cats_nom);
        $('#cat_id').val(ticketData.cat_nom);
        $('#cats_id').val(ticketData.cats_nom);
        $('#emp_id').val(ticketData.emp_nom);
        $('#dp_id').val(ticketData.dp_nom);
        $('#tick_titulo').val(ticketData.tick_titulo);
        $('#tickd_descripusu').summernote('code', ticketData.tick_descrip);

        // Mostrar contador de días transcurridos (solo tickets abiertos)
        $('#panel_dias_transcurridos').remove(); // Limpiar previo
        if (ticketData.campos_dias_transcurridos && ticketData.campos_dias_transcurridos.length > 0) {
            var diasHtml = '<div id="panel_dias_transcurridos" class="alert alert-warning" style="margin-top: 15px; margin-bottom: 15px;"><h5><i class="fa fa-clock-o"></i> Días Transcurridos</h5><ul style="margin-bottom: 0;">';
            ticketData.campos_dias_transcurridos.forEach(function (campo) {
                if (campo.formato) {
                    diasHtml += '<li><strong>' + campo.campo_nombre + ':</strong> ' + campo.formato + ' (desde ' + campo.valor + ')</li>';
                }
            });
            diasHtml += '</ul></div>';
            // Insertar después del panel de línea de tiempo
            $('#panel_linea_tiempo').after(diasHtml);
        }

        // Limpiar paneles de acciones antes de re-evaluar
        $('#panel_seleccion_usuario').hide();
        $('#usuario_seleccionado').html('');
        $('#panel_checkbox_flujo').hide().data('acciones', null);
        $('#checkbox_avanzar_flujo').prop('checked', false).prop('disabled', true).hide();
        $('#panel_aprobacion').hide();
        $('#panel_guia_paso').hide();
        $('#panel_guia_paso .attachment-section').remove(); // Limpiar adjuntos previos
        $('#btnenviar').show();
        $('#btncrearnovedad').show();
        $('#btncerrarticket').show();
        $('#panel_respuestas_rapidas').show();
        $('#btnresolvernovedad').hide();
        $('#btn_despacho_masivo').hide();


        if (ticketData.siguientes_pasos_lineales && ticketData.siguientes_pasos_lineales.length > 0 && ticketData.siguientes_pasos_lineales[0].requiere_seleccion_manual) {
            console.log('Modo: Seleccionar Siguiente Usuario');

            $('#panel_seleccion_usuario').show();
            var options = '<option value="">Seleccionar Usuario</option>';
            ticketData.usuarios_seleccionables.forEach(function (usuario) {
                options += '<option value="' + usuario.usu_id + '">' + usuario.usu_nom + ' ' + usuario.usu_ape + ' (' + usuario.usu_correo + ')</option>';
            });
            $('#usuario_seleccionado').html(options);
            $('#usuario_seleccionado').select2();

            // Ocultar otras acciones de flujo
            $('#panel_checkbox_flujo').hide().data('acciones', null);
            $('#checkbox_avanzar_flujo').prop('checked', false).prop('disabled', true).hide();
        }

        // Lógica para cargar campos del SIGUIENTE paso (Look-ahead)
        if (ticketData.siguientes_pasos_lineales && ticketData.siguientes_pasos_lineales.length === 1) {
            var siguientePasoId = ticketData.siguientes_pasos_lineales[0].paso_id;
            loadStepFields(siguientePasoId);
        } else {
            $('#panel_campos_plantilla').hide();
            $('#campos_plantilla_inputs').empty();
        }


        // --- Reinserción del bloque de paso que tenías antes ---
        var pasoInfo = ticketData.paso_actual_info || {};

        if (pasoInfo && Object.keys(pasoInfo).length > 0) {
            if (pasoInfo.es_aprobacion == 1) {
                console.log("El paso actual es de aprobación");
                $('#panel_respuestas_rapidas').hide();
                $('#btnenviar').prop('disabled', false) // Habilitar 'Enviar' si es aprobación
                $('#panel_aprobacion').show();
                // Ocultar otros controles de avance
                $('#panel_checkbox_flujo').hide();
                $('#panel_seleccion_usuario').hide();
            } else {
                $('#panel_guia_paso').show();
                $('#guia_paso_nombre').text('Paso Actual: ' + (pasoInfo.paso_nombre || ''));
                $('#guia_paso_tiempo').text(pasoInfo.paso_tiempo_habil || '');

                if (pasoInfo.paso_descripcion) {
                    var pasoTemplate = pasoInfo.paso_descripcion;
                    // Setear el contenido en el editor y almacenar la plantilla
                    $('#tickd_descrip').summernote('code', pasoTemplate);
                    $('#tickd_descrip').data('template', pasoTemplate);
                } else {
                    $('#tickd_descrip').data('template', '');
                }
                if (pasoInfo) {
                    var attachmentHtml = '';

                    if (pasoInfo.documento_firmado_actual) {
                        var doc = pasoInfo.documento_firmado_actual;
                        var docUrl = '';
                        var displayName = doc.det_nom;

                        // Construct URL based on new structure: public/document/flujo/{flujo_id}/{paso_id}/{usu_id}/{doc_nom}
                        if (doc.flujo_id && doc.paso_id && doc.usu_id) {
                            docUrl = '../../public/document/flujo/' + doc.flujo_id + '/' + doc.paso_id + '/' + doc.usu_id + '/' + doc.det_nom;
                        } else {
                            // Fallback if some ID is missing (shouldn't happen with new logic)
                            docUrl = '../../public/document/flujo/' + doc.det_nom;
                        }

                        attachmentHtml =
                            '<div class="alert alert-success" role="alert">' +
                            '<strong>Documento Firmado Actual:</strong> ' +
                            '<a href="' + docUrl + '" target="_blank">' + displayName + '</a>' +
                            '</div>';
                    } else if (pasoInfo.paso_nom_adjunto) {
                        attachmentHtml =
                            '<div class="alert alert-info" role="alert">' +
                            '<strong>Documento del Paso:</strong> ' +
                            '<a href="../../public/document/paso/' + pasoInfo.paso_nom_adjunto + '" target="_blank">' + pasoInfo.paso_nom_adjunto + '</a>' +
                            '</div>';
                    }

                    $('#paso_attachment_display').html(attachmentHtml);
                }
            }
        } else {
            // Si no hay info del paso, ocultamos la guía por seguridad
            $('#panel_guia_paso').hide();
        }

        // Ocultar paneles por defecto y resetear
        $('#panel_checkbox_flujo').hide().data('acciones', null);
        $('#btncerrarticket').hide().prop('disabled', true);
        $('#checkbox_avanzar_flujo').prop('checked', false).prop('disabled', true);

        // Evaluar permisos y estado del flujo
        var user_id = $('#user_idx').val();
        var isAssigned = false;
        if (ticketData.usu_asig) {
            var assignedUsers = String(ticketData.usu_asig).split(',');
            isAssigned = assignedUsers.includes(String(user_id));
        }
        console.log("Is Assigned: " + isAssigned);

        var hasDecisions = ticketData.decisiones_disponibles && ticketData.decisiones_disponibles.length > 0;
        var hasNextLinear = ticketData.siguientes_pasos_lineales && ticketData.siguientes_pasos_lineales.length > 0;
        var isApprovalStep = (pasoInfo.es_aprobacion == 1);
        var isSelectionStep = $('#panel_seleccion_usuario').is(':visible');

        // El último paso se redefine: no hay decisiones, no hay sig. paso lineal, Y no es un paso de selección de usuario
        var isLastStep = !hasDecisions && !hasNextLinear && !isSelectionStep;

        var allowsBulkDispatch = (pasoInfo && pasoInfo.permite_despacho_masivo == 1);
        // Permitir si está asignado y el paso lo permite
        if (isAssigned && allowsBulkDispatch) {
            $('#btn_despacho_masivo').show();
        }
        var canCloseStep = (pasoInfo && pasoInfo.permite_cerrar == 1);
        var forceClose = (pasoInfo && pasoInfo.cerrar_ticket_obligatorio == 1);

        // Guardar flags en el contenedor para consultas posteriores (event handlers)
        $('#boxdetalleticket').data('isAssigned', isAssigned);
        $('#boxdetalleticket').data('isLastStep', isLastStep);

        // Si ya está cerrado, ocultar todo y salir
        if (ticketData.tick_estado_texto === 'Cerrado') {
            $('#boxdetalleticket').hide();
            return;
        }

        // Si está Pausado (Novedad)
        if (ticketData.tick_estado_texto === 'Pausado') {
            $('#btnenviar').hide();
            $('#btncrearnovedad').hide();
            $('#btncerrarticket').hide();
            $('#panel_checkbox_flujo').hide();
            $('#panel_respuestas_rapidas').hide();
            $('#panel_aprobacion').hide();
            $('#panel_seleccion_usuario').hide();
            $('#boxdetalleticket').show(); // Asegurarse que el panel de respuesta esté oculto

            // Lógica para mostrar el botón de resolver novedad
            $.post("../../controller/ticket.php?op=get_novedad_abierta", { tick_id: tick_id }, function (novedadData) {
                var novedad = JSON.parse(novedadData);
                // Solo el usuario asignado A LA NOVEDAD puede resolverla
                if (novedad && String(novedad.usu_asig_novedad) === String(user_id)) {
                    $('#btnresolvernovedad').show();
                }
            });
            return; // Salir de la función aquí
        }

        // Solo el usuario asignado puede ver/usar controles de avance o cierre
        if (isAssigned) {

            // NUEVO: Si es obligatoria la clausura del ticket en este paso (Terminal forzado)
            if (forceClose) {
                $('#btncerrarticket').show().prop('disabled', false);
                $('#panel_checkbox_flujo').hide();
                $('#panel_seleccion_usuario').hide();
                $('#panel_aprobacion').hide();
                $('#checkbox_avanzar_flujo').prop('disabled', true).hide();
            }
            // Si es un paso de aprobación, ya mostramos el panel. Ocultar el resto.
            else if (isApprovalStep) {
                $('#panel_checkbox_flujo').hide();
                $('#panel_seleccion_usuario').hide();
                $('#btncerrarticket').hide();
            }
            // Si es un paso de selección de usuario, ya lo mostramos. Ocultar el resto.
            else if (isSelectionStep) {
                $('#panel_checkbox_flujo').hide();
                $('#btncerrarticket').hide();
            }
            // Si es el último paso: mostrar botón cerrar, ocultar avanzar
            else if (isLastStep) {
                $('#btncerrarticket').show().prop('disabled', false);
                $('#panel_checkbox_flujo').hide();
                $('#panel_seleccion_usuario').hide();
            }
            // Si NO es el último paso (y no es aprobación/selección): mostrar opción de avanzar
            else {
                if (canCloseStep) {
                    $('#btncerrarticket').show().prop('disabled', false);
                } else {
                    $('#btncerrarticket').hide(); // Ocultar cierre si no es el último paso y no permite cerrar
                }

                if (hasDecisions || hasNextLinear) {
                    var acciones = {
                        decisiones: ticketData.decisiones_disponibles || [],
                        siguiente_paso: hasNextLinear
                    };
                    $('#panel_checkbox_flujo').data('acciones', acciones).show();
                    $('#checkbox_avanzar_flujo').prop('disabled', false).show();
                }
            }
        } else {
            // No es usuario asignado: ocultar/desactivar todos los controles de acción
            $('#boxdetalleticket').hide(); // Ocultar toda la caja de respuesta
            $('#panel_checkbox_flujo').hide();
            $('#btncerrarticket').hide();
            $('#panel_aprobacion').hide();
            $('#panel_seleccion_usuario').hide();
        }

        // Refrescar el estado del botón de enviar al final
        updateEnviarButtonState();

        // === Manejo seguro y robusto de mermaid ===
        if (ticketData.timeline_graph && ticketData.timeline_graph.length > 0) {
            $('#panel_linea_tiempo').show();

            const mermaidContainer = document.querySelector("#panel_linea_tiempo .mermaid");
            mermaidContainer.innerHTML = '';
            let graph = ticketData.timeline_graph.trim();
            graph = graph.replace(/graph\s+(TD|TB)/i, 'graph LR');
            mermaidContainer.textContent = graph;

            setTimeout(function () {
                if (window.mermaid) {
                    try {
                        mermaid.initialize({ startOnLoad: false });
                    } catch (e) { }

                    try {
                        const renderResult = mermaid.render ? mermaid.render('mermaid_graph_' + Date.now(), graph) : null;

                        if (renderResult && typeof renderResult.then === 'function') {
                            renderResult.then(res => {
                                mermaidContainer.innerHTML = (res.svg || res);
                            }).catch(err => {
                                console.error("mermaid.render (promise) falló:", err);
                                try { mermaid.init(undefined, mermaidContainer); } catch (e) { console.error(e); }
                            });
                        } else if (renderResult) {
                            mermaidContainer.innerHTML = (renderResult.svg || renderResult);
                        } else {
                            try {
                                mermaid.init(undefined, mermaidContainer);
                            } catch (err) {
                                console.error("mermaid.init falló:", err);
                            }
                        }
                    } catch (err) {
                        console.error("Error al renderizar con mermaid.render:", err);
                        try { mermaid.init(undefined, mermaidContainer); } catch (e) { console.error(e); }
                    }
                } else {
                    console.error("Mermaid no está cargado en window cuando intentamos renderizar.");
                }
            }, 50);
        } else {
            $('#panel_linea_tiempo').hide();
        }

    });
}

$(document).on('click', '#btn_asignar_usuario', function () {
    var tick_id = getUrlParameter('ID');
    var usu_asig = $('#usuario_seleccionado').val();
    var usu_id = $('#user_idx').val();

    if (!usu_asig) {
        swal("Atención", "Por favor, seleccione un usuario para asignar el ticket.", "warning");
        return;
    }

    swal({
        title: "¿Estás seguro?",
        text: "Se asignará este ticket al usuario seleccionado.",
        type: "warning",
        showCancelButton: true,
        confirmButtonClass: "btn-primary",
        confirmButtonText: "Sí, asignar ahora",
        cancelButtonText: "Cancelar",
        closeOnConfirm: false
    }, function (isConfirm) {
        if (isConfirm) {
            $.post("../../controller/ticket.php?op=updateasignacion", { tick_id: tick_id, usu_asig: usu_asig, how_asig: usu_id })
                .done(function () {

                    // MODIFICADO: Redirigir después de asignar
                    swal({
                        title: "¡Asignado!",
                        text: "El ticket ha sido asignado. Redirigiendo a la lista...",
                        type: "success",
                        timer: 1600,
                        showConfirmButton: false
                    });
                    setTimeout(function () { window.location.href = "../../view/ConsultarTicket/"; }, 1700);

                })
                .fail(function () {
                    swal("Error", "No se pudo asignar el ticket.", "error");
                });
        }
    });
});

$(document).on('change', '#checkbox_avanzar_flujo', function () {
    decisionSeleccionada = null;
    var $chk = $(this);
    var isChecked = $chk.is(':checked');       // nuevo estado después del click
    var acciones = $('#panel_checkbox_flujo').data('acciones') || { decisiones: [], siguiente_paso: false };

    if (isChecked) {
        // El usuario intentó marcar el checkbox: interceptamos
        if (acciones.decisiones && acciones.decisiones.length > 0) {
            // Hay decisiones -> abrir modal para seleccionar (no dejar marcado hasta confirmar)
            $chk.prop('checked', false); // temporalmente desmarcar visualmente
            var options_html = '<option value="" selected disabled>-- Seleccione una opción --</option>';
            console.log("Decisiones disponibles:", acciones.decisiones);
            acciones.decisiones.forEach(function (d) {
                console.log("Procesando decision:", d.condicion_nombre, "Manual:", d.requiere_seleccion_manual, "Target:", d.target_step_id);
                options_html += `<option value="${d.condicion_nombre}" data-manual="${d.requiere_seleccion_manual}" data-target-step="${d.target_step_id}">${d.condicion_nombre}</option>`;
            });
            $('#select_siguiente_paso').html(options_html);
            $('#modal_seleccionar_paso_label').text('Seleccionar Decisión de Avance');
            $('#modal_seleccionar_paso .modal-body p').text('Este paso tiene múltiples caminos. Por favor, selecciona la decisión que deseas tomar:');
            $('#modal_seleccionar_paso').modal('show');
        } else if (acciones.siguiente_paso) {
            // Avance lineal -> sí se puede marcar directamente
            // dejarlo marcado y avisar
            $.post("../../controller/ticket.php?op=check_next_step_candidates", { tick_id: getUrlParameter('ID') }, function (resRaw) {
                try {
                    var requirements = JSON.parse(resRaw);
                    // Check for missing candidates
                    var missingRoles = []; // Array of requirement objects

                    if (Array.isArray(requirements)) {
                        requirements.forEach(function (req) {
                            if (!req.candidates || req.candidates.length === 0) {
                                missingRoles.push(req);
                            }
                        });
                    }

                    if (missingRoles.length > 0) {
                        // Fix: Open the modal explicitly so the user can resolve it NOW, 
                        // instead of waiting for the submit button.
                        showManualAssignmentModal(missingRoles);
                    } else {
                        // Optional: clear any previous manual assignments if everything is now automatic?
                        // window.currentManualAssignments = null; 
                        swal("Avance Lineal", "Al enviar su respuesta, el ticket avanzará al siguiente paso.", "info");
                    }
                } catch (e) {
                    console.error("Error parsing candidates check", e);
                }
            });
        } else {
            // No hay acciones -> revertir y avisar
            $chk.prop('checked', false);
            swal("Atención", "No hay acciones de avance disponibles.", "warning");
        }
    } else {
        // El usuario desmarcó el checkbox (no hacemos nada especial)
    }

    updateEnviarButtonState();
});



$(document).on('click', '#btn_confirmar_paso_seleccionado', function () {
    var $selectedOption = $('#select_siguiente_paso option:selected');
    var seleccion = $selectedOption.val();

    console.log("Opción seleccionada:", seleccion);

    if (seleccion && seleccion !== '') {
        decisionSeleccionada = seleccion; // Guardamos la decisión

        // Check for manual selection requirement
        var requiresManual = $selectedOption.data('manual');
        var targetStepId = $selectedOption.data('target-step');

        console.log("Requires Manual:", requiresManual);
        console.log("Target Step ID:", targetStepId);

        if (requiresManual == 1 && targetStepId) {
            console.log('Transition requires manual selection. Target Step:', targetStepId);
            $('#panel_seleccion_usuario').show();

            // Fetch users for the target step
            $.post("../../controller/ticket.php?op=get_usuarios_paso", { paso_id: targetStepId }, function (data) {
                var usuarios = JSON.parse(data);
                var options = '<option value="">Seleccionar Usuario</option>';
                usuarios.forEach(function (usuario) {
                    options += '<option value="' + usuario.usu_id + '">' + usuario.usu_nom + ' ' + usuario.usu_ape + ' (' + usuario.usu_correo + ')</option>';
                });
                $('#usuario_seleccionado').html(options);
                $('#usuario_seleccionado').select2();
                swal("Atención", "Esta transición requiere que selecciones manualmente al siguiente usuario.", "info");
            });
        } else {
            // Hide if not required (cleanup)
            $('#panel_seleccion_usuario').hide();
            $('#usuario_seleccionado').val('').trigger('change');
        }

        $('#checkbox_avanzar_flujo').prop('checked', true); // Marcamos el checkbox
        $('#modal_seleccionar_paso').modal('hide');

        if (requiresManual != 1) {
            swal("Decisión registrada", "Tu elección \"" + decisionSeleccionada + "\" se aplicará al enviar la respuesta.", "info");
        }

        // Cargar campos dinámicos para el paso destino de la decisión
        if (targetStepId) {
            loadStepFields(targetStepId);
        }

        updateEnviarButtonState();
    } else {
        swal("Atención", "Por favor, selecciona una opción para continuar.", "warning");
    }
});

// Cierre del modal de decisión
$('#modal_seleccionar_paso').on('hidden.bs.modal', function () {
    // Si el modal se cierra sin confirmar, y no hay decisión, desmarcar el check
    if (!decisionSeleccionada) {
        $('#checkbox_avanzar_flujo').prop('checked', false);
        updateEnviarButtonState();
    }
});


init();

function checkParallelStatus(tick_id, paso_id) {
    $.post("../../controller/ticket.php?op=listar_paralelo", { tick_id: tick_id, paso_id: paso_id }, function (data) {
        data = JSON.parse(data);
        if (data.length > 0) {
            $('#panel_paralelo').show();
            var html = '';
            var currentUserPending = false;
            var currentUserId = $('#user_idx').val();

            data.forEach(function (row) {
                var statusClass = row.estado == 'Pendiente' ? 'label-warning' : 'label-success';
                html += '<tr>';
                html += '<td>' + row.usu_nom + ' ' + row.usu_ape + '</td>';
                html += '<td><span class="label ' + statusClass + '">' + row.estado + '</span></td>';
                html += '<td>' + (row.fech_cierre ? row.fech_cierre : '-') + '</td>';
                html += '<td>' + (row.comentario ? row.comentario : '-') + '</td>';
                html += '</tr>';

                if (row.usu_id == currentUserId && row.estado == 'Pendiente') {
                    currentUserPending = true;
                }
            });
            $('#tabla_paralelo_body').html(html);

            if (currentUserPending) {
                $('#btnenviar').html('Terminar mi parte');
                $('#btnenviar').show();
                $('#panel_aprobacion').hide(); // Hide standard approval if parallel
                $('#panel_checkbox_flujo').hide(); // Hide standard flow advance
                updateEnviarButtonState(); // Re-evaluate button state
            } else {
                // If I am not pending (either done or not assigned), hide the main action button
                $('#btnenviar').hide();
                $('#panel_aprobacion').hide(); // Ensure these are hidden
                $('#panel_checkbox_flujo').hide(); // Ensure these are hidden

                if (data.some(r => r.usu_id == currentUserId)) {
                    // I was part of it and finished
                    // Maybe show a message "Esperando a otros..."
                }
            }
        } else {
            $('#panel_paralelo').hide();
        }
    });
}

function loadStepFields(pasoId) {
    $.post("../../controller/flujopaso.php?op=get_campos_paso", { paso_id: pasoId }, function (data) {
        var response = JSON.parse(data);
        if (response.requiere) {
            $('#campos_plantilla_inputs').empty();
            response.campos.forEach(function (campo) {
                var inputHtml = '';
                // Reutilizar lógica de renderizado de nuevoticket.js
                if (campo.campo_tipo === 'regional') {
                    inputHtml = `
                        <div class="col-md-6">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="campo_${campo.campo_id}">${campo.campo_nombre}</label>
                                <select class="form-control select2" id="campo_${campo.campo_id}" name="campo_${campo.campo_id}" required>
                                    <option value="">Seleccionar...</option>
                                </select>
                            </fieldset>
                        </div>
                    `;
                    $.post("../../controller/regional.php?op=combo", function (data) {
                        $('#campo_' + campo.campo_id).append(data);
                    });
                } else if (campo.campo_tipo === 'cargo') {
                    inputHtml = `
                        <div class="col-md-6">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="campo_${campo.campo_id}">${campo.campo_nombre}</label>
                                <select class="form-control select2" id="campo_${campo.campo_id}" name="campo_${campo.campo_id}" required>
                                    <option value="">Seleccionar...</option>
                                </select>
                            </fieldset>
                        </div>
                    `;
                    $.post("../../controller/cargo.php?op=combo", function (data) {
                        $('#campo_' + campo.campo_id).append(data);
                    });
                } else {
                    inputHtml = `
                        <div class="col-md-6">
                            <fieldset class="form-group">
                                <label class="form-label semibold" for="campo_${campo.campo_id}">${campo.campo_nombre}</label>
                                <input type="text" class="form-control" id="campo_${campo.campo_id}" name="campo_${campo.campo_id}" placeholder="${campo.campo_nombre}" required>
                            </fieldset>
                        </div>
                    `;
                }
                $('#campos_plantilla_inputs').append(inputHtml);
            });
            $('#panel_campos_plantilla').show();
        } else {
            $('#panel_campos_plantilla').hide();
            $('#campos_plantilla_inputs').empty();
        }
    });
}
// --- LOGICA DE ETIQUETAS ---

$(document).ready(function () {
    initEtiquetas();
});

function initEtiquetas() {
    var tick_id = getUrlParameter('ID');

    // Inicializar Select2 con formato para los badges
    $('#ticket_etiquetas').select2({
        placeholder: "Agregar etiqueta...",
        tags: false,
        tokenSeparators: [','],
        templateSelection: formatEtiquetaState,
        templateResult: formatEtiquetaState
    });

    // Cargar todas las etiquetas disponibles
    loadEtiquetasDisponibles(function () {
        // Cargar etiquetas asignadas al ticket actual una vez cargado el combo
        loadEtiquetasAsignadas(tick_id);
    });

    // Evento: Seleccionar etiqueta (Asignar)
    $('#ticket_etiquetas').on('select2:select', function (e) {
        var eti_id = e.params.data.id;
        var usu_id = $('#user_idx').val();
        // Evitar peticiones si no hay ID valido
        if (!eti_id) return;

        $.post("../../controller/etiqueta.php?op=asignar", { tick_id: tick_id, eti_id: eti_id, usu_id: usu_id }, function (data) {
            console.log("Etiqueta asignada: " + data);
        });
    });

    // Evento: Deseleccionar etiqueta (Eliminar)
    $('#ticket_etiquetas').on('select2:unselect', function (e) {
        var eti_id = e.params.data.id;
        if (!eti_id) return;

        $.post("../../controller/etiqueta.php?op=desligar", { tick_id: tick_id, eti_id: eti_id }, function (data) {
            console.log("Etiqueta desligada: " + data);
        });
    });

    // Abrir modal
    $('#btn_gestionar_etiquetas').click(function () {
        $('#modal_crear_etiqueta').modal('show');
        renderListaEtiquetasExistentes();
    });

    // Guardar nueva etiqueta desde modal
    $('#etiqueta_form').on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        // op=guardar por GET en url

        $.ajax({
            url: "../../controller/etiqueta.php?op=guardar",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response == "1") {
                    swal("Éxito", "Etiqueta creada correctamente", "success");
                    $('#etiqueta_form')[0].reset();
                    $('#modal_crear_etiqueta').modal('hide');
                    // Recargar combo manteniendo seleccion
                    // Primero guardamos lo seleccionado
                    var currentSelection = $('#ticket_etiquetas').val();

                    loadEtiquetasDisponibles(function () {
                        // Restaurar seleccion (aunque al recargar combo se pierde selection visual, val() lo restaura si los options existen)
                        // Pero mejor volver a cargar asignadas para asegurar consistencia
                        loadEtiquetasAsignadas(tick_id);
                    });
                } else {
                    swal("Error", "No se pudo crear la etiqueta", "error");
                }
            }
        });
    });
}

function formatEtiquetaState(state) {
    if (!state.id) { return state.text; }

    // Obtener color del atributo data-color del option
    var color = $(state.element).attr('data-color');
    // Si no tiene attr (e.g. initial load search), intentar buscar en objeto original si paso por AJAX (no es el caso aquí, es combo renderizado)
    // Select2 copia atributos del option al objeto data en versiones recientes, o accedemos via state.element

    if (!color) color = 'secondary';

    var labelClass = "label label-" + color;
    if (color == "secondary") labelClass = "label label-default";
    if (color == "dark") labelClass = "label label-primary";

    var $state = $(
        '<span class="' + labelClass + '">' + state.text + '</span>'
    );
    return $state;
}

function loadEtiquetasDisponibles(callback) {
    $.post("../../controller/etiqueta.php?op=combo", function (data) {
        $('#ticket_etiquetas').html(data);
        // Trigger change para que select2 se entere de los nuevos options?
        // No necesario si solo reemplazamos html, pero si queremos refrescar UI...
        $('#ticket_etiquetas').trigger('change.select2'); // Esto podría borrar la selección visual si no controlamos

        if (callback) callback();
    });
}

function loadEtiquetasAsignadas(tick_id) {
    $.post("../../controller/etiqueta.php?op=listar_x_ticket", { tick_id: tick_id }, function (data) {
        var assigned = JSON.parse(data);
        var selectedIds = [];
        assigned.forEach(function (tag) {
            selectedIds.push(tag.eti_id);
        });
        $('#ticket_etiquetas').val(selectedIds).trigger('change');
        // Nota: trigger('change') es suficiente para Select2 visual update. 
        // PERO disparará el evento 'select2:select'? 
        // En Select2 v4, val().trigger('change') NO dispara 'select2:select'. 
        // Dispara 'change', así que debemos asegurar que nuestro listener es 'select2:select' y no 'change'.
        // Mis listeners son 'select2:select' y 'select2:unselect', así que esto es seguro, no creará bucle.
    });
}

function renderListaEtiquetasExistentes() {
    // Reutilizar el combo para listar en el modal
    var options = $('#ticket_etiquetas option');
    var html = '';
    options.each(function () {
        var color = $(this).attr('data-color');
        var text = $(this).text();
        // Skip placeholders if any
        if ($(this).val()) {
            var badgeClass = "label label-" + (color == 'secondary' ? 'default' : color);
            html += '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                '<span class="' + badgeClass + '">' + text + '</span>' +
                '</li>';
        }
    });
    $('#lista_etiquetas_existentes').html(html);
}

$(document).on('click', '#btn_despacho_masivo', function () {
    $('#modal_seleccionar_excel').modal('show');
});

$(document).on('click', '#btn_confirmar_despacho', function () {
    var tick_id = getUrlParameter('ID');
    var fileInput = document.getElementById('file_despacho_masivo');

    if (fileInput.files.length === 0) {
        swal("Error", "Por favor seleccione un archivo.", "error");
        return;
    }

    var formData = new FormData();
    formData.append('tick_id', tick_id);
    formData.append('excel_file', fileInput.files[0]);

    // Show loading state
    var $btn = $(this);
    $btn.prop('disabled', true).text('Procesando...');

    $.ajax({
        url: "../../controller/ticket.php?op=dispatch_uploaded_excel",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
            $btn.prop('disabled', false).text('Procesar Despacho');
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    var msg = "Procesados: " + data.processed + ". Fallidos: " + data.failed + ".";
                    if (data.failed > 0) {
                        var errorMsg = data.errors.slice(0, 10).join("\n");
                        if (data.errors.length > 10) errorMsg += "\n... (" + (data.errors.length - 10) + " más)";
                        swal("Proceso completado con errores", msg + "\n\nDetalle:\n" + errorMsg, "warning");
                    } else {
                        swal("Éxito", msg, "success");
                    }
                    $('#modal_seleccionar_excel').modal('hide');
                    if (data.processed > 0) {
                        setTimeout(function () { location.reload(); }, 2000);
                    }
                } else {
                    swal("Error", data.message, "error");
                }
            } catch (e) {
                console.error(e);
                swal("Error", "Error al procesar la respuesta del servidor.", "error");
            }
        },
        error: function (e) {
            console.error(e);
            $btn.prop('disabled', false).text('Procesar Despacho');
            swal("Error", "Error en la petición AJAX.", "error");
        }
    });
});

// Helper function for Manual Assignment
function showManualAssignmentModal(missingRoles) {
    // Remove existing modal if any
    $('#manualAssignmentModal').remove();

    var modalHtml = `
    <div class="modal fade" id="manualAssignmentModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                        <i class="font-icon-close-2"></i>
                    </button>
                    <h4 class="modal-title">Asignación Manual Requerida</h4>
                </div>
                <div class="modal-body">
                    <p>No se encontraron usuarios automáticos para los siguientes roles. Por favor seleccione manualmente:</p>
                    <form id="manualAssignmentForm">
    `;

    missingRoles.forEach(function (role, index) {
        modalHtml += `
            <div class="form-group">
                <label class="form-label semibold" for="manual_role_${index}">${role.role_name}</label>
                <select class="form-control manual-role-select manual" id="manual_role_${index}" data-role-key="${role.role_key}" style="width:100%">
                    <option value="">Cargando usuarios...</option>
                </select>
            </div>
        `;
    });

    modalHtml += `
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-rounded btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-rounded btn-primary" id="btnConfirmManualAssignment">Confirmar y Enviar</button>
                </div>
            </div>
        </div>
    </div>
    `;

    $('body').append(modalHtml);

    // Initialise Select2 and Load Users
    // Initialise Select2 and Load Users using JSON data to prevent "undefined" strings
    $.post("../../controller/usuario.php?op=combo_usuarios_select2", function (data) {
        try {
            var userData = JSON.parse(data);
            console.log("Manual Assignment User Data:", userData);
            // Prepend a placeholder option
            userData.unshift({ id: "", text: "Seleccione..." });

            $('.manual-role-select').each(function () {
                // Clear and init select2 with data
                $(this).empty();
                $(this).select2({
                    dropdownParent: $('#manualAssignmentModal'),
                    data: userData,
                    width: '100%',
                    templateResult: function (d) {
                        if (d.text) return d.text;
                        // Debugging: show the object structure in dropdown
                        return "DBG: " + JSON.stringify(d);
                    },
                    templateSelection: function (d) {
                        if (d.text) return d.text;
                        return d.id;
                    }
                });
            });
        } catch (e) {
            console.error("Error loading users for manual assignment", e);
            swal("Error", "No se pudo cargar la lista de usuarios.", "error");
        }
    });

    $('#manualAssignmentModal').modal('show');

    // Handle Confirm
    $('#btnConfirmManualAssignment').on('click', function () {
        var assignments = [];
        var allSelected = true;

        $('.manual-role-select').each(function () {
            var val = $(this).val();
            // Need to handle both string and integer keys carefully
            var roleKey = $(this).data('role-key');

            if (!val) {
                allSelected = false;
                return false;
            }

            assignments.push({
                role_key: roleKey,
                usu_id: val
            });
        });

        if (!allSelected) {
            swal("Atención", "Debe seleccionar un usuario para todos los roles pendientes.", "warning");
            return;
        }

        // Store and Retry
        window.currentManualAssignments = assignments;
        $('#manualAssignmentModal').modal('hide');

        // Restore button state
        $('#btnenviar').prop('disabled', false);
        // Trigger click again to bypass the check (as manualAssignments is now set)
        $('#btnenviar').click();
    });
}
