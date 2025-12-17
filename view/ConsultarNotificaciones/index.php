<?php
require_once('../../config/conexion.php');
if (isset($_SESSION["usu_id"])) {
?>
    <!DOCTYPE html>
    <html>
    <?php require_once('../MainHead/head.php') ?>
    <title>Centro de Notificaciones</title>
    <style>
        .notification-feed {
            width: 100%;
        }

        .notification-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 15px rgba(0, 0, 0, .05);
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-area {
            flex-grow: 1;
            max-width: 300px;
            position: relative;
        }

        .search-area input {
            width: 100%;
            padding-left: 35px;
            border-radius: 20px;
        }

        .search-area i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #919fa9;
        }

        .actions-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Tabs Style */
        .notification-tabs {
            display: flex;
            gap: 5px;
            margin-right: auto;
            /* Push actions to right */
        }

        .notif-tab {
            padding: 6px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #6c7a89;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .notif-tab:hover {
            background-color: #f1f1f1;
        }

        .notif-tab.active {
            background-color: #00a8ff;
            color: #fff;
            box-shadow: 0 2px 5px rgba(0, 168, 255, 0.3);
        }

        /* Notification Item */
        .notification-item {
            background: #fff;
            border-bottom: 1px solid #ebedf2;
            padding: 20px 25px;
            display: flex;
            align-items: flex-start;
            transition: all 0.2s ease;
            margin-bottom: 1px;
            position: relative;
        }

        .notification-checkbox-container {
            padding-top: 12px;
            padding-right: 15px;
        }

        .notification-checkbox {
            transform: scale(1.2);
            cursor: pointer;
        }

        .notification-item-clickable {
            flex-grow: 1;
            display: flex;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: #f5f8fa;
        }

        .notification-item.unread {
            background-color: #f2f9ff;
            border-left: 4px solid #00a8ff;
        }

        .notification-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: #e0e6ed;
            color: #919fa9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notification-item.unread .notification-avatar {
            background-color: #00a8ff;
            color: #fff;
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-message {
            color: #3e405e;
            font-size: 15px;
            margin-bottom: 8px;
            line-height: 1.5;
            font-weight: 400;
        }

        .notification-item.unread .notification-message {
            font-weight: 600;
            color: #1b1e2c;
        }

        .notification-meta {
            font-size: 13px;
            color: #919fa9;
            display: flex;
            align-items: center;
        }

        .notification-time i {
            margin-right: 6px;
        }

        .box-typical-feed {
            background: #fff;
            box-shadow: 0 1px 15px rgba(0, 0, 0, .05);
            border-radius: 4px;
            overflow: hidden;
            border: none;
        }

        .selection-count {
            margin-right: 15px;
            font-weight: 600;
            color: #00a8ff;
            display: none;
        }
    </style>
    </head>

    <body class="with-side-menu">

        <?php require_once('../MainHeader/header.php') ?>

        <div class="mobile-menu-left-overlay"></div>

        <?php require_once('../MainNav/nav.php') ?>

        <!-- contenido -->
        <div class="page-content">
            <div class="container-fluid">
                <header class="section-header">
                    <div class="tbl">
                        <div class="tbl-row">
                            <div class="tbl-cell">
                                <h3>Centro de Notificaciones</h3>
                                <ol class="breadcrumb breadcrumb-simple">
                                    <li><a href="..\Home\">Home</a></li>
                                    <li class="active">Notificaciones</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="notification-toolbar">
                    <div class="search-area">
                        <i class="font-icon-search"></i>
                        <input type="text" class="form-control" id="search_notif" placeholder="Buscar...">
                    </div>

                    <div class="notification-tabs">
                        <div class="notif-tab active" data-filter="all">Todas</div>
                        <div class="notif-tab" data-filter="unread">No Leídas</div>
                        <div class="notif-tab" data-filter="read">Leídas</div>
                    </div>

                    <div class="actions-area">
                        <span class="selection-count" id="selection_count">0 seleccionados</span>

                        <div id="bulk_actions" style="display: none;">
                            <button type="button" class="btn btn-sm btn-inline btn-primary-outline" id="btn_mark_read_sel" title="Marcar como Leídos">
                                <i class="font-icon font-icon-ok"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-inline btn-secondary-outline" id="btn_mark_unread_sel" title="Marcar como No Leídos">
                                <i class="font-icon font-icon-del"></i>
                            </button>
                        </div>

                        <button type="button" class="btn btn-sm btn-inline btn-primary" id="btn_mark_all_read">
                            <i class="font-icon font-icon-check-circle"></i> Marcar Todo Leído
                        </button>
                    </div>
                </div>

                <div class="box-typical box-typical-feed" id="notification_feed">
                    <!-- Notifications will be loaded here -->
                    <div class="text-center p-4">
                        <i class="fa fa-spinner fa-spin fa-2x"></i> <br />
                        <span class="text-muted mt-2 d-inline-block">Cargando historial...</span>
                    </div>
                </div>

            </div>
        </div>
        <!-- contenido -->

        <?php require_once('../MainJs/js.php') ?>
        <script type="text/javascript" src="consultarnotificaciones.js"></script>

    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>