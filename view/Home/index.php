<?php
require_once('../../config/conexion.php');
if (isset($_SESSION["usu_id"])) {
?>
    <!DOCTYPE html>
    <html>
    <?php require_once('../MainHead/head.php') ?>
    <title>Dashboard</title>
    </head>

    <body class="with-side-menu">
        <?php require_once('../MainHeader/header.php') ?>
        <div class="mobile-menu-left-overlay"></div>
        <?php require_once('../MainNav/nav.php') ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-4">
                        <article class="statistic-box red">
                            <div>
                                <div class="number" id="lblasignados">0</div>
                                <div class="caption">
                                    <div>Pasos Asignados</div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="col-xl-4">
                        <article class="statistic-box purple">
                            <div>
                                <div class="number" id="lblfinalizados">0</div>
                                <div class="caption">
                                    <div>Pasos Finalizados</div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="col-xl-4">
                        <article class="statistic-box yellow">
                            <div>
                                <div class="number" id="lblpromedio">0 min</div>
                                <div class="caption">
                                    <div>Tiempo Promedio Respuesta</div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once('../MainJs/js.php') ?>
        <script type="text/javascript" src="../Home/home.js"></script>
        <script type="text/javascript" src="../notificacion.js"></script>
    </body>

    </html>
<?php
} else {
    $conectar = new Conectar();
    header("Location: " . $conectar->ruta() . "index.php");
}
?>