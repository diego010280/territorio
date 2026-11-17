<?php declare(strict_types=1); ?>
<div class="container-fluid px-3 py-3" id="geoJurisRoot"
  data-csrf="<?= htmlspecialchars((string)$_SESSION['terr_jurisdicciones_csrf'],ENT_QUOTES,'UTF-8') ?>"
  data-alta="<?= $perm['alta']?'1':'0' ?>" data-modifica="<?= $perm['modifica']?'1':'0' ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div><h3 class="mb-1">Gestión de Jurisdicciones</h3><span class="text-muted">Vinculación RRHH · importaciones preliminares · propuestas con revisión</span></div>
    <span class="badge bg-warning text-dark">Etapa 1 — Sin publicación automática</span>
  </div>
  <div id="jurAviso" class="alert alert-info">Buscá una dependencia de RRHH o una jurisdicción importada. Los polígonos se presentan como solicitud y requieren aprobación.</div>
  <div class="small text-muted mb-2">Búsqueda automática desde 2 caracteres. Categorías autorizadas: 52 Comisaría, 53 Subcomisaría, 54 Destacamento, 55 Puesto policial y 56 Base Operativa. Las DDP se administran como capa territorial independiente.</div>
  <div class="row g-3">
    <div class="col-12 col-xl-5">
      <div class="card mb-3"><div class="card-header"><strong>Buscar organismos RRHH (categorías 52 a 56)</strong></div>
        <div class="card-body"><div class="input-group mb-2"><input id="jurRRHHBuscar" class="form-control" placeholder="Comisaría, Subcomisaría, Destacamento, Puesto o Base" maxlength="90" autocomplete="off"><button id="jurBtnRRHH" class="btn btn-outline-primary" type="button">Buscar</button></div>
        <div class="table-responsive" style="max-height:260px;overflow:auto"><table class="table table-sm table-hover"><thead><tr><th>ID RRHH</th><th>Organismo</th><th>Territorio</th><th></th></tr></thead><tbody id="jurRRHHLista"><tr><td colspan="4" class="text-muted">Ingrese al menos dos caracteres</td></tr></tbody></table></div></div>
      </div>
      <div class="card"><div class="card-header"><strong>Jurisdicciones de Territorio</strong></div>
        <div class="card-body"><div class="input-group mb-2"><input id="jurBuscar" class="form-control" placeholder="Escribí nombre, ID RRHH o código KML" maxlength="90"><button class="btn btn-outline-primary" id="jurBtnBuscar" type="button">Buscar</button></div>
        <div class="table-responsive" style="max-height:380px;overflow:auto"><table class="table table-sm table-hover"><thead><tr><th>Jurisdicción</th><th>RRHH</th><th>Estado</th><th></th></tr></thead><tbody id="jurLista"></tbody></table></div>
        </div></div>
    </div>
    <div class="col-12 col-xl-7">
      <div class="card"><div class="card-header d-flex justify-content-between flex-wrap gap-2"><strong>Proponer geometría de jurisdicción</strong><span class="small text-muted" id="jurSeleccion">Seleccione una jurisdicción</span></div>
        <div class="card-body">
          <div class="d-flex flex-wrap gap-3 mb-2 small">
            <label><input type="checkbox" class="form-check-input" id="jurCapaAprobada" checked> Jurisdicciones aprobadas</label>
            <label><input type="checkbox" class="form-check-input" id="jurCapaBorrador" checked> KML preliminar / borradores</label>
            <button class="btn btn-sm btn-outline-secondary" id="jurCentrar" type="button">Centrar selección</button>
          </div>
          <div id="jurMapa" style="height:530px;max-height:72vh;border-radius:.5rem;border:1px solid #ddd"></div>
          <div class="small text-muted mt-2 mb-2"><strong>Pasos:</strong> 1. Elegí la jurisdicción. 2. Cargá el borrador del KML o dibujá uno nuevo. 3. Presioná <strong>Editar vértices</strong>. 4. Guardá la edición. 5. Completá el motivo y presentá la solicitud. Los cambios NO se publican automáticamente.</div>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <button class="btn btn-sm btn-outline-primary" id="jurCargarBorrador" type="button">1. Cargar borrador KML</button>
            <button class="btn btn-sm btn-outline-secondary" id="jurCargarAprobada" type="button" title="Solo si ya existe un polígono aprobado: carga una copia para proponer otra versión">Copiar polígono oficial</button>
            <button class="btn btn-sm btn-outline-primary" id="jurDibujarNuevo" type="button">Dibujar nuevo</button>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-2 p-2 border rounded bg-body-tertiary">
            <button class="btn btn-sm btn-primary" id="jurEditarVertices" type="button" disabled>2. Editar vértices</button>
            <button class="btn btn-sm btn-success" id="jurGuardarEdicion" type="button" disabled>3. Guardar edición</button>
            <button class="btn btn-sm btn-outline-danger" id="jurCancelarEdicion" type="button" disabled>Cancelar edición</button>
          </div>
          <p class="small text-muted mb-2">Durante la edición las capas de referencia se ocultan para trabajar mejor. Arrastrá los nodos para moverlos y los puntos intermedios para agregar vértices. «Guardar edición» solo guarda la copia en pantalla: para enviar el cambio debés presionar «Presentar solicitud».</p>
          <div class="small text-muted my-2" id="jurEstadoGeometria"></div>
          <label for="jurMotivo" class="form-label">Motivo de la propuesta</label>
          <textarea id="jurMotivo" rows="2" class="form-control" placeholder="Explicar por qué se incorpora o modifica la jurisdicción"></textarea>
          <button type="button" id="jurProponer" class="btn btn-success mt-2" <?= !$perm['modifica']?'disabled':'' ?>>Presentar solicitud de geometría</button>
          <button type="button" id="jurLimpiarMapa" class="btn btn-outline-secondary mt-2">Limpiar dibujo</button>
        </div></div>
    </div>
  </div>
</div>
