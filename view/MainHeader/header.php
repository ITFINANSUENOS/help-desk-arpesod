<header class="site-header">
	<div class="container-fluid">

		<a href="#" class="site-logo">
			<img class="hidden-md-down" src="../../public/img/electro-logo.png" alt="">
			<img class="hidden-lg-up" src="../../public/img/logo-2-mob.png" alt="">
		</a>

		<button id="show-hide-sidebar-toggle" class="show-hide-sidebar">
			<span>toggle menu</span>
		</button>

		<button class="hamburger hamburger--htla">
			<span>toggle menu</span>
		</button>
		<div class="site-header-content">
			<div class="site-header-content-in">
				<div class="site-header-shown">
					<div class="dropdown dropdown-notification notif">
						<a href="#"
							class="header-alarm"
							id="dd-notification"
							data-toggle="dropdown"
							aria-haspopup="true"
							aria-expanded="false">
							<i class="font-icon-alarm"></i>
							<div class="dot"></div>
						</a>
						<div class="dropdown-menu dropdown-menu-right dropdown-menu-notif" aria-labelledby="dd-notification">
							<div class="dropdown-menu-notif-header">
								Notifications
								<span id="lblcontar" class="label label-pill label-danger"></span>
							</div>
							<div id="lblmenulist" class="dropdown-menu-notif-list">

							</div>
							<div class="dropdown-menu-notif-more">
								<a href="../ConsultarNotificaciones/">Ver Todo</a>
							</div>
						</div>
					</div>

					<div class="dropdown dropdown-notification">
						<a href=".	.\Calendario\"
							class="header-alarm">
							<i class="font-icon font-icon-calend"></i>
						</a>
					</div>

					<input type="hidden" id="user_idx" value="<?php echo $_SESSION["usu_id"] ?>">
					<input type="hidden" id="rol_idx" value="<?php echo $_SESSION["rol_id"] ?>">
					<input type="hidden" id="rol_real_idx" value="<?php echo $_SESSION["rol_id_real"] ?>">
					<input type="hidden" id="dp_idx" value="<?php echo $_SESSION["dp_id"] ?>">



					<div class="dropdown user-menu">
						<button class="dropdown-toggle" id="dd-user-menu" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							<img src="../../public/img/user-<?php echo $_SESSION["rol_id"] ?>.png" alt="">
						</button>
						<div class="dropdown-menu dropdown-menu-right" aria-labelledby="dd-user-menu">
							<a class="dropdown-item" href="../../view/Perfil/"><span class="font-icon glyphicon glyphicon-user"></span><?php echo isset($_SESSION["car_nom"]) ? $_SESSION["car_nom"] : "" ?> <?php echo isset($_SESSION["reg_nom"]) ? "(" . $_SESSION["reg_nom"] . ")" : "" ?></a>
							<a class="dropdown-item" href="#"><span class="font-icon glyphicon glyphicon-cog"></span>Ayuda</a>
							<div class="dropdown-divider"></div>
							<a class="dropdown-item" href="../Logout/logout.php"><span class="font-icon glyphicon glyphicon-log-out"></span>Cerrar sesion</a>
						</div>
					</div>
				</div><!--.site-header-shown-->

				<div class="mobile-menu-right-overlay"></div>
			</div><!--site-header-content-in-->
		</div><!--.site-header-content-->
	</div><!--.container-fluid-->
</header><!--.site-header-->