(()=>{'use strict';
const root=document.getElementById('geoJurisRoot');if(!root)return;
const $=id=>document.getElementById(id), endpoint='/territorio/ajax/gestion_jurisdicciones.php',token=root.dataset.csrf;
let selected=null,selectedName='',map,drawn,layerDraft,layerOfficial,allDraft=[],allOfficial=[],busy=false,editHandler=null,drawHandler=null,edicionEnCurso=false,edicionOriginal=null;
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
 const td=document.createElement('td');if(v.id_jurisdiccion)td.append(boton('Seleccionar',()=>seleccionar(v.id_jurisdiccion,v.nombre)));
 else td.append(boton('Crear',async()=>{try{const x=await api('crear_desde_rrhh',{id_rrhh:Number(v.id_rrhh)});aviso('Jurisdicción '+x.id_jurisdiccion+' creada como preliminar.','success');seleccionar(x.id_jurisdiccion,v.nombre);await listar();await buscarRRHH();}catch(e){aviso(e.message,'danger');}},root.dataset.alta!=='1'));
 tr.append(td);tb.append(tr);}
 }catch(e){if(vigente())aviso(e.message,'danger');}
}
async function listar(n=0,vigente=()=>true){const q=$('jurBuscar').value.trim();try{
 const r=await api('listar',null,{q});if(!vigente())return;const tb=$('jurLista');tb.replaceChildren();
 if(!r.data?.length){tb.appendChild(Object.assign(document.createElement('tr'),{textContent:'Sin coincidencias'}));return;}
 for(const v of r.data){const tr=document.createElement('tr');tr.append(cell(v.nombre+' ('+v.tipo+')'),cell(v.id_organismo_rrhh),cell(v.estado));const td=document.createElement('td');td.append(boton('Elegir',()=>seleccionar(v.id_jurisdiccion,v.nombre)));tr.append(td);tb.append(tr);}
 }catch(e){if(vigente())aviso(e.message,'danger');}}
function botonesEdicion(){
 const tiene=!!drawn && drawn.getLayers().length>0;
 $('jurEditarVertices').disabled=!tiene||edicionEnCurso;
 $('jurGuardarEdicion').disabled=!edicionEnCurso;
 $('jurCancelarEdicion').disabled=!edicionEnCurso;
 $('jurDibujarNuevo').disabled=edicionEnCurso;
 $('jurCargarBorrador').disabled=edicionEnCurso;
 $('jurCargarAprobada').disabled=edicionEnCurso;
 $('jurLimpiarMapa').disabled=edicionEnCurso;
 $('jurProponer').disabled=busy||edicionEnCurso||root.dataset.modifica!=='1';
}
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
  edicionEnCurso=true;centrarEditable();botonesEdicion();
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
  edicionEnCurso=false;edicionOriginal=null;
  restaurarReferencias();botonesEdicion();
  aviso(guardar?'Edición aplicada a la propuesta local. Para registrarla, completá el motivo y presioná «Presentar solicitud».':'Edición cancelada: se restauraron los vértices anteriores.',guardar?'success':'info');
 }catch(e){aviso('No se pudo cerrar la edición: '+e.message,'danger');}
}
function dibujarNuevo(){if(edicionEnCurso){aviso('Terminá o cancelá la edición en curso.','warning');return;}
 if(!selected){aviso('Seleccioná primero una jurisdicción.','warning');return;}
 if(!window.L?.Draw?.Polygon){aviso('Leaflet.draw no está cargado.','danger');return;}
 if(drawHandler){try{drawHandler.disable();}catch(_){}}
 drawHandler=new L.Draw.Polygon(map,{allowIntersection:false,shapeOptions:{color:'#2563eb',fillOpacity:.2}});
 drawHandler.enable();aviso('Dibujando: tocá los puntos del contorno y el primero para cerrar. El polígono se añade a la propuesta, no al oficial.','info');
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
 map.on(L.Draw.Event.CREATED,e=>{try{const g=e.layer.toGeoJSON().geometry;for(const l of capasSimples(g))drawn.addLayer(l);}catch(err){aviso('El polígono dibujado no es editable: '+err.message,'danger');return;}drawHandler=null;centrarEditable();botonesEdicion();aviso('Polígono agregado a la propuesta. Para cambiar su forma presioná «Editar vértices».','success');});
 }else aviso('Falta Leaflet.draw: no se pueden editar vértices.','warning');
 $('jurDibujarNuevo').addEventListener('click',dibujarNuevo);
 $('jurEditarVertices').addEventListener('click',editarVertices);
 $('jurGuardarEdicion').addEventListener('click',()=>finalizarEdicion(true));
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
function seleccionar(id,nombre){if(edicionEnCurso){aviso('Guardá o cancelá la edición antes de cambiar de jurisdicción.','warning');return;}selected=Number(id);selectedName=nombre;$('jurSeleccion').textContent=nombre+' — ID '+id;
 drawn?.clearLayers();botonesEdicion();const draft=allDraft.find(f=>Number(f.properties?.id_jurisdiccion)===selected);
 const off=allOfficial.find(f=>Number(f.properties?.id_jurisdiccion)===selected);
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
  centrarEditable();
  aviso(estado==='BORRADOR'?'Se cargó una COPIA del borrador KML. Presioná «Editar vértices», mové los nodos y «Guardar edición».':'Se cargó una COPIA del polígono aprobado. Editá y presentá una solicitud, sin modificar la versión vigente.','info');
 }catch(e){aviso('No se pudo preparar la geometría para editar: '+e.message,'danger');}
}
function geojsonDibujado(){if(!drawn)return null;const polys=[];drawn.eachLayer(layer=>{const g=layer.toGeoJSON().geometry;if(g.type==='Polygon')polys.push(g.coordinates);if(g.type==='MultiPolygon')polys.push(...g.coordinates);});return polys.length?{type:'MultiPolygon',coordinates:polys}:null;}
const rrhhRun=buscadorAutomatico($('jurRRHHBuscar'),buscarRRHH);
const jurRun=buscadorAutomatico($('jurBuscar'),listar);
$('jurBtnRRHH').addEventListener('click',rrhhRun);$('jurBtnBuscar').addEventListener('click',jurRun);
$('jurLimpiarMapa').addEventListener('click',()=>{if(edicionEnCurso){aviso('Guardá o cancelá la edición antes de limpiar.','warning');return;}drawn?.clearLayers();aviso('Dibujo editable limpiado. Las capas de consulta permanecen.');});
$('jurProponer').addEventListener('click',async()=>{if(busy)return;try{
 if(edicionEnCurso)throw new Error('Primero presioná «Guardar edición» o «Cancelar edición».');if(!selected)throw new Error('Seleccione primero una jurisdicción.');const g=geojsonDibujado();if(!g)throw new Error('Dibuje o cargue un polígono para editar.');
 const motivo=$('jurMotivo').value.trim();if(motivo.length<10)throw new Error('Ingrese un fundamento de al menos 10 caracteres.');
 if(!confirm('¿Presentar esta geometría como PENDIENTE? No reemplaza el KML ni publica un polígono oficial.'))return;
 busy=true;$('jurProponer').disabled=true;
 const x=await api('proponer_geometria',{id_jurisdiccion:selected,geometria:g,motivo});
 aviso('Solicitud N.º '+x.id_solicitud+' registrada como PENDIENTE. Requiere revisión.','success');drawn.clearLayers();botonesEdicion();$('jurMotivo').value='';
 }catch(e){aviso(e.message,'danger');}finally{busy=false;botonesEdicion();}});
configurarMapa();listar();cargarCapas();
})();
