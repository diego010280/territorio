<div class="app-content-header">
  <div class="container-fluid">
    <h1 class="mb-1"><i class="bi bi-map me-2"></i>Visor Territorial</h1>
    <p class="text-body-secondary mb-0">
      Cartografía municipal preliminar. Ningún polígono representa un barrio aprobado.
    </p>
  </div>
</div>
<div class="app-content">
 <div class="container-fluid">
  <div class="card shadow-sm">
   <div class="card-body">
    <div class="row g-3 mb-3">
     <div class="col-xl-7">
      <label for="visorBuscar" class="form-label">Buscar barrio municipal</label>
      <input class="form-control" id="visorBuscar" placeholder="Escribí el nombre del barrio…" autocomplete="off">
      <div id="visorResultados" class="list-group mt-1" aria-label="Coincidencias de barrios"></div>
     </div>
     <div class="col-xl-5">
      <label for="visorLat" class="form-label">Consultar coordenadas (latitud y longitud)</label>
      <div class="input-group">
       <input class="form-control" id="visorLat" inputmode="decimal" aria-label="Latitud" placeholder="-24.7821">
       <input class="form-control" id="visorLon" inputmode="decimal" aria-label="Longitud" placeholder="-65.4232">
       <button class="btn btn-territorio" id="visorConsultar" type="button">Consultar</button>
      </div>
     </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Limpiar filtros y selecciones">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="visorLimpiarBusqueda"><i class="bi bi-x-circle me-1"></i>Limpiar búsqueda</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="visorLimpiarPunto"><i class="bi bi-geo-alt me-1"></i>Quitar marcador</button>
      <button type="button" class="btn btn-outline-success btn-sm" id="visorLimpiarTodo"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar todo</button>
    </div>
    <div class="visor-mapa-wrapper">
      <div id="mapaTerritorio" role="region" aria-label="Mapa con polígonos de barrios municipales"></div>
      <div class="visor-leyenda" aria-label="Leyenda cartográfica">
        <strong>Geometrías municipales</strong>
        <div><span class="visor-muestra visor-muestra-pendiente"></span>Sin vincular / pendiente</div>
        <div><span class="visor-muestra visor-muestra-validada"></span>Validado en importación (no publicado)</div>
        <small>Estas capas no son oficiales.</small>
      </div>
    </div>
    <div id="visorEstado" class="small text-body-secondary mt-2" role="status" aria-live="polite">Preparando mapa…</div>
   </div>
  </div>
 </div>
</div>
