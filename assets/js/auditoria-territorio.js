(()=>{'use strict';
const $=id=>document.getElementById(id), url='/territorio/ajax/auditoria_territorio.php';
const csrf=$('atConfig')?.dataset.csrf||'';
if(!csrf||!window.jQuery?.fn?.DataTable){console.error('[AUDITORIA TERRITORIO] falta token o DataTables');return;}
let vista='abm',tabla;
const mensaje=(s,error=false)=>{const n=$('atMensaje');n.textContent=s;n.className='small mt-2 '+(error?'text-danger':'text-body-secondary');};
const filtros=()=>({vista,desde:$('atDesde').value,hasta:$('atHasta').value,usuario:$('atUsuario').value.trim(),tipo:$('atTipo').value,tabla:$('atTabla').value.trim(),registro:$('atRegistro').value.trim(),texto:$('atTexto').value.trim()});
const post=async(data)=>{
 const cuerpo=new URLSearchParams({...data,csrf});
 const r=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:cuerpo,cache:'no-store'});
 if(!r.ok){let e={};try{e=await r.json()}catch(_){}throw new Error(e.error||`HTTP ${r.status}`)}
 return r;
};
const htmlEscape=t=>String(t??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const bonito=s=>{if(s==null||s==='')return '—';try{return JSON.stringify(JSON.parse(s),null,2)}catch(_){return String(s)}};
const columns=[
 {data:'id'}, {data:'fecha'}, {data:'usuario_id'}, {data:'modulo_id'}, {data:'tipo'},
 {data:'tabla',defaultContent:'—'}, {data:'registro',defaultContent:'—'},
 {data:'observacion',defaultContent:'—'}, {data:'ip',defaultContent:'—'},
 {data:'id',orderable:false,searchable:false,render:(d,t)=>t==='display'?'<button class="btn btn-sm btn-outline-primary at-detalle" type="button" data-id="'+Number(d)+'"><i class="bi bi-eye"></i> Detalle</button>':d}
];
tabla=jQuery('#atTablaResultados').DataTable({processing:true,serverSide:true,searching:false,ordering:false,pageLength:15,lengthMenu:[[15,30,50,100],[15,30,50,100]],autoWidth:false,
 ajax:(data,callback)=>{
    post({...filtros(),accion:'listar',start:data.start,length:data.length,draw:data.draw})
    .then(r=>r.json()).then(j=>{mensaje('Resultados filtrados: '+j.recordsFiltered);callback(j)})
    .catch(e=>{mensaje(e.message,true);callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]})});
 },columns,createdRow:(tr)=>{tr.querySelectorAll('td').forEach((td,i)=>{if(i!==9){td.title=td.textContent;td.classList.add('text-break')}})} });
async function tipos(){try{const r=await post({accion:'tipos'}), j=await r.json();(j.data||[]).forEach(t=>$('atTipo').add(new Option(t.codigo+' — '+t.descripcion,t.id_tipo)))}catch(e){mensaje('No se pudieron cargar los tipos: '+e.message,true)}}
tipos();
$('atTabs').addEventListener('click',e=>{const b=e.target.closest('[data-vista]');if(!b)return;vista=b.dataset.vista;document.querySelectorAll('#atTabs [data-vista]').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('.at-abm').forEach(x=>{x.hidden=vista!=='abm';x.style.display=vista==='abm'?'':'none'});$('atTabla').value='';$('atRegistro').value='';tabla.ajax.reload()});
$('atBuscar').addEventListener('click',()=>tabla.ajax.reload());
$('atLimpiar').addEventListener('click',()=>{['atDesde','atHasta','atUsuario','atTipo','atTexto','atTabla','atRegistro'].forEach(id=>$(id).value='');tabla.ajax.reload()});
$('atTablaResultados').addEventListener('click',async e=>{const btn=e.target.closest('.at-detalle');if(!btn)return;
 const modal=bootstrap.Modal.getOrCreateInstance($('atDetalleModal'));$('atDetalleTitulo').textContent='Detalle de auditoría #'+btn.dataset.id;
 $('atDetalleMeta').textContent='Consultando...';$('atAntes').textContent='';$('atDespues').textContent='';modal.show();
 try{const r=await post({accion:'detalle',vista,id:btn.dataset.id}),j=await r.json(),d=j.data||{};
 $('atDetalleMeta').textContent=`${d.tipo||''} · Usuario ${d.usuario_id||''} · ${d.fecha||''} · IP ${d.ip||''} · Registro ${d.registro||'—'} · Correlación ${d.correlacion||'—'} · ${d.observacion||''} · Agente: ${d.user_agent||'—'}`;
 $('atAntes').textContent=bonito(d.antes);
 $('atDespues').textContent=bonito(vista==='abm'?d.despues:d.parametros);
 }catch(err){$('atDetalleMeta').textContent='Error: '+err.message}
});
$('atExcel').addEventListener('click',async()=>{const b=$('atExcel');b.disabled=true;try{
 const r=await post({...filtros(),accion:'exportar',formato:'excel'}),blob=await r.blob();
 const href=URL.createObjectURL(blob),a=document.createElement('a');a.href=href;a.download='auditoria_territorio_'+vista+'.csv';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(href),5000);mensaje('Exportación CSV registrada en auditoría.');
 }catch(e){mensaje(e.message,true)}finally{b.disabled=false}});
async function imprimir(formato){
 const ventana=window.open('','_blank');if(!ventana){mensaje('Habilitá las ventanas emergentes para imprimir o guardar PDF.',true);return}
 ventana.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Auditoría Territorio</title></head><body><p>Preparando reporte...</p></body></html>');
 try{const r=await post({...filtros(),accion:'exportar',formato}),j=await r.json();
 const keys=['id','fecha','usuario_id','modulo_id','tipo','tabla','registro','observacion','correlacion','ip'];
 const cabecera=keys.map(k=>'<th>'+htmlEscape(k)+'</th>').join('');
 const cuerpo=(j.data||[]).map(x=>'<tr>'+keys.map(k=>'<td>'+htmlEscape(x[k])+'</td>').join('')+'</tr>').join('');
 const detalle='Vista: '+vista+' · Filtrado: '+JSON.stringify(filtros());
 ventana.document.open();ventana.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Auditoría Territorio</title><style>body{font:11px Arial;margin:18px}h1{font-size:18px}p{word-break:break-word}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:4px;vertical-align:top;word-break:break-word}th{background:#eee}@page{size:landscape;margin:10mm}</style></head><body><h1>Auditoría de Territorio</h1><p>'+htmlEscape(detalle)+'</p><table><thead><tr>'+cabecera+'</tr></thead><tbody>'+cuerpo+'</tbody></table><p>Registros: '+(j.data||[]).length+'</p></body></html>');ventana.document.close();
 ventana.focus();setTimeout(()=>ventana.print(),300);mensaje(formato==='pdf'?'Reporte auditado. En la impresión elegí «Guardar como PDF».':'Impresión registrada en auditoría.');
 }catch(e){ventana.close();mensaje(e.message,true)}
}
$('atPDF').addEventListener('click',()=>imprimir('pdf'));
$('atPrint').addEventListener('click',()=>imprimir('print'));
})();
