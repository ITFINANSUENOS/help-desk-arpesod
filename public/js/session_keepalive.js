$(document).ready(function () {
    // Intervalo de 5 minutos (300,000 milisegundos)
    var interval = 5 * 60 * 1000;

    setInterval(function () {
        // Se asume que la estructura de carpetas es /view/Carpeta/index.php
        // Por lo tanto, el controlador está en ../../controller/
        $.post("../../controller/keepalive.php", function (data) {
            console.log("Sesión renovada: " + data);
        });
    }, interval);
});
