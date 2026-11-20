<div class="app-content-header"><div class="container-fluid">
  <h1 class="mb-1"><i class="bi bi-map me-2"></i>Visor Territorial</h1>
  <p class="text-body-secondary mb-0">Consultá barrios y jurisdicciones policiales aprobadas. Seleccioná un punto para identificar todas las coberturas oficiales disponibles.</p>
</div></div>
<div class="app-content"><div class="container-fluid"><div class="card shadow-sm"><div class="card-body">
  <div class="row g-3 mb-3">
    <div class="col-lg-7"><label class="form-label" for="visorBuscar">Buscar barrio o dependencia policial</label>
      <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="visorBuscar" placeholder="Ej.: San Martín, Comisaría 1, 2404" autocomplete="off" aria-controls="visorResultados"><button class="btn btn-outline-secondary" type="button" id="visorLimpiarBusqueda">Limpiar</button></div>
      <div id="visorResultados" class="list-group visor-resultados" role="listbox" aria-label="Resultados de búsqueda"></div>
    </div>
    <div class="col-lg-5"><label class="form-label" for="visorLat">Consulta de coordenadas</label><div class="input-group"><input class="form-control" id="visorLat" placeholder="Latitud" inputmode="decimal" aria-label="Latitud"><input class="form-control" id="visorLon" placeholder="Longitud" inputmode="decimal" aria-label="Longitud"><button class="btn btn-success" type="button" id="visorConsultar">Consultar</button></div></div>
  </div>
  <div class="visor18-toolbar mb-2" aria-label="Capas y herramientas">
    <div class="visor18-capabox"><span class="fw-semibold me-1">Mostrar en el mapa:</span>
      <label class="visor18-check"><input type="checkbox" id="visorCapaBarrios" checked> <span class="visor18-muestra visor18-barrio"></span> Barrios oficiales <small id="visorCuentaBarrios"></small></label>
      <label class="visor18-check"><input type="checkbox" id="visorCapaDependencias" checked> <span class="visor18-muestra visor18-dep"></span> Jurisdicciones oficiales <small id="visorCuentaDependencias"></small></label>
    </div>
    <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="visorLimpiarPunto"><i class="bi bi-geo-alt"></i> Quitar punto</button><button type="button" class="btn btn-sm btn-outline-success" id="visorLimpiarTodo"><i class="bi bi-arrow-counterclockwise"></i> Restablecer</button></div>
  </div>
  <div id="mapaTerritorio" data-visor-csrf="<?= htmlspecialchars((string)($_SESSION['visor18_csrf'] ?? ($_SESSION['visor18_csrf'] = bin2hex(random_bytes(16)))), ENT_QUOTES, 'UTF-8') ?>" class="visor18-mapa" role="region" aria-label="Mapa con barrios y jurisdicciones oficiales aprobadas"></div>
  <div class="visor19-leyenda mt-2" aria-label="Significado de las capas">
    <span><i class="visor19-trazo visor19-trazo-barrio"></i> Barrio oficial: borde definido y relleno suave</span>
    <span><i class="visor19-trazo visor19-trazo-juris"></i> Jurisdicción policial: borde destacado y color asignado</span>
    <span class="text-body-secondary">Al pulsar un punto se identifican ambas capas, si coinciden.</span>
  </div>
  <section class="visor18-panel card mt-3 d-none" id="visorResultadoPanel" aria-live="polite">
    <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold"><i class="bi bi-geo-alt me-1"></i> Identificación territorial del punto</span>
      <button class="btn btn-sm btn-outline-secondary" type="button" id="visorCerrarIdentificacion" aria-label="Cerrar identificación">Cerrar</button>
    </div>
    <div class="card-body" id="visorIdentificacion"></div>
  </section>
  <div class="small text-body-secondary mt-2" id="visorEstado" role="status" aria-live="polite">Cargando cartografía oficial…</div>
</div></div></div></div>
<link rel="stylesheet" href="./assets/css/visor-oficial-v19.css?v=19">
