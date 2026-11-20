(()=>{'use strict';
const $=id=>document.getElementById(id), root=$('geoJurisRoot');if(!root||!$('jurGrillaPrincipal'))return;
const endpoint='/territorio/ajax/gestion_jurisdicciones.php';let pagina=1,total=0,tam=25,seq=0,timer=null;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function mostrarGrilla(){ $('jurEditorPanel').classList.add('d-none');$('jurGrillaPrincipal').classList.remove('d-none');$('jurGrillaPrincipal').scrollIntoView({block:'start',behavior:'smooth'});}
function mostrarEditor(v, modo='editar'){
 $('jurGrillaPrincipal').classList.add('d-none');$('jurEditorPanel').classList.remove('d-none');
 $('jurTituloDetalle').textContent=(modo==='ver'?'Consultar':'Gestionar')+' — '+v.nombre+' · ID '+v.id_jurisdiccion;
 window.territorioSeleccionarJurisdiccion?.(v.id_jurisdiccion,v.nombre,v.id_organismo_rrhh,v.tipo);
 // En modo consulta se ocultan las herramientas de propuesta; no se cambian los permisos del servidor.
 const tools=$('jurDibujarGuiado')?.closest('.border.rounded'); if(tools)tools.classList.toggle('d-none',modo==='ver');
 const motivo=$('jurMotivo');if(motivo){motivo.closest('label')?.classList.remove('d-none');motivo.classList.toggle('d-none',modo==='ver');}
 for(const id of ['jurProponer','jurLimpiarMapa'])$(id)?.classList.toggle('d-none',modo==='ver');
 setTimeout(()=>window.territorioRefrescarMapaJurisdiccion?.(),120);
 $('jurEditorPanel').scrollIntoView({block:'start',behavior:'smooth'});
}
window.territorioMostrarGrilla=mostrarGrilla;window.territorioRecargarGrilla=()=>cargar();
function filtros(){return {accion:'listar_paginado',q:$('jgTexto').value.trim(),tipo:$('jgTipo').value,estado:$('jgEstado').value,ddp:$('jgDDP').value,geometria:$('jgGeometria').value,pagina:String(pagina),tam:String(tam)};}
async function cargar(){const n=++seq;$('jgEstadoCarga').textContent='Buscando...';try{
 const resp=await fetch(endpoint+'?'+new URLSearchParams(filtros()),{credentials:'same-origin',cache:'no-store'});
 const data=await resp.json();if(!resp.ok)throw new Error(data.error||'Error consultando jurisdicciones');if(n!==seq)return;
 total=Number(data.total)||0;const rows=data.data||[];let html='';
 for(const v of rows){let c=/^#[0-9A-Fa-f]{6}$/.test(v.color_mapa||'')?v.color_mapa:'#287C67';const of=Number(v.version_oficial)>0;
 html+='<tr><td>'+esc(v.id_jurisdiccion)+'</td><td>'+esc(v.nombre)+'</td><td>'+esc(v.tipo)+(v.numero_ddp?' / '+esc(v.numero_ddp):'')+'</td><td>'+esc(v.id_organismo_rrhh??'—')+'</td><td><span class="badge '+(v.estado==='APROBADO'?'bg-success':'bg-warning text-dark')+'">'+esc(v.estado)+'</span></td><td>'+(of?'<span class="badge bg-success">Oficial</span>':Number(v.borradores)>0?'<span class="badge bg-warning text-dark">Borrador</span>':'Sin geometría')+'</td><td>'+esc(v.version_oficial??'—')+'</td><td><span class="d-inline-block rounded border align-middle me-1" style="width:20px;height:18px;background:'+c+'"></span><small>'+esc(v.color_mapa??'Por defecto')+'</small></td><td>'+esc(v.pendientes||0)+'</td><td class="text-nowrap"><button type="button" data-act="ver" data-id="'+esc(v.id_jurisdiccion)+'" class="btn btn-sm btn-outline-secondary me-1">Ver</button><button type="button" data-act="editar" data-id="'+esc(v.id_jurisdiccion)+'" class="btn btn-sm btn-outline-primary me-1">Editar</button>'+(of?'<button type="button" data-act="color" data-id="'+esc(v.id_jurisdiccion)+'" class="btn btn-sm btn-outline-success">Color</button>':'')+'</td></tr>';
 }
 $('jgFilas').innerHTML=html||'<tr><td colspan="10" class="text-muted text-center">Sin resultados para los filtros seleccionados.</td></tr>';
 const paginas=Math.max(1,Math.ceil(total/tam));$('jgPagina').textContent='Página '+pagina+' de '+paginas;
 $('jgConteo').textContent=total+' jurisdicciones · '+rows.length+' en esta página';$('jgAnterior').disabled=pagina<=1;$('jgSiguiente').disabled=pagina>=paginas;
 $('jgEstadoCarga').textContent='';
 $('jgFilas').querySelectorAll('button[data-act]').forEach(b=>b.addEventListener('click',()=>{const v=rows.find(x=>String(x.id_jurisdiccion)===b.dataset.id);if(!v)return;
 const action=b.dataset.act;mostrarEditor(v,action==='ver'?'ver':'editar');if(action==='color')setTimeout(()=>$('jurAbrirColor')?.click(),220);
 }));
 }catch(e){if(n!==seq)return;$('jgEstadoCarga').textContent=e.message;$('jgFilas').innerHTML='<tr><td colspan="10" class="text-danger">No se pudieron cargar los datos.</td></tr>';}}
for(const id of ['jgTexto'])$(id).addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{pagina=1;cargar();},350)});
for(const id of ['jgTipo','jgEstado','jgDDP','jgGeometria'])$(id).addEventListener('change',()=>{pagina=1;cargar()});
$('jgBuscar').addEventListener('click',()=>{pagina=1;cargar()});$('jgTexto').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();pagina=1;cargar()}});
$('jgLimpiar').addEventListener('click',()=>{for(const id of ['jgTexto','jgTipo','jgEstado'])$(id).value='';$('jgDDP').value='0';$('jgGeometria').value='';pagina=1;cargar()});
$('jgAnterior').addEventListener('click',()=>{if(pagina>1){pagina--;cargar()}});$('jgSiguiente').addEventListener('click',()=>{if(pagina*tam<total){pagina++;cargar()}});
$('jgCerrarDetalle').addEventListener('click',()=>$('jurVolverListado')?.click());
$('jgNuevaRRHH').addEventListener('click',()=>{ $('jurGrillaPrincipal').classList.add('d-none');$('jurEditorPanel').classList.remove('d-none');$('jurRRHHBuscar').focus();$('jurEditorPanel').scrollIntoView({block:'start',behavior:'smooth'});setTimeout(()=>window.territorioRefrescarMapaJurisdiccion?.(),120);});
cargar();
})();
