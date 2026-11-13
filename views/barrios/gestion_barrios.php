<?php declare(strict_types=1); ?>
<div class="app-content-header"><div class="container-fluid">
 <h1 class="mb-1"><i class="bi bi-houses me-2"></i>Administración de Barrios</h1>
 <p class="text-body-secondary mb-0">Catálogo territorial y trazabilidad histórica</p>
</div></div>
<div class="app-content"><div class="container-fluid">
 <div class="d-flex align-items-center flex-wrap justify-content-between gap-2 mb-3">
   <div class="small text-body-secondary">Consulta y gestión de solicitudes de barrios</div>
   <?php if ($authBarrios->puede('gestion_barrios.php', 'alta')): ?>
     <a class="btn btn-success" href="propuesta_barrio.php">
       <i class="bi bi-plus-circle me-1"></i>Nueva solicitud
     </a>
   <?php endif; ?>
 </div>
 <div class="card shadow-sm mb-3">
  <div class="card-header"><h2 class="card-title fs-6 mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h2></div>
  <div class="card-body">
   <div class="row g-3 align-items-end">
    <div class="col-12 col-md-5"><label for="gbNombre" class="form-label">Nombre</label><input id="gbNombre" class="form-control" type="search" placeholder="Nombre del barrio" maxlength="120" autocomplete="off" aria-controls="gbNombreResultados"><div id="gbNombreResultados" class="list-group geo-autocomplete" role="listbox"></div></div>
    <div class="col-12 col-md-4"><label for="gbLocalidad" class="form-label">Localidad</label><input id="gbLocalidadBusqueda" class="form-control mb-1" type="search" placeholder="Buscar localidad..." autocomplete="off" aria-controls="gbLocalidadResultados"><div id="gbLocalidadResultados" class="list-group geo-autocomplete" role="listbox"></div><select id="gbLocalidad" class="form-select"><option value="">Todas las localidades</option></select></div>
    <div class="col-12 col-md-3"><label for="gbEstado" class="form-label">Estado</label><select id="gbEstado" class="form-select"><option value="">Todos</option><option value="IMPORTADO">Importado</option><option value="PENDIENTE">Pendiente</option><option value="APROBADO">Aprobado</option><option value="RECHAZADO">Rechazado</option></select></div>
   </div>
   <div class="row g-3 mt-1">
     <div class="col-12 col-md-5">
       <label for="gbVista" class="form-label">Visualización del catálogo</label>
       <select id="gbVista" class="form-select">
         <option value="principales">Barrios principales y no consolidados</option>
         <option value="historicos">Solo históricos consolidados</option>
         <option value="todos">Todos los registros (administración)</option>
       </select>
       <div class="form-text">Los históricos aprobados se consultan desde el gobernante; nunca se eliminan.</div>
     </div>
   </div>
   <div class="d-flex gap-2 flex-wrap mt-3">
    <button class="btn btn-success" id="gbBuscar" type="button"><i class="bi bi-search me-1"></i>Buscar</button>
    <button class="btn btn-outline-secondary" id="gbLimpiar" type="button"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar filtros</button>
   </div>
  </div>
 </div>
 <div class="card shadow-sm">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
   <h2 class="card-title fs-6 mb-0"><i class="bi bi-list-ul me-2"></i>Barrios registrados</h2>
   <span class="badge text-bg-secondary">Consultas y solicitudes · fase 2</span>
  </div>
  <div class="card-body">
   <?php if (session_status() !== PHP_SESSION_ACTIVE) session_start();
      if (empty($_SESSION['terr_color_csrf'])) $_SESSION['terr_color_csrf'] = bin2hex(random_bytes(32)); ?>
   <div id="gbPermisos" data-puede-modificar="<?= $authBarrios->puede('gestion_barrios.php', 'modifica') ? '1' : '0' ?>"
      data-color-csrf="<?= htmlspecialchars($_SESSION['terr_color_csrf'], ENT_QUOTES, 'UTF-8') ?>" hidden></div>
   <div class="table-responsive"><table id="gbTabla" class="table table-hover table-striped align-middle w-100">
    <thead><tr><th>ID</th><th>Nombre</th><th>Localidad</th><th>Origen DBGRAL</th><th>Jurisdicción histórica</th><th>Estado</th><th>Geometría aprobada</th><th>Vínculo SURC</th><th>Color</th><th>Acción</th></tr></thead>
    <tbody></tbody>
   </table></div>
   <div id="gbMensaje" class="small text-body-secondary mt-2" role="status" aria-live="polite"></div>
  </div>
 </div>
</div></div>
<div class="modal fade" id="gbColorModal" tabindex="-1" aria-labelledby="gbColorTitulo" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><h2 class="modal-title fs-5" id="gbColorTitulo">Color del barrio aprobado</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
 <div class="modal-body">
  <p id="gbColorNombre" class="fw-semibold mb-2"></p>
  <label for="gbColorInput" class="form-label">Color de relleno y contorno</label>
  <div class="d-flex align-items-center gap-2 mb-2"><input id="gbColorInput" type="color" class="form-control form-control-color" value="#28846A" title="Elegir color" aria-label="Color de barrio"><output id="gbColorHex">#28846A</output></div>
  <p class="small text-body-secondary">El visor usará este color con relleno semitransparente. No modifica la geometría ni su versión.</p>
  <div id="gbColorEstado" role="status" aria-live="polite" class="small"></div>
 </div>
 <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button type="button" id="gbColorGuardar" class="btn btn-success">Guardar color</button></div>
 </div></div>
</div>
<div class="modal fade" id="gbModal" tabindex="-1" aria-labelledby="gbModalTitulo" aria-hidden="true">
 <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
 <div class="modal-header"><h2 class="modal-title fs-5" id="gbModalTitulo">Detalle de barrio</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
 <div class="modal-body" id="gbModalContenido">Cargando…</div>
 <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
 </div></div>
</div>
