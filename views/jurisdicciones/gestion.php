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
  <!-- Grilla principal de Jurisdicciones: filtro y paginación en servidor -->
  <section class="card mb-3" id="jurGrillaPrincipal">
    <div class="card-header d-flex justify-content-between flex-wrap align-items-center gap-2">
      <strong><i class="bi bi-table me-1"></i> Jurisdicciones registradas</strong>
      <button type="button" id="jgNuevaRRHH" class="btn btn-sm btn-outline-success"><i class="bi bi-plus-circle"></i> Vincular nueva dependencia RRHH</button>
    </div>
    <div class="card-body">
      <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-lg-4"><label class="form-label" for="jgTexto">Nombre, código, ID territorial o RRHH</label><input id="jgTexto" type="search" class="form-control" placeholder="Escribí para buscar..." autocomplete="off"></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="jgTipo">Tipo</label><select id="jgTipo" class="form-select"><option value="">Todos</option><option value="DEPENDENCIA">Dependencias</option><option value="DDP">DDP</option></select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="jgEstado">Estado</label><select id="jgEstado" class="form-select"><option value="">Todos</option><option value="PRELIMINAR">Preliminar</option><option value="APROBADO">Aprobado</option></select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="jgDDP">DDP</label><select id="jgDDP" class="form-select"><option value="0">Todos</option><?php for($n=1;$n<=14;$n++): ?><option value="<?= $n ?>"><?= $n ?></option><?php endfor ?></select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="jgGeometria">Geometría</label><select id="jgGeometria" class="form-select"><option value="">Todas</option><option value="APROBADA">Oficial</option><option value="SIN_APROBAR">Sin oficial</option><option value="BORRADOR">Borrador KML</option></select></div>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-2"><button id="jgBuscar" type="button" class="btn btn-success btn-sm">Buscar</button><button id="jgLimpiar" type="button" class="btn btn-outline-secondary btn-sm">Limpiar filtros</button><span id="jgEstadoCarga" class="small text-muted align-self-center" role="status"></span></div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle" aria-label="Listado de jurisdicciones"><thead><tr><th>ID</th><th>Jurisdicción</th><th>Tipo / DDP</th><th>RRHH</th><th>Estado</th><th>Geometría</th><th>Versión</th><th>Color</th><th>Solicitudes</th><th>Acción</th></tr></thead><tbody id="jgFilas"><tr><td colspan="10" class="text-muted">Cargando jurisdicciones...</td></tr></tbody></table></div>
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3"><small id="jgConteo" class="text-muted"></small><div class="d-flex gap-2 align-items-center"><button type="button" class="btn btn-outline-secondary btn-sm" id="jgAnterior">Anterior</button><span id="jgPagina" class="small"></span><button type="button" class="btn btn-outline-secondary btn-sm" id="jgSiguiente">Siguiente</button></div></div>
    </div>
  </section>
  <div id="jurEditorPanel" class="d-none">
    <div class="d-flex align-items-center justify-content-between mb-3"><strong id="jurTituloDetalle">Detalle de jurisdicción</strong><button type="button" class="btn btn-outline-secondary btn-sm" id="jgCerrarDetalle"><i class="bi bi-arrow-left"></i> Volver a la grilla</button></div>
  <div class="row g-3">
    <div class="col-12 col-xl-5">
      <div class="card mb-3"><div class="card-header"><strong>Buscar organismos RRHH (categorías 52 a 56)</strong></div>
        <div class="card-body"><div class="input-group mb-2"><input id="jurRRHHBuscar" class="form-control" placeholder="Comisaría, Subcomisaría, Destacamento, Puesto o Base" maxlength="90" autocomplete="off"><button id="jurBtnRRHH" class="btn btn-outline-primary" type="button">Buscar</button></div>
        <div class="table-responsive" style="max-height:260px;overflow:auto"><table class="table table-sm table-hover"><thead><tr><th>ID RRHH</th><th>Organismo</th><th>Territorio</th><th></th></tr></thead><tbody id="jurRRHHLista"><tr><td colspan="4" class="text-muted">Ingrese al menos dos caracteres</td></tr></tbody></table></div></div>
      </div>
      <div class="card" id="jurListadoPanel"><div class="card-header"><strong>Jurisdicciones de Territorio</strong></div>
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
          <div class="border rounded p-2 mb-2" id="jurVecinosPanel">
            <div class="fw-semibold small mb-2"><i class="bi bi-intersect me-1"></i>Edición con jurisdicciones colindantes</div>
            <div class="row g-2 align-items-center">
              <div class="col-12 col-lg-7"><label for="jurVecinoSeleccion" class="form-label small mb-1">Jurisdicción vecina aprobada</label><select id="jurVecinoSeleccion" class="form-select form-select-sm"><option value="">Elegí un vecino aprobado…</option></select></div>
              <div class="col-12 col-lg-5 d-flex flex-column gap-1">
                <label class="small"><input id="jurVerVecinos" type="checkbox" class="form-check-input" checked> Ver límite vecino (violeta)</label>
                <label class="small"><input id="jurAjusteVecinos" type="checkbox" class="form-check-input" checked> Ajustar vértices arrastrados</label>
              </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
              <button type="button" id="jurZoomVecino" class="btn btn-sm btn-outline-primary"><i class="bi bi-zoom-in me-1"></i>Acercar al límite compartido</button>
              <label for="jurToleranciaSnap" class="small mb-0">Sensibilidad:</label>
              <input id="jurToleranciaSnap" type="range" class="form-range" min="8" max="60" step="2" value="18" style="width:120px" aria-label="Distancia de ajuste automático en píxeles">
              <span class="small fw-semibold" id="jurToleranciaTexto">18 px</span>
              <span class="text-muted small">A mayor valor, el vértice se imanta desde más lejos.</span>
            </div>
            <div id="jurPrecisionEstado" class="small text-muted mt-1" role="status">El vecino es solo referencia; ninguna acción cambia su geometría oficial.</div>
            <div id="jurVecinoEstado" class="form-text">Seleccioná una jurisdicción. Solo los polígonos aprobados se utilizan como referencia.</div>
          </div>
          <div id="jurMapa" style="height:630px;max-height:82vh;border-radius:.5rem;border:1px solid #ddd"></div>
          <div class="small text-muted mt-2 mb-2"><strong>Pasos:</strong> 1. Elegí la jurisdicción. 2. Cargá el borrador del KML o dibujá uno nuevo. 3. Presioná <strong>Editar vértices</strong>. 4. Guardá la edición. 5. Completá el motivo y presentá la solicitud. Los cambios NO se publican automáticamente.</div>
          <div class="border rounded p-3 mb-3 bg-body-tertiary" aria-label="Herramientas de geometría">
            <div class="fw-semibold mb-2">Herramientas de geometría — propuesta NO oficial</div>
            <div class="small text-muted mb-2">Primero elegí la jurisdicción y cargá el borrador KML, copiá el oficial (si ya existe), dibujá una nueva geometría o importá GeoJSON.</div>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <button class="btn btn-sm btn-outline-primary" id="jurCargarBorrador" type="button">Cargar borrador KML</button>
              <button class="btn btn-sm btn-outline-secondary" id="jurCargarAprobada" type="button" title="Disponible únicamente si ya existe un polígono oficial aprobado">Copiar polígono oficial</button>
              <button type="button" class="btn btn-sm btn-outline-dark" id="jurImportarGeoJSON">Importar GeoJSON</button>
              <input type="file" id="jurArchivoGeoJSON" accept=".json,.geojson,application/geo+json,application/json" hidden>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <button type="button" class="btn btn-sm btn-outline-success" id="jurDibujarGuiado">⬡ Dibujar</button>
              <button type="button" class="btn btn-sm btn-outline-primary" id="jurCerrarPoligono" disabled>Cerrar polígono</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="jurDeshacerVertice" disabled>Deshacer vértice</button>
              <button type="button" class="btn btn-sm btn-primary" id="jurEditarGuiado">Editar vértices</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="jurAjustarVertice" disabled><i class="bi bi-magnet me-1"></i>Ajustar vértice elegido al vecino</button>
              <div id="jurAjusteAyuda" role="status" class="small text-muted w-100">Cargá la geometría, seleccioná un vecino aprobado, activá «Editar vértices» y tocá un nodo cuadrado azul.</div>
              <button type="button" class="btn btn-sm btn-outline-danger" id="jurEliminarVertice" disabled>Eliminar vértice seleccionado</button>
              <button type="button" class="btn btn-sm btn-outline-success" id="jurFinalizarGuiado" disabled>Finalizar edición</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="jurCancelarEdicion" disabled>Cancelar edición</button>
              <button type="button" class="btn btn-sm btn-outline-danger" id="jurQuitarPropuesta">Quitar geometría propuesta</button>
            </div>
            <div class="small" id="jurModoEditor" role="status">Modo: consulta. Elegí una jurisdicción para comenzar.</div>
            <div class="small text-muted mt-2">Para mover o agregar vértices, elegí «Editar vértices». «Finalizar edición» conserva el dibujo solamente en pantalla; «Presentar solicitud» registra la propuesta pendiente.</div>
          </div>
          <div class="alert alert-light border py-2 small" id="jurSeleccionDetalle">Ninguna jurisdicción seleccionada.</div>
          <div class="small text-muted my-2" id="jurEstadoGeometria"></div>
          <div class="d-flex align-items-center gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-outline-primary" id="jurAbrirColor" disabled><i class="bi bi-palette"></i> Color oficial</button>
            <span id="jurMuestraColor" class="d-none border rounded" style="width:28px;height:26px;background:#287c67" aria-hidden="true"></span>
            <span class="small text-muted" id="jurColorTexto">Disponible al seleccionar una jurisdicción aprobada.</span>
          </div>
          <div class="border rounded p-2 my-2 bg-light d-none" id="jurBloqueCorreccionPendiente">
           <div class="small fw-semibold mb-1">Corrección de solicitud pendiente (con historial)</div>
           <button type="button" class="btn btn-outline-primary btn-sm" id="jurCargarPendiente">Cargar solicitud pendiente</button>
           <button type="button" class="btn btn-primary btn-sm" id="jurGuardarCorreccion" disabled>Guardar corrección auditada</button>
           <div id="jurPendienteEstado" class="small text-muted mt-2">Seleccioná una jurisdicción para consultar sus solicitudes pendientes.</div>
         </div>
         <div class="my-2"><button type="button" class="btn btn-outline-warning btn-sm" id="jurComprobarSolape"><i class="bi bi-shield-check me-1"></i>Comprobar solapamiento</button><div id="jurResultadoSolape" role="status" class="small mt-2"></div><p class="small text-muted mb-1">El ajuste de puntos no reemplaza segmentos completos: un resultado sin solapamientos NO confirma ausencia de espacios. Revisá el límite compartido a zoom alto y no apruebes hasta comprobar su continuidad.</p></div>
          <label for="jurMotivo" class="form-label">Motivo de la propuesta <strong>geométrica</strong></label>
          <textarea id="jurMotivo" rows="2" class="form-control" placeholder="Explicar por qué se incorpora o modifica la jurisdicción"></textarea>
          <div id="jurEstadoPropuesta" role="status" class="small mt-2 text-muted"></div>
          <button type="button" id="jurProponer" class="btn btn-success mt-2" <?= !($perm['alta']||$perm['modifica'])?'disabled':'' ?>>Presentar solicitud de geometría</button>
          <button type="button" id="jurLimpiarMapa" class="btn btn-outline-secondary mt-2">Limpiar dibujo</button>
          <div class="border-top mt-3 pt-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <button type="button" id="jurVolverListado" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-circle me-1"></i> Volver a la grilla de jurisdicciones</button>
            <a href="revision_jurisdicciones.php" class="btn btn-outline-primary"><i class="bi bi-clipboard-check me-1"></i> Ir a revisión de solicitudes</a>
          </div>
          <p class="small text-muted mt-2 mb-0">El cambio de color se guarda directamente y mantiene la jurisdicción aprobada. Una propuesta de geometría queda PENDIENTE hasta su revisión y aprobación.</p>
        </div></div>
    </div>
  </div>
  </div><!-- /jurEditorPanel -->
</div>

<!-- Modal de color: requerido por gestion-jurisdicciones.js -->
<div class="modal fade" id="jurColorModal" tabindex="-1" aria-labelledby="jurColorModalTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="jurColorModalTitulo"><i class="bi bi-palette me-2"></i>Color de la jurisdicción aprobada</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="fw-semibold mb-3" id="jurColorNombre"></div>
        <label for="jurColorInput" class="form-label">Color de relleno y contorno</label>
        <div class="d-flex align-items-center gap-3">
          <input class="form-control form-control-color" type="color" id="jurColorInput" value="#287c67" title="Elegir color">
          <code id="jurColorHex">#287C67</code>
        </div>
        <label for="jurColorMotivo" class="form-label mt-3">Motivo del cambio de color <span class="text-danger">*</span></label>
        <textarea id="jurColorMotivo" rows="2" maxlength="255" class="form-control" placeholder="Ej.: Diferenciar visualmente esta jurisdicción de las vecinas" aria-describedby="jurColorMotivoAyuda"></textarea>
        <div id="jurColorMotivoAyuda" class="form-text">Obligatorio: entre 10 y 255 caracteres. Se guardará con el color anterior y el nuevo en la auditoría ABM.</div>
        <div class="form-text mt-2">Cambiar el color no modifica los límites ni crea otra versión geométrica. No requiere presentar una solicitud.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="jurGuardarColor" disabled>Guardar color</button>
      </div>
    </div>
  </div>
</div>
