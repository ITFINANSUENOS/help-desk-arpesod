var dynamicChart;
var currentLevel = 0; // 0: Dept, 1: User, 2: Subcat
var currentFilters = {
    dp_id: null,
    car_id: null,
    target_usu_id: null,
    cats_id: null,
    view_mode: 'dept'
};

// Premium Colors (HSL)
function getColor(index) {
    var hue = index * 137.508; // Golden angle for distribution
    return `hsla(${hue % 360}, 70%, 50%, 0.7)`;
}

function init() { }

$(document).ready(function () {
    var usu_id = $('#user_idx').val();

    // KPI Cards (Original Logic)
    $.post("../../controller/kpi.php?op=menukpi", { usu_id: usu_id }, function (data) {
        data = JSON.parse(data);
        $('#lblasignados').html(data.asignados);
        $('#lblfinalizados').html(data.finalizados);
        $('#lblpromedio').html(data.promedio_formato);
    });

    // Initial Load (Level 0)
    loadDynamicData(usu_id, {});

    // Load Detailed Stats Logic
    if (typeof loadDetailedStats === 'function') {
        loadDetailedStats();
    } else {
        console.warn('loadDetailedStats function not found yet.');
    }
});

/* =========================================================
 *  ADVANCED ANALYSIS LOGIC
 * ========================================================= */

var currentSearchTerm = '';

// Load Subcategories Combo
$.post("../../controller/subcategoria.php?op=combo_usuario_tickets", function (data) {
    $('#cmbSubcategoria').html(data);
});

$('#btnSearchFlow').click(function () {
    var subcatId = $('#cmbSubcategoria').val();
    var term = $('#cmbSubcategoria option:selected').text();

    if (!subcatId || subcatId === '') {
        swal("Error", "Selecciona una subcategoría.", "warning");
        return;
    }
    refreshFlowChart(term);
});

function refreshFlowChart(term) {
    currentSearchTerm = term;
    var usu_id = $('#user_idx').val();
    var payload = { subcat_name: term, usu_id: usu_id };

    if (currentFilters && currentFilters.target_usu_id) {
        payload.target_usu_id = currentFilters.target_usu_id;
    }

    $.post("../../controller/kpi.php?op=subcat_metrics", payload, function (data) {
        try {
            var res = JSON.parse(data);
            if (!res.found) {
                // Handle forbidden
                if (res.forbidden) {
                    swal("Acceso Denegado", "No tienes permiso para ver esta subcategoría.", "error");
                } else {
                    $('#flowErrorMsg').show();
                }
                return;
            }
            $('#flowErrorMsg').hide();

            // Adjust chart title if filtered by user
            var titleSuffix = "";
            if (currentFilters && currentFilters.target_usu_id && currentFilters.usu_nom) {
                titleSuffix = ` (${currentFilters.usu_nom})`;
            }

            renderFlowChart(res.subcat_name + titleSuffix, res.chart_data);

            // Also Filter User Stats
            loadDetailedStats(term);
        } catch (e) {
            console.error("Error parsing subcat metrics", e);
        }
    });
}

var flowChartInstance;

function renderFlowChart(name, data) {
    var ctx = document.getElementById('flowChart').getContext('2d');

    // Process Data
    var labels = data.map(item => item.step_name);
    var values = data.map(item => item.avg_minutes);

    // Highlight colors
    var pointColors = data.map(item => item.is_novedad ? 'red' : 'rgba(75, 192, 192, 1)');
    var pointRadii = data.map(item => item.is_novedad ? 6 : 3);

    if (flowChartInstance) flowChartInstance.destroy();

    flowChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Promedio Tiempo (Min) - ' + name,
                data: values,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                pointBackgroundColor: pointColors,
                pointRadius: pointRadii,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Minutos' } }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            var idx = context.dataIndex;
                            var item = data[idx];
                            var label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.parsed.y + ' min';
                            if (item.is_novedad) {
                                label += ' (Intervención Externa)';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
}

function loadDetailedStats(subcatName) {
    var usu_id = $('#user_idx').val();
    var payload = { usu_id: usu_id };
    if (subcatName) payload.subcat_name = subcatName;

    // Add target_usu_id if it exists in current filters (Drill-down)
    if (currentFilters && currentFilters.target_usu_id) {
        payload.target_usu_id = currentFilters.target_usu_id;
    }

    $.post("../../controller/kpi.php?op=detailed_stats", payload, function (data) {
        try {
            var stats = JSON.parse(data);
            var html = '';

            stats.forEach(function (u) {
                var novLink = `<b>${u.nov_count}</b>`;
                if (u.nov_count > 0) {
                    novLink = `<a href="#" onclick="viewNovedadesDetails(${u.usu_id}); return false;" class="text-primary" title="Ver detalle">${u.nov_count}</a>`;
                }

                html += `<tr>
                    <td class="font-w600">${u.usu_nom}</td>
                    <td class="text-center">
                        <a href="#" onclick="viewPerformanceDetails(${u.usu_id}, 'on_time'); return false;" class="badge badge-success" title="A Tiempo">${u.on_time}</a> / 
                        <a href="#" onclick="viewPerformanceDetails(${u.usu_id}, 'late'); return false;" class="badge badge-danger" title="Atrasados">${u.late}</a>
                    </td>
                    <td class="text-center">
                        <a href="#" onclick="viewPerformanceDetails(${u.usu_id}, 'created'); return false;" class="label label-primary" title="Ver Creados">${u.tick_created}</a>
                    </td>
                    <td class="text-center">
                        ${novLink} <br>
                        <small class="text-muted">${u.nov_avg_time} min</small>
                    </td>
                    <td class="text-center">
                        <a href="#" onclick="viewErrorDetails(${u.usu_id}, 'process'); return false;" class="badge badge-warning" title="Errores de Proceso">${u.err_process}</a> / 
                        <a href="#" onclick="viewErrorDetails(${u.usu_id}, 'info'); return false;" class="badge badge-info" title="Errores Informativos">${u.err_info}</a>
                    </td>
                </tr>`;
            });

            $('#tbDetailedStats').html(html);
        } catch (e) {
            console.error('Error parsing detailed stats:', e);
        }
    });
}
// Removed duplication to keep file clean

function viewNovedadesDetails(usu_id) {
    var payload = { usu_id: usu_id };
    if (currentSearchTerm) payload.subcat_name = currentSearchTerm;

    $.post("../../controller/kpi.php?op=user_novedades_details", payload, function (data) {
        var novedades = JSON.parse(data);
        var html = '';
        novedades.forEach(function (n) {
            var desc = n.descripcion_novedad || 'Sin descripción';
            // Replace newlines with <br>
            desc = desc.replace(/\n/g, '<br>');

            html += `<tr>
                <td><a href="../DetalleTicket/?ID=${n.tick_id}" target="_blank">#${n.tick_id}</a></td>
                <td>${desc}</td>
                <td>${n.fecha_inicio}</td>
                <td>${n.fecha_fin}</td>
                <td><span class="label label-danger">${n.duracion_min} min</span></td>
            </tr>`;
        });
        $('#tbNovedadesBody').html(html);
        $('#modalNovedades').modal('show');
    });
}

function viewErrorDetails(usu_id, type) {
    var payload = { usu_id: usu_id, type: type };
    if (currentSearchTerm) payload.subcat_name = currentSearchTerm;

    $('#lblTitleError').text(type === 'process' ? 'Errores de Proceso' : 'Errores Informativos');

    $.post("../../controller/kpi.php?op=user_error_details", payload, function (data) {
        var errores = JSON.parse(data);
        var html = '';
        if (errores) {
            errores.forEach(function (e) {
                var desc = e.error_descrip || 'Sin descripción';
                // Replace newlines with <br>
                desc = desc.replace(/\n/g, '<br>');

                html += `<tr>
                    <td><a href="../DetalleTicket/?ID=${e.tick_id}" target="_blank">#${e.tick_id}</a></td>
                    <td>${e.answer_nom}</td>
                    <td>${desc}</td>
                    <td>${e.fech_crea}</td>
                </tr>`;
            });
        }
        $('#tbErroresBody').html(html);
        $('#modalErrores').modal('show');
    });
}

function viewPerformanceDetails(usu_id, type) {
    var payload = { usu_id: usu_id, type: type };
    if (currentSearchTerm) payload.subcat_name = currentSearchTerm;

    $('#lblTitleDesempeno').text(
        type === 'on_time' ? 'Tickets A Tiempo' :
            type === 'late' ? 'Tickets Atrasados/Vencidos' :
                'Tickets Creados'
    );

    $.post("../../controller/kpi.php?op=user_performance_details", payload, function (data) {
        var items = JSON.parse(data);
        var html = '';
        items.forEach(function (i) {
            var badgeClass = 'label-default';
            if (type === 'on_time') badgeClass = 'label-success';
            else if (type === 'late') badgeClass = 'label-danger';
            else if (type === 'created') badgeClass = 'label-primary';

            // Check event type
            var eventoBadge = 'label-default';
            var eventoText = i.tipo_evento || 'Asignación';
            if (eventoText === 'Error') eventoBadge = 'label-danger';
            if (eventoText === 'Novedad') eventoBadge = 'label-warning';
            if (eventoText === 'Devolución') eventoBadge = 'label-info';
            if (eventoText === 'Asignación') eventoBadge = 'label-success'; // Or secondary

            html += `<tr>
                <td><a href="../DetalleTicket/?ID=${i.tick_id}" target="_blank">#${i.tick_id}</a></td>
                <td>${i.tick_titulo}</td>
                <td><span class="label ${badgeClass}">${i.estado_tiempo_paso || i.tick_estado || 'N/A'}</span></td>
                <td><span class="label ${eventoBadge}">${eventoText}</span></td>
                <td>${i.fech_asig}</td>
            </tr>`;
        });
        $('#tbDesempenoBody').html(html);
        $('#modalDesempeno').modal('show');
    });
}

/* =========================================================
 *  DYNAMIC CHART LOGIC (Updated for Cargo/Dept)
 * ========================================================= */



function switchViewMode(mode) {
    if (currentFilters.view_mode === mode) return;
    currentFilters.view_mode = mode;

    if (mode === 'dept') {
        $('#btnViewDept').removeClass('btn-default').addClass('btn-primary');
        $('#btnViewCargo').removeClass('btn-primary').addClass('btn-default');
    } else {
        $('#btnViewDept').removeClass('btn-primary').addClass('btn-default');
        $('#btnViewCargo').removeClass('btn-default').addClass('btn-primary');
    }
    resetChart();
}

function switchDrillGroup(group) {
    if (currentFilters.group_by === group) return;
    currentFilters.group_by = group;

    if (group === 'users') {
        $('#btnGroupUsers').removeClass('btn-default').addClass('btn-primary');
        $('#btnGroupCats').removeClass('btn-primary').addClass('btn-default');
    } else {
        $('#btnGroupUsers').removeClass('btn-primary').addClass('btn-default');
        $('#btnGroupCats').removeClass('btn-default').addClass('btn-primary');
    }

    // If we are inside a department, reload data
    if (currentFilters.dp_id) {
        loadDynamicData(null, currentFilters); // Pass null usu_id, let function handle it
    }
}

function loadDynamicData(usu_id, filters) {
    var uid = usu_id || $('#user_idx').val();
    var payload = { usu_id: uid, view_mode: filters.view_mode };

    if (filters.dp_id) payload.dp_id = filters.dp_id;
    if (filters.car_id) payload.car_id = filters.car_id;
    if (filters.target_usu_id) payload.target_usu_id = filters.target_usu_id;
    if (filters.cat_id) payload.cat_id = filters.cat_id;

    // Pass group_by if in Dept and NOT deep filtered
    if (filters.dp_id && !filters.cat_id && !filters.target_usu_id) {
        payload.group_by = filters.group_by;
    }

    $.post("../../controller/kpi.php?op=dynamic_chart", payload, function (data) {
        var res = JSON.parse(data);
        renderChart(res, res.chartLevel);

        // Sync Table and Flow Chart with Chart Context
        if (typeof refreshFlowChart === 'function' && currentSearchTerm) {
            refreshFlowChart(currentSearchTerm);
        } else {
            loadDetailedStats();
        }
    });
}

var dynamicChart;

function renderChart(data, level) {
    var ctx = document.getElementById('dynamicChart').getContext('2d');
    var chartData = data.chartData;

    // Show/Hide Toggle
    // Only show if level is 'user' (inside dept) OR 'category' (inside dept)
    if (currentFilters.dp_id && !currentFilters.cat_id && !currentFilters.target_usu_id) {
        $('#grpDrillToggle').show();
    } else {
        $('#grpDrillToggle').hide();
    }

    // Also, handle button states if reset
    if (!currentFilters.dp_id) {
        $('#grpDrillToggle').hide();
    }

    // Process Data
    var labels = chartData.map(item => item.label);
    var updatedData = chartData.map(item => item.value);
    var ids = chartData.map(item => item.id);
    var colors = chartData.map((_, i) => getColor(i));

    if (dynamicChart) dynamicChart.destroy();

    var labelStr = "Tickets";
    if (level === 'dept') labelStr = "Tickets por Departamento";
    if (level === 'cargo') labelStr = "Tickets por Cargo";
    if (level === 'user') labelStr = "Tickets por Usuario";
    if (level === 'category') labelStr = "Tickets por Categoría";
    if (level === 'subcat') labelStr = "Tickets por Subcategoría";
    if (level === 'status') labelStr = "Tickets por Estado";

    $('#chartTitle').text(labelStr);
    updateBreadcrumb(level);

    dynamicChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: labelStr,
                data: updatedData,
                backgroundColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: function (c, i) {
                var e = i[0];
                if (!e) return;
                var index = e.index;
                var selectedId = ids[index];
                var selectedLabel = labels[index];
                handleDrillDown(level, selectedId, selectedLabel);
            },
            scales: { y: { beginAtZero: true } }
        }
    });

    if (currentFilters.dp_id || currentFilters.car_id) {
        $('#btnBack').show();
        $('#btnViewDept').prop('disabled', true);
        $('#btnViewCargo').prop('disabled', true);
    } else {
        $('#btnBack').hide();
        $('#btnViewDept').prop('disabled', false);
        $('#btnViewCargo').prop('disabled', false);
    }
}

function handleDrillDown(currentLevelStr, id, label) {
    var usu_id = $('#user_idx').val();

    if (currentLevelStr === 'dept') {
        currentFilters.dp_id = id;
        currentFilters.dp_nom = label;
    } else if (currentLevelStr === 'cargo') {
        currentFilters.car_id = id;
        currentFilters.car_nom = label;
    } else if (currentLevelStr === 'category') {
        currentFilters.cat_id = id; // Filter by this category
        // Breadcrumb update handled by renderChart calling updateBreadcrumb based on level
    } else if (currentLevelStr === 'user') {
        currentFilters.target_usu_id = id;
        currentFilters.usu_nom = label;
    } else if (currentLevelStr === 'subcat') {
        currentFilters.cats_id = id;
        currentSearchTerm = label; // Trigger Flow Sync in loadDynamicData

        // Sync Dropdown
        $("#cmbSubcategoria option").filter(function () {
            return $(this).text() == label;
        }).prop('selected', true);
    }

    loadDynamicData(usu_id, currentFilters);
}

function updateBreadcrumb(level) {
    var html = '<li class="breadcrumb-item"><a href="#" onclick="resetChart()">Inicio</a></li>';

    if (currentFilters.dp_id) {
        html += `<li class="breadcrumb-item ${!currentFilters.cat_id && !currentFilters.target_usu_id ? 'active' : ''}">${currentFilters.dp_nom}</li>`;
    }
    if (currentFilters.cat_id) {
        // Need label for category? We don't store "cat_nom" in filters globally usually, 
        // but for breadcrumb we might want it. 
        // For now, simpler: "Categoría"
        html += `<li class="breadcrumb-item active">Categoría</li>`;
    }
    if (currentFilters.car_id) {
        html += `<li class="breadcrumb-item active">${currentFilters.car_nom}</li>`;
    }
    if (currentFilters.target_usu_id) {
        html += `<li class="breadcrumb-item active">${currentFilters.usu_nom}</li>`;
    }

    $('#chartBreadcrumb').html(html);
}

function resetChart() {
    var mode = currentFilters.view_mode || 'dept';
    currentFilters = { dp_id: null, car_id: null, target_usu_id: null, cats_id: null, cat_id: null, view_mode: mode, group_by: 'users' }; // Reset group_by too? Or keep?
    // Reset buttons
    $('#btnGroupUsers').removeClass('btn-default').addClass('btn-primary');
    $('#btnGroupCats').removeClass('btn-primary').addClass('btn-default');

    var usu_id = $('#user_idx').val();
    loadDynamicData(usu_id, currentFilters);
    $('#btnBack').hide();
    $('#grpDrillToggle').hide();
}

$('#btnBack').click(function () {
    // Logic to go back one level
    if (currentFilters.target_usu_id) {
        currentFilters.target_usu_id = null;
    } else if (currentFilters.cat_id) {
        currentFilters.cat_id = null;
    } else if (currentFilters.dp_id) {
        currentFilters.dp_id = null;
        $('#btnBack').hide();
    } else if (currentFilters.car_id) {
        currentFilters.car_id = null;
        $('#btnBack').hide();
    }
    var usu_id = $('#user_idx').val();
    loadDynamicData(usu_id, currentFilters);
});

init();
