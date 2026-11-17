(()=>{'use strict';
const root=document.getElementById('geoJurisRoot');if(!root)return;
const $=id=>document.getElementById(id),endpoint='/territorio/ajax/gestion_jurisdicciones.php';
const token=root.dataset.csrf;let selected=null;
function aviso(msg,kind='info'){$('jurAviso').className='alert alert-'+kind;$('jurAviso').textContent=msg;}
async function api(accion,params=null){
 const post=params!==null,url=endpoint+'?accion='+encodeURIComponent(accion)+(post?'':'&'+new URLSearchParams({q:accion==='listar' ? $('jurBuscar').value : $('jurRRHHBuscar').value}));
 const res=await fetch(url,post?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify(params)}:{credentials:'same-origin'});
 let json;try{json=await res.json();}catch(_){throw new Error('El servidor no respondió en JSON.');}
 if(!res.ok)throw new Error(json.error||'Error HTTP '+res.status);return json;
}
function cell(txt){const td=document.createElement('td');td.textContent=txt==null?'':String(txt);return td;}
function boton(label,cb,disabled){const b=document.createElement('button');b.className='btn btn-sm btn-outline-primary';b.type='button';b.textContent=label;b.disabled=!!disabled;b.onclick=cb;return b;}
async function buscarRRHH(){try{const r=await api('buscar_rrhh'),tb=$('jurRRHHLista');tb.replaceChildren();if(!r.data?.length){tb.appendChild(Object.assign(document.createElement('tr'),{textContent:'No se encontraron organismos'}));return;}
 r.data.forEach(v=>{let tr=document.createElement('tr');tr.append(cell(v.id_rrhh),cell(v.nombre),cell(v.id_jurisdiccion?'ID '+v.id_jurisdiccion:'Sin vincular'));
 let td=document.createElement('td');if(v.id_jurisdiccion)td.append(boton('Seleccionar',()=>seleccionar(v.id_jurisdiccion,v.nombre)));
 else td.append(boton('Crear',async()=>{try{let x=await api('crear_desde_rrhh',{id_rrhh:Number(v.id_rrhh)});aviso('Jurisdicción '+x.id_jurisdiccion+' creada como preliminar. No está publicada.','success');seleccionar(x.id_jurisdiccion,v.nombre);await listar();await buscarRRHH();}catch(e){aviso(e.message,'danger');}},root.dataset.alta!=='1'));tr.append(td);tb.append(tr)});
 }catch(e){aviso(e.message,'danger');}}
async function listar(){try{const r=await api('listar'),tb=$('jurLista');tb.replaceChildren();r.data?.forEach(v=>{const tr=document.createElement('tr');tr.append(cell(v.nombre+' ('+v.tipo+')'),cell(v.id_organismo_rrhh),cell(v.estado));let td=document.createElement('td');td.append(boton('Elegir',()=>seleccionar(v.id_jurisdiccion,v.nombre)));tr.append(td);tb.append(tr);});}catch(e){aviso(e.message,'danger');}}
function seleccionar(id,name){selected=Number(id);$('jurSeleccion').textContent=name+' — ID '+id;aviso('Jurisdicción seleccionada. Dibuje el polígono propuesto; no se publicará automáticamente.');}
let map,drawn;
if(window.L){map=L.map('jurMapa').setView([-24.7859,-65.4117],11);
 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap',maxZoom:19}).addTo(map);
 drawn=new L.FeatureGroup();map.addLayer(drawn);
 if(L.Control.Draw){const ctrl=new L.Control.Draw({edit:{featureGroup:drawn},draw:{polyline:false,rectangle:false,circle:false,circlemarker:false,marker:false,polygon:true}});map.addControl(ctrl);
 map.on(L.Draw.Event.CREATED,e=>{drawn.addLayer(e.layer);});}
 setTimeout(()=>map.invalidateSize(),400);
}else aviso('Leaflet no está disponible. Revise su conexión y archivos JS.','warning');
function geojsonDibujado(){if(!drawn)return null;const polys=[];drawn.eachLayer(layer=>{const g=layer.toGeoJSON().geometry;if(g.type==='Polygon')polys.push(g.coordinates);if(g.type==='MultiPolygon')polys.push(...g.coordinates);});
 return polys.length?{type:'MultiPolygon',coordinates:polys}:null;}
$('jurBtnRRHH').onclick=buscarRRHH;$('jurBtnBuscar').onclick=listar;
$('jurRRHHBuscar').addEventListener('keydown',e=>{if(e.key==='Enter')buscarRRHH();});
$('jurBuscar').addEventListener('keydown',e=>{if(e.key==='Enter')listar();});
$('jurLimpiarMapa').onclick=()=>drawn?.clearLayers();
$('jurProponer').onclick=async()=>{try{if(!selected)throw new Error('Seleccione primero una jurisdicción.');let g=geojsonDibujado();if(!g)throw new Error('Dibuje al menos un polígono.');const motivo=$('jurMotivo').value.trim();if(motivo.length<10)throw new Error('Ingrese un fundamento de al menos 10 caracteres.');
 if(!confirm('¿Presentar esta geometría como PENDIENTE? No modifica la cartografía aprobada.'))return;
 const x=await api('proponer_geometria',{id_jurisdiccion:selected,geometria:g,motivo});aviso('Solicitud N.º '+x.id_solicitud+' registrada como PENDIENTE. Debe ser revisada.','success');drawn.clearLayers();$('jurMotivo').value='';
 }catch(e){aviso(e.message,'danger');}};
 listar();
})();
