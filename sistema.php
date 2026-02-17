<?php
require_once("config/conexion.php");
if (isset($_POST["enviar"]) and $_POST["enviar"] == "si") {
    require_once("models/Usuario.php");
    $usuario = new Usuario();
    $usuario->login();
}
?>
<!DOCTYPE html>
<html>

<head lang="es">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Iniciar Sesión | Mesa de Ayuda</title>

    <link rel="stylesheet" href="public/css/lib/font-awesome/font-awesome.min.css">
    <link rel="stylesheet" href="public/css/lib/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="public/css/main.css">
    <link href="public/img/favicon.ico" rel="shortcut icon">

    <style>
        body {
            height: 100vh;
            overflow: hidden;
        }

        .login-container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* -- Columna de la Marca (Izquierda) -- */
        .login-branding {
            /* IMPORTANTE: Cambia esta URL por la de tu propia imagen de fondo */
            background-image: linear-gradient(rgba(233, 228, 228, 1), rgba(109, 134, 197, 1)), url('https://images.unsplash.com/photo-1554224155-8d04421cd6e2?auto=format&fit=crop&q=80&w=2070');
            background-size: cover;
            background-position: center;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            /* Alineado a la izquierda */
            text-align: left;
            padding: 5rem;
        }

        .login-branding h1 {
            font-weight: 300;
            font-size: 3rem;
            color: black;
            margin-top: 1.5rem;
        }

        .login-branding p {
            font-size: 1.2rem;
            color: black;
            opacity: 0.8;
        }

        /* -- Columna del Formulario (Derecha) -- */
        .login-form-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            background-color: #fff;
        }

        .sign-box {
            max-width: 400px;
            width: 100%;
            background: transparent;
            padding: 20px;
        }

        .sign-box-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .sign-title {
            text-align: center;
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
        }

        .sign-subtitle {
            text-align: center;
            color: #777;
            margin-bottom: 2rem;
        }

        /* Animación de entrada */
        .form-group,
        .sign-box button,
        .sign-box .float-right {
            animation: fadeIn 0.5s ease-in-out forwards;
            opacity: 0;
        }

        .form-group:nth-child(2) {
            animation-delay: 0.1s;
        }

        .form-group:nth-child(3) {
            animation-delay: 0.2s;
        }

        .float-right {
            animation-delay: 0.3s;
        }

        .sign-box button {
            animation-delay: 0.4s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Media query para móviles */
        @media (max-width: 992px) {
            .login-branding {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="col-lg-7 login-branding">
            <div>
                <img src="public/img/electro-logo.png" alt="Logo de la Empresa" style="max-width: 20rem; max-height: 20rem;">
                <h1>Plataforma de Soporte</h1>
                <p class="lead">Gestión eficiente para todas tus solicitudes.</p>
            </div>
        </div>

        <div class="col-lg-5 col-12 login-form-wrapper">
            <form class="sign-box" action="" method="post" id="login-form">
                <input type="hidden" name="rol_id" id="rol_id" value="2">

                <div class="sign-box-logo">
                    <img src="public/img/user-2.png" alt="Logo" id="imgtipo" style="max-width: 120px;">
                </div>

                <header class="sign-title">Iniciar Sesión</header>
                <div class="sign-subtitle" id="lbltitulo">Acceso Soporte</div>

                <?php
                if (isset($_GET["m"])) {
                    // Tu código de switch para los errores no cambia
                    if ($_GET["m"] == 1) {
                        echo '<div class="alert alert-danger">Correo o contraseña incorrectos.</div>';
                    } else if ($_GET["m"] == 2) {
                        echo '<div class="alert alert-warning">Los campos están vacíos.</div>';
                    }
                }
                ?>

                <div class="form-group">
                    <input type="text" class="form-control" id="usu_correo" name="usu_correo" placeholder="Correo Electrónico" required />
                </div>

                <div class="form-group">
                    <input type="password" class="form-control" id="usu_pass" name="usu_pass" placeholder="Contraseña" required />
                </div>
                <div class="form-group">
                    <div class="float-left reset">
                        <a href="view/Recuperar/index.php">¿Olvidaste tu contraseña?</a>
                    </div>
                </div>

                <div class="form-group">
                    <div class="float-right reset">
                        <a href="#" id="btnsoporte">Acceso Usuario</a>
                    </div>
                </div>

                <input type="hidden" name="enviar" value="si">
                <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
            </form>
        </div>
    </div>

    <script src="public/js/lib/jquery/jquery.min.js"></script>
    <script src="public/js/lib/jquery/jquery.min.js"></script>
    <script src="public/js/lib/tether/tether.min.js"></script>
    <script src="public/js/lib/bootstrap/bootstrap.min.js"></script>
    <script src="public/js/plugins.js"></script>
    <script type="text/javascript" src="public/js/lib/match-height/jquery.matchHeight.min.js"></script>
    <script src="public/js/lib/hide-show-password/bootstrap-show-password.min.js"></script>
    <script src="public/js/lib/bootstrap/bootstrap.min.js"></script>
    <script src="public/js/app.js"></script>
    <script type="text/javascript" src="index.js"></script>
</body>

</html>