<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['terr_auditoria_csrf']))$_SESSION['terr_auditoria_csrf']=bin2hex(random_bytes(32));
?>
<div class="app-content-header"><div class="container-fluid">
<h1 class="mb-1"><i class="bi bi-shield-check me-2"></i>Auditoría de Territorio</h1>
<p class="text-body-secondary mb-0">Trazabilidad institucional de operaciones ABM, consultas y exportaciones</p>
</div></div>
<div class="app-content"><div class="container-fluid">
<div id="atConfig" data-csrf="<?= htmlspecialchars($_SESSION['terr_auditoria_csrf'],ENT_QUOTES,'UTF-8') ?>" hidden></div>
<div class="card shadow-sm mb-3"><div class="card-header"><ul class="nav nav-pills gap-2" id="atTabs" role="tablist">
<li class="nav-item"><button type="button" class="nav-link active" data-vista="abm">Auditoría ABM</button></li>
<li class="nav-item"><button type="button" class="nav-link" data-vista="acciones">Auditoría de acciones</button></li>
</ul></div><div class="card-body">
<div class="row g-3 align-items-end">
<div class="col-6 col-lg-2"><label class="form-label" for="atDesde">Desde</label><input id="atDesde" type="date" class="form-control"></div>
<div class="col-6 col-lg-2"><label class="form-label" for="atHasta">Hasta</label><input id="atHasta" type="date" class="form-control"></div>
<div class="col-6 col-lg-2"><label class="form-label" for="atUsuario">ID usuario</label><input id="atUsuario" type="text" inputmode="numeric" class="form-control" maxlength="10" placeholder="Ej. 2323"></div>
<div class="col-6 col-lg-3"><label class="form-label" for="atTipo">Tipo de operación</label><select id="atTipo" class="form-select"><option value="">Todos</option></select></div>
<div class="col-12 col-lg-3"><label class="form-label" for="atTexto">Observación contiene</label><input id="atTexto" class="form-control" maxlength="120" placeholder="Buscar descripción"></div>
<div class="col-6 col-lg-4 at-abm"><label class="form-label" for="atTabla">Tabla afectada</label><input id="atTabla" class="form-control" maxlength="150" placeholder="db_barrios.bar_barrios"></div>
<div class="col-6 col-lg-3 at-abm"><label class="form-label" for="atRegistro">ID registro</label><input id="atRegistro" class="form-control" maxlength="100" placeholder="Ej. 1872"></div>
</div>
<div class="d-flex flex-wrap gap-2 align-items-center mt-3">
<button type="button" id="atBuscar" class="btn btn-success"><i class="bi bi-search me-1"></i>Buscar</button>
<button type="button" id="atLimpiar" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</button>
<div class="ms-auto d-flex flex-wrap gap-2">
<button type="button" id="atExcel" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel (CSV)</button>
<button type="button" id="atPDF" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Guardar PDF</button>
<button type="button" id="atPrint" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i>Imprimir</button>
</div></div>
<p class="form-text mb-0">Las exportaciones respetan los filtros. Límite: CSV 10.000 registros; impresión/PDF 300 registros. Para PDF utilizá «Guardar como PDF» en el cuadro de impresión del navegador.</p>
</div></div>
<div class="card shadow-sm"><div class="card-header"><h2 class="card-title fs-6 mb-0">Eventos registrados</h2></div><div class="card-body">
<div class="table-responsive"><table id="atTablaResultados" class="table table-striped table-hover align-middle w-100">
<thead><tr><th>ID</th><th>Fecha</th><th>Usuario</th><th>Módulo</th><th>Tipo</th><th>Tabla</th><th>Registro</th><th>Observación</th><th>IP</th><th>Detalle</th></tr></thead><tbody></tbody></table></div>
<div id="atMensaje" role="status" aria-live="polite" class="small mt-2 text-body-secondary"></div>
</div></div></div></div>
<div class="modal fade" id="atDetalleModal" tabindex="-1" aria-labelledby="atDetalleTitulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h2 id="atDetalleTitulo" class="modal-title fs-5">Detalle de auditoría</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" type="button"></button></div>
<div class="modal-body"><div id="atDetalleMeta" class="small text-body-secondary mb-3"></div><div class="row g-3"><div class="col-md-6"><h3 class="fs-6">Datos anteriores</h3><pre id="atAntes" class="bg-body-tertiary border rounded p-3" style="min-height:5rem;white-space:pre-wrap;overflow-wrap:anywhere"></pre></div><div class="col-md-6"><h3 class="fs-6">Datos nuevos / Parámetros</h3><pre id="atDespues" class="bg-body-tertiary border rounded p-3" style="min-height:5rem;white-space:pre-wrap;overflow-wrap:anywhere"></pre></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div></div></div></div>
