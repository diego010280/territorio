(()=>{'use strict';
const root=document.getElementById('geoJurisRoot');if(!root)return;
const $=id=>document.getElementById(id), endpoint='/territorio/ajax/gestion_jurisdicciones.php',token=root.dataset.csrf;
let selected=null,selectedName='',selectedRRHH=null,selectedTipo='',map,drawn,layerDraft,layerOfficial,allDraft=[],allOfficial=[],busy=false,editHandler=null,drawHandler=null,edicionEnCurso=false,edicionOriginal=null,verticeSeleccionado=null;
const puedeProponer=root.dataset.alta==='1'||root.dataset.modifica==='1';
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
 else td.append(boton('Crear',async()=>{try{const x=await api('crear_desde_rrhh',{id_rrhh:Number(v.id_rrhh)});aviso('Jurisdicción '+x.id_jurisdiccion+' creada como preliminar.','success');seleccionar(x.id_jurisdiccion,v.nombre);await listar();await buscarRRHH();}catch(e){aviso(e.message,'danger');}},root.dataset.alta!=='1'));
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
 $('jurEliminarVertice').disabled=!edicionEnCurso||!verticeSeleccionado;
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
 $('jurProponer').disabled=razones.length>0;
 const estado=$('jurEstadoPropuesta');
 if(estado){estado.textContent=razones.length?'Para presentar falta: '+razones.join('; ')+'.':'Propuesta lista para presentar como PENDIENTE. La geometría oficial NO se modificará.';estado.className='small mt-2 '+(razones.length?'text-warning':'text-success');}
}
function botonesEdicion(){actualizarGuiado();}
function centrarEditable(){if(!map||!drawn||!drawn.getLayers().length)return;
 const bounds=drawn.getBounds();if(bounds.isValid())map.fitBounds(bounds,{padding:[45,45],maxZoom:18,animate:false});}
function ocultarReferencias(){if(map.hasLayer(layerDraft))map.removeLayer(layerDraft);if(map.hasLayer(layerOfficial))map.removeLayer(layerOfficial);}
function restaurarReferencias(){if($('jurCapaBorrador').checked&&!map.hasLayer(layerDraft))map.addLayer(layerDraft);if($('jurCapaAprobada').checked&&!map.hasLayer(layerOfficial))map.addLayer(layerOfficial);}
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
  edicionEnCurso=true;verticeSeleccionado=null;centrarEditable();botonesEdicion();enlazarVertices();estadoGuiado('Modo: edición de vértices. Elegí un nodo para moverlo o seleccionarlo.');
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
 // Solo utiliza los nodos visibles de Leaflet.draw: nunca toca el polígono oficial.
 drawn.eachLayer(layer=>{
  const handlers=layer.editing?._verticesHandlers||[];
  handlers.forEach(h=>{
   (h._markers||[]).forEach(marker=>{
    marker.off('click',seleccionarVertice);
    marker.on('click',seleccionarVertice);
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
 if(found)estadoGuiado('Vértice '+(found.idx+1)+' seleccionado. Arrastralo o elegí «Eliminar vértice seleccionado».');
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
  verticeSeleccionado=null;enlazarVertices();botonesEdicion();
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
 if(drawn.getLayers().length&&!confirm('¿Reemplazar la propuesta local con el archivo importado? No altera el KML ni la geometría aprobada.'))return;
 drawn.clearLayers();nuevas.forEach(l=>drawn.addLayer(l));centrarEditable();botonesEdicion();
 estadoGuiado('Modo: GeoJSON importado, '+nuevas.length+' pieza(s). Puede editarse antes de presentar.');
 aviso('GeoJSON importado como COPIA local. Revisá los límites y presentá la solicitud con fundamento; no se publicó nada.','success');
}
function jsonStyle(color,preview){return {color,weight:preview?3:2,opacity:1,fillColor:color,fillOpacity:preview?.2:.12,dashArray:preview?'5 5':null};}
function configurarMapa(){if(!window.L){aviso('Leaflet no está disponible.','danger');return;}
 map=L.map('jurMapa').setView([-24.7859,-65.4117],11);
 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap',maxZoom:19}).addTo(map);
 layerDraft=L.geoJSON(null,{style:()=>jsonStyle('#dc8b15',true),onEachFeature:(f,l)=>{l.bindPopup('PRELIMINAR KML / NO APROBADO: '+(f.properties?.nombre||''));l.on('click',()=>seleccionar(f.properties.id_jurisdiccion,f.properties.nombre));}}).addTo(map);
 layerOfficial=L.geoJSON(null,{style:f=>jsonStyle(/^#[0-9a-f]{6}$/i.test(f.properties?.color_mapa||'')?f.properties.color_mapa:'#287c67',false),onEachFeature:(f,l)=>{l.bindPopup('APROBADA: '+(f.properties?.nombre||''));l.on('click',()=>seleccionar(f.properties.id_jurisdiccion,f.properties.nombre));}}).addTo(map);
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
 $('jurQuitarPropuesta').addEventListener('click',()=>{if(edicionEnCurso)return;if(drawn.getLayers().length&&confirm('¿Quitar la propuesta local? La geometría KML y la oficial no se modifican.')){drawn.clearLayers();estadoGuiado('Modo: consulta. Propuesta local quitada.');aviso('Propuesta local retirada.','info');}});
 $('jurImportarGeoJSON').addEventListener('click',()=>$('jurArchivoGeoJSON').click());
 $('jurArchivoGeoJSON').addEventListener('change',async e=>{try{if(e.target.files?.[0])await importarGeoJSON(e.target.files[0]);}catch(err){aviso('Error al importar GeoJSON: '+err.message,'danger');}finally{e.target.value='';}});
 $('jurCancelarEdicion').addEventListener('click',()=>finalizarEdicion(false));
 botonesEdicion();
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
 $('jurEstadoGeometria').textContent=allOfficial.length+' geometrías aprobadas; '+allDraft.length+' borradores KML visibles (NO validados). Las geometrías con incidencias no están cargadas como borrador.';
 }catch(e){aviso('No se pudieron cargar las capas: '+e.message,'warning');}}
function seleccionar(id,nombre,idRRHH=null,tipo=null){if(edicionEnCurso){aviso('Guardá o cancelá la edición antes de cambiar de jurisdicción.','warning');return;}selected=Number(id);selectedName=nombre;$('jurSeleccion').textContent=nombre+' — ID '+id;
 drawn?.clearLayers();botonesEdicion();const draft=allDraft.find(f=>Number(f.properties?.id_jurisdiccion)===selected);
 const off=allOfficial.find(f=>Number(f.properties?.id_jurisdiccion)===selected);
 selectedRRHH=idRRHH||draft?.properties?.id_rrhh||off?.properties?.id_rrhh||null;selectedTipo=tipo||draft?.properties?.tipo||off?.properties?.tipo||'DEPENDENCIA';
 $('jurSeleccionDetalle').textContent='Seleccionada: '+nombre+' · Territorio ID '+selected+' · RRHH '+(selectedRRHH??'por consultar')+' · '+selectedTipo+' · '+(off?'Polígono oficial disponible':'Sin geometría oficial')+' · '+(draft?'Borrador KML disponible':'Sin borrador KML');
 estadoGuiado('Modo: consulta. Jurisdicción seleccionada: '+nombre);
 $('jurEstadoGeometria').textContent=draft?'Tiene borrador KML, editable.':off?'Tiene polígono aprobado; se puede copiar para proponer cambios.':'Sin polígono cargado: podés dibujar uno nuevo.';
 aviso('Jurisdicción seleccionada: '+nombre+'. Las capas preliminares no son oficiales.');centrarSeleccion();}
function centrarSeleccion(){if(!map||!selected)return;const f=[...allDraft,...allOfficial].find(f=>Number(f.properties?.id_jurisdiccion)===selected);if(!f)return;
 try{const l=L.geoJSON(f);if(l.getBounds().isValid())map.fitBounds(l.getBounds(),{padding:[45,45],maxZoom:18,animate:false});}catch(_){} }
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
$('jurMotivo').addEventListener('input',botonesEdicion);
const rrhhRun=buscadorAutomatico($('jurRRHHBuscar'),buscarRRHH);
const jurRun=buscadorAutomatico($('jurBuscar'),listar);
$('jurBtnRRHH').addEventListener('click',rrhhRun);$('jurBtnBuscar').addEventListener('click',jurRun);
$('jurLimpiarMapa').addEventListener('click',()=>{if(edicionEnCurso){aviso('Guardá o cancelá la edición antes de limpiar.','warning');return;}drawn?.clearLayers();aviso('Dibujo editable limpiado. Las capas de consulta permanecen.');});
$('jurProponer').addEventListener('click',async()=>{if(busy)return;try{
 if(!puedeProponer)throw new Error('Tu perfil requiere permiso ALTA o MODIFICA de Gestión de Jurisdicciones para presentar solicitudes.');
 if(edicionEnCurso)throw new Error('Primero presioná «Finalizar edición» o «Cancelar edición».');if(!selected)throw new Error('Seleccione primero una jurisdicción.');const g=geojsonDibujado();if(!g)throw new Error('Dibuje o cargue un polígono para editar.');
 const motivo=$('jurMotivo').value.trim();if(motivo.length<10)throw new Error('Ingrese un fundamento de al menos 10 caracteres.');
 if(!confirm('¿Presentar esta geometría como PENDIENTE? No reemplaza el KML ni publica un polígono oficial.'))return;
 busy=true;$('jurProponer').disabled=true;
 const x=await api('proponer_geometria',{id_jurisdiccion:selected,geometria:g,motivo});
 aviso('Solicitud N.º '+x.id_solicitud+' registrada como PENDIENTE. Requiere revisión.','success');drawn.clearLayers();botonesEdicion();$('jurMotivo').value='';
 }catch(e){aviso(e.message,'danger');}finally{busy=false;botonesEdicion();}});
configurarMapa();listar();cargarCapas();
})();
