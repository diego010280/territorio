(() => {
'use strict';
document.addEventListener('DOMContentLoaded', () => {
 const el=id=>document.getElementById(id);
 const base=String(window.APP_BASE_URL || '/territorio').replace(/\/$/,'');
 const url=base+'/ajax/revision_consolidaciones.php';
 let actual=null,mapa=null,capa=null,simulacionOK=false;
 let jurisdiccionEnRevision=null;
 const ver=(texto)=>{el('rcEstadoTexto').textContent=texto};
 const msg=(texto,error=false)=>{el('rcMensaje').textContent=texto;el('rcMensaje').className='small mt-2 '+(error?'text-danger':'text-success')};
 async function api(accion,params={},options={}) {
   const response=await fetch(url+'?'+new URLSearchParams({accion,...params}),{
       cache:'no-store',credentials:'same-origin',headers:{Accept:'application/json'},...options});
   const data=await response.json().catch(()=>({error:'Respuesta no JSON'}));
   if(!response.ok)throw Error(data.error||`HTTP ${response.status}`);
   return data;
 }
 function textCell(tr,txt){const td=document.createElement('td');td.textContent=txt==null?'—':String(txt);tr.appendChild(td);return td}
 function badgeStatus(s){return s==='PENDIENTE'?'Pendiente':s==='APROBADA'?'Aprobada':s==='RECHAZADA'?'Rechazada':s==='CANCELADA'?'Cancelada':s}
 async function listar(){
   el('rcPanel').classList.add('d-none');actual=null;
   const tbody=el('rcTabla').tBodies[0];tbody.replaceChildren();ver('Cargando…');
   try{
     const r=await api('listar',{estado:el('rcEstado').value});
     for(const item of r.data){
       const tr=document.createElement('tr');
       textCell(tr,item.id_consolidacion);textCell(tr,item.nombre_gobernante);
       textCell(tr,item.localidad_nombre||item.id_localidad);textCell(tr,item.total_historicos);
       textCell(tr,item.barrio_codigo_surc);textCell(tr,item.id_importacion_municipal);
       textCell(tr,badgeStatus(item.estado));
       const td=tr.insertCell();const boton=document.createElement('button');
       boton.className='btn btn-sm btn-outline-success rc-row-btn';boton.type='button';
       boton.textContent='Revisar';boton.addEventListener('click',()=>detalle(item.id_consolidacion));
       td.appendChild(boton);tbody.appendChild(tr);
     }
     ver(`${r.data.length} consolidación(es) encontradas.`);
   } catch(e){ver('No se pudo cargar: '+e.message)}
 }
 function linea(dl,n,v){const dt=document.createElement('dt');dt.className='col-sm-5';dt.textContent=n;
     const dd=document.createElement('dd');dd.className='col-sm-7';dd.textContent=v==null?'—':String(v);
     dl.append(dt,dd)}
 function representar(c){
   el('rcId').textContent='#'+c.id_consolidacion;
   const datos=el('rcDatos');datos.replaceChildren();
   [['Nombre propuesto',c.nombre_gobernante],['Localidad',c.localidad_nombre],
    ['Estado',c.estado],['Relación',c.tipo_relacion],['Motivo',c.motivo],
    ['Código SURC',c.barrio_codigo_surc],['Nombre SURC',c.nombre_surc],
    ['Localidad SURC',c.localidad_surc],['Estado de equivalencia',c.estado_surc_actual||'Sin equivalencia'],
    ['Código municipal',c.codigo_municipal],['Nombre municipal',c.nombre_municipal],
    ['Solicitante ID',c.usuario_solicitante],['Fecha',c.fecha_solicitud],
    ['Resolución',c.observaciones_resolucion]].forEach(([k,v])=>linea(datos,k,v));
   const historicos=el('rcHistoricos');historicos.replaceChildren();
   for(const h of c.historicos){const p=document.createElement('div');p.className='small border-bottom py-1';
       p.textContent=`${h.id_barrio_origen} — ${h.nombre_actual} (${h.estado_barrio})`;historicos.appendChild(p)}
   // Las relaciones jurisdiccionales son candidatas, no se convierten en vigentes en esta pantalla.
   const jurDiv=el('rcJurisdicciones');jurDiv.replaceChildren();
   jurisdiccionEnRevision=null;el('rcJurDecision').classList.add('d-none');el('rcJurMensaje').textContent='';
   const jur=Array.isArray(c.jurisdicciones)?c.jurisdicciones:[];
   if(!jur.length){const p=document.createElement('p');p.textContent='No se propusieron jurisdicciones policiales.';jurDiv.appendChild(p)}
   for(const j of jur){
     const div=document.createElement('div');div.className='border rounded p-2 mb-2';
     const n=document.createElement('div');n.className='fw-semibold';
     n.textContent=`${j.gen_organismos_id} — ${j.dependencia||'Dependencia no encontrada en RRHH'}`;div.appendChild(n);
     const details=document.createElement('div');details.className='text-body-secondary';
     details.textContent=`Categoría: ${j.categoria??'?'} · Activa: ${j.activo==1?'Sí':'No'} · Cobertura: ${j.cobertura} · Estado: ${j.estado}`;div.appendChild(details);
     if(j.observaciones){const obs=document.createElement('div');obs.textContent='Observaciones: '+j.observaciones;div.appendChild(obs)}
     if(c.estado==='PENDIENTE'&&j.estado==='POR_VERIFICAR'){
       const btn=document.createElement('button');btn.type='button';btn.className='btn btn-sm btn-outline-primary mt-1';btn.textContent='Resolver relación';
       btn.addEventListener('click',()=>{jurisdiccionEnRevision=Number(j.gen_organismos_id);
         el('rcJurDecisionTitulo').textContent='Revisar dependencia '+j.gen_organismos_id;
         el('rcJurFundamento').value='';el('rcJurDecision').classList.remove('d-none');el('rcJurFundamento').focus()});
       div.appendChild(btn);
     }
     jurDiv.appendChild(div);
   }
   if(jur.length){const a=document.createElement('a');a.className='btn btn-sm btn-outline-secondary mb-2';
     a.href=base+'/jurisdicciones_consolidacion.php?id='+encodeURIComponent(c.id_consolidacion);
     a.textContent='Ver propuesta de jurisdicciones';jurDiv.appendChild(a)}
   const controles=el('rcControles');controles.replaceChildren();
   const titulos={al_menos_dos_historicos:'Dos o más registros históricos',misma_localidad:'Todos pertenecen a la misma localidad',
     sin_gobernante_previo:'Históricos sin gobernante aprobado',surc_no_validado_con_otro:'SURC no validado con otro barrio',
     municipal_disponible:'Geometría municipal disponible o no requerida'};
   Object.entries(c.controles).forEach(([k,v])=>{const div=document.createElement('div');
       div.className='rc-control '+(v?'rc-ok':'rc-error');div.textContent=(v?'✓ ':'✕ ')+(titulos[k]||k);controles.appendChild(div)});
   simulacionOK=false; el('rcAbrirAprobar').disabled=true; el('rcAprobacionBox').classList.add('d-none');
   el('rcMostrarRechazo').classList.toggle('d-none',c.estado!=='PENDIENTE');
   el('rcSimular').disabled=(c.estado!=='PENDIENTE');
   el('rcRechazoBox').classList.add('d-none');el('rcConfirmarRechazo').classList.add('d-none');
   el('rcFundamento').value='';msg('');
   el('rcPanel').classList.remove('d-none');
   if(typeof L==='undefined'){
       el('rcMapa').textContent='No está disponible Leaflet; comprobá las bibliotecas CSS y JS.';
       return;
   }
   if(!mapa){mapa=L.map('rcMapa',{scrollWheelZoom:false}).setView([-24.7821,-65.4232],12);
       L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
           maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(mapa)}
   if(capa){capa.remove();capa=null}
   if(c.geometry){capa=L.geoJSON({type:'Feature',properties:{},geometry:c.geometry},{
       style:{color:'#d99a26',weight:3,fillColor:'#d99a26',fillOpacity:.13}}).addTo(mapa);
       if(capa.getBounds().isValid())mapa.fitBounds(capa.getBounds(),{padding:[26,26]});}
   else mapa.setView([-24.7821,-65.4232],12);
   setTimeout(()=>mapa.invalidateSize(),70);
 }
 async function detalle(id){
   el('rcPanel').classList.remove('d-none');msg('Cargando detalle…');
   try{actual=await api('detalle',{id});representar(actual);
       el('rcPanel').scrollIntoView({behavior:'smooth',block:'start'});
   }catch(e){msg('Error al consultar: '+e.message,true)}
 }
 async function postSeguro(accion,data){
    return api(accion,{}, {method:'POST',headers:{'Content-Type':'application/json',
      'X-CSRF-Token':String(window.GEO_CSRF||'')},body:JSON.stringify(data)});
 }
 el('rcSimular').addEventListener('click',async()=>{
   if(!actual || actual.estado!=='PENDIENTE')return;
   simulacionOK=false;
   el('rcAbrirAprobar').disabled=true;
   el('rcAprobacionBox').classList.add('d-none');
   try {
     const r=await postSeguro('simular_aprobacion',{id_consolidacion:actual.id_consolidacion});
     simulacionOK=true;
     el('rcAbrirAprobar').disabled=false;
     el('rcSimulacionDetalle').textContent=`Gobernante: ${r.gobernante} · Históricos: ${r.historicos.join(', ')} · SURC: ${r.surc||'sin'} · Municipalidad: ${r.municipal||'sin'}. ${r.advertencia}`;
     msg('Simulación preliminar válida. Revisá cartografía y fundamentos antes de aprobar.');
   }catch(e){ msg('No se puede publicar: '+e.message,true); }
 });
 el('rcAbrirAprobar').addEventListener('click',()=>{
   if(!simulacionOK)return;
   el('rcAprobacionBox').classList.remove('d-none');
   el('rcMotivoAprobar').focus();
 });
 el('rcConfirmarAprobar').addEventListener('click',async()=>{
   if(!simulacionOK || !actual || actual.estado!=='PENDIENTE')return;
   const fundamento=el('rcMotivoAprobar').value.trim();
   if(fundamento.length<15){msg('Fundamento: mínimo 15 caracteres.',true);return;}
   if(!el('rcRevisionCartografica').checked){msg('Confirmá la revisión cartográfica.',true);return;}
   if(el('rcConfirmacionTexto').value.trim()!=='APROBAR'){msg('Escribí APROBAR para continuar.',true);return;}
   if(!confirm(`¿Publicar definitivamente la consolidación N.º ${actual.id_consolidacion} como gobernante?`))return;
   el('rcConfirmarAprobar').disabled=true;
   try {
     const r=await postSeguro('aprobar',{id_consolidacion:actual.id_consolidacion,fundamento,
       confirmacion:'APROBAR',revision_cartografica:true});
     simulacionOK=false;
     msg('Gobernante publicado: ID '+r.id_barrio_gobernante+'. La aprobación fue auditada.');
     await listar();
     await detalle(r.id_consolidacion);
     msg('Aprobación finalizada. Nuevo barrio gobernante ID '+r.id_barrio_gobernante+'.');
   }catch(e){msg('No se aprobó: '+e.message,true)}
   finally{el('rcConfirmarAprobar').disabled=false;}
 });
 el('rcJurCancelar').addEventListener('click',()=>{jurisdiccionEnRevision=null;el('rcJurDecision').classList.add('d-none')});
 async function resolverJurisdiccion(decision){
   if(!actual||!jurisdiccionEnRevision)return;
   const fundamento=el('rcJurFundamento').value.trim();
   if(fundamento.length<12){el('rcJurMensaje').textContent='Ingresá un fundamento de al menos 12 caracteres.';return}
   if(!confirm(`¿${decision==='VALIDADA'?'Validar':'Descartar'} jurisdicción ${jurisdiccionEnRevision}?`))return;
   el('rcJurValidar').disabled=true;el('rcJurDescartar').disabled=true;
   try {
     await postSeguro('resolver_jurisdiccion',{id_consolidacion:actual.id_consolidacion,
       gen_organismos_id:jurisdiccionEnRevision,decision,fundamento});
     const id=actual.id_consolidacion;await detalle(id);
     el('rcJurMensaje').textContent='Decisión registrada y auditada. La jurisdicción aún no está publicada.';
   }catch(e){el('rcJurMensaje').textContent='No se pudo resolver: '+e.message;}
   finally{el('rcJurValidar').disabled=false;el('rcJurDescartar').disabled=false;}
 }
 el('rcJurValidar').addEventListener('click',()=>resolverJurisdiccion('VALIDADA'));
 el('rcJurDescartar').addEventListener('click',()=>resolverJurisdiccion('DESCARTADA'));
 el('rcEstado').addEventListener('change',listar);
 el('rcMostrarRechazo').addEventListener('click',()=>{
   el('rcRechazoBox').classList.remove('d-none');el('rcConfirmarRechazo').classList.remove('d-none');
   el('rcFundamento').focus();
 });
 el('rcConfirmarRechazo').addEventListener('click',async()=>{
    if(!actual || actual.estado!=='PENDIENTE')return;
    const motivo=el('rcFundamento').value.trim();
    if(motivo.length<12){msg('El fundamento del rechazo debe contener al menos 12 caracteres.',true);return}
    if(!confirm(`¿Rechazar definitivamente la consolidación N.º ${actual.id_consolidacion}?`))return;
    el('rcConfirmarRechazo').disabled=true;
    try{await api('rechazar',{}, {method:'POST',headers:{'Content-Type':'application/json',
        'X-CSRF-Token':String(window.GEO_CSRF||'')},body:JSON.stringify({id_consolidacion:actual.id_consolidacion,motivo})});
        msg('Consolidación rechazada. Los barrios y códigos históricos no se modificaron.');
        await listar();
    }catch(e){msg('No se pudo rechazar: '+e.message,true)}
    finally{el('rcConfirmarRechazo').disabled=false}
 });
 listar();
});
})();
