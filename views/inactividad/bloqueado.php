<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if (empty($_SESSION['logged'])) {
    header("Location: /acceso/login.php");
    exit;
}


$mensaje = $_SESSION['mensaje_bloqueo'] ?? 
"EL PRESENTE USUARIO NO TIENE AUTORIZACION PARA INGRESAR AL MODULO (FUERA DEL HORARIO LABORAL, NO FUE CONFIRMADO EN SIRH FUNCIONES, CM, LIC, ETC.)";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Acceso Restringido</title>

<!-- Bootstrap 4 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<!-- Alertify -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css">

<style>
html, body {
    height: 100%;
    margin: 0;
    overflow: hidden;
    background: #eef1f5;
}

/* Overlay total */
.overlay-bloqueo {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.80);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Caja central */
.card-bloqueo {
    max-width: 550px;
    width: 95%;
    border-radius: 15px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    animation: aparecer 0.3s ease;
}

@keyframes aparecer {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

/* Icono */
.icono-bloqueo {
    font-size: 55px;
    color: #dc3545;
}

/* Botones */
.btn-custom {
    min-width: 140px;
}
</style>
</head>
<body>

<div class="overlay-bloqueo">

    <div class="card card-bloqueo text-center p-4">

        <div class="card-body">

            <div class="icono-bloqueo mb-3">
                🔒
            </div>

            <h4 class="text-danger">Acceso Bloqueado</h4>

            <p class="mt-3 mb-4">
                <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div class="d-flex justify-content-center gap-2">

                <button class="btn btn-primary btn-custom mr-2" onclick="irPanel()">
                    Volver al sistema
                </button>

                <button class="btn btn-danger btn-custom" onclick="cerrarSesion()">
                    Cerrar sesión
                </button>

            </div>

        </div>

    </div>

</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

<script>

// 🚫 Bloquear botón atrás
history.pushState(null, "", location.href);
window.onpopstate = function () {
    history.pushState(null, "", location.href);
};

// 🚫 Bloquear F5 y CTRL+R
document.addEventListener("keydown", function(e) {
    if (e.key === "F5" || (e.ctrlKey && e.key === "r")) {
        e.preventDefault();
    }
});

// Alertify informativo
alertify.alert(
    'Acceso Restringido',
    <?= json_encode($mensaje) ?>,
    function(){}
).set({
    closable: false,
    movable: false,
    resizable: false,
    pinnable: false
});

// 🔁 Volver a panel seguro
function irPanel() {
    window.location.replace("/acceso/segacceso/checks.php");
}

// 🔓 Cerrar sesión
function cerrarSesion() {
     window.location.replace("/siad/segacceso/logout.php");
}

</script>

</body>
</html>