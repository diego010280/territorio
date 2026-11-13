(() => { 'use strict'; document.addEventListener('DOMContentLoaded',()=>{
const e=id=>document.getElementById(id), base=(window.APP_BASE_URL||'/territorio').replace(/\/$/,'');
const url=base+'/ajax/jurisdicciones_consolidacion.php';
let idActual=0, seleccionadas=[];
const aviso=(m,err=false)=>{e('jcMensaje').className='small mt-3 '+(err?'text-danger':'text-success');e('jcMensaje').textContent=m;};
async function api(action,args={},post=false){
 const req=await fetch(url+'?accion='+encodeURIComponent(action)+(post?'':'&'+new URLSearchParams(args)),{
  method:post?'POST':'GET',headers:post?{'Content-Type':'application/json','X-CSRF-Token':window.GEO_CSRF||''}:{Accept:'application/json'},
  credentials:'same-origin',cache:'no-store',body:post?JSON.stringify(args):undefined
 }); const obj=await req.json().catch(()=>({error:'Respuesta no JSON'}));
 if(!req.ok)throw Error(obj.error||'HTTP '+req.status);return obj;
}
function pintar(){const cont=e('jcSeleccionadas');cont.replaceChildren();
 if(!seleccionadas.length){cont.textContent='Todavía no seleccionaste dependencias actuales.';return;}
 seleccionadas.forEach((s,i)=>{
  const fila=document.createElement('div');fila.className='jc-fila border rounded p-2 mb-2';
  const tit=document.createElement('strong');tit.textContent=s.descripcion+' (ID '+s.id_dependencia+', '+s.categoria_nombre+')';fila.appendChild(tit);
  const row=document.createElement('div');row.className='d-flex flex-wrap gap-2 mt-2 align-items-center';
  const sel=document.createElement('select');sel.className='form-select form-select-sm';sel.setAttribute('aria-label','Cobertura');
  for(const v of ['POR_VERIFICAR','PARCIAL','TOTAL']){const op=document.createElement('option');op.value=v;op.textContent=v.replaceAll('_',' ');sel.appendChild(op);}sel.value=s.cobertura;
  sel.onchange=()=>{s.cobertura=sel.value;};row.appendChild(sel);
  const obs=document.createElement('input');obs.className='form-control form-control-sm';obs.maxLength=500;obs.placeholder='Observaciones sobre la cobertura';obs.value=s.observaciones||'';obs.oninput=()=>{s.observaciones=obs.value;};row.appendChild(obs);
  const quitar=document.createElement('button');quitar.type='button';quitar.className='btn btn-sm btn-outline-danger';quitar.textContent='Quitar';quitar.onclick=()=>{seleccionadas.splice(i,1);pintar();};row.appendChild(quitar);
  fila.appendChild(row);cont.appendChild(fila);
 });
}
async function cargar(){ const id=Number(e('jcId').value);if(!Number.isInteger(id)||id<1){aviso('Número inválido',true);return;}
 try{const data=await api('detalle',{id});idActual=id;
  e('jcCabecera').textContent='Consolidación '+id+' · '+data.consolidacion.nombre_gobernante+' · '+data.consolidacion.estado;
  e('jcGuardar').disabled=data.consolidacion.estado!=='PENDIENTE';
  const ant=e('jcAntecedentes');ant.replaceChildren();
  (data.antecedentes||[]).forEach(a=>{
   const d=document.createElement('div');d.className='list-group-item';
   const titulo=document.createElement('strong');
   titulo.textContent='Histórica '+a.id_historico+' — '+a.nombre_historico;
   d.appendChild(titulo);
   const sucesores=a.sucesores_rrhh||[];
   const info=document.createElement('div');info.className='small text-body-secondary mt-1';
   if (!sucesores.length) info.textContent='RRHH no informa un ID sucesor.';
   else {
     const seleccionables=sucesores.filter(x=>x.es_seleccionable);
     const principal=document.createElement('span');
     principal.textContent=sucesores.length>1?'Varios destinos registrados en RRHH; verificar: ':'ID sucesor informado por RRHH: ';
     info.appendChild(principal);
     sucesores.forEach((x,i)=>{
       const detalle=document.createElement('div');
       detalle.textContent=String(x.id_actual)+' — '+(x.nombre_actual||'Sin nombre')+
         ' · categoría '+(x.categoria_actual??'s/d')+
         ' · '+(String(x.activo_actual)==='1'?'activo':'inactivo')+
         (x.es_seleccionable?' · seleccionable':' · no seleccionable');
       info.appendChild(detalle);
     });
     if (seleccionables.length!==1 || sucesores.length!==1){
       const advert=document.createElement('em');advert.textContent='La continuidad institucional requiere revisión; no se asigna jurisdicción automáticamente.';
       info.appendChild(advert);
     }
   }
   d.appendChild(info);ant.appendChild(d);
  });
  if(!data.antecedentes?.length)ant.textContent='No hay referencias históricas cargadas.';
  seleccionadas=(data.propuestas||[]).map(p=>({id_dependencia:Number(p.gen_organismos_id),descripcion:p.descripcion,categoria_nombre:p.categoria_nombre,cobertura:p.cobertura,observaciones:p.observaciones||''}));
  pintar();aviso('Se cargaron las jurisdicciones propuestas.');
 }catch(ex){aviso(ex.message,true);}}
e('jcCargar').onclick=cargar;
e('jcBuscar').addEventListener('input',()=>{const q=e('jcBuscar').value.trim();const cont=e('jcResultados');cont.replaceChildren();if(q.length<2)return;
 clearTimeout(window._jcTimer);window._jcTimer=setTimeout(async()=>{try{const r=await api('buscar',{q});
  if(e('jcBuscar').value.trim()!==q)return;
  cont.replaceChildren();(r.data||[]).forEach(item=>{const boton=document.createElement('button');boton.type='button';boton.className='list-group-item list-group-item-action text-start';
   boton.textContent=item.descripcion+' (ID '+item.id_dependencia+', '+item.categoria_nombre+')';boton.onclick=()=>{
    if(seleccionadas.some(s=>s.id_dependencia===Number(item.id_dependencia))){aviso('Ya está seleccionada.',true);return;}
    seleccionadas.push({id_dependencia:Number(item.id_dependencia),descripcion:item.descripcion,categoria_nombre:item.categoria_nombre,cobertura:'POR_VERIFICAR',observaciones:''});pintar();cont.replaceChildren();e('jcBuscar').value='';
   };cont.appendChild(boton);});
  if(!r.data?.length)aviso('Sin resultados entre las dependencias activas.',true);
 }catch(ex){aviso(ex.message,true);}},200);
});
e('jcGuardar').onclick=async()=>{if(!idActual){aviso('Primero abrí una consolidación.',true);return;}
 e('jcGuardar').disabled=true;try{const items=seleccionadas.map(s=>({id_dependencia:s.id_dependencia,cobertura:s.cobertura,observaciones:s.observaciones}));
 const res=await api('guardar',{id_consolidacion:idActual,items},true);aviso('Guardado correcto. '+res.cantidad+' jurisdicción(es) POR VERIFICAR, consolidación PENDIENTE.');
 await cargar();}catch(ex){aviso(ex.message,true);}finally{e('jcGuardar').disabled=false;}}
 cargar();
});})();
