<section class="app-content-header"><div class="container-fluid">
<h1 class="mb-1"><i class="bi bi-clipboard-check me-2"></i>Revisión de Solicitudes de Barrios</h1>
<p class="text-body-secondary">Comparación de datos y geometrías. Esta fase es de solo lectura; no publica versiones.</p>
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
</div></div><div id="rsMensaje" class="small mt-3" role="status"></div></div></div>
</div></section>
