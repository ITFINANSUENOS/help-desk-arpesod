function init() {
    // Esta función se puede dejar vacía o usar para inicializar otros componentes si es necesario.
}

$(document).ready(function () {
    // Obtenemos el ID del ticket desde la URL
    var tick_id = getUrlParameter('ID');

    // Cargar la cabecera del ticket (título, estado, etc.)
    $.post("../../controller/ticket.php?op=mostrar", { tick_id: tick_id }, function (data) {
        data = JSON.parse(data);
        $('#lbltickestadoh').html(data.tick_estado);
        $('#lblprioridadh').html(data.pd_nom);
        $('#lblnomusuarioh').html(data.usu_nom + ' ' + data.usu_ape);
        $('#lblfechacreah').html(data.fech_crea);
        $('#lblticketidh').html("Detalle del tikect #" + data.tick_id);
        $('#cat_nom').val(data.cat_nom);
        $('#cats_nom').val(data.cats_nom);
        $('#emp_id').val(data.emp_nom);
        $('#dp_id').val(data.dp_nom);
        $('#tick_titulo').val(data.tick_titulo);
        // Usamos summernote para mostrar la descripción con formato
        $('#tickd_descripusu').summernote('code', data.tick_descrip);

        // Mostrar contador de días transcurridos (solo tickets abiertos)
        $('#panel_dias_transcurridos').remove(); // Limpiar previo
        if (data.campos_dias_transcurridos && data.campos_dias_transcurridos.length > 0) {
            var diasHtml = '<div id="panel_dias_transcurridos" class="alert alert-warning" style="margin-top: 15px; margin-bottom: 15px;"><h5><i class="fa fa-clock-o"></i> Días Transcurridos</h5><ul style="margin-bottom: 0;">';
            data.campos_dias_transcurridos.forEach(function (campo) {
                if (campo.formato) {
                    diasHtml += '<li><strong>' + campo.campo_nombre + ':</strong> ' + campo.formato + ' (desde ' + campo.valor + ')</li>';
                }
            });
            diasHtml += '</ul></div>';
            $('#panel_linea_tiempo').after(diasHtml);
        }

        if (data.timeline_graph && data.timeline_graph.length > 0) {
            $('#panel_linea_tiempo').show();

            const mermaidContainer = document.querySelector("#panel_linea_tiempo .mermaid");
            // Limpia el contenedor antes de dibujar
            mermaidContainer.innerHTML = '';

            // Normalizamos el texto: quitar espacios excesivos, convertir ; a saltos si quieres
            let graph = data.timeline_graph.trim();
            // opcional: si ves que el ; a final de línea causa problemas, reemplaza:
            graph = graph.replace(/graph\s+(TD|TB)/i, 'graph LR');

            // Inyectar como textContent para evitar que el navegador parsee HTML
            mermaidContainer.textContent = graph;

            // Asegurarnos que el panel está visible antes de renderizar
            // (en algunas versiones mermaid necesita layout en elementos visibles)
            setTimeout(function () {
                if (window.mermaid) {
                    try {
                        // Inicializamos sin startOnLoad (no queremos que busque automáticamente)
                        mermaid.initialize({ startOnLoad: false });
                    } catch (e) {
                        // algunas versiones ignoran initialize, no pasa nada
                    }

                    // Intentamos mermaid.render (maneja versiones modernas y devuelve SVG)
                    try {
                        const renderResult = mermaid.render
                            ? mermaid.render('mermaid_graph_' + Date.now(), graph)
                            : null;

                        // renderResult puede ser promesa o resultado inmediato
                        if (renderResult && typeof renderResult.then === 'function') {
                            renderResult.then(res => {
                                mermaidContainer.innerHTML = (res.svg || res);
                            }).catch(err => {
                                console.error("mermaid.render (promise) falló:", err);
                                // fallback a init
                                try { mermaid.init(undefined, mermaidContainer); } catch (e) { console.error(e); }
                            });
                        } else if (renderResult) {
                            // resultado inmediato
                            mermaidContainer.innerHTML = (renderResult.svg || renderResult);
                        } else {
                            // fallback para versiones que usan init
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
            }, 50); // un pequeño delay ayuda si el contenedor estaba oculto
        } else {
            $('#panel_linea_tiempo').hide();
        }
    });

    // Cargar el historial completo del ticket (comentarios, asignaciones, etc.)
    $.post("../../controller/ticket.php?op=listarhistorial", { tick_id: tick_id }, function (data) {
        $('#lbldetalle').html(data);
    });

    // Inicializar el editor Summernote en modo "solo lectura"
    $('#tickd_descripusu').summernote({
        height: 150,
        toolbar: [], // Sin barra de herramientas
    });
    // Deshabilitar la edición
    $('#tickd_descripusu').summernote('disable');

});

// Función para obtener parámetros de la URL
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
};

init();
