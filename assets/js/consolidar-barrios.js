(()=>{'use strict';document.addEventListener('DOMContentLoaded',()=>{
 const el=id=>document.getElementById(id);const api='/territorio/ajax/consolidar_barrios.php';
 let loc=null,municipal=null;const origenes=new Map();let verificadoSurc=null;
 const mensaje=(s,err=false)=>{el('cbMensaje').textContent=s;el('cbMensaje').className='mt-3 small '+(err?'text-danger':'text-success');};
 async function request(params,post){const url=api+'?'+new URLSearchParams(params);const response=await fetch(url,{method:post?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(post?{'Content-Type':'application/json','X-CSRF-Token':window.GEO_CSRF||''}:{})},body:post?JSON.stringify(post):undefined,cache:'no-store'});const data=await response.json().catch(()=>({error:'Respuesta no JSON'}));if(!response.ok)throw Error(data.error||`HTTP ${response.status}`);return data;}
 const limpiar=(id)=>el(id).replaceChildren();
 function item(texto,accion){const b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action text-start';b.textContent=texto;b.addEventListener('click',accion);return b;}
 function buscador(inputId,resultId,fn){let seq=0;el(inputId).addEventListener('input',async()=>{const q=el(inputId).value.trim();const n=++seq;limpiar(resultId);if(q.length<2)return;try{const data=await fn(q);if(n!==seq)return;(data.data||[]).forEach(r=>el(resultId).appendChild(item(r.texto,()=>r.accion())));}catch(e){mensaje(e.message,true);}});}
 buscador('cbLocalidadBusca','cbLocalidadLista',async q=>{const d=await request({accion:'localidades',q});return {data:d.data.map(r=>({texto:`${r.nombre} — ID ${r.id} · Municipio ${r.municipio_id}`,accion:()=>{loc=r.id;el('cbLocalidadBusca').value=r.nombre;el('cbLocalidadElegida').textContent=`Localidad elegida: ${r.nombre} (ID ${r.id})`;origenes.clear();dibujarOrigenes();municipal=null;el('cbMunicipalElegido').textContent='';limpiarSurc();++surcSeq;el('cbSurcBusca').value='';limpiar('cbSurcLista');el('cbSurcInfo').textContent='Seleccioná un barrio SURC de esta localidad.';limpiar('cbLocalidadLista');}}))};});
 function dibujarOrigenes(){const dest=el('cbElegidos');dest.replaceChildren();origenes.forEach((name,id)=>{const span=document.createElement('span');span.className='cb-elegido';span.append(document.createTextNode(`${name} · ID ${id}`));const remove=document.createElement('button');remove.type='button';remove.textContent='×';remove.setAttribute('aria-label',`Quitar ${name}`);remove.onclick=()=>{origenes.delete(id);dibujarOrigenes();};span.appendChild(remove);dest.appendChild(span);});}
 buscador('cbHistoricoBusca','cbHistoricoLista',async q=>{if(!loc)throw Error('Seleccioná una localidad primero.');const d=await request({accion:'barrios',localidad:loc,q});return {data:d.data.map(r=>({texto:`${r.nombre} · ID territorial ${r.id_barrio}`,accion:()=>{origenes.set(Number(r.id_barrio),r.nombre);dibujarOrigenes();limpiar('cbHistoricoLista');}}))};});
 buscador('cbMunicipalBusca','cbMunicipalLista',async q=>{const d=await request({accion:'municipales',q});return {data:d.data.map(r=>({texto:`${r.nombre_original} — municipal ${r.id_externo} · importación ${r.id_importacion}`,accion:()=>{municipal=Number(r.id_importacion);el('cbMunicipalElegido').textContent=`Polígono candidato: ${r.nombre_original}, importación ${municipal}. No vinculado aún.`;limpiar('cbMunicipalLista');}}))};});
 // Búsqueda real SURC: código y localidad provienen de una selección, no de texto libre.
 let surcSeq=0;
 function limpiarSurc() {
   verificadoSurc=null;
   el('cbSurcElegido').textContent='';
   el('cbQuitarSurc').disabled=true;
 }
 el('cbSurcBusca').addEventListener('input',async()=>{
   limpiarSurc();
   limpiar('cbSurcLista');
   const q=el('cbSurcBusca').value.trim(),seq=++surcSeq;
   if(q.length<2){el('cbSurcInfo').textContent='Escribí al menos 2 caracteres.';return;}
   if(!loc){el('cbSurcInfo').textContent='Seleccioná una localidad primero.';return;}
   el('cbSurcInfo').textContent='Buscando en dbseg.ref_barrio…';
   try{
     const d=await request({accion:'surc_buscar',q,localidad:loc});
     if(seq!==surcSeq)return;
     const arr=d.data||[];
     el('cbSurcInfo').textContent=d.advertencia || `${arr.length} coincidencia(s) del catálogo SURC. Seleccioná la que corresponda al barrio.`;
     arr.forEach(r=>{
       const estado=r.estado_asociacion||'SIN EQUIVALENCIA';
       const titulo=`${r.Barrio_Descrip} — SURC ${r.Barrio_Codigo} · localidad SURC ${r.Localidad_Codigo} · ${estado}`;
       el('cbSurcLista').appendChild(item(titulo,()=>{
         if(r.asociado && r.estado_asociacion==='VALIDADA'){
            mensaje('Este código SURC ya tiene una equivalencia VALIDADA. Requiere revisión administrativa.',true);
            return;
         }
         verificadoSurc=Number(r.Barrio_Codigo);
         el('cbSurcBusca').value=String(r.Barrio_Descrip);
         el('cbSurcElegido').textContent=`Seleccionado: ${r.Barrio_Descrip} · Código SURC ${r.Barrio_Codigo} · localidad ${r.Localidad_Codigo} · ${estado}. Pendiente de aprobación.`;
         el('cbQuitarSurc').disabled=false;
         limpiar('cbSurcLista');
       }));
     });
   }catch(e){if(seq===surcSeq)el('cbSurcInfo').textContent='Error: '+e.message;}
 });
 el('cbQuitarSurc').addEventListener('click',()=>{
   limpiarSurc();el('cbSurcBusca').value='';limpiar('cbSurcLista');
   el('cbSurcInfo').textContent='Sin código SURC seleccionado.';
 });

 // Validación individual de campos sin cambiar las autorizaciones ni el backend.
 const controles=['cbLocalidadBusca','cbNombre','cbMotivo','cbHistoricoBusca','cbSurcBusca'];
 function borrarError(id){
   const campo=el(id);
   campo?.classList.remove('is-invalid');
   campo?.removeAttribute('aria-invalid');
   campo?.parentElement?.querySelector(`[data-cb-error="${id}"]`)?.remove();
 }
 function limpiarErrores(){ controles.forEach(borrarError); }
 function marcarError(id,texto){
   const campo=el(id);if(!campo)return;
   borrarError(id);
   campo.classList.add('is-invalid');campo.setAttribute('aria-invalid','true');
   const aviso=document.createElement('div');
   aviso.className='cb-error text-danger small mt-1';
   aviso.dataset.cbError=id;aviso.setAttribute('role','alert');
   aviso.textContent=texto;
   campo.insertAdjacentElement('afterend',aviso);
 }
 controles.forEach(id=>el(id)?.addEventListener('input',()=>{
   borrarError(id);
   if(el('cbMensaje').classList.contains('text-danger'))mensaje('');
 }));
 el('cbElegidos')?.addEventListener('click',()=>borrarError('cbHistoricoBusca'));
 el('cbGuardar').addEventListener('click',()=>{
   // Los mensajes de validación anteriores no persisten al intentar de nuevo.
   if(el('cbMensaje').classList.contains('text-danger'))mensaje('');
 });
 el('cbForm').addEventListener('submit',async e=>{
   e.preventDefault();
   limpiarErrores();
   const nombre=el('cbNombre').value.trim(),motivo=el('cbMotivo').value.trim();
   const errores=[];
   if(!loc) {marcarError('cbLocalidadBusca','Seleccioná una localidad desde las coincidencias.');errores.push('localidad');}
   if(nombre.length<3) {marcarError('cbNombre','Ingresá un nombre de al menos 3 caracteres.');errores.push('nombre');}
   if(motivo.length<5) {marcarError('cbMotivo','Escribí un fundamento de al menos 5 caracteres.');errores.push('fundamento');}
   if(origenes.size<2) {marcarError('cbHistoricoBusca','Seleccioná al menos dos registros históricos.');errores.push('históricos');}
   if(el('cbSurcBusca').value.trim()&&verificadoSurc===null){
     marcarError('cbSurcBusca','Seleccioná un resultado SURC o quitá la búsqueda.');errores.push('SURC');
   }
   if(errores.length){
     mensaje('Revisá los campos marcados en rojo ('+errores.join(', ')+').',true);
     el(errores[0]==='localidad'?'cbLocalidadBusca':errores[0]==='nombre'?'cbNombre':errores[0]==='fundamento'?'cbMotivo':errores[0]==='históricos'?'cbHistoricoBusca':'cbSurcBusca').focus();
     return;
   }
   const btn=el('cbGuardar');btn.disabled=true;
   try{
     const d=await request({accion:'guardar'},{
       nombre,id_localidad:loc,tipo:el('cbTipo').value,motivo,
       origenes:[...origenes.keys()],id_municipal:municipal,codigo_surc:verificadoSurc
     });
     limpiarErrores();
     mensaje(`Consolidación N.º ${d.id_consolidacion} registrada PENDIENTE. El código SURC se guarda solo como candidato; no se modificaron barrios ni equivalencias.`);
     el('cbForm').querySelectorAll('input,textarea,select,button').forEach(x=>x.disabled=true);
   }catch(error){mensaje('No se registró: '+error.message,true);btn.disabled=false;}
 });
});})();
