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
 <p class="text-muted">Geometrías propuestas pendientes · aprobación con validación GIS y auditoría</p>
 <div id="jrAviso" class="alert alert-info">Seleccioná una solicitud pendiente y revisá el mapa y el fundamento.</div>
 <div class="row g-3">
  <div class="col-lg-4"><div class="card"><div class="card-header"><strong>Solicitudes pendientes</strong></div>
   <div class="card-body"><button class="btn btn-sm btn-outline-primary mb-2" id="jrRecargar" type="button">Actualizar listado</button>
   <div id="jrListado" class="list-group small"></div></div></div></div>
  <div class="col-lg-8"><div class="card"><div class="card-header"><strong>Comparación cartográfica y resolución</strong></div>
   <div class="card-body"><div id="jrDetalle" class="mb-2 small text-muted">Seleccioná una solicitud.</div>
    <div class="d-flex flex-wrap gap-3 mb-2 small">
     <label><input type="checkbox" id="jrCapaOficial" checked> Oficial vigente (azul)</label>
     <label><input type="checkbox" id="jrCapaPropuesta" checked> Propuesta (naranja)</label>
    </div>
    <div id="jrMapa" style="height:430px;border:1px solid #ddd;border-radius:.5rem"></div>
    <div class="alert alert-warning py-2 small mt-2">Esta revisión acepta polígonos simples SRID 4326. Autointersecciones, huecos, superposiciones o geometrías complejas se bloquean para revisión GIS especializada. No basta con que la figura se vea bien en el mapa.</div>
    <label class="form-label" for="jrFundamento">Fundamento de la decisión (mínimo 10 caracteres)</label>
    <textarea class="form-control" id="jrFundamento" rows="2" placeholder="Indicar las comprobaciones efectuadas y el motivo de la decisión"></textarea>
    <label class="form-check my-2"><input class="form-check-input" type="checkbox" id="jrConfirmar"><span class="form-check-label">Verifiqué el organismo, el límite territorial y la propuesta</span></label>
    <div class="d-flex flex-wrap gap-2"><button class="btn btn-success" type="button" id="jrAprobar">Aprobar y publicar geometría</button>
    <button class="btn btn-outline-danger" type="button" id="jrRechazar">Rechazar solicitud</button></div>
    <div class="small text-muted mt-2">La aprobación historiza el polígono anterior, publica una única versión vigente y registra la decisión y la auditoría ABM en la misma transacción. Se requiere permiso MODIFICA.</div>
   </div></div></div>
 </div>
</div>
</main>
<?php require_once __DIR__.'/views/inactividad/bloqueototal.php'; ?>
<script>window.APP_BASE_URL = '/territorio';</script>
<?php require_once __DIR__.'/templates/footer.php'; ?>
<?php require_once __DIR__.'/templates/scripts.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/territorio/assets/js/revision-jurisdicciones.js?v=1"></script>
</body></html>
