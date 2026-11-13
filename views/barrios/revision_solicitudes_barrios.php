<section class="app-content-header"><div class="container-fluid">
<h1 class="mb-1"><i class="bi bi-clipboard-check me-2"></i>Revisión de Solicitudes de Barrios</h1>
<p class="text-body-secondary">Comparación de datos y geometrías. Resoluciones administrativas auditadas. Permite resolver cambios de datos y publicar una nueva versión de geometría simple con validaciones conservadoras.</p>
</div></section>
<section class="app-content"><div class="container-fluid">
<div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
<strong>Solicitudes recibidas</strong><select id="rsEstado" class="form-select form-select-sm" style="max-width:200px">
<option value="PENDIENTE">Pendientes</option><option value="APROBADA">Aprobadas</option><option value="RECHAZADA">Rechazadas</option><option value="CANCELADA">Canceladas</option><option value="TODAS">Todas</option></select>
</div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-striped table-hover" id="rsTabla">
<thead><tr><th>Solicitud</th><th>Tipo</th><th>Barrio</th><th>Estado</th><th>Versión base</th><th>Fecha</th><th>Acción</th></tr></thead><tbody></tbody></table></div>
<div id="rsEstadoLista" role="status" class="small text-body-secondary"></div></div></div>
<div class="card shadow-sm d-none" id="rsDetalle"><div class="card-header"><strong>Detalle de solicitud <span id="rsNumero"></span></strong></div>
<div class="card-body"><div id="rsResumen" class="mb-3"></div><div class="row g-3">
<div class="col-lg-5"><h6>Comparación de datos</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Campo</th><th>Vigente</th><th>Propuesto</th></tr></thead><tbody id="rsDatos"></tbody></table></div>
<div id="rsControles" class="small"></div>
<div class="alert alert-warning small mt-3">La validación básica de SRID y versión no garantiza topología correcta. No aprobar ni reemplazar polígonos sin revisión GIS.</div>
</div><div class="col-lg-7"><h6>Comparación cartográfica</h6><div class="d-flex gap-3 flex-wrap small mb-2">
<label><input type="checkbox" checked id="rsVerVigente"> Vigente (azul)</label>
<label><input type="checkbox" checked id="rsVerPropuesta"> Propuesta (naranja)</label></div>
<div id="rsMapa" role="application" aria-label="Comparación cartográfica"></div>
<div class="form-text">Las geometrías se muestran como capas distintas. No se realizan modificaciones.</div>
</div></div><div id="rsMensaje" class="small mt-3" role="status"></div>
<?php if($puedeResolver): ?>
<div class="border-top mt-3 pt-3" id="rsResolucion">
  <h6>Resolución administrativa</h6>
  <label for="rsMotivo" class="form-label">Fundamento de la decisión (obligatorio, mínimo 10 caracteres)</label>
  <textarea id="rsMotivo" class="form-control mb-2" rows="3" maxlength="2000" placeholder="Explicá el motivo de la aprobación o rechazo"></textarea>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="rsConfGeo">
    <label class="form-check-label" for="rsConfGeo"><strong>He revisado la modificación geométrica propuesta y solicito publicar una nueva versión si cambiaron los límites.</strong></label>
    <div class="form-text">El servidor valida el anillo simple, autointersecciones, SRID y solapamientos con otros barrios oficiales. Los casos complejos se bloquean para validación GIS externa. Esta confirmación no reemplaza la verificación administrativa del límite.</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <button class="btn btn-success" id="rsAprobar" type="button"><i class="bi bi-check-circle"></i> Aprobar solicitud</button>
    <button class="btn btn-outline-danger" id="rsRechazar" type="button"><i class="bi bi-x-circle"></i> Rechazar solicitud</button>
  </div>
  <div class="form-text">Si cambia la geometría se genera una nueva versión y la versión anterior pasa a HISTORICO. Se vuelve a verificar la versión base y la geometría en el servidor.</div>
</div>
<?php endif; ?></div></div>
</div></section>
