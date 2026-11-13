<section class="app-content-header"><div class="container-fluid">
 <h1 class="mb-1"><i class="bi bi-clipboard-check me-2"></i>Revisión de consolidaciones</h1>
 <p class="text-body-secondary">Verificá gobernante, históricos, SURC y cartografía municipal antes de resolver propuestas.</p>
</div></section>
<section class="app-content"><div class="container-fluid"><div class="card shadow-sm">
 <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <strong>Solicitudes de consolidación</strong>
  <select id="rcEstado" class="form-select form-select-sm rc-select" aria-label="Filtrar por estado">
   <option value="PENDIENTE">Pendientes</option><option value="APROBADA">Aprobadas</option>
   <option value="RECHAZADA">Rechazadas</option><option value="CANCELADA">Canceladas</option><option value="TODAS">Todas</option>
  </select>
 </div>
 <div class="card-body">
 <div class="table-responsive"><table class="table table-sm table-hover align-middle" id="rcTabla">
  <thead><tr><th>ID</th><th>Gobernante propuesto</th><th>Localidad</th><th>Históricos</th><th>SURC</th><th>Municipal</th><th>Estado</th><th></th></tr></thead>
  <tbody></tbody>
 </table></div><div class="small text-body-secondary" id="rcEstadoTexto" role="status"></div>
 </div></div>
 <div id="rcPanel" class="card shadow-sm d-none">
 <div class="card-header"><strong>Detalle y verificación de propuesta <span id="rcId"></span></strong></div>
 <div class="card-body">
  <div class="row g-3">
    <div class="col-lg-6">
     <dl class="row mb-2" id="rcDatos"></dl>
     <h6>Registros históricos seleccionados</h6><div id="rcHistoricos"></div>
     <section class="mt-3" aria-label="Jurisdicciones propuestas">
      <h6>Jurisdicciones policiales propuestas</h6>
      <div id="rcJurisdicciones" class="mb-2 small"></div>
      <div id="rcJurDecision" class="border rounded p-2 d-none">
        <div id="rcJurDecisionTitulo" class="fw-semibold small mb-2"></div>
        <label for="rcJurFundamento" class="form-label small">Fundamento de la decisión</label>
        <textarea id="rcJurFundamento" class="form-control form-control-sm" maxlength="1500" rows="2" placeholder="Fundamento documental y/o territorial (mínimo 12 caracteres)"></textarea>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <button type="button" id="rcJurValidar" class="btn btn-sm btn-outline-success">Validar relación</button>
          <button type="button" id="rcJurDescartar" class="btn btn-sm btn-outline-danger">Descartar relación</button>
          <button type="button" id="rcJurCancelar" class="btn btn-sm btn-outline-secondary">Cancelar</button>
        </div>
      </div>
      <div id="rcJurMensaje" class="small mt-1" role="status" aria-live="polite"></div>
    </section>
     <h6 class="mt-3">Controles automáticos preliminares</h6><div id="rcControles"></div>
    </div>
    <div class="col-lg-6">
     <h6>Geometría municipal preliminar</h6>
     <div id="rcMapa" aria-label="Previsualización del perímetro municipal"></div>
     <small class="text-body-secondary">Vista de referencia. La correspondencia territorial y la validez topológica requieren revisión.</small>
    </div>
  </div>
  <div class="alert alert-warning mt-3 mb-3">Antes de aprobar, ejecutá la simulación y verificá la cartografía municipal, las referencias históricas, el código SURC y la auditoría. Los polígonos complejos quedan bloqueados.</div>
  <div id="rcRechazoBox" class="d-none">
   <label class="form-label" for="rcFundamento">Fundamento del rechazo (obligatorio)</label>
   <textarea class="form-control" id="rcFundamento" rows="3" maxlength="3000"></textarea>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
   <button type="button" id="rcMostrarRechazo" class="btn btn-outline-danger">Rechazar propuesta</button>
   <button type="button" id="rcConfirmarRechazo" class="btn btn-danger d-none">Confirmar rechazo</button>
   <button type="button" class="btn btn-outline-success" id="rcSimular">Simular aprobación</button>
   <button type="button" class="btn btn-success" id="rcAbrirAprobar" disabled>Aprobar consolidación</button>
  </div>
  <div id="rcAprobacionBox" class="border rounded p-3 mt-3 d-none">
    <div id="rcSimulacionDetalle" class="alert alert-info small"></div>
    <label for="rcMotivoAprobar" class="form-label">Fundamento de aprobación (15 caracteres mínimo)</label>
    <textarea id="rcMotivoAprobar" class="form-control" rows="3" maxlength="3000"></textarea>
    <div class="form-check my-2"><input type="checkbox" class="form-check-input" id="rcRevisionCartografica">
      <label for="rcRevisionCartografica" class="form-check-label">Confirmo que revisé la correspondencia territorial y la geometría municipal (no solo su nombre).</label></div>
    <label class="form-label" for="rcConfirmacionTexto">Escribí APROBAR para confirmar</label>
    <input type="text" id="rcConfirmacionTexto" class="form-control" autocomplete="off">
    <button type="button" id="rcConfirmarAprobar" class="btn btn-success mt-3">Confirmar publicación</button>
  </div>
  <div id="rcMensaje" class="small mt-2" role="status" aria-live="polite"></div>
 </div></div>
</div></section>
