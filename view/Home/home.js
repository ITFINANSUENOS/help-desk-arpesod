function init() {

}

$(document).ready(function () {
    var usu_id = $('#user_idx').val();

    $.post("../../controller/kpi.php?op=menukpi", { usu_id: usu_id }, function (data) {
        data = JSON.parse(data);
        $('#lblasignados').html(data.asignados);
        $('#lblfinalizados').html(data.finalizados);
        $('#lblpromedio').html(data.promedio_formato);
    });
});

init();
