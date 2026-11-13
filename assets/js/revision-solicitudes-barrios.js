(()=>{'use strict';document.addEventListener('DOMContentLoaded',()=>{
  const e=id=>document.getElementById(id), endpoint='/territorio/ajax/revision_solicitudes_barrios.php';
  const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let map,vigente,propuesta,solicitudActual=null;
  async function get(accion,params={}){const url=endpoint+'?'+new URLSearchParams({accion,...params});
    const r=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
    const d=await r.json().catch(()=>({error:'El servidor devolvió una respuesta no JSON.'}));
    if(!r.ok)throw Error(d.error||('HTTP '+r.status));return d;}
  async function listar(){e('rsEstadoLista').textContent='Cargando solicitudes…';e('rsTabla').querySelector('tbody').replaceChildren();
    try{const r=await get('listar',{estado:e('rsEstado').value});
      for(const s of r.data){const tr=document.createElement('tr');
        for(const v of [s.id_solicitud,s.tipo_solicitud,s.nombre_actual||'(Alta nueva)',s.estado,s.version_base??'—',s.fecha_presentacion??'—']){
          const td=document.createElement('td');td.textContent=v;tr.appendChild(td);}
        const td=document.createElement('td'),b=document.createElement('button');b.type='button';b.className='btn btn-sm btn-outline-success';b.textContent='Revisar';
        b.onclick=()=>detalle(s.id_solicitud);td.appendChild(b);tr.appendChild(td);e('rsTabla').querySelector('tbody').appendChild(tr);}
      e('rsEstadoLista').textContent=`${r.data.length} solicitud(es) en el listado.`;
    }catch(err){e('rsEstadoLista').textContent='Error al listar: '+err.message;}}
  function tablaComparacion(r){const body=e('rsDatos');body.replaceChildren();
    const d=r.propuestos||{};
    const campos=[['Nombre',r.nombre_actual,d.nombre],['Localidad (ID)',r.localidad_actual,d.id_localidad],
    ['Clasificación',r.clasificacion_actual,d.clasificacion],['Descripción',r.descripcion_actual,d.descripcion]];
    for(const [nombre,actual,propuesto] of campos){const tr=document.createElement('tr');
      for(const v of [nombre,actual??'—',propuesto??'—']){const td=document.createElement('td');td.textContent=v;tr.appendChild(td);}
      if(String(actual??'')!==String(propuesto??''))tr.classList.add('table-warning');body.appendChild(tr);}
    const c=r.controles||{};e('rsControles').innerHTML=`<div>Versión base vigente: <b>${c.version_base_vigente?'Sí':'NO'}</b></div>
      <div>Histórico subordinado: <b>${c.historico_subordinado?'Sí — revisar':'No'}</b></div>
      <div>SRID de propuesta: <b>${c.geometria_srid_4326?'4326 o sin geometría':'Diferente de 4326'}</b></div>`;}
  function crearMapa(){if(map)return; if(typeof window.L==='undefined'){e('rsMensaje').textContent='Leaflet no está disponible.';return;}
    map=L.map('rsMapa',{zoomControl:true}).setView([-24.79,-65.41],12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(map);}
  function dibujar(r){crearMapa();if(!map)return;if(vigente){map.removeLayer(vigente);vigente=null;}if(propuesta){map.removeLayer(propuesta);propuesta=null;}
    const g1=r.geometria_vigente?.geometry,g2=r.geometria_propuesta?.geometry;
    if(g1)vigente=L.geoJSON(g1,{style:{color:'#2064ad',weight:3,fillOpacity:.12}}).addTo(map);
    if(g2)propuesta=L.geoJSON(g2,{style:{color:'#dc8a20',weight:3,dashArray:'6 4',fillOpacity:.12}}).addTo(map);
    e('rsVerVigente').checked=true;e('rsVerPropuesta').checked=true;
    setTimeout(()=>{map.invalidateSize();const all=L.featureGroup([vigente,propuesta].filter(Boolean));if(all.getLayers().length&&all.getBounds().isValid())map.fitBounds(all.getBounds(),{padding:[25,25]});},80);
    e('rsMensaje').textContent=!g2?'La solicitud no incluye una geometría nueva; se comparan solamente los datos.':
      'Polígono vigente en azul y propuesta en naranja. Revisión de solo lectura.';}
  async function detalle(id){e('rsDetalle').classList.remove('d-none');e('rsNumero').textContent='#'+id;
    e('rsMensaje').textContent='Cargando detalle…';try{const r=await get('detalle',{id});
      solicitudActual=r;
      if(e('rsResolucion'))e('rsResolucion').classList.toggle('d-none',r.estado!=='PENDIENTE');
      if(e('rsConfGeo'))e('rsConfGeo').checked=false;
      e('rsResumen').innerHTML=`<strong>${escape(r.nombre_actual||'(Alta nueva)')}</strong> · ${escape(r.tipo_solicitud)} · ${escape(r.estado)} · Versión base ${escape(r.version_base??'—')}`;
      tablaComparacion(r);dibujar(r);e('rsDetalle').scrollIntoView({behavior:'smooth',block:'start'});
    }catch(err){e('rsMensaje').textContent='Error de detalle: '+err.message;}}
  async function resolver(decision){
    if(!solicitudActual || solicitudActual.estado!=='PENDIENTE')return;
    const motivo=e('rsMotivo')?.value.trim()||'';
    if(motivo.length<10){alert('Ingresá un fundamento de al menos 10 caracteres.');e('rsMotivo')?.focus();return;}
    const confirmarGeo=e('rsConfGeo')?.checked===true;
    const txt=decision==='APROBADA'?'APROBAR':'RECHAZAR';
    if(decision==='APROBADA' && !confirmarGeo && solicitudActual.geometria_propuesta){
      if(!confirm('¿Aprobar únicamente cambios de datos? Si la geometría difiere, el servidor bloqueará la operación.'))return;
    }
    if(!confirm('¿Confirmás '+txt+' la solicitud #'+solicitudActual.id_solicitud+'?'+(confirmarGeo&&decision==='APROBADA'?' Se publicará una NUEVA VERSIÓN de la geometría si los límites difieren.':'')))return;
    const botones=[e('rsAprobar'),e('rsRechazar')].filter(Boolean);
    botones.forEach(b=>b.disabled=true);
    try{
      const r=await fetch(endpoint+'?accion=resolver',{
        method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json',
          'X-CSRF-Token':window.TERR_REVISION?.csrf||''},
        body:JSON.stringify({id_solicitud:Number(solicitudActual.id_solicitud),decision,motivo,confirmacion_geometria:decision==='APROBADA'&&confirmarGeo})});
      const d=await r.json().catch(()=>({error:'Respuesta del servidor inválida.'}));
      if(!r.ok)throw Error(d.error||('HTTP '+r.status));
      e('rsMensaje').textContent='Solicitud #'+d.id_solicitud+' '+d.estado+'. Auditoría registrada.'+(d.geometria_publicada?' Geometría oficial v'+d.version_oficial+' publicada.':'');
      if(e('rsMotivo'))e('rsMotivo').value='';
      await listar();await detalle(d.id_solicitud);
    }catch(err){e('rsMensaje').textContent='No se guardó la resolución: '+err.message;}
    finally{botones.forEach(b=>b.disabled=false);}
  }
  if(e('rsAprobar'))e('rsAprobar').onclick=()=>resolver('APROBADA');
  if(e('rsRechazar'))e('rsRechazar').onclick=()=>resolver('RECHAZADA');
  e('rsVerVigente').onchange=()=>{if(map&&vigente){if(e('rsVerVigente').checked)map.addLayer(vigente);else map.removeLayer(vigente);}};
  e('rsVerPropuesta').onchange=()=>{if(map&&propuesta){if(e('rsVerPropuesta').checked)map.addLayer(propuesta);else map.removeLayer(propuesta);}};
  e('rsEstado').onchange=listar;listar();
});})();
