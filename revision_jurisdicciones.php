<?php
declare(strict_types=1);
$usarDataTables=false;
require_once __DIR__.'/segacceso/segacceso.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/administracion/autorizaciones_perfil.php';
require_once __DIR__.'/clases/Autorizacion.php';
$auth=new Autorizacion();$auth->exigir('revision_jurisdicciones.php','ejec');
$perm=$auth->permisos('revision_jurisdicciones.php');
if(empty($_SESSION['terr_revision_juris_csrf']))$_SESSION['terr_revision_juris_csrf']=bin2hex(random_bytes(32));
$tituloPagina='Revisión de Jurisdicciones';$paginaActiva='revision_jurisdicciones';
require_once __DIR__.'/templates/header.php';
require_once __DIR__.'/templates/navbar.php';
require_once __DIR__.'/templates/menu.php';
?>
<main class="app-main">
<div class="container-fluid px-3 py-3" id="jurRevisionRoot"
 data-csrf="<?= htmlspecialchars((string)$_SESSION['terr_revision_juris_csrf'],ENT_QUOTES,'UTF-8') ?>"
 data-resolver="<?= $perm['modifica']?'1':'0' ?>">
 <h3>Revisión de Jurisdicciones</h3>
 <p class="text-muted">Consulta histórica de solicitudes · revisión con validación GIS y auditoría</p>
 <div id="jrAviso" class="alert alert-info">Consultá todas las solicitudes o filtrá por estado, dependencia, RRHH y fechas.</div>
 <div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <strong>Solicitudes de jurisdicciones</strong>
    <button class="btn btn-sm btn-outline-primary" id="jrRecargar" type="button"><i class="bi bi-arrow-clockwise"></i> Actualizar</button>
  </div>
  <div class="card-body">
    <form id="jrFiltros" class="row g-2 align-items-end mb-3" autocomplete="off">
      <div class="col-12 col-md-2"><label for="jrEstado" class="form-label small mb-1">Estado</label>
        <select id="jrEstado" class="form-select form-select-sm">
          <option value="TODAS" selected>Todas</option><option value="PENDIENTE">Pendientes</option>
          <option value="APROBADA">Aprobadas</option><option value="RECHAZADA">Rechazadas</option>
          <option value="CANCELADA">Canceladas</option><option value="BORRADOR">Borrador</option>
        </select></div>
      <div class="col-12 col-md-3"><label for="jrTexto" class="form-label small mb-1">Jurisdicción / N.º solicitud</label>
        <input type="search" class="form-control form-control-sm" id="jrTexto" maxlength="100" placeholder="Ej. Centro o Comisaría 1"></div>
      <div class="col-12 col-md-1"><label for="jrRRHH" class="form-label small mb-1">ID RRHH</label>
        <input type="number" min="1" step="1" class="form-control form-control-sm" id="jrRRHH" placeholder="2404"></div>
      <div class="col-6 col-md-2"><label for="jrDesde" class="form-label small mb-1">Desde</label>
        <input type="date" class="form-control form-control-sm" id="jrDesde"></div>
      <div class="col-6 col-md-2"><label for="jrHasta" class="form-label small mb-1">Hasta</label>
        <input type="date" class="form-control form-control-sm" id="jrHasta"></div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button class="btn btn-sm btn-success flex-fill" type="submit">Buscar</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" id="jrLimpiar">Limpiar</button>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover align-middle" aria-label="Solicitudes historicas de jurisdicciones">
        <thead><tr><th>N.º</th><th>Jurisdicción</th><th>RRHH</th><th>Tipo</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead>
        <tbody id="jrListado"><tr><td colspan="7" class="text-muted">Cargando solicitudes...</td></tr></tbody>
      </table>
    </div>
    <p class="text-muted small mb-0">Se muestran solicitudes de todos los estados (máximo 200 filas por consulta). Las aprobadas y rechazadas pueden abrirse en modo consulta. Cada búsqueda y detalle se audita.</p>
  </div>
 </div>
 <div id="jrPanelRevision" class="card d-none">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <strong>Comparación cartográfica y resolución</strong>
    <button class="btn btn-sm btn-outline-secondary" id="jrCerrarDetalle" type="button">Volver al listado</button>
  </div>
  <div class="card-body">
    <div id="jrDetalle" class="mb-2 small text-muted">Seleccioná una solicitud.</div>
    <div class="d-flex flex-wrap gap-3 mb-2 small">
      <label><input type="checkbox" id="jrCapaOficial" checked> Oficial vigente (azul)</label>
      <label><input type="checkbox" id="jrCapaPropuesta" checked> Propuesta (naranja)</label>
    </div>
    <div id="jrMapa" style="height:480px;border:1px solid #ddd;border-radius:.5rem"></div>
    <div class="alert alert-warning py-2 small mt-2">Solo se publican geometrías que superan las validaciones del servidor. Los casos complejos requieren revisión GIS especializada.</div>
    <label class="form-label" for="jrFundamento">Fundamento de la decisión (mínimo 10 caracteres)</label>
    <textarea class="form-control" id="jrFundamento" rows="2" placeholder="Indicar las comprobaciones efectuadas y el motivo de la decisión"></textarea>
    <label class="form-check my-2"><input class="form-check-input" type="checkbox" id="jrConfirmar"><span class="form-check-label">Verifiqué el organismo, el límite territorial y la propuesta</span></label>
    <div class="d-flex flex-wrap gap-2" id="jrAccionesResolucion"><button class="btn btn-success" type="button" id="jrAprobar">Aprobar y publicar geometría</button>
    <button class="btn btn-outline-danger" type="button" id="jrRechazar">Rechazar solicitud</button></div>
    <div class="small text-muted mt-2">Se requiere permiso MODIFICA. Las decisiones quedan registradas en la auditoría central.</div>
  </div>
 </div>
</div>
</main>
<?php require_once __DIR__.'/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL = '/territorio';</script>
<?php require_once __DIR__.'/templates/footer.php'; ?>
<?php require_once __DIR__.'/templates/scripts.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/territorio/assets/js/revision-jurisdicciones.js?v=9"></script>
</body></html>
