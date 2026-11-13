<div class="app-content-header"><div class="container-fluid">
 <h1 class="mb-1"><i class="bi bi-diagram-3 me-2"></i>Consolidación de barrios</h1>
 <p class="text-body-secondary">Propuesta administrativa: no crea gobernantes, no oculta históricos ni vincula códigos SURC hasta que sea aprobada.</p>
</div></div>
<div class="app-content"><div class="container-fluid">
 <div class="card shadow-sm"><div class="card-body">
  <form id="cbForm" novalidate>
   <div class="row g-3">
    <div class="col-12 col-lg-5">
     <label for="cbLocalidadBusca" class="form-label">1. Buscar localidad *</label>
     <input id="cbLocalidadBusca" type="search" class="form-control" placeholder="SALTA" autocomplete="off">
     <div id="cbLocalidadLista" class="list-group mt-1 cb-resultados"></div>
     <div id="cbLocalidadElegida" class="small text-success mt-1" aria-live="polite"></div>
     <label for="cbNombre" class="form-label mt-3">2. Nombre del gobernante *</label>
     <input id="cbNombre" class="form-control" maxlength="200" placeholder="SAN MARTÍN" required>
     <label for="cbTipo" class="form-label mt-3">Tipo de relación *</label>
     <select id="cbTipo" class="form-select"><option value="SECTOR">SECTOR: sectores del mismo barrio</option><option value="DUPLICADO">DUPLICADO: registro repetido</option><option value="ABSORBIDO">ABSORBIDO: antecedente absorbido</option></select>
     <label for="cbMotivo" class="form-label mt-3">Fundamento de la consolidación *</label>
     <textarea id="cbMotivo" rows="4" class="form-control" required placeholder="Justificación y evidencia territorial de la relación…"></textarea>
    </div>
    <div class="col-12 col-lg-7">
     <label for="cbHistoricoBusca" class="form-label">3. Buscar registros históricos (mínimo dos) *</label>
     <input id="cbHistoricoBusca" type="search" class="form-control" placeholder="SAN MARTÍN" autocomplete="off">
     <div id="cbHistoricoLista" class="list-group mt-1 cb-resultados"></div>
     <div id="cbElegidos" class="mt-2 d-flex gap-2 flex-wrap"></div>
     <div class="alert alert-light border small mt-3 mb-2">La propuesta de gobernante será nueva. Los históricos elegidos se conservarán y no se modificarán mientras la propuesta esté pendiente.</div>
     <label for="cbMunicipalBusca" class="form-label">4. Polígono municipal candidato (opcional)</label>
     <input id="cbMunicipalBusca" type="search" class="form-control" placeholder="SAN MARTÍN">
     <div id="cbMunicipalLista" class="list-group mt-1 cb-resultados"></div>
     <div id="cbMunicipalElegido" class="small text-success mt-1"></div>
     <label for="cbSurcBusca" class="form-label mt-3">5. Buscar barrio SURC / dbseg (opcional)</label>
     <input id="cbSurcBusca" type="search" class="form-control" autocomplete="off" placeholder="Escribir nombre: SAN MARTÍN">
     <div id="cbSurcLista" class="list-group mt-1 cb-resultados" aria-label="Coincidencias del catálogo SURC"></div>
     <div id="cbSurcInfo" class="small mt-2" aria-live="polite">Seleccioná un resultado del catálogo SURC. No se vincula automáticamente.</div>
     <div id="cbSurcElegido" class="small text-success mt-1" aria-live="polite"></div>
     <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="cbQuitarSurc" disabled>Quitar selección SURC</button>
    </div>
   </div>
   <div id="cbMensaje" class="mt-3 small" role="status" aria-live="polite"></div>
   <div class="d-flex gap-2 mt-3 flex-wrap"><button class="btn btn-success" type="submit" id="cbGuardar"><i class="bi bi-send me-1"></i>Presentar consolidación pendiente</button><a class="btn btn-outline-secondary" href="gestion_barrios.php">Volver a Gestión Barrios</a></div>
  </form>
 </div></div>
</div></div>
