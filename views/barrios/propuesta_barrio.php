<?php declare(strict_types=1);
$idEdicion = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$esEdicion = $idEdicion && $idEdicion > 0;
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['geo_csrf'])) $_SESSION['geo_csrf'] = bin2hex(random_bytes(32));
?>
<div class="app-content-header"><div class="container-fluid">
<h1 class="mb-1"><i class="bi bi-pencil-square me-2"></i><?= $esEdicion?'Proponer modificación de barrio':'Proponer alta de barrio' ?></h1>
<p class="text-body-secondary mb-0">Los cambios quedan pendientes de aprobación. No se modifica el catálogo oficial.</p>
</div></div>
<div class="app-content"><div class="container-fluid">
<form id="pbForm" data-id="<?= $esEdicion?(int)$idEdicion:0 ?>" novalidate>
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['geo_csrf'],ENT_QUOTES,'UTF-8') ?>">
<div class="row g-3">
<div class="col-12 col-xl-4"><div class="card h-100"><div class="card-header fw-semibold">Datos de la solicitud</div><div class="card-body">
<div class="mb-3"><label class="form-label" for="pbNombre">Nombre del barrio *</label><input class="form-control" id="pbNombre" maxlength="200" required autocomplete="off" aria-controls="pbCoincidencias"><div id="pbCoincidencias" class="list-group geo-autocomplete" role="status"></div></div>
<div class="mb-3"><label class="form-label" for="pbLocalidad">Localidad *</label><input class="form-control" id="pbLocalidadBusqueda" placeholder="Escribí la localidad…" autocomplete="off" aria-controls="pbLocalidadResultados"><div id="pbLocalidadResultados" class="list-group geo-autocomplete" role="listbox"></div><select class="form-select mt-2" id="pbLocalidad" required><option value="">Seleccionar localidad</option></select><div class="form-text">Seleccioná la localidad correcta de las coincidencias; si hay homónimas se indica municipio y departamento.</div></div>
<div class="mb-3"><label class="form-label" for="pbClasificacion">Clasificación</label><select class="form-select" id="pbClasificacion"><option value="">Seleccionar clasificación</option></select><div class="form-text">Categorías controladas por el catálogo territorial.</div></div>
<div class="mb-3"><label class="form-label" for="pbDescripcion">Descripción</label><textarea id="pbDescripcion" class="form-control" rows="2" maxlength="5000"></textarea></div>
<div class="mb-3"><label class="form-label" for="pbMotivo">Fundamento / motivo del cambio *</label><textarea id="pbMotivo" class="form-control" rows="3" required maxlength="2000"></textarea></div>
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> El polígono es opcional: podés proponer una corrección de datos sin editar geometría.</div>
<button type="submit" id="pbGuardar" class="btn btn-success w-100"><i class="bi bi-send me-1"></i>Enviar solicitud pendiente</button>
<a href="gestion_barrios.php" class="btn btn-outline-secondary w-100 mt-2">Volver al listado</a>
<div id="pbEstado" class="small mt-3" role="status" aria-live="polite"></div>
</div></div></div>
<div class="col-12 col-xl-8"><div class="card h-100"><div class="card-header fw-semibold">Geometría propuesta (no oficial)</div><div class="card-body">
<div class="d-flex gap-2 flex-wrap mb-2">
<button type="button" class="btn btn-outline-success" id="pbDibujar"><i class="bi bi-pentagon me-1"></i>Dibujar</button>
<button type="button" class="btn btn-outline-primary" id="pbTerminar">Cerrar polígono</button>
<button type="button" class="btn btn-outline-secondary" id="pbDeshacer">Deshacer vértice</button>
<button type="button" class="btn btn-outline-primary" id="pbEditar">Editar vértices</button>
<button type="button" class="btn btn-outline-danger" id="pbEliminarVertice" disabled>Eliminar vértice seleccionado</button>
<button type="button" class="btn btn-outline-success" id="pbFinEdicion" disabled>Finalizar edición</button>
<button type="button" class="btn btn-outline-danger" id="pbBorrar"><i class="bi bi-trash me-1"></i>Quitar geometría propuesta</button>
<label class="btn btn-outline-dark mb-0" for="pbArchivo"><i class="bi bi-upload me-1"></i>Importar GeoJSON</label>
<input type="file" id="pbArchivo" class="visually-hidden" accept=".json,.geojson,application/json">
</div>
<div id="pbModoActual" class="geo-modo-actual mb-2" role="status" aria-live="polite">Modo: consulta · Para comenzar, elegí Dibujar</div>
<div class="small text-body-secondary mb-2" id="pbAyudaEdicion">Dibujar: tocá el mapa para agregar puntos. Una vez cerrado, usá Editar vértices para mover, agregar o quitar puntos.</div><div id="pbMapa"></div><div id="pbGeoEstado" class="small text-body-secondary mt-2">Dibujá un polígono o importá GeoJSON. KML/KMZ se incorporará en otra etapa.</div>
</div></div></div>
</div></form>
</div></div>
