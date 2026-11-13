<div class="app-content-header"><div class="container-fluid">
<h1 class="mb-1"><i class="bi bi-shield-check me-2"></i>Jurisdicciones propuestas</h1>
<p class="text-body-secondary">Seleccioná las dependencias policiales vinculadas a una consolidación pendiente. No se publican asignaciones actuales.</p>
</div></div>
<div class="app-content"><div class="container-fluid">
<div class="card shadow-sm"><div class="card-body">
<div class="row g-2 align-items-end mb-3"><div class="col-sm-4 col-lg-3">
<label for="jcId" class="form-label">Consolidación N.º</label>
<input id="jcId" type="number" min="1" class="form-control" value="<?= (int)($_GET['id']??1) ?>">
</div><div class="col-auto"><button id="jcCargar" type="button" class="btn btn-outline-success">Abrir consolidación</button></div></div>
<div id="jcCabecera" class="fw-semibold mb-2"></div>
<div class="row g-3"><div class="col-lg-5">
<h2 class="h6">Referencias históricas RRHH (solo consulta)</h2>
<div id="jcAntecedentes" class="list-group mb-3"></div>
<div class="alert alert-warning small">La continuidad del ID institucional no demuestra la jurisdicción geográfica. Las referencias históricas NO se convierten automáticamente en vigentes.</div>
</div><div class="col-lg-7">
<h2 class="h6">Agregar dependencias policiales activas</h2>
<label for="jcBuscar" class="form-label">Buscar por nombre o ID (categorías 52, 53, 54)</label>
<input id="jcBuscar" class="form-control" type="search" autocomplete="off" placeholder="Comisaría, subcomisaría, destacamento...">
<div id="jcResultados" class="list-group mt-1"></div>
<div id="jcSeleccionadas" class="mt-3"></div>
</div></div>
<div id="jcMensaje" class="small mt-3" role="status" aria-live="polite"></div>
<div class="mt-3 d-flex flex-wrap gap-2"><button id="jcGuardar" class="btn btn-success" type="button">Guardar jurisdicciones propuestas</button>
<a class="btn btn-outline-secondary" href="revision_consolidaciones.php">Volver a revisión</a></div>
</div></div></div></div>
