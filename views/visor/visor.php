<div class="app-content-header">
    <div class="container-fluid">
        <h1 class="mb-1"><i class="bi bi-map me-2"></i>Visor Territorial</h1>
        <p class="text-body-secondary mb-0">Consulta de cartografía municipal en revisión, no oficial.</p>
    </div>
</div>
<div class="app-content">
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-lg-8">
                        <label for="visorBuscar" class="form-label">Buscar por barrio</label>
                        <input class="form-control" id="visorBuscar" placeholder="Nombre del barrio" autocomplete="off">
                        <div id="visorResultados" class="list-group mt-1"></div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Coordenadas seleccionadas</label>
                        <div class="input-group input-group-sm">
                            <input class="form-control" id="visorLat" readonly aria-label="Latitud">
                            <input class="form-control" id="visorLon" readonly aria-label="Longitud">
                            <button class="btn btn-territorio" id="visorConsultar" type="button" disabled>Consultar</button>
                        </div>
                    </div>
                </div>
                <div id="mapaTerritorio" role="region" aria-label="Mapa territorial"></div>
                <div id="visorEstado" class="small text-body-secondary mt-2" role="status">Preparando mapa…</div>
            </div>
        </div>
    </div>
</div>
