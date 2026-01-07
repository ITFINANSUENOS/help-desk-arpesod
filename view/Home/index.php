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

                <input type="hidden" id="user_idx" value="<?php echo $_SESSION["usu_id"] ?>">
                <!-- ORIGINAL KPI CARDS -->
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
                                    <div>Tiempo Mediana Respuesta</div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- BREADCRUMB & DYNAMIC CHART -->
                <div class="row">
                    <div class="col-xl-12">
                        <section class="card">
                            <header class="card-header">
                                <span id="chartTitle">Desempeño por Departamento</span>
                                <div class="float-right">
                                    <div class="btn-group btn-group-sm mr-2" role="group">
                                        <button type="button" class="btn btn-primary" id="btnViewDept" onclick="switchViewMode('dept')">Departamento</button>
                                        <button type="button" class="btn btn-default" id="btnViewCargo" onclick="switchViewMode('cargo')">Cargos</button>
                                    </div>
                                    <div class="btn-group btn-group-sm mr-2" role="group" id="grpDrillToggle" style="display:none;">
                                        <button type="button" class="btn btn-primary" id="btnGroupUsers" onclick="switchDrillGroup('users')">Usuarios</button>
                                        <button type="button" class="btn btn-default" id="btnGroupCats" onclick="switchDrillGroup('category')">Categorías</button>
                                    </div>
                                    <button class="btn btn-sm btn-secondary" id="btnBack" style="display:none;">
                                        <i class="fa fa-arrow-left"></i> Volver
                                    </button>
                                </div>
                            </header>
                            <div class="card-block">
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb" id="chartBreadcrumb">
                                        <li class="breadcrumb-item active" aria-current="page">Inicio</li>
                                    </ol>
                                </nav>
                                <div style="height: 400px;">
                                    <canvas id="dynamicChart"></canvas>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <!-- ADVANCED ANALYSIS -->
            <div class="row">
                <div class="col-xl-6">
                    <section class="card">
                        <header class="card-header">
                            Análisis de Flujo (Subcategoría)
                        </header>
                        <div class="card-block">
                            <div class="form-group row">
                                <div class="col-md-9">
                                    <select class="form-control" id="cmbSubcategoria">
                                        <option value="">Seleccionar Subcategoría</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary btn-block" id="btnSearchFlow">Buscar</button>
                                </div>
                            </div>
                            <div id="flowChartContainer" style="height: 300px;">
                                <canvas id="flowChart"></canvas>
                            </div>
                            <div id="flowErrorMsg" class="text-danger mt-2" style="display:none;">No se encontró la subcategoría.</div>
                        </div>
                    </section>
                </div>
                <div class="col-xl-6">
                    <section class="card">
                        <header class="card-header">
                            Desempeño Detallado por Usuario
                        </header>
                        <div class="card-block">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-vcenter js-dataTable-full">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th class="text-center" title="A Tiempo / Atrasados"><i class="fa fa-clock-o"></i></th>
                                            <th class="text-center" title="Tickets Creados">Creados</th>
                                            <th class="text-center" title="Novedades (Total - Promedio Min)"><i class="fa fa-exclamation-triangle"></i></th>
                                            <th class="text-center" title="Errores (Proceso / Info)"><i class="fa fa-times-circle"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbDetailedStats">
                                        <!-- JS Content -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

        </div>
        </div>

        <!-- Modal Novedades Detalle -->
        <div id="modalNovedades" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Detalle de Novedades Resueltas</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered table-striped table-vcenter">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Descripción (Por qué)</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Duración</th>
                                </tr>
                            </thead>
                            <tbody id="tbNovedadesBody">
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Errores Detalle -->
        <div id="modalErrores" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" id="lblTitleError">Detalle de Errores</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered table-striped table-vcenter">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Causa/Tipo</th>
                                    <th>Descripción</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="tbErroresBody">
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Desempeño Detalle -->
        <div id="modalDesempeno" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" id="lblTitleDesempeno">Detalle de Gestión</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered table-striped table-vcenter">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Título</th>
                                    <th>Estado Asignación</th>
                                    <th>Evento/Fuente</th>
                                    <th>Fecha Asignación</th>
                                </tr>
                            </thead>
                            <tbody id="tbDesempenoBody">
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
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