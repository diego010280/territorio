(()=>{'use strict';
const root=document.getElementById('geoJurisRoot');if(!root)return;
const $=id=>document.getElementById(id), endpoint='/territorio/ajax/gestion_jurisdicciones.php',token=root.dataset.csrf;
let pendienteEdicion=null;
let pendienteDisponible=null,consultaPendienteSec=0;
let selected=null,selectedName='',selectedRRHH=null,selectedTipo='',map,drawn,layerDraft,layerOfficial,layerVecinos,allDraft=[],allOfficial=[],busy=false,editHandler=null,drawHandler=null,edicionEnCurso=false,edicionOriginal=null,verticeSeleccionado=null,vecinosCercanos=[],vecinoElegido=null,ajusteActivado=true, toleranciaSnapPx=18;
const puedeProponer=root.dataset.alta==='1'||root.dataset.modifica==='1';
function dialogo(titulo,texto,icono='question',color='#198754'){
 if(window.Swal?.fire)return Swal.fire({title:titulo,text:texto,icon:icono,showCancelButton:true,confirmButtonText:'Confirmar',cancelButtonText:'Cancelar',confirmButtonColor:color,reverseButtons:true,focusCancel:true}).then(r=>r.isConfirmed);
 return Promise.resolve(window.confirm(titulo+'\n\n'+texto));
}
function notificacion(titulo,texto='',tipo='success'){
 if(window.Swal?.fire){Swal.fire({icon:tipo,title:titulo,text:texto,confirmButtonText:'Aceptar',timer:tipo==='success'?2600:undefined,timerProgressBar:tipo==='success'});}
}

function aviso(msg,kind='info'){$('jurAviso').className='alert alert-'+kind;$('jurAviso').textContent=msg;}
async function api(accion,params=null,query={}){
 const post=params!==null;
 const url=endpoint+'?'+new URLSearchParams({accion,...query});
 const res=await fetch(url,post?{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify(params)}:{credentials:'same-origin',cache:'no-store'});
 const data=await res.json().catch(()=>({error:'Respuesta del servidor no válida'}));
 if(!res.ok)throw new Error(data.error||'Error HTTP '+res.status);
 return data;
}
function cell(txt){const td=document.createElement('td');td.textContent=txt==null?'':String(txt);return td;}
function boton(label,cb,disabled){const b=document.createElement('button');b.className='btn btn-sm btn-outline-primary';b.type='button';b.textContent=label;b.disabled=!!disabled;b.addEventListener('click',cb);return b;}
// Debounce: búsqueda automática mientras escribe, con descarte de respuestas anteriores.
function buscadorAutomatico(input,callback){let timer=null,numero=0;const run=()=>{const n=++numero;clearTimeout(timer);timer=setTimeout(()=>callback(n,()=>n===numero),280);};input.addEventListener('input',run);input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();run();}});return run;}
async function buscarRRHH(n=0,vigente=()=>true){
 const q=$('jurRRHHBuscar').value.trim(),tb=$('jurRRHHLista');
 if(q.length<2){tb.replaceChildren();tb.appendChild(Object.assign(document.createElement('tr'),{textContent:'Escribí al menos dos caracteres'}));return;}
 try{const r=await api('buscar_rrhh',null,{q});if(!vigente())return;tb.replaceChildren();
 if(!r.data?.length){tb.appendChild(Object.assign(document.createElement('tr'),{textContent:'Sin coincidencias en categorías 52 a 56'}));return;}
 for(const v of r.data){const tr=document.createElement('tr');tr.append(cell(v.id_rrhh),cell(v.nombre),cell(v.id_jurisdiccion?'ID '+v.id_jurisdiccion:'Sin vincular'));
 const td=document.createElement('td');if(v.id_jurisdiccion)td.append(boton('Seleccionar',()=>seleccionar(v.id_jurisdiccion,v.nombre,v.id_rrhh??v.id_organismo_rrhh,v.tipo)));
 else td.append(boton('Crear',async()=>{try{const x=await api('crear_desde_rrhh',{id_rrhh:Number(v.id_rrhh)});aviso('Jurisdicción '+x.id_jurisdiccion+' creada como preliminar.','success');notificacion('Jurisdicción preliminar creada','ID '+x.id_jurisdiccion+'. Falta asignar y aprobar su geometría.');seleccionar(x.id_jurisdiccion,v.nombre);await listar();await buscarRRHH();}catch(e){aviso(e.message,'danger');}},root.dataset.alta!=='1'));
 tr.append(td);tb.append(tr);}
 }catch(e){if(vigente())aviso(e.message,'danger');}
}
async function listar(n=0,vigente=()=>true){const q=$('jurBuscar').value.trim();try{
 const r=await api('listar',null,{q});if(!vigente())return;const tb=$('jurLista');tb.replaceChildren();
 if(!r.data?.length){tb.appendChild(Object.assign(document.createElement('tr'),{textContent:'Sin coincidencias'}));return;}
 for(const v of r.data){const tr=document.createElement('tr');tr.append(cell(v.nombre+' ('+v.tipo+')'),cell(v.id_organismo_rrhh),cell(v.estado));const td=document.createElement('td');td.append(boton('Elegir',()=>seleccionar(v.id_jurisdiccion,v.nombre,v.id_rrhh??v.id_organismo_rrhh,v.tipo)));tr.append(td);tb.append(tr);}
 }catch(e){if(vigente())aviso(e.message,'danger');}}
function estadoGuiado(t){const el=$('jurModoEditor');if(el)el.textContent=t;}
function actualizarGuiado(){
 const tiene=!!drawn&&drawn.getLayers().length>0;
 const active=!!drawHandler&&!!drawHandler._enabled;
 for(const id of ['jurCerrarPoligono','jurDeshacerVertice'])$(id).disabled=!active;
 $('jurEditarGuiado').disabled=!tiene||edicionEnCurso||active;
 $('jurFinalizarGuiado').disabled=!edicionEnCurso;
 $('jurCancelarEdicion').disabled=!edicionEnCurso;
 $('jurEliminarVertice').disabled=!edicionEnCurso||!verticeSeleccionado;activarBotonAjuste();
 $('jurDibujarGuiado').disabled=!selected||edicionEnCurso;
 $('jurQuitarPropuesta').disabled=edicionEnCurso||active||!tiene;
 $('jurImportarGeoJSON').disabled=edicionEnCurso||active||!selected;
 $('jurCargarBorrador').disabled=edicionEnCurso||active||!selected;
 $('jurCargarAprobada').disabled=edicionEnCurso||active||!selected;
 $('jurLimpiarMapa').disabled=edicionEnCurso||active;
 const motivo=$('jurMotivo').value.trim();
 const razones=[];
 if(!selected)razones.push('seleccionar una jurisdicción');
 if(!tiene)razones.push('cargar, importar o dibujar un polígono');
 if(edicionEnCurso)razones.push('finalizar o cancelar la edición de vértices');
 if(active)razones.push('cerrar o cancelar el dibujo en curso');
 if(motivo.length<10)razones.push('escribir un fundamento de al menos 10 caracteres');
 if(!puedeProponer)razones.push('permiso ALTA o MODIFICA del objeto gestion_jurisdicciones.php');
 if(busy)razones.push('esperar la respuesta del servidor');
 $('jurProponer').disabled=razones.length>0||!!pendienteEdicion;
 const btnCor=$('jurGuardarCorreccion');if(btnCor)btnCor.disabled=!pendienteEdicion||razones.length>0;
 const estadoPend=$('jurPendienteEstado');if(estadoPend&&pendienteEdicion)estadoPend.textContent='Corrigiendo solicitud N.º '+pendienteEdicion.id_solicitud+' · revisión '+pendienteEdicion.revision_actual+'. Guardar corrección conserva la anterior y requiere validar GIS.';
 const estado=$('jurEstadoPropuesta');
 if(estado){estado.textContent=razones.length?'Para presentar falta: '+razones.join('; ')+'.':'Propuesta lista para presentar como PENDIENTE. La geometría oficial NO se modificará.';estado.className='small mt-2 '+(razones.length?'text-warning':'text-success');}
}
function botonesEdicion(){actualizarGuiado();}
function centrarEditable(){if(!map||!drawn||!drawn.getLayers().length)return;
 const bounds=drawn.getBounds();if(bounds.isValid())map.fitBounds(bounds,{padding:[45,45],maxZoom:19,animate:false});}
function ocultarReferencias(){if(map.hasLayer(layerDraft))map.removeLayer(layerDraft);if(map.hasLayer(layerOfficial))map.removeLayer(layerOfficial);if($('jurVerVecinos')?.checked&&layerVecinos&&!map.hasLayer(layerVecinos))map.addLayer(layerVecinos);}
function restaurarReferencias(){if($('jurCapaBorrador').checked&&!map.hasLayer(layerDraft))map.addLayer(layerDraft);if($('jurCapaAprobada').checked&&!map.hasLayer(layerOfficial))map.addLayer(layerOfficial);if($('jurVerVecinos')?.checked&&layerVecinos&&!map.hasLayer(layerVecinos))map.addLayer(layerVecinos);}
// Las geometrías vecinas se dibujan en una capa propia NO EDITABLE.
// El ajuste ocurre únicamente sobre la copia local del polígono propuesto.
function actualizarVecinos(){
 if(!map||!layerVecinos)return;
 layerVecinos.clearLayers();vecinosCercanos=[];
 const sel=document.getElementById('jurVecinoSeleccion');
 if(sel)sel.replaceChildren(new Option('Elegí un vecino aprobado…',''));
 const f=[...allOfficial,...allDraft].find(x=>Number(x.properties?.id_jurisdiccion)===selected);
 if(!f)return;
 let bounds;try{bounds=L.geoJSON(f).getBounds();}catch(_){return;}
 if(!bounds?.isValid())return;
 // Se cargan solo polígonos con un rectángulo próximo; no las 184 jurisdicciones.
 const cercano=bounds.pad(.13);
 for(const item of allOfficial){
  if(Number(item.properties?.id_jurisdiccion)===selected)continue;
  let b;try{b=L.geoJSON(item).getBounds();}catch(_){continue;}
  if(!b?.isValid()||!cercano.intersects(b))continue;
  vecinosCercanos.push(item);
  if(sel)sel.add(new Option(`${item.properties?.nombre||'Jurisdicción'} — ID ${item.properties?.id_jurisdiccion}`,String(item.properties.id_jurisdiccion)));
 }
 if(vecinoElegido&&!vecinosCercanos.some(x=>Number(x.properties?.id_jurisdiccion)===vecinoElegido))vecinoElegido=null;
 if(!vecinoElegido&&vecinosCercanos.length===1)vecinoElegido=Number(vecinosCercanos[0].properties.id_jurisdiccion);
 if(sel)sel.value=vecinoElegido?String(vecinoElegido):'';
 dibujarVecinoElegido();
}
function dibujarVecinoElegido(){
 if(!layerVecinos)return;
 layerVecinos.clearLayers();
 const ids=vecinoElegido?[vecinoElegido]:vecinosCercanos.map(x=>Number(x.properties.id_jurisdiccion));
 for(const f of vecinosCercanos){
  if(!ids.includes(Number(f.properties.id_jurisdiccion)))continue;
  L.geoJSON(f,{style:{color:'#8b5cf6',weight:4,opacity:1,fillColor:'#8b5cf6',fillOpacity:.025,interactive:false}}).eachLayer(l=>layerVecinos.addLayer(l));
 }
 if($('jurVecinoEstado'))$('jurVecinoEstado').textContent=vecinoElegido?
  'Límite violeta: jurisdicción aprobada '+vecinoElegido+' (solo referencia, no editable).':'Elegí una jurisdicción vecina aprobada para ajustar sus límites.';
 if(map&&$('jurVerVecinos')?.checked&&!map.hasLayer(layerVecinos))map.addLayer(layerVecinos);
 if(map&&!$('jurVerVecinos')?.checked&&map.hasLayer(layerVecinos))map.removeLayer(layerVecinos);
}
// Punto más cercano a CUALQUIER segmento del vecino, en píxeles de pantalla.
function ajustarLatLngAlVecino(latlng,toleranciaPx=toleranciaSnapPx){
 if(!map||!ajusteActivado||!vecinoElegido)return null;
 const f=vecinosCercanos.find(x=>Number(x.properties?.id_jurisdiccion)===vecinoElegido);
 if(!f)return null;
 const cursor=map.latLngToLayerPoint(latlng);
 let best=null,dist2=toleranciaPx*toleranciaPx;
 const polys=f.geometry?.type==='Polygon'?[f.geometry.coordinates]:f.geometry?.coordinates||[];
 for(const poly of polys){for(const ring of poly){for(let i=1;i<ring.length;i++){
  const a=map.latLngToLayerPoint(L.latLng(ring[i-1][1],ring[i-1][0]));
  const b=map.latLngToLayerPoint(L.latLng(ring[i][1],ring[i][0]));
  const dx=b.x-a.x,dy=b.y-a.y,den=dx*dx+dy*dy;
  if(den===0)continue;
  const t=Math.max(0,Math.min(1,((cursor.x-a.x)*dx+(cursor.y-a.y)*dy)/den));
  const pt=L.point(a.x+t*dx,a.y+t*dy);const dd=(pt.x-cursor.x)**2+(pt.y-cursor.y)**2;
  if(dd<dist2){dist2=dd;best=map.layerPointToLatLng(pt);}
 }}}
 return best;
}
// Utilidades de edición de precisión. Solo modifican la COPIA local de la propuesta.
// El ajuste de un vértice no asegura que todos los segmentos coincidan: requiere
// comprobación de solapamientos y examen visual de huecos antes de publicación.
function aplicarAjusteAMarcador(marker,snapped){
 if(!marker||!snapped)return false;
 let actualizado=false;
 drawn.eachLayer(layer=>{
  for(const h of (layer.editing?._verticesHandlers||[])){
   const idx=(h._markers||[]).indexOf(marker);
   if(idx<0)continue;
   const rings=layer.getLatLngs();
   if(!Array.isArray(rings[0])||!rings[0][idx])continue;
   rings[0][idx].lat=snapped.lat;rings[0][idx].lng=snapped.lng;
   if(marker._origLatLng){marker._origLatLng.lat=snapped.lat;marker._origLatLng.lng=snapped.lng;}
   marker.setLatLng(snapped);layer.redraw();actualizado=true;
  }
 });
 return actualizado;
}
function activarBotonAjuste(){
 const b=$('jurAjustarVertice'),ayuda=$('jurAjusteAyuda');
 const listo=!!(edicionEnCurso&&verticeSeleccionado&&vecinoElegido);
 if(b)b.disabled=!listo;
 if(ayuda){
  ayuda.textContent=!selected?'Primero seleccioná una jurisdicción.':
   !vecinoElegido?'Seleccioná una jurisdicción vecina aprobada como referencia.':
   !edicionEnCurso?'Cargá el polígono y presioná «Editar vértices».':
   !verticeSeleccionado?'Hacé clic o presioná un nodo CUADRADO del polígono azul (no la línea violeta). El botón se habilitará cuando quede seleccionado.':
   'Vértice seleccionado: '+(verticeSeleccionado.idx+1)+'. Presioná «Ajustar vértice elegido al vecino» para proyectarlo al límite oficial.';
  ayuda.className='small mt-1 '+(listo?'text-success':'text-muted');
 }
}
function mostrarCorreccionPendiente(visible){
 const bloque=$('jurBloqueCorreccionPendiente');
 if(bloque)bloque.classList.toggle('d-none',!visible);
 const cargar=$('jurCargarPendiente');if(cargar)cargar.disabled=!visible;
 if(!visible){pendienteEdicion=null;const estado=$('jurPendienteEstado');if(estado)estado.textContent='';}
 actualizarGuiado();
}
async function consultarPendienteSeleccionada(){
 const id=Number(selected),secuencia=++consultaPendienteSec;
 pendienteDisponible=null;
 mostrarCorreccionPendiente(false);
 if(!id)return;
 try{
  const r=await api('solicitud_pendiente',null,{id});
  if(secuencia!==consultaPendienteSec||Number(selected)!==id)return;
  pendienteDisponible=r.solicitud||null;
  mostrarCorreccionPendiente(!!pendienteDisponible);
  if(pendienteDisponible){
   $('jurPendienteEstado').textContent='Solicitud N.º '+pendienteDisponible.id_solicitud+' PENDIENTE, revisión '+pendienteDisponible.revision_actual+'. Cargala para corregirla y conservar su historial.';
  }
 }catch(e){
  if(secuencia!==consultaPendienteSec||Number(selected)!==id)return;
  mostrarCorreccionPendiente(false);
  aviso('No se pudo verificar si esta jurisdicción tiene una solicitud pendiente: '+e.message,'warning');
 }
}

function limiteMasCercano(){
 if(!map||!drawn?.getLayers().length||!vecinoElegido)return null;
 // Buscar el par punto de propuesta / segmento vecino más próximo EN PÍXELES,
 // sin modificar geometrías ni depender del zoom actual.
 const editable=geojsonDibujado();
 const vecino=vecinosCercanos.find(f=>Number(f.properties?.id_jurisdiccion)===vecinoElegido);
 if(!editable||!vecino)return null;
 const scan=(geom,cb)=>{
  if(!geom)return;
  const polys=geom.type==='Polygon'?[geom.coordinates]:geom.type==='MultiPolygon'?geom.coordinates:[];
  for(const poly of polys)for(const ring of poly)for(let i=0;i<ring.length;i++)cb(ring[i],ring,i);
 };
 let best=null;
 scan(editable,p=>{
  if(!Array.isArray(p))return;
  const a=L.latLng(Number(p[1]),Number(p[0]));if(!Number.isFinite(a.lat)||!Number.isFinite(a.lng))return;
  const near=ajustarLatLngAlVecino(a,Number.POSITIVE_INFINITY);
  if(!near)return;
  const distancia=a.distanceTo(near);
  if(!best||distancia<best.distancia)best={origen:a,destino:near,distancia};
 });
 return best;
}
function zoomLimiteCompartido(){
 if(!vecinoElegido){aviso('Elegí la jurisdicción vecina aprobada.','warning');return;}
 const info=limiteMasCercano();
 if(!info){aviso('Cargá primero el polígono de la propuesta.','warning');return;}
 map.invalidateSize();
 map.setView(L.latLng((info.origen.lat+info.destino.lat)/2,(info.origen.lng+info.destino.lng)/2),19,{animate:false});
 if($('jurVerVecinos')&&!$('jurVerVecinos').checked){$('jurVerVecinos').checked=true;dibujarVecinoElegido();}
 if(layerVecinos?.bringToFront)layerVecinos.bringToFront();
 if(drawn?.bringToFront)drawn.bringToFront();
 const msg='Zoom 19 sobre la zona más próxima entre la propuesta y el vecino. Ajustá los vértices y comprobá TODA la frontera compartida.';
 if($('jurPrecisionEstado'))$('jurPrecisionEstado').textContent=msg;
 aviso(msg,'info');
}
async function ajustarVerticeSeleccionado(){
 if(!edicionEnCurso||!verticeSeleccionado||!vecinoElegido)return;
 const marker=verticeSeleccionado.handler?._markers?.[verticeSeleccionado.idx];
 if(!marker){aviso('Volvé a seleccionar un vértice.','warning');return;}
 const origen=marker.getLatLng();
 // Ajuste manual preciso, sin umbral en píxeles. No permitir movimientos grandes.
 const destino=ajustarLatLngAlVecino(origen,Number.POSITIVE_INFINITY);
 if(!destino){aviso('No se encontró un segmento del vecino aprobado.','warning');return;}
 const metros=origen.distanceTo(destino);
 if(!Number.isFinite(metros)||metros>50){aviso('El vértice está a '+Math.round(metros)+' m del límite vecino. Para evitar un cambio accidental, solo se permiten ajustes de hasta 50 m; acercalo manualmente.','warning');return;}
 if(metros>.25&&!(await dialogo('Ajustar vértice al vecino','El vértice seleccionado se moverá '+metros.toFixed(2)+' m hasta el contorno oficial de la jurisdicción '+vecinoElegido+'. Solo cambiará la propuesta local.')))return;
 if(aplicarAjusteAMarcador(marker,destino)){
  verticeSeleccionado=null;actualizarGuiado();
  aviso('Vértice acoplado exactamente al segmento oficial. Revisá los puntos intermedios de todo el borde y comprobá solapamientos.','success');
 }else aviso('No se pudo actualizar el vértice; seleccioná nuevamente el nodo.','warning');
}
function vincularSnapVertices(){
 drawn.eachLayer(layer=>{
  (layer.editing?._verticesHandlers||[]).forEach(h=>{
   (h._markers||[]).forEach(marker=>{
    marker.off('dragend',snapVerticeArrastrado);
    marker.on('dragend',snapVerticeArrastrado);
   });
  });
 });
}
function snapVerticeArrastrado(e){
 if(!edicionEnCurso||!ajusteActivado||!vecinoElegido)return;
 const marker=e.target,latlng=marker.getLatLng(),snapped=ajustarLatLngAlVecino(latlng);
 if(!snapped)return;
 if(aplicarAjusteAMarcador(marker,snapped)){
  aviso('Vértice ajustado al vecino '+vecinoElegido+'. Revisá todos los segmentos compartidos.','success');
 }
}
// Convertir GeoJSON MultiPolygon a polígonos simples editables por Leaflet.draw.
// Leaflet.draw puede fallar con coordenadas nulas al intentar editar directamente
// las estructuras anidadas generadas por L.geoJSON(MultiPolygon).
const estiloEditable={color:'#2563eb',weight:3,fillColor:'#2563eb',fillOpacity:.16};
function anilloSimpleValido(ring){
 if(!Array.isArray(ring)||ring.length<4)return false;
 const coords=ring.map(p=>Array.isArray(p)&&p.length>=2?[Number(p[0]),Number(p[1])]:null);
 if(coords.some(p=>!p||!Number.isFinite(p[0])||!Number.isFinite(p[1])||Math.abs(p[1])>90||Math.abs(p[0])>180))return false;
 if(coords.length>1&&coords[0][0]===coords.at(-1)[0]&&coords[0][1]===coords.at(-1)[1])coords.pop();
 return new Set(coords.map(p=>p.join(','))).size>=3;
}
function capasSimples(geometry){
 if(!geometry||!['Polygon','MultiPolygon'].includes(geometry.type))throw new Error('La geometría no es POLYGON ni MULTIPOLYGON.');
 const polys=geometry.type==='Polygon'?[geometry.coordinates]:geometry.coordinates;
 if(!Array.isArray(polys)||!polys.length)throw new Error('El polígono no tiene coordenadas.');
 const resultado=[];
 for(const poly of polys){
  if(!Array.isArray(poly)||!poly.length)throw new Error('Se encontró un polígono vacío en el KML.');
  // No aplanar huecos: hacerlo cambiaría el territorio sin consentimiento.
  if(poly.length!==1)throw new Error('Esta geometría tiene huecos interiores. Requiere un editor GIS compatible con anillos interiores; no se modificará aquí.');
  const ring=poly[0];
  if(!anilloSimpleValido(ring))throw new Error('El KML contiene un anillo inválido o coordenadas vacías. Corregir antes de editar.');
  const verts=ring.slice();
  if(verts.length>1&&Number(verts[0][0])===Number(verts.at(-1)[0])&&Number(verts[0][1])===Number(verts.at(-1)[1]))verts.pop();
  const latlngs=verts.map(p=>L.latLng(Number(p[1]),Number(p[0])));
  resultado.push(L.polygon(latlngs,{...estiloEditable}));
 }
 return resultado;
}
function ponerCapasEditables(geometry){
 const nuevas=capasSimples(geometry); // validar por completo antes de alterar lo dibujado
 drawn.clearLayers();
 for(const layer of nuevas)drawn.addLayer(layer);
 botonesEdicion();
}
function editarVertices(){
 if(!map||!drawn.getLayers().length){aviso('Primero cargá el borrador o dibujá un polígono.','warning');return;}
 if(edicionEnCurso)return;
 if(!window.L?.Edit?.Poly){aviso('Falta el complemento de edición de polígonos Leaflet.draw.','danger');return;}
 edicionOriginal=geojsonDibujado();
 try{
  // Aislar la geometría editable de las 137 capas preliminares.
  ocultarReferencias();
  const capas=drawn.getLayers();
  for(const layer of capas){
   if(!(layer instanceof L.Polygon))throw new Error('Solo se pueden editar polígonos.');
   // Recrea cada polígono para evitar referencias de vértices nulos del GeoJSON importado.
   if(!layer.editing || typeof layer.editing.enable!=='function')layer.editing=new L.Edit.Poly(layer);
   layer.editing.enable();
  }
  edicionEnCurso=true;verticeSeleccionado=null;centrarEditable();botonesEdicion();enlazarVertices();vincularSnapVertices();estadoGuiado('Modo: edición de vértices. Elegí un nodo para moverlo o seleccionarlo.');
  aviso('EDITANDO: arrastrá los nodos para mover vértices. Tocá el punto intermedio para agregar otro. Al terminar, presioná «Guardar edición».','info');
 }catch(e){
  drawn.eachLayer(l=>{try{l.editing?.disable();}catch(_){}});
  if(edicionOriginal){try{ponerCapasEditables(edicionOriginal);}catch(_){}}
  edicionOriginal=null;edicionEnCurso=false;restaurarReferencias();botonesEdicion();
  aviso('No se pudo iniciar edición de este polígono: '+e.message+'. Si contiene geometría compleja, revisala antes de presentarla.','danger');
 }
}
function finalizarEdicion(guardar){
 if(!edicionEnCurso)return;
 try{
  drawn.eachLayer(l=>{if(l.editing?.enabled())l.editing.disable();});
  if(!guardar&&edicionOriginal)ponerCapasEditables(edicionOriginal);
  edicionEnCurso=false;edicionOriginal=null;verticeSeleccionado=null;
  restaurarReferencias();botonesEdicion();estadoGuiado('Modo: consulta; geometría propuesta '+(guardar?'editada':'restaurada')+'.');
  aviso(guardar?'Edición aplicada a la propuesta local. Para registrarla, completá el motivo y presioná «Presentar solicitud».':'Edición cancelada: se restauraron los vértices anteriores.',guardar?'success':'info');
 }catch(e){aviso('No se pudo cerrar la edición: '+e.message,'danger');}
}
function dibujarNuevo(){if(edicionEnCurso){aviso('Terminá o cancelá la edición en curso.','warning');return;}
 if(!selected){aviso('Seleccioná primero una jurisdicción.','warning');return;}
 if(!window.L?.Draw?.Polygon){aviso('Leaflet.draw no está cargado.','danger');return;}
 if(drawHandler){try{drawHandler.disable();}catch(_){}}
 drawHandler=new L.Draw.Polygon(map,{allowIntersection:false,shapeOptions:{color:'#2563eb',fillOpacity:.2}});
 drawHandler.enable();estadoGuiado('Modo: dibujo activo. Tocá el mapa para agregar vértices.');actualizarGuiado();aviso('Dibujando: tocá los puntos del contorno y el primero para cerrar. El polígono se añade a la propuesta, no al oficial.','info');
}
function enlazarVertices(){
 // Leaflet.draw puede consumir el evento click durante la edición.
 // Registrar también mousedown/touchstart permite seleccionar el nodo sin arrastrarlo.
 drawn.eachLayer(layer=>{
  const handlers=layer.editing?._verticesHandlers||[];
  handlers.forEach(h=>{
   (h._markers||[]).forEach(marker=>{
    for(const evt of ['click','mousedown','touchstart']){
     marker.off(evt,seleccionarVertice);
     marker.on(evt,seleccionarVertice);
    }
   });
  });
 });
}
function seleccionarVertice(e){
 if(!edicionEnCurso)return;
 const marker=e.target;let found=null;
 drawn.eachLayer(layer=>{
  (layer.editing?._verticesHandlers||[]).forEach(h=>{
   const idx=(h._markers||[]).indexOf(marker);
   if(idx>=0)found={layer,idx,handler:h};
  });
 });
 verticeSeleccionado=found;actualizarGuiado();
 if(found)estadoGuiado('Vértice '+(found.idx+1)+' seleccionado. Podés ajustarlo al vecino, arrastrarlo o eliminarlo.');
}
function eliminarVertice(){
 const v=verticeSeleccionado;if(!edicionEnCurso||!v)return;
 const latlngs=v.layer.getLatLngs();
 if(latlngs.length!==1||!Array.isArray(latlngs[0])||latlngs[0].length<=3){aviso('No se puede eliminar ese vértice: el polígono debe conservar al menos tres puntos y un solo anillo editable.','warning');return;}
 try{
  // Siempre reconstruir la copia editable y no mutar los handlers internos de Leaflet.draw.
  const geo=geojsonDibujado();
  const layers=capasSimples(geo);
  const index=drawn.getLayers().indexOf(v.layer);const rings=layers[index].getLatLngs();
  if(rings.length!==1||rings[0].length<=3)throw new Error('Estructura de anillos incompatible.');
  rings[0].splice(v.idx,1);if(rings[0].length<3)throw new Error('Mínimo tres vértices.');
  layers[index].setLatLngs(rings);
  drawn.eachLayer(l=>{try{l.editing?.disable();}catch(_){}});
  drawn.clearLayers();layers.forEach(l=>drawn.addLayer(l));
  drawn.eachLayer(l=>{if(!l.editing)l.editing=new L.Edit.Poly(l);l.editing.enable();});
  verticeSeleccionado=null;enlazarVertices();vincularSnapVertices();botonesEdicion();
  aviso('Vértice eliminado de la copia propuesta. Elegí «Finalizar edición» para conservar el cambio local.','success');
 }catch(err){aviso('No se pudo eliminar el vértice: '+err.message,'danger');}
}
function normalizarGeoJSON(input){
 const features=input?.type==='FeatureCollection'?input.features:input?.type==='Feature'?[input]:[{geometry:input}];
 if(!Array.isArray(features)||!features.length||features.length>25)throw new Error('El archivo debe contener entre 1 y 25 geometrías.');
 const piezas=[];
 for(const f of features){const g=f?.geometry;
  if(!g||!['Polygon','MultiPolygon'].includes(g.type))throw new Error('Solo se admiten polígonos Polygon/MultiPolygon.');
  const ps=g.type==='Polygon'?[g.coordinates]:g.coordinates;
  piezas.push(...ps);
 }
 if(piezas.length>25)throw new Error('Demasiadas piezas (máximo 25).');
 return {type:'MultiPolygon',coordinates:piezas};
}
async function importarGeoJSON(file){
 if(!selected)throw new Error('Primero seleccioná la jurisdicción que recibirá la propuesta.');
 if(!file||file.size>2*1024*1024)throw new Error('Archivo GeoJSON ausente o mayor a 2 MB.');
 const input=JSON.parse(await file.text());const geometry=normalizarGeoJSON(input);
 // Todas las piezas se validan ANTES de reemplazar la propuesta local.
 const nuevas=capasSimples(geometry);
 if(drawn.getLayers().length&&!(await dialogo('Reemplazar propuesta','La geometría previa solo se reemplazará en esta pantalla.')))return;
 drawn.clearLayers();nuevas.forEach(l=>drawn.addLayer(l));centrarEditable();botonesEdicion();
 estadoGuiado('Modo: GeoJSON importado, '+nuevas.length+' pieza(s). Puede editarse antes de presentar.');
 aviso('GeoJSON importado como COPIA local. Revisá los límites y presentá la solicitud con fundamento; no se publicó nada.','success');
}
function jsonStyle(color,preview){return {color,weight:preview?3:2,opacity:1,fillColor:color,fillOpacity:preview?.2:.12,dashArray:preview?'5 5':null};}
function configurarMapa(){if(!window.L){aviso('Leaflet no está disponible.','danger');return;}
 map=L.map('jurMapa').setView([-24.7859,-65.4117],11);
 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap',maxNativeZoom:19,maxZoom:21}).addTo(map);
 layerDraft=L.geoJSON(null,{style:()=>jsonStyle('#dc8b15',true),onEachFeature:(f,l)=>{l.bindPopup('PRELIMINAR KML / NO APROBADO: '+(f.properties?.nombre||''));l.on('click',()=>seleccionar(f.properties.id_jurisdiccion,f.properties.nombre));}}).addTo(map);
 layerOfficial=L.geoJSON(null,{style:f=>jsonStyle(/^#[0-9a-f]{6}$/i.test(f.properties?.color_mapa||'')?f.properties.color_mapa:'#287c67',false),onEachFeature:(f,l)=>{l.bindPopup('APROBADA: '+(f.properties?.nombre||''));l.on('click',()=>seleccionar(f.properties.id_jurisdiccion,f.properties.nombre));}}).addTo(map);
 layerVecinos=L.featureGroup().addTo(map);
 drawn=new L.FeatureGroup().addTo(map);
 drawn.on('layeradd layerremove',()=>botonesEdicion());
 if(L.Draw?.Event){
 map.on(L.Draw.Event.CREATED,e=>{try{const g=e.layer.toGeoJSON().geometry;for(const l of capasSimples(g))drawn.addLayer(l);}catch(err){aviso('El polígono dibujado no es editable: '+err.message,'danger');return;}drawHandler=null;centrarEditable();botonesEdicion();estadoGuiado('Modo: consulta; polígono agregado a la propuesta.');aviso('Polígono agregado a la propuesta. Para cambiar su forma presioná «Editar vértices».','success');});
 }else aviso('Falta Leaflet.draw: no se pueden editar vértices.','warning');
 $('jurDibujarGuiado').addEventListener('click',dibujarNuevo);
 $('jurCerrarPoligono').addEventListener('click',()=>{if(drawHandler?._enabled&&drawHandler._markers?.length>=3){drawHandler.completeShape();drawHandler=null;botonesEdicion();}else aviso('Dibujá al menos tres vértices para cerrar el polígono.','warning');});
 $('jurDeshacerVertice').addEventListener('click',()=>{if(drawHandler?._enabled){drawHandler.deleteLastVertex();actualizarGuiado();}});
 $('jurEditarGuiado').addEventListener('click',editarVertices);
 $('jurFinalizarGuiado').addEventListener('click',()=>finalizarEdicion(true));
 $('jurEliminarVertice').addEventListener('click',eliminarVertice);
 $('jurQuitarPropuesta').addEventListener('click',async()=>{if(edicionEnCurso)return;if(drawn.getLayers().length&&(await dialogo('Quitar propuesta local','Se descartará el dibujo sin eliminar la geometría oficial ni el KML.','warning','#dc3545'))){drawn.clearLayers();estadoGuiado('Modo: consulta. Propuesta local quitada.');aviso('Propuesta local retirada.','info');}});
 $('jurImportarGeoJSON').addEventListener('click',()=>$('jurArchivoGeoJSON').click());
 $('jurArchivoGeoJSON').addEventListener('change',async e=>{try{if(e.target.files?.[0])await importarGeoJSON(e.target.files[0]);}catch(err){aviso('Error al importar GeoJSON: '+err.message,'danger');}finally{e.target.value='';}});
 $('jurCancelarEdicion').addEventListener('click',()=>finalizarEdicion(false));
 botonesEdicion();
 $('jurVerVecinos').addEventListener('change',dibujarVecinoElegido);
 $('jurVecinoSeleccion').addEventListener('change',e=>{vecinoElegido=Number(e.target.value)||null;dibujarVecinoElegido();});
 $('jurAjusteVecinos').addEventListener('change',e=>{ajusteActivado=e.target.checked;actualizarGuiado();});
 $('jurToleranciaSnap').addEventListener('input',e=>{toleranciaSnapPx=Number(e.target.value)||18;$('jurToleranciaTexto').textContent=toleranciaSnapPx+' px';});
 $('jurZoomVecino').addEventListener('click',zoomLimiteCompartido);
 $('jurAjustarVertice').addEventListener('click',ajustarVerticeSeleccionado);
 $('jurComprobarSolape').addEventListener('click',comprobarSolapamiento);
 $('jurCargarPendiente').addEventListener('click',cargarPendiente);
 $('jurGuardarCorreccion').addEventListener('click',guardarCorreccion);
 $('jurCapaAprobada').addEventListener('change',e=>e.target.checked?map.addLayer(layerOfficial):map.removeLayer(layerOfficial));
 $('jurCapaBorrador').addEventListener('change',e=>e.target.checked?map.addLayer(layerDraft):map.removeLayer(layerDraft));
 $('jurCentrar').addEventListener('click',()=>centrarSeleccion());
 $('jurCargarBorrador').addEventListener('click',()=>cargarEditable('BORRADOR'));
 $('jurCargarAprobada').addEventListener('click',()=>cargarEditable('APROBADO'));
 setTimeout(()=>map.invalidateSize(),300);
}
async function cargarCapas(){if(!map)return;
 try{const [a,b]=await Promise.all([api('capas',null,{estado:'APROBADO'}),api('capas',null,{estado:'BORRADOR'})]);
 allOfficial=a.features||[];allDraft=b.features||[];layerOfficial.clearLayers().addData(a);layerDraft.clearLayers().addData(b);
 actualizarVecinos();estadoColorSeleccion();$('jurEstadoGeometria').textContent=allOfficial.length+' geometrías aprobadas; '+allDraft.length+' borradores KML visibles (NO validados). Las geometrías con incidencias no están cargadas como borrador.';
 }catch(e){aviso('No se pudieron cargar las capas: '+e.message,'warning');}}
async function comprobarSolapamiento(){
 const salida=$('jurResultadoSolape');
 if(!selected||!drawn?.getLayers().length){aviso('Primero seleccioná una jurisdicción y cargá la propuesta para verificarla.','warning');return;}
 if(edicionEnCurso||drawHandler?._enabled){aviso('Finalizá la edición antes de comprobar el solapamiento.','warning');return;}
 try{
  salida.textContent='Analizando intersecciones con geometrías oficiales…';salida.className='small text-muted mt-2';
  const r=await api('comprobar_solapamiento',{id_jurisdiccion:selected,geometria:geojsonDibujado()});
  const conflictos=r.conflictos||[];
  if(conflictos.length){salida.textContent='Superposición detectada con: '+conflictos.map(x=>x.nombre+' (ID '+x.id_jurisdiccion+')').join('; ')+'. Debés corregir antes de aprobar.';salida.className='alert alert-danger py-2 mt-2';}
  else if(r.indeterminados?.length){salida.textContent='Comparación espacial indeterminada con: '+r.indeterminados.map(x=>x.nombre).join('; ')+'. Revisión GIS necesaria.';salida.className='alert alert-warning py-2 mt-2';}
  else{salida.textContent='No se detectaron superposiciones de superficie con las geometrías oficiales. Esto NO garantiza ausencia de huecos ni valida topología: el revisor comprobará nuevamente.';salida.className='alert alert-success py-2 mt-2';}
 }catch(e){salida.textContent=e.message;salida.className='alert alert-warning py-2 mt-2';}
}
// Cargar EXACTAMENTE la versión PENDIENTE guardada (no el borrador KML ni la oficial).
async function cargarPendiente(){
 if(!selected||edicionEnCurso){aviso('Seleccioná una jurisdicción y finalizá la edición antes de cargar la solicitud.','warning');return;}
 try{
   const r=await api('solicitud_pendiente',null,{id:selected});const s=r.solicitud;
   if(!s){pendienteEdicion=null;$('jurPendienteEstado').textContent='No existe ninguna solicitud pendiente para esta jurisdicción.';actualizarGuiado();return;}
   if(!s.geometria)throw new Error('La solicitud pendiente no contiene geometría.');
   if(drawn.getLayers().length&&!(await dialogo('Cargar propuesta guardada','Se reemplazará únicamente el dibujo local por la propuesta pendiente. Ningún dato de base será eliminado.','warning')))return;
   ponerCapasEditables(s.geometria);pendienteEdicion=s;$('jurMotivo').value=s.motivo||'';
   $('jurPendienteEstado').textContent='Solicitud N.º '+s.id_solicitud+' cargada (revisión '+s.revision_actual+'). Editá los vértices, finalizá y guardá la corrección.';
   centrarEditable();botonesEdicion();aviso('Se cargó la propuesta guardada N.º '+s.id_solicitud+'. La versión anterior se conservará al corregir.','info');
 }catch(e){aviso('No se pudo cargar la solicitud: '+e.message,'danger');}
}
async function guardarCorreccion(){
 if(!pendienteEdicion||!selected){aviso('Primero cargá una solicitud pendiente.','warning');return;}
 if(edicionEnCurso||drawHandler?._enabled){aviso('Finalizá la edición antes de guardar.','warning');return;}
 const g=geojsonDibujado(),motivo=$('jurMotivo').value.trim();
 if(!g||motivo.length<10){aviso('Se requiere geometría y fundamento mínimo de 10 caracteres.','warning');return;}
 try{
  // Prechequeo CON EL MISMO VALIDADOR de publicación.
  const resultado=await api('comprobar_solapamiento',{id_jurisdiccion:selected,geometria:g});
  if(resultado.conflictos?.length||resultado.indeterminados?.length){
    const problemas=[...(resultado.conflictos||[]),...(resultado.indeterminados||[])];
    throw new Error('Hay conflictos espaciales con: '+problemas.map(p=>p.nombre+' (ID '+p.id_jurisdiccion+')').join('; ')+'. No se guardó.');
  }
  if(!(await dialogo('Guardar corrección versionada','Se conservará la geometría anterior, se creará una nueva revisión de la misma solicitud y se registrará auditoría. NO se publicará la jurisdicción.')))return;
  busy=true;botonesEdicion();
  const r=await api('corregir_solicitud',{id_solicitud:Number(pendienteEdicion.id_solicitud),revision_actual:Number(pendienteEdicion.revision_actual),id_jurisdiccion:selected,geometria:g,motivo});
  pendienteEdicion.revision_actual=r.revision;
  pendienteEdicion.motivo=motivo;
  pendienteDisponible=pendienteEdicion;
  $('jurPendienteEstado').textContent='Solicitud N.º '+r.id_solicitud+' corregida. Nueva revisión '+r.revision+' PENDIENTE, auditada. Podés verla en Revisión de Solicitudes.';
  aviso('Corrección de la solicitud N.º '+r.id_solicitud+' guardada como revisión '+r.revision+'. Requiere aprobación.','success');
  notificacion('Corrección guardada','Solicitud N.º '+r.id_solicitud+' · revisión '+r.revision+' pendiente de aprobación.');
 }catch(e){aviso('No se guardó la corrección: '+e.message,'danger');}
 finally{busy=false;botonesEdicion();}
}
let colorActual=null,colorModalId=null,colorModal=null;
function estadoColorSeleccion(){
 const off=allOfficial.find(f=>Number(f.properties?.id_jurisdiccion)===selected);
 $('jurAbrirColor').disabled=!off||root.dataset.modifica!=='1'||edicionEnCurso;
 $('jurColorTexto').textContent=off?'Color oficial: '+(off.properties.color_mapa||'#287C67'):'Color disponible una vez publicada la primera geometría.';
 $('jurMuestraColor').classList.toggle('d-none',!off);
 if(off)$('jurMuestraColor').style.background=off.properties.color_mapa||'#287C67';
}
function refrescarColorModal(){const c=$('jurColorInput').value.toUpperCase();const motivo=$('jurColorMotivo').value.trim();$('jurColorHex').textContent=c;$('jurGuardarColor').disabled=!colorModalId||c===colorActual||motivo.length<10||motivo.length>255;}
async function abrirColor(){
 if(!selected||root.dataset.modifica!=='1')return;
 try{const r=await api('consultar_color',null,{id:selected});colorModalId=Number(r.id_jurisdiccion);colorActual=(r.color_mapa||'#287C67').toUpperCase();
 $('jurColorNombre').textContent=r.nombre+' — ID '+colorModalId;
 $('jurColorInput').value=colorActual;$('jurColorMotivo').value='';$('jurColorHex').textContent=colorActual;refrescarColorModal();
 colorModal=bootstrap.Modal.getOrCreateInstance($('jurColorModal'));colorModal.show();
 }catch(e){aviso(e.message,'danger');notificacion('No se pudo consultar el color',e.message,'error');}
}
async function guardarColor(){if(!colorModalId)return;const color=$('jurColorInput').value.toUpperCase();const motivo=$('jurColorMotivo').value.trim();if(color===colorActual)return;if(motivo.length<10||motivo.length>255){aviso('Ingresá un fundamento de 10 a 255 caracteres para el color.','warning');return;}
 try{$('jurGuardarColor').disabled=true;const r=await api('guardar_color',{id_jurisdiccion:colorModalId,color,motivo});
  if(!r.ok)throw new Error('El servidor no confirmó el guardado.');
  colorActual=color;for(const f of allOfficial){if(Number(f.properties?.id_jurisdiccion)===colorModalId)f.properties.color_mapa=color;}
  layerOfficial.clearLayers().addData({type:'FeatureCollection',features:allOfficial});
  colorModal?.hide();estadoColorSeleccion();await listar();window.territorioRecargarGrilla?.();
  notificacion(r.cambiado?'Color actualizado':'Sin cambios',r.cambiado?'El color oficial se guardó y auditó.':'Ya tenía asignado ese color.');
 }catch(e){aviso(e.message,'danger');notificacion('No se guardó el color',e.message,'error');refrescarColorModal();}}
const colorBtn=$('jurAbrirColor'),colorGuardar=$('jurGuardarColor'),colorInput=$('jurColorInput'),colorDialog=$('jurColorModal');
if(colorBtn&&colorGuardar&&colorInput&&colorDialog&&$('jurColorMotivo')){
 colorBtn.addEventListener('click',abrirColor);
 colorGuardar.addEventListener('click',guardarColor);
 colorInput.addEventListener('input',refrescarColorModal);
 $('jurColorMotivo').addEventListener('input',refrescarColorModal);
}else if(colorBtn){
 colorBtn.disabled=true;
 aviso('No está instalado el modal para cambiar el color. Verificá views/jurisdicciones/gestion.php V8.','warning');
}
function seleccionar(id,nombre,idRRHH=null,tipo=null){if(edicionEnCurso){aviso('Guardá o cancelá la edición antes de cambiar de jurisdicción.','warning');return;}selected=Number(id);pendienteEdicion=null;pendienteDisponible=null;mostrarCorreccionPendiente(false);selectedName=nombre;$('jurSeleccion').textContent=nombre+' — ID '+id;
 drawn?.clearLayers();botonesEdicion();const draft=allDraft.find(f=>Number(f.properties?.id_jurisdiccion)===selected);
 const off=allOfficial.find(f=>Number(f.properties?.id_jurisdiccion)===selected);
 selectedRRHH=idRRHH||draft?.properties?.id_rrhh||off?.properties?.id_rrhh||null;selectedTipo=tipo||draft?.properties?.tipo||off?.properties?.tipo||'DEPENDENCIA';
 $('jurSeleccionDetalle').textContent='Seleccionada: '+nombre+' · Territorio ID '+selected+' · RRHH '+(selectedRRHH??'por consultar')+' · '+selectedTipo+' · '+(off?'Polígono oficial disponible':'Sin geometría oficial')+' · '+(draft?'Borrador KML disponible':'Sin borrador KML');
 estadoGuiado('Modo: consulta. Jurisdicción seleccionada: '+nombre);
 $('jurEstadoGeometria').textContent=draft?'Tiene borrador KML, editable.':off?'Tiene polígono aprobado; se puede copiar para proponer cambios.':'Sin polígono cargado: podés dibujar uno nuevo.';
 aviso('Jurisdicción seleccionada: '+nombre+'. Las capas preliminares no son oficiales.');actualizarVecinos();estadoColorSeleccion();centrarSeleccion();void consultarPendienteSeleccionada();}
window.territorioSeleccionarJurisdiccion=seleccionar;
window.territorioRefrescarMapaJurisdiccion=()=>{if(map){map.invalidateSize();centrarSeleccion();}};
function centrarSeleccion(){if(!map||!selected)return;const f=[...allDraft,...allOfficial].find(f=>Number(f.properties?.id_jurisdiccion)===selected);if(!f)return;
 try{const l=L.geoJSON(f);if(l.getBounds().isValid())map.fitBounds(l.getBounds(),{padding:[45,45],maxZoom:19,animate:false});}catch(_){} }
function cargarEditable(estado){
 if(edicionEnCurso){aviso('Guardá o cancelá la edición actual antes de cargar otro polígono.','warning');return;}
 if(!map||!selected){aviso('Seleccioná primero una jurisdicción.','warning');return;}
 const f=(estado==='BORRADOR'?allDraft:allOfficial).find(f=>Number(f.properties?.id_jurisdiccion)===selected);
 if(!f){aviso('No existe geometría '+estado+' para esta jurisdicción. Podés dibujar una nueva.','warning');return;}
 try{
  // La fuente KML y la geometría oficial no se alteran: solo se usa una copia.
  ponerCapasEditables(f.geometry);
  centrarEditable();estadoGuiado('Modo: copia '+estado+' cargada para '+selectedName+'. Edición disponible.');
  aviso(estado==='BORRADOR'?'Se cargó una COPIA del borrador KML. Presioná «Editar vértices», mové los nodos y «Guardar edición».':'Se cargó una COPIA del polígono aprobado. Editá y presentá una solicitud, sin modificar la versión vigente.','info');
 }catch(e){aviso('No se pudo preparar la geometría para editar: '+e.message,'danger');}
}
function geojsonDibujado(){if(!drawn)return null;const polys=[];drawn.eachLayer(layer=>{const g=layer.toGeoJSON().geometry;if(g.type==='Polygon')polys.push(g.coordinates);if(g.type==='MultiPolygon')polys.push(...g.coordinates);});return polys.length?{type:'MultiPolygon',coordinates:polys}:null;}
$('jurVolverListado').addEventListener('click',async()=>{
 if(busy){aviso('Esperá a que finalice la operación en curso.','warning');return;}
 if(edicionEnCurso||drawHandler?._enabled){aviso('Finalizá o cancelá la edición antes de volver al listado.','warning');return;}
 if(drawn?.getLayers().length&&!(await dialogo('Volver al listado','Tenés una propuesta local no presentada. ¿Querés descartarla y volver al listado?','warning','#dc3545')))return;
 drawn?.clearLayers();selected=null;pendienteEdicion=null;pendienteDisponible=null;++consultaPendienteSec;mostrarCorreccionPendiente(false);selectedName='';selectedRRHH=null;selectedTipo='';
 $('jurSeleccion').textContent='Seleccione una jurisdicción';
 $('jurSeleccionDetalle').textContent='Ninguna jurisdicción seleccionada.';
 $('jurEstadoGeometria').textContent='';$('jurMotivo').value='';vecinoElegido=null;layerVecinos?.clearLayers();
 estadoGuiado('Modo: consulta. Elegí una jurisdicción para comenzar.');
 estadoColorSeleccion();botonesEdicion();
 aviso('Volviste al listado de jurisdicciones. Seleccioná una para continuar.','info');
 window.territorioMostrarGrilla?.();
 window.territorioRecargarGrilla?.();
});
$('jurMotivo').addEventListener('input',botonesEdicion);
const rrhhRun=buscadorAutomatico($('jurRRHHBuscar'),buscarRRHH);
const jurRun=buscadorAutomatico($('jurBuscar'),listar);
$('jurBtnRRHH').addEventListener('click',rrhhRun);$('jurBtnBuscar').addEventListener('click',jurRun);
$('jurLimpiarMapa').addEventListener('click',()=>{if(edicionEnCurso){aviso('Guardá o cancelá la edición antes de limpiar.','warning');return;}drawn?.clearLayers();aviso('Dibujo editable limpiado. Las capas de consulta permanecen.');});
$('jurProponer').addEventListener('click',async()=>{if(busy)return;try{
 if(pendienteEdicion)throw new Error('Estás corrigiendo una solicitud pendiente: usá Guardar corrección auditada.');
 if(!puedeProponer)throw new Error('Tu perfil requiere permiso ALTA o MODIFICA de Gestión de Jurisdicciones para presentar solicitudes.');
 if(edicionEnCurso)throw new Error('Primero presioná «Finalizar edición» o «Cancelar edición».');if(!selected)throw new Error('Seleccione primero una jurisdicción.');const g=geojsonDibujado();if(!g)throw new Error('Dibuje o cargue un polígono para editar.');
 const motivo=$('jurMotivo').value.trim();if(motivo.length<10)throw new Error('Ingrese un fundamento de al menos 10 caracteres.');
 if(!(await dialogo('Presentar solicitud','La geometría se registrará como PENDIENTE y deberá ser aprobada. No altera el KML ni el polígono oficial.')))return;
 busy=true;$('jurProponer').disabled=true;
 const x=await api('proponer_geometria',{id_jurisdiccion:selected,geometria:g,motivo});
 aviso('Solicitud N.º '+x.id_solicitud+' registrada como PENDIENTE. Requiere revisión.','success');
 drawn.clearLayers();$('jurMotivo').value='';botonesEdicion();
 // La propuesta nunca se convierte en APROBADA por el solo hecho de presentarse.
 // Al confirmarla se ofrece ir al listado histórico de Revisión de Jurisdicciones.
 const irRevision=window.Swal?.fire
   ? (await Swal.fire({icon:'success',title:'Solicitud N.º '+x.id_solicitud+' presentada',text:'Quedó PENDIENTE y auditada, a la espera de aprobación.',showCancelButton:true,confirmButtonText:'Ir a Revisión',cancelButtonText:'Volver a Jurisdicciones',confirmButtonColor:'#198754',reverseButtons:true})).isConfirmed
   : window.confirm('Solicitud N.º '+x.id_solicitud+' registrada como PENDIENTE.\nAceptar: Ir a Revisión.\nCancelar: Volver a Jurisdicciones.');
 if(irRevision){window.location.assign('revision_jurisdicciones.php');return;}
 selected=null;selectedName='';selectedRRHH=null;selectedTipo='';
 $('jurSeleccion').textContent='Seleccione una jurisdicción';$('jurSeleccionDetalle').textContent='Ninguna jurisdicción seleccionada.';
 $('jurEstadoGeometria').textContent='';estadoGuiado('Modo: consulta. Seleccioná una jurisdicción.');estadoColorSeleccion();
 await listar();window.territorioMostrarGrilla?.();window.territorioRecargarGrilla?.();
 }catch(e){aviso(e.message,'danger');}finally{busy=false;botonesEdicion();}});
configurarMapa();listar();cargarCapas();
})();
