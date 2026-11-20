(()=>{'use strict';
const root=document.getElementById('jurRevisionRoot');if(!root)return;
const $=id=>document.getElementById(id);const url='/territorio/ajax/revision_jurisdicciones.php';
let elegido=null,ocupado=false,peticionListado=0,debounce=null;
const filtros=()=>({estado:$('jrEstado').value,texto:$('jrTexto').value.trim(),rrhh:$('jrRRHH').value.trim(),desde:$('jrDesde').value,hasta:$('jrHasta').value});
function dialogo(titulo,texto,icono='question',color='#198754'){
 if(window.Swal?.fire)return Swal.fire({title:titulo,text:texto,icon:icono,showCancelButton:true,confirmButtonText:'Confirmar',cancelButtonText:'Cancelar',confirmButtonColor:color,reverseButtons:true,focusCancel:true}).then(r=>r.isConfirmed);
 return Promise.resolve(window.confirm(titulo+'\n\n'+texto));
}
function notificacion(titulo,texto='',tipo='success'){
 if(window.Swal?.fire){Swal.fire({icon:tipo,title:titulo,text:texto,confirmButtonText:'Aceptar',timer:tipo==='success'?2600:undefined,timerProgressBar:tipo==='success'});}
}
const map=L.map('jrMapa').setView([-24.78,-65.41],11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
const oficial=L.geoJSON(null,{style:{color:'#2563eb',weight:3,fillOpacity:.1}}).addTo(map);
const propuesta=L.geoJSON(null,{style:{color:'#ea8500',weight:3,dashArray:'6 5',fillOpacity:.16}}).addTo(map);
function aviso(s,t='info'){$('jrAviso').className='alert alert-'+t;$('jrAviso').textContent=s;}
async function api(a,data=null,extra={}){const post=data!==null;const res=await fetch(url+'?'+new URLSearchParams({accion:a,...extra}),post?{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf},body:JSON.stringify(data)}:{credentials:'same-origin',cache:'no-store'});const j=await res.json().catch(()=>({error:'Respuesta JSON inválida'}));if(!res.ok)throw new Error(j.error||'HTTP '+res.status);return j;}
function activar(){const editable=root.dataset.resolver==='1'&&!!elegido&&!ocupado&&elegido.estado==='PENDIENTE';$('jrAprobar').disabled=!editable;$('jrRechazar').disabled=!editable;}
function texto(parent,text){const e=document.createElement('div');e.textContent=String(text??'');parent.append(e);return e;}
function celda(tr,v){const td=document.createElement('td');td.textContent=String(v??'—');tr.appendChild(td);return td;}
function cerrarDetalle(){elegido=null;$('jrPanelRevision').classList.add('d-none');oficial.clearLayers();propuesta.clearLayers();activar();}
async function listar(){const pedido=++peticionListado;try{
 const r=await api('listar',null,filtros());if(pedido!==peticionListado)return;
 const list=$('jrListado');list.replaceChildren();cerrarDetalle();
 if(!r.data?.length){const tr=document.createElement('tr');const td=celda(tr,'No hay solicitudes que coincidan con los filtros.');td.colSpan=7;td.className='text-muted text-center py-3';list.append(tr);cerrarDetalle();return;}
 for(const sol of r.data){
   const tr=document.createElement('tr');
   celda(tr,sol.id_solicitud);celda(tr,sol.nombre);celda(tr,sol.id_rrhh);celda(tr,sol.tipo);celda(tr,sol.estado);
   celda(tr,sol.fecha_solicitud);
   const td=document.createElement('td');const btn=document.createElement('button');btn.type='button';btn.className='btn btn-sm btn-outline-primary';btn.textContent=sol.estado==='PENDIENTE'?'Ver / Revisar':'Ver resolución';btn.addEventListener('click',()=>detalle(sol.id_solicitud));td.append(btn);tr.append(td);list.append(tr);
 }
}catch(e){if(pedido===peticionListado)aviso(e.message,'danger');}}
async function detalle(id){try{const d=await api('detalle',null,{id});elegido=d;$('jrPanelRevision').classList.remove('d-none');
$('jrAccionesResolucion').classList.toggle('d-none',d.estado!=='PENDIENTE');
$('jrFundamento').disabled=d.estado!=='PENDIENTE';$('jrConfirmar').disabled=d.estado!=='PENDIENTE';
$('jrFundamento').value='';$('jrConfirmar').checked=false;activar();oficial.clearLayers();propuesta.clearLayers();if(d.oficial)oficial.addData(d.oficial);if(d.propuesta)propuesta.addData(d.propuesta);
const el=$('jrDetalle');el.replaceChildren();texto(el,'Solicitud #'+d.id_solicitud+' · '+d.nombre+' · '+d.tipo_jurisdiccion+' · RRHH '+(d.id_rrhh??'sin vínculo'));
texto(el,'Solicitante '+d.usuario_solicitante+' · Estado '+d.estado+' · Base '+d.version_base+' · Versión oficial '+d.version_oficial);
texto(el,'Motivo: '+d.motivo);texto(el,'SRID propuesta: '+d.srid_propuesta+' · Versión oficial actual: '+d.version_oficial);
if(d.estado!=='PENDIENTE'){
 texto(el,'Solicitud resuelta: consulta histórica de solo lectura.');
 if(d.resolucion){texto(el,'Decisión: '+d.resolucion.decision+' · Revisor: '+d.resolucion.usuario_revisor+' · Fecha: '+d.resolucion.fecha_resolucion);texto(el,'Fundamento de resolución: '+d.resolucion.fundamento);texto(el,'Correlación: '+d.resolucion.correlacion_id);}
}
const bounds=L.featureGroup([oficial,propuesta]).getBounds();if(bounds.isValid())map.fitBounds(bounds,{padding:[25,25],maxZoom:17,animate:false});
setTimeout(()=>map.invalidateSize(),180);$('jrPanelRevision').scrollIntoView({behavior:'smooth',block:'start'});aviso('Revisá el polígono naranja y comparalo con el oficial azul.','info');
}catch(e){aviso(e.message,'danger');}}
async function resolver(decision){if(!elegido||ocupado)return;try{const motivo=$('jrFundamento').value.trim();if(motivo.length<10)throw new Error('Ingresá un fundamento de al menos diez caracteres.');if(decision==='APROBADA'&&!$('jrConfirmar').checked)throw new Error('Confirmá la revisión del organismo y sus límites.');if(!(await dialogo(decision==='APROBADA'?'Aprobar y publicar':'Rechazar solicitud','Solicitud N.º '+elegido.id_solicitud+'. La decisión y su fundamento quedarán auditados.',decision==='APROBADA'?'question':'warning',decision==='APROBADA'?'#198754':'#dc3545')))return;
 ocupado=true;activar();const r=await api('resolver',{id_solicitud:Number(elegido.id_solicitud),decision,fundamento:motivo,confirmado:$('jrConfirmar').checked});
 aviso('Solicitud #'+r.id_solicitud+' '+r.estado+(r.version?' · Versión oficial '+r.version:'')+'. Auditoría registrada.','success');notificacion(decision==='APROBADA'?'Geometría publicada':'Solicitud rechazada','Solicitud N.º '+r.id_solicitud+(r.version?' · Versión '+r.version:'')+'. Resolución auditada.');cerrarDetalle();$('jrDetalle').textContent='Solicitud resuelta.';$('jrFundamento').value='';$('jrConfirmar').checked=false;oficial.clearLayers();propuesta.clearLayers();await listar();
 }catch(e){aviso('No se publicó la resolución: '+e.message,'danger');notificacion('No se pudo resolver la solicitud',e.message,'error');}finally{ocupado=false;activar();}}
$('jrRecargar').addEventListener('click',listar);
$('jrFiltros').addEventListener('submit',e=>{e.preventDefault();listar();});
$('jrLimpiar').addEventListener('click',()=>{$('jrFiltros').reset();listar();});
$('jrTexto').addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(listar,400);});
$('jrEstado').addEventListener('change',listar);
$('jrRRHH').addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(listar,400);});
$('jrDesde').addEventListener('change',listar);$('jrHasta').addEventListener('change',listar);$('jrCerrarDetalle').addEventListener('click',cerrarDetalle);$('jrAprobar').addEventListener('click',()=>resolver('APROBADA'));$('jrRechazar').addEventListener('click',()=>resolver('RECHAZADA'));
$('jrCapaOficial').addEventListener('change',e=>e.target.checked?map.addLayer(oficial):map.removeLayer(oficial));
$('jrCapaPropuesta').addEventListener('change',e=>e.target.checked?map.addLayer(propuesta):map.removeLayer(propuesta));
activar();listar();setTimeout(()=>map.invalidateSize(),200);
})();
