var usu_id = $('#user_idx').val();
var allNotifications = [];
var selectedNotifications = new Set();
var currentTab = 'all'; // all, unread, read

function init() {
    loadNotificationFeed();
}

$(document).ready(function () {
    // Search Filter
    $('#search_notif').on('keyup', function () {
        applyFilters();
    });

    // Tab Filter
    $('.notif-tab').click(function () {
        $('.notif-tab').removeClass('active');
        $(this).addClass('active');
        currentTab = $(this).data('filter');
        applyFilters();
    });

    // Mark All Read
    $('#btn_mark_all_read').click(function () {
        swal({
            title: "¿Estás seguro?",
            text: "Se marcarán todas tus notificaciones como leídas.",
            type: "warning",
            showCancelButton: true,
            confirmButtonClass: "btn-primary",
            confirmButtonText: "Sí, marcar todo",
            cancelButtonText: "Cancelar",
            // FIX: Close on confirm to prevent body lock issues
            closeOnConfirm: true
        }, function (isConfirm) {
            if (isConfirm) {
                $.post("../../controller/notificacion.php?op=leido_todas", { usu_id: usu_id }, function () {
                    loadNotificationFeed();
                    // Optional: Small toast instead of big alert to keep flow smooth
                    // swal("¡Listo!", "...", "success"); 

                    // FIX: Ensure scrolling is enabled if swal locked it
                    $('body').removeClass('stop-scrolling');

                    if (typeof actualizarContadorDeNotificaciones === 'function') {
                        actualizarContadorDeNotificaciones();
                    }
                });
            }
        });
    });

    // Bulk Actions
    $('#btn_mark_read_sel').click(function () {
        processBulkAction('leido_varios', 0);
    });

    $('#btn_mark_unread_sel').click(function () {
        processBulkAction('no_leido_varios', 1);
    });
});

function loadNotificationFeed() {
    $.post("../../controller/notificacion.php?op=listar_historial_feed", { usu_id: usu_id }, function (data) {
        try {
            allNotifications = JSON.parse(data);
            selectedNotifications.clear();
            updateSelectionUI();
            applyFilters();
        } catch (e) {
            console.error("Error parsing notification data", e);
            $('#notification_feed').html('<div class="text-center p-4 text-danger">Error al cargar notificaciones.</div>');
        }
    });
}

function applyFilters() {
    var query = $('#search_notif').val().toLowerCase();

    var filtered = allNotifications.filter(function (notif) {
        // 1. Text Filter
        var matchText = notif.not_mensaje.toLowerCase().indexOf(query) > -1;

        // 2. Tab Filter
        var matchTab = true;
        if (currentTab === 'unread') {
            matchTab = (notif.est == 1);
        } else if (currentTab === 'read') {
            matchTab = (notif.est == 0);
        }

        return matchText && matchTab;
    });

    renderFeed(filtered);
}

function renderFeed(notifications) {
    var container = $('#notification_feed');
    container.empty();

    if (notifications.length === 0) {
        container.html('<div class="text-center p-4 text-muted">No se encontraron notificaciones en esta sección.</div>');
        return;
    }

    notifications.forEach(function (notif) {
        var isUnread = notif.est == 1;
        var unreadClass = isUnread ? 'unread' : '';
        var date = new Date(notif.fech_not);
        var formattedDate = date.toLocaleString('es-ES', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        var isChecked = selectedNotifications.has(String(notif.not_id)) ? 'checked' : '';

        var html = `
            <div class="notification-item ${unreadClass}">
                <div class="notification-checkbox-container">
                    <input type="checkbox" class="notification-checkbox" value="${notif.not_id}" ${isChecked} onchange="toggleSelection(this)">
                </div>
                <div class="notification-item-clickable" onclick="verNotificacion(${notif.not_id}, ${notif.tick_id})">
                    <div class="notification-avatar">
                        <i class="font-icon-alarm"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-message">
                            ${notif.not_mensaje}
                        </div>
                        <div class="notification-meta">
                            <div class="notification-time">
                                <i class="font-icon font-icon-clock"></i> ${formattedDate}
                            </div>
                            <div class="notification-status-dot" title="${isUnread ? 'No Leído' : 'Leído'}" style="margin-left:auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.append(html);
    });
}

function toggleSelection(checkbox) {
    var id = checkbox.value;
    if (checkbox.checked) {
        selectedNotifications.add(id);
    } else {
        selectedNotifications.delete(id);
    }
    updateSelectionUI();
}

function updateSelectionUI() {
    var count = selectedNotifications.size;

    if (count > 0) {
        $('#selection_count').text(count + ' seleccionados').show();
        $('#bulk_actions').show();
        $('#btn_mark_all_read').hide();
    } else {
        $('#selection_count').hide();
        $('#bulk_actions').hide();
        $('#btn_mark_all_read').show();
    }
}

function processBulkAction(op, newState) {
    var ids = Array.from(selectedNotifications).join(',');

    $.post("../../controller/notificacion.php?op=" + op, { not_ids: ids }, function () {
        loadNotificationFeed();
        swal("¡Actualizado!", "Las notificaciones seleccionadas han sido actualizadas.", "success");
        // FIX: Ensure body is cleaned just in case swal acts up
        $('body').removeClass('stop-scrolling');
    });
}

function verNotificacion(not_id, tick_id) {
    $.post("../../controller/notificacion.php?op=leido", { not_id: not_id }, function (data) {
        window.location.href = "../../view/DetalleTicket/?ID=" + tick_id;
    });
}

init();
