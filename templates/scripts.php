

<script src="/territorio/assets/plugins/jquery/jquery-3.7.1.min.js"></script>


<!-- OverlayScrollbars -->
<script
    src="/territorio/assets/plugins/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"
></script>

<!-- Bootstrap 5 -->
<script
    src="/territorio/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"
></script>

<?php if (!empty($usarDataTables)): ?>
  <!--   <script src="/siad/assets/plugins/datatables/js/datatables.js"></script> -->
    <script src="/territorio/assets/plugins/datatables/js/dataTables.min.js"></script>
<?php endif; ?>

<!-- AdminLTE -->
<script src="/territorio/assets/js/adminlte.js"></script>

<script src="/territorio/assets/plugins/sweetalert2/sweetalert2.all.min.js"></script>


<?php if (($paginaActiva ?? '') === 'visor' || !empty($cargarLeaflet)): ?>
<!-- <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script> -->
<script src="/territorio/assets/plugins/leaflet/leaflet.js"></script>
<script src="/territorio/assets/js/visor.js?v=5"></script>
<?php endif; ?>
<!-- Inicialización del scrollbar lateral -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarWrapper = document.querySelector('.sidebar-wrapper');

    const esMovil = window.innerWidth <= 992;

    if (
        sidebarWrapper &&
        window.OverlayScrollbarsGlobal?.OverlayScrollbars &&
        !esMovil
    ) {
        OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
            scrollbars: {
                theme: 'os-theme-light',
                autoHide: 'leave',
                clickScroll: true
            }
        });
    }
});

</script>

<script src="/territorio/assets/js/inactividad.js?v=1.16"></script>