'use strict';

/* =========================================================
   CONFIGURACIÓN
========================================================= */

/* Producción */
 const TIEMPO_SESION_SEGUNDOS = 1800;
 const AVISO_ANTES_SEGUNDOS = 60;

/* Prueba */
//const TIEMPO_SESION_SEGUNDOS = 120;
//const AVISO_ANTES_SEGUNDOS = 60;

/* Renovar sesión cada 60 segundos si hubo actividad */
const INTERVALO_RENOVACION_MS = 60000;


/* =========================================================
   VARIABLES DE CONTROL
========================================================= */

let segundosSinActividad = 0;
let sesionExpirada = false;
let huboActividad = false;
let avisoMostrado = false;
let avisoPrevioLanzado = false;

let temporizadorControl = null;
let temporizadorRenovacion = null;
let renovacionEnCurso = false;


/* =========================================================
   RUTAS
========================================================= */

/*
 * Se recomienda definir esta variable antes de cargar
 * inactividad.js:
 *
 * <script>
 *     window.APP_BASE_URL = '/silegin';
 * </script>
 */

const BASE_URL = String(window.APP_BASE_URL || '')
    .replace(/\/+$/, '');

function construirUrl(ruta) {
    return `${BASE_URL}/${String(ruta).replace(/^\/+/, '')}`;
}


/* =========================================================
   REDIRECCIÓN
========================================================= */

function irAlLoginPorSesionVencida() {
    const urlLogin = construirUrl(
        '/../../acceso/login?access=timeout'
    );

    window.location.replace(urlLogin);
}


/* =========================================================
   PETICIONES AL SERVIDOR
========================================================= */

async function solicitarJson(url, opciones = {}) {
    const respuesta = await fetch(url, {
        method: 'GET',
        cache: 'no-store',
        credentials: 'same-origin',
        ...opciones,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(opciones.headers || {})
        }
    });

    const contenido = await respuesta.text();

    if (!respuesta.ok) {
        throw new Error(
            `Error HTTP ${respuesta.status}: ${contenido}`
        );
    }

    if (!contenido.trim()) {
        throw new Error(
            `Respuesta vacía recibida desde ${url}`
        );
    }

    try {
        return JSON.parse(contenido);
    } catch (error) {
        console.error(
            `La respuesta de ${url} no es JSON válido:`,
            contenido
        );

        throw new Error(
            `Respuesta JSON inválida desde ${url}`
        );
    }
}


/* =========================================================
   ACTIVIDAD DEL USUARIO
========================================================= */

function registrarActividad() {
    if (sesionExpirada) {
        return;
    }

    segundosSinActividad = 0;
    huboActividad = true;

    /*
     * Permite volver a mostrar el aviso en un próximo
     * período completo de inactividad.
     */
    avisoPrevioLanzado = false;
}


/* =========================================================
   AVISO PREVIO
========================================================= */

function mostrarAvisoPrevio() {
    if (
        sesionExpirada ||
        avisoMostrado ||
        avisoPrevioLanzado
    ) {
        return;
    }

    if (typeof Swal === 'undefined') {
        console.error(
            'SweetAlert2 no está cargado.'
        );

        avisoPrevioLanzado = true;
        return;
    }

    avisoMostrado = true;
    avisoPrevioLanzado = true;

    Swal.fire({
        title: 'Sesión próxima a vencer',
        html:
            'Por inactividad, su sesión se cerrará en ' +
            '<b>1 minuto</b> si no registra actividad.',
        icon: 'warning',
        confirmButtonText: 'Continuar en el sistema',
        confirmButtonColor: '#0d6efd',
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: true,
        customClass: {
            popup: 'swal-aviso-sesion',
            title: 'swal-aviso-titulo',
            confirmButton: 'swal-aviso-boton'
        }
    }).then(function (resultado) {
        avisoMostrado = false;

        if (
            resultado.isConfirmed &&
            !sesionExpirada
        ) {
            registrarActividad();
            renovarSesion();
        }
    });
}


/* =========================================================
   BLOQUEO FINAL
========================================================= */

async function mostrarBloqueoFinal() {
    if (sesionExpirada) {
        return;
    }

    sesionExpirada = true;

    detenerTemporizadores();

    if (typeof Swal !== 'undefined') {
        Swal.close();
    }

    const bloqueo = document.getElementById(
        'bloqueoSesionBanco'
    );

    if (bloqueo) {
        bloqueo.style.display = 'flex';
        bloqueo.setAttribute('aria-hidden', 'false');
    } else {
        console.error(
            'No se encontró #bloqueoSesionBanco.'
        );
    }

    document.documentElement.classList.add('bloqueado');
    document.body.classList.add('bloqueado');

    history.pushState(null, '', location.href);

    try {
        const respuesta = await solicitarJson(
            construirUrl(
                '/segacceso/expirar_sesion.php'
            ),
            {
                method: 'POST'
            }
        );

        console.log(
            'Sesión expirada en el servidor:',
            respuesta
        );
    } catch (error) {
        /*
         * Aunque falle esta petición, el bloqueo visual
         * permanece activo.
         */
        console.error(
            'No se pudo registrar la expiración de sesión:',
            error
        );
    }
}


/* =========================================================
   RENOVAR SESIÓN
========================================================= */

async function renovarSesion() {
    if (
        sesionExpirada ||
        renovacionEnCurso
    ) {
        return;
    }

    renovacionEnCurso = true;

    try {
        const respuesta = await solicitarJson(
            construirUrl(
                '/segacceso/renovar_sesion.php'
            ),
            {
                method: 'POST'
            }
        );

        if (
            respuesta &&
            respuesta.success === true
        ) {
            segundosSinActividad = 0;
            huboActividad = false;
            avisoMostrado = false;
            avisoPrevioLanzado = false;
        } else {
            console.error(
                'El servidor rechazó la renovación:',
                respuesta
            );

            mostrarBloqueoFinal();
        }
    } catch (error) {
        console.error(
            'No se pudo renovar la sesión:',
            error
        );

        /*
         * No bloqueamos inmediatamente por un error de red
         * transitorio. El control de tiempo continúa.
         */
    } finally {
        renovacionEnCurso = false;
    }
}


/* =========================================================
   VERIFICAR SESIÓN
========================================================= */

async function verificarSesionAlVolver() {
    if (sesionExpirada) {
        irAlLoginPorSesionVencida();
        return;
    }

    try {
        const respuesta = await solicitarJson(
            construirUrl(
                '/segacceso/verificar_sesion.php'
            ),
            {
                method: 'GET'
            }
        );

        if (
            !respuesta ||
            respuesta.activa !== true
        ) {
            irAlLoginPorSesionVencida();
        }
    } catch (error) {
        console.error(
            'No se pudo verificar la sesión:',
            error
        );

        irAlLoginPorSesionVencida();
    }
}


/* =========================================================
   TEMPORIZADORES
========================================================= */

function detenerTemporizadores() {
    if (temporizadorControl !== null) {
        clearInterval(temporizadorControl);
        temporizadorControl = null;
    }

    if (temporizadorRenovacion !== null) {
        clearInterval(temporizadorRenovacion);
        temporizadorRenovacion = null;
    }
}


function iniciarControlSesion() {
    if (temporizadorControl !== null) {
        clearInterval(temporizadorControl);
    }

    temporizadorControl = setInterval(function () {
        if (sesionExpirada) {
            return;
        }

        segundosSinActividad++;

        const segundosRestantes =
            TIEMPO_SESION_SEGUNDOS -
            segundosSinActividad;

        if (
            segundosRestantes <= AVISO_ANTES_SEGUNDOS &&
            segundosRestantes > 0 &&
            !avisoPrevioLanzado
        ) {
            mostrarAvisoPrevio();
        }

        if (
            segundosSinActividad >=
            TIEMPO_SESION_SEGUNDOS
        ) {
            mostrarBloqueoFinal();
        }
    }, 1000);
}


function iniciarRenovacionAutomatica() {
    if (temporizadorRenovacion !== null) {
        clearInterval(temporizadorRenovacion);
    }

    temporizadorRenovacion = setInterval(function () {
        if (
            sesionExpirada ||
            !huboActividad
        ) {
            return;
        }

        renovarSesion();
    }, INTERVALO_RENOVACION_MS);
}


/* =========================================================
   EVENTOS DEL USUARIO
========================================================= */

function registrarEventosDeActividad() {
    const eventos = [
        'mousemove',
        'mousedown',
        'keydown',
        'scroll',
        'touchstart',
        'click'
    ];

    eventos.forEach(function (nombreEvento) {
        document.addEventListener(
            nombreEvento,
            registrarActividad,
            {
                capture: true,
                passive:
                    nombreEvento === 'mousemove' ||
                    nombreEvento === 'scroll' ||
                    nombreEvento === 'touchstart'
            }
        );
    });
}


/* =========================================================
   CONTROL DE NAVEGACIÓN
========================================================= */

function controlarNavegacion() {
    history.pushState(null, '', location.href);

    window.addEventListener(
        'popstate',
        function () {
            if (sesionExpirada) {
                irAlLoginPorSesionVencida();
            } else {
                verificarSesionAlVolver();
            }
        }
    );

    window.addEventListener(
        'pageshow',
        function () {
            if (sesionExpirada) {
                irAlLoginPorSesionVencida();
                return;
            }

            verificarSesionAlVolver();
        }
    );

    document.addEventListener(
        'visibilitychange',
        function () {
            if (
                document.visibilityState === 'visible'
            ) {
                verificarSesionAlVolver();
            }
        }
    );
}


/* =========================================================
   TECLAS CON SESIÓN BLOQUEADA
========================================================= */

function controlarTeclasConSesionExpirada() {
    document.addEventListener(
        'keydown',
        function (evento) {
            if (!sesionExpirada) {
                return;
            }

            const tecla = String(
                evento.key || ''
            ).toLowerCase();

            if (evento.key === 'F5') {
                evento.preventDefault();
                irAlLoginPorSesionVencida();
                return;
            }

            if (
                (evento.ctrlKey || evento.metaKey) &&
                tecla === 'r'
            ) {
                evento.preventDefault();
                irAlLoginPorSesionVencida();
                return;
            }

            const elementoActivo =
                document.activeElement;

            const etiqueta = elementoActivo
                ? elementoActivo.tagName.toLowerCase()
                : '';

            const esEditable =
                elementoActivo && (
                    elementoActivo.isContentEditable ||
                    etiqueta === 'input' ||
                    etiqueta === 'textarea' ||
                    etiqueta === 'select'
                );

            if (
                evento.key === 'Backspace' &&
                !esEditable
            ) {
                evento.preventDefault();
                irAlLoginPorSesionVencida();
            }
        },
        true
    );
}


/* =========================================================
   INICIALIZACIÓN
========================================================= */

function inicializarControlSesion() {
    const bloqueo = document.getElementById(
        'bloqueoSesionBanco'
    );

    const btnIrLogin = document.getElementById(
        'btnIrLogin'
    );

    if (!bloqueo) {
        console.error(
            'No se encontró #bloqueoSesionBanco. ' +
            'El control de sesión no se iniciará.'
        );
        return;
    }

    if (!btnIrLogin) {
        console.error(
            'No se encontró #btnIrLogin. ' +
            'El control de sesión no se iniciará.'
        );
        return;
    }

    bloqueo.style.display = 'none';
    bloqueo.setAttribute('aria-hidden', 'true');

    btnIrLogin.addEventListener(
        'click',
        irAlLoginPorSesionVencida
    );

    registrarEventosDeActividad();
    controlarNavegacion();
    controlarTeclasConSesionExpirada();

    iniciarControlSesion();
    iniciarRenovacionAutomatica();

    console.log(
        'Control de inactividad iniciado correctamente.'
    );
}


if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        inicializarControlSesion
    );
} else {
    inicializarControlSesion();
}