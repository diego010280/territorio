(()=>{'use strict';
const root=document.getElementById('jurRevisionRoot');if(!root)return;
const $=id=>document.getElementById(id);const url='/territorio/ajax/revision_jurisdicciones.php';
let elegido=null,ocupado=false;const map=L.map('jrMapa').setView([-24.78,-65.41],11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
const oficial=L.geoJSON(null,{style:{color:'#2563eb',weight:3,fillOpacity:.1}}).addTo(map);
const propuesta=L.geoJSON(null,{style:{color:'#ea8500',weight:3,dashArray:'6 5',fillOpacity:.16}}).addTo(map);
function aviso(s,t='info'){$('jrAviso').className='alert alert-'+t;$('jrAviso').textContent=s;}
async function api(a,data=null,extra={}){const post=data!==null;const res=await fetch(url+'?'+new URLSearchParams({accion:a,...extra}),post?{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf},body:JSON.stringify(data)}:{credentials:'same-origin',cache:'no-store'});const j=await res.json().catch(()=>({error:'Respuesta JSON inválida'}));if(!res.ok)throw new Error(j.error||'HTTP '+res.status);return j;}
function activar(){const editable=root.dataset.resolver==='1'&&!!elegido&&!ocupado&&elegido.estado==='PENDIENTE';$('jrAprobar').disabled=!editable;$('jrRechazar').disabled=!editable;}
function texto(parent,text){const e=document.createElement('div');e.textContent=String(text??'');parent.append(e);return e;}
async function listar(){try{const r=await api('listar'),list=$('jrListado');list.replaceChildren();if(!r.data?.length){texto(list,'No hay solicitudes pendientes.');return;}for(const s of r.data){const b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action';b.textContent='#'+s.id_solicitud+' · '+s.nombre+' · RRHH '+(s.id_rrhh??'—');b.addEventListener('click',()=>detalle(s.id_solicitud));list.append(b);}}catch(e){aviso(e.message,'danger');}}
async function detalle(id){try{const d=await api('detalle',null,{id});elegido=d;activar();oficial.clearLayers();propuesta.clearLayers();if(d.oficial)oficial.addData(d.oficial);if(d.propuesta)propuesta.addData(d.propuesta);
const el=$('jrDetalle');el.replaceChildren();texto(el,'Solicitud #'+d.id_solicitud+' · '+d.nombre+' · '+d.tipo_jurisdiccion+' · RRHH '+(d.id_rrhh??'sin vínculo'));
texto(el,'Solicitante '+d.usuario_solicitante+' · Estado '+d.estado+' · Base '+d.version_base+' · Versión oficial '+d.version_oficial);
texto(el,'Motivo: '+d.motivo);texto(el,'SRID propuesta: '+d.srid_propuesta+' · Los límites deben confirmarse antes de aprobar.');
const bounds=L.featureGroup([oficial,propuesta]).getBounds();if(bounds.isValid())map.fitBounds(bounds,{padding:[25,25],maxZoom:17,animate:false});
setTimeout(()=>map.invalidateSize(),100);aviso('Revisá el polígono naranja y comparalo con el oficial azul.','info');
}catch(e){aviso(e.message,'danger');}}
async function resolver(decision){if(!elegido||ocupado)return;try{const motivo=$('jrFundamento').value.trim();if(motivo.length<10)throw new Error('Ingresá un fundamento de al menos diez caracteres.');if(decision==='APROBADA'&&!$('jrConfirmar').checked)throw new Error('Confirmá la revisión del organismo y sus límites.');if(!confirm('¿'+(decision==='APROBADA'?'APROBAR Y PUBLICAR':'RECHAZAR')+' solicitud #'+elegido.id_solicitud+'? Quedará auditada.'))return;
 ocupado=true;activar();const r=await api('resolver',{id_solicitud:Number(elegido.id_solicitud),decision,fundamento:motivo,confirmado:$('jrConfirmar').checked});
 aviso('Solicitud #'+r.id_solicitud+' '+r.estado+(r.version?' · Versión oficial '+r.version:'')+'. Auditoría registrada.','success');elegido=null;$('jrDetalle').textContent='Solicitud resuelta.';$('jrFundamento').value='';$('jrConfirmar').checked=false;oficial.clearLayers();propuesta.clearLayers();await listar();
 }catch(e){aviso('No se publicó la resolución: '+e.message,'danger');}finally{ocupado=false;activar();}}
$('jrRecargar').addEventListener('click',listar);$('jrAprobar').addEventListener('click',()=>resolver('APROBADA'));$('jrRechazar').addEventListener('click',()=>resolver('RECHAZADA'));
$('jrCapaOficial').addEventListener('change',e=>e.target.checked?map.addLayer(oficial):map.removeLayer(oficial));
$('jrCapaPropuesta').addEventListener('change',e=>e.target.checked?map.addLayer(propuesta):map.removeLayer(propuesta));
activar();listar();setTimeout(()=>map.invalidateSize(),200);
})();
