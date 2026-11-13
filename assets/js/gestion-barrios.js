/* Gestión de Barrios - consulta segura sin operaciones de escritura */
(() => {
 'use strict';
 document.addEventListener('DOMContentLoaded', () => {
  const tablaNodo=document.getElementById('gbTabla'); if (!tablaNodo) return;
  const estado=document.getElementById('gbMensaje');
  const $=id=>document.getElementById(id);
  const url='/territorio/ajax/gestion_barrios.php';
  const mostrar=msg=>{estado.textContent=msg;};
  if (typeof DataTable==='undefined') {mostrar('No se cargó DataTables. Revisá $usarDataTables y templates/scripts.php.');return;}
  const texto=(valor)=> valor===null || valor===undefined || valor===''?'—':String(valor);
  const celda=(valor)=>{ const span=document.createElement('span'); span.textContent=texto(valor); return span.outerHTML; };
  const puedeModificar=$('gbPermisos')?.dataset.puedeModificar === '1';
  let idElegido='';
  const tabla=new DataTable(tablaNodo,{
    serverSide:true, processing:true, searching:false, responsive:false,
    pageLength:10,lengthMenu:[10,25,50,100],
    language:{emptyTable:'No hay barrios',processing:'Consultando...',zeroRecords:'Sin resultados',info:'Mostrando _START_ a _END_ de _TOTAL_',infoEmpty:'No hay registros',infoFiltered:'(de _MAX_ en total)',lengthMenu:'Mostrar _MENU_',paginate:{first:'Primera',last:'Última',next:'Siguiente',previous:'Anterior'}},
    ajax:{url, type:'GET', data:(d)=>{
      d.accion='listar';d.nombre=$('gbNombre').value.trim();d.localidad=$('gbLocalidad').value;d.estado=$('gbEstado').value;d.vista=$('gbVista').value;d.id_barrio=idElegido;
    },error:(xhr)=>mostrar('No se pudo obtener la lista (HTTP '+xhr.status+'). Revisá permisos y consola.')},
    columns:[
      {data:'id_barrio',render:(d,t)=>t==='display'?celda(d):d},
      {data:'nombre',render:(d,t)=>t==='display'?celda(d):d},
      {data:'localidad_nombre',render:(d,t)=>t==='display'?celda(d):d},
      {data:'id_origen_dbgral',render:(d,t)=>t==='display'?celda(d):d},
      {data:'codigo_dependencia_legacy',orderable:false,render:(d,t)=>t==='display'?celda(d):d},
      {data:'estado',render:(d,t)=>t==='display'?celda(d):d},
      {data:'tiene_geometria',orderable:false,render:(d,t)=>t==='display'?(Number(d)?'<span class="badge text-bg-success">Sí</span>':'<span class="badge text-bg-secondary">No</span>'):d},
      {data:null,orderable:false,render:(d,t)=>{
        if(t!=='display')return '';
        const v=Number(d.surc_validadas)||0, p=Number(d.surc_pendientes)||0;
        if(v)return '<span class="badge text-bg-success">Validada ('+v+')</span>'+(p?' <span class="badge text-bg-warning">+'+p+' pendiente(s)</span>':'');
        if(p)return '<span class="badge text-bg-warning">Pendiente ('+p+')</span>';
        return '<span class="badge text-bg-secondary">Sin equivalencia validada</span>';
      }},
      {data:'color_mapa',orderable:false,searchable:false,render:(d,t,row)=>{
        if(t!=='display')return d||'';
        if(row.estado!=='APROBADO'||Number(row.activo)!==1||row.id_gobernante)return '<span class="text-body-secondary">—</span>';
        const asignado=/^#[0-9A-Fa-f]{6}$/.test(String(d||''));
        const color=asignado?d.toUpperCase():'#28846A';
        // Nunca insertar HTML que proceda de la BD sin validar el hexadecimal.
        return '<span class="d-inline-flex gap-2 align-items-center" title="'+(asignado?'Color asignado':'Color predeterminado')+'">'
          +'<span style="display:inline-block;width:20px;height:20px;border-radius:5px;border:1px solid #8998A3;background-color:'+color+'"></span>'
          +'<small class="text-nowrap">'+(asignado?color:'Predeterminado')+'</small></span>';
      }},
      {data:'id_barrio',orderable:false,render:(d,t,row)=>{ if(t!=='display') return d; const id=Number(d); return '<div class="d-flex flex-wrap gap-1">' + '<button class="btn btn-sm btn-outline-success gb-detalle" data-id="'+id+'" type="button"><i class="bi bi-eye"></i> Ver</button>' + (puedeModificar ? '<a class="btn btn-sm btn-outline-primary" href="propuesta_barrio.php?id='+id+'"><i class="bi bi-pencil-square"></i> Modificar</a>' : '') + (puedeModificar && row.estado === 'APROBADO' && Number(row.activo) === 1 && !row.id_gobernante ? '<button type="button" class="btn btn-sm btn-outline-secondary gb-color" data-id="'+id+'"><i class="bi bi-palette"></i> Color</button>' : '') + '</div>'; }}
    ],
    order:[[1,'asc']],
    drawCallback:()=>mostrar('Los datos se consultan sin modificarlos. Las geometrías municipales sin vincular no figuran como aprobadas.')
  });
  $('gbBuscar').addEventListener('click',()=>tabla.ajax.reload());
  $('gbVista').addEventListener('change',()=>{ idElegido=''; $('gbNombreResultados').replaceChildren();tabla.ajax.reload(); });
  $('gbNombre').addEventListener('keydown',e=>{if(e.key==='Enter')tabla.ajax.reload();});
  $('gbLimpiar').addEventListener('click',()=>{
    $('gbNombre').value='';idElegido='';$('gbVista').value='principales';$('gbLocalidad').value='';$('gbLocalidadBusqueda').value='';$('gbEstado').value='';$('gbNombreResultados').replaceChildren();$('gbLocalidadResultados').replaceChildren();tabla.ajax.reload();
  });
  // Buscadores contextuales: muestran coincidencia y localidad antes de elegir.
  let timerB=null,tokenB=0;
  $('gbNombre').addEventListener('input',()=>{
    clearTimeout(timerB);idElegido='';const q=$('gbNombre').value.trim();const destino=$('gbNombreResultados');destino.replaceChildren();const token=++tokenB;
    if(q.length<2)return;
    timerB=setTimeout(async()=>{
      try{
        const r=await fetch(url+'?accion=sugerencias&q='+encodeURIComponent(q)+'&vista='+encodeURIComponent($('gbVista').value),{credentials:'same-origin'});
        const v=await r.json();if(!r.ok)throw Error(v.error||'HTTP '+r.status);if(token!==tokenB)return;
        (v.data||[]).forEach(f=>{
          const b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action text-start';
          b.textContent=f.nombre+' — '+(f.localidad_nombre||'Sin localidad')+' (ID barrio '+f.id_barrio+')'+(f.coincidencia_historica?' · Histórico: '+f.coincidencia_historica:'');
          b.addEventListener('click',()=>{
            $('gbNombre').value=f.nombre;idElegido=String(f.id_barrio);
            // Si la localidad figura en el filtro se selecciona; así evitamos homónimos.
            if(f.id_localidad && ![...$('gbLocalidad').options].some(o=>o.value===String(f.id_localidad))) {
              const o=new Option(f.localidad_nombre||'Localidad',String(f.id_localidad));$('gbLocalidad').add(o);
            }
            $('gbLocalidad').value=f.id_localidad||'';$('gbLocalidadBusqueda').value=f.localidad_nombre||'';
            destino.replaceChildren();++tokenB;tabla.ajax.reload();
          });destino.appendChild(b);
        });
      }catch(e){mostrar('Error buscando barrios: '+e.message);}
    },280);
  });
  let localidadesCache=[];
  $('gbLocalidadBusqueda').addEventListener('input',()=>{
    const q=$('gbLocalidadBusqueda').value.trim().toLocaleLowerCase('es');
    $('gbLocalidad').value='';const dest=$('gbLocalidadResultados');dest.replaceChildren();
    if(q.length<2)return;
    localidadesCache.filter(f=>f.nombre.toLocaleLowerCase('es').includes(q)).slice(0,25).forEach(f=>{
      const b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action text-start';
      b.textContent=f.nombre+' (ID '+f.id+')';
      b.addEventListener('click',()=>{$('gbLocalidad').value=String(f.id);$('gbLocalidadBusqueda').value=f.nombre;dest.replaceChildren();tabla.ajax.reload();});dest.appendChild(b);
    });
  });
  fetch(url+'?accion=localidades',{credentials:'same-origin'})
    .then(r=>{if(!r.ok)throw Error('HTTP '+r.status);return r.json();})
    .then(r=>{localidadesCache=(r.data||[]);for(const v of localidadesCache) {const opt=document.createElement('option');opt.value=v.id;opt.textContent=v.nombre+' (ID '+v.id+')';$('gbLocalidad').appendChild(opt);}})
    .catch(e=>mostrar('No se pudo cargar el filtro de localidades: '+e.message));
  let barrioColorId=null;
  let colorGuardado=null;
  const colorInput=$('gbColorInput'), colorHex=$('gbColorHex'), colorStatus=$('gbColorEstado');
  const botonGuardar=$('gbColorGuardar');
  const colorNormalizado=(v)=>String(v||'').toUpperCase();
  function refrescarGuardarColor(){
    if(!botonGuardar)return;
    botonGuardar.disabled=!barrioColorId||colorGuardado===null||colorNormalizado(colorInput.value)===colorGuardado;
  }
  colorInput?.addEventListener('input',()=>{
    colorHex.textContent=colorNormalizado(colorInput.value);
    refrescarGuardarColor();
  });
  function notificarColor(texto,tipo='success') {
    if(window.Swal?.fire){
      window.Swal.fire({toast:true,position:'top-end',icon:tipo,title:texto,
        showConfirmButton:false,timer:2600,timerProgressBar:true});
    }else{
      mostrar(texto);
    }
  }
  tablaNodo.addEventListener('click',async ev=>{
    const btn=ev.target.closest('button.gb-color');if(!btn)return;
    const id=Number(btn.dataset.id),modalNodo=$('gbColorModal');
    if(!window.bootstrap?.Modal||!modalNodo)return;
    barrioColorId=id;colorGuardado=null;
    botonGuardar.disabled=true;
    colorStatus.textContent='Consultando…';
    const modal=bootstrap.Modal.getOrCreateInstance(modalNodo);modal.show();
    try{
      const resp=await fetch('/territorio/ajax/color_barrio.php?accion=consultar&id='+id,
        {credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
      const data=await resp.json();if(!resp.ok)throw Error(data.error||'HTTP '+resp.status);
      if(barrioColorId!==id)return;
      $('gbColorNombre').textContent=data.nombre+' — ID '+id;
      colorGuardado=/^#[0-9a-fA-F]{6}$/.test(String(data.color_mapa||''))?
        colorNormalizado(data.color_mapa):'';
      colorInput.value=colorGuardado||'#28846A';
      colorHex.textContent=colorNormalizado(colorInput.value);
      colorStatus.textContent=colorGuardado?'Color actual cargado. Elegí otro para modificarlo.':'Sin color personalizado. Podés asignar uno.';
      refrescarGuardarColor();
    }catch(err){barrioColorId=null;botonGuardar.disabled=true;colorStatus.textContent='Error: '+err.message;}
  });
  $('gbColorModal')?.addEventListener('hidden.bs.modal',()=>{
    barrioColorId=null;colorGuardado=null;
    botonGuardar.disabled=true;
    colorStatus.textContent='';
  });
  botonGuardar?.addEventListener('click',async()=>{
    if(!barrioColorId||botonGuardar.disabled)return;
    const id=barrioColorId, nuevo=colorNormalizado(colorInput.value);
    botonGuardar.disabled=true;
    colorStatus.textContent='Guardando y auditando…';
    try{
      const resp=await fetch('/territorio/ajax/color_barrio.php?accion=guardar',{
        method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',
          'Accept':'application/json','X-CSRF-Token':$('gbPermisos').dataset.colorCsrf},
        body:JSON.stringify({id_barrio:id,color_mapa:nuevo})
      });
      const data=await resp.json().catch(()=>({error:'Respuesta inválida del servidor'}));
      if(!resp.ok||!data.ok)throw Error(data.error||'HTTP '+resp.status);
      colorGuardado=nuevo;
      // Actualizar la grilla inmediatamente y consultar después al servidor sin perder la página.
      tabla.rows().every(function(){
        const fila=this.data();
        if(Number(fila?.id_barrio)===id){
          this.data({...fila,color_mapa:data.color_mapa||nuevo});
        }
      });
      tabla.draw(false);
      bootstrap.Modal.getOrCreateInstance($('gbColorModal')).hide();
      notificarColor(data.modificado?'Color guardado y auditado correctamente.':'El color ya estaba actualizado.');
      tabla.ajax.reload(null,false);
    }catch(err){
      colorStatus.textContent='No se guardó: '+err.message;
      refrescarGuardarColor();
    }
  });
  tablaNodo.addEventListener('click', async event=>{
    const boton=event.target.closest('button.gb-detalle');if(!boton)return;
    const id=Number(boton.dataset.id);
    const modalNodo=$('gbModal'), cuerpo=$('gbModalContenido');
    if(!window.bootstrap?.Modal){mostrar('Falta bootstrap.bundle.min.js.');return;}
    const modal=bootstrap.Modal.getOrCreateInstance(modalNodo);
    cuerpo.textContent='Cargando detalle…';modal.show();
    try{
      const resp=await fetch(url+'?accion=detalle&id='+encodeURIComponent(id),{credentials:'same-origin'});
      const b=await resp.json();if(!resp.ok)throw Error(b.error||'HTTP '+resp.status);
      cuerpo.replaceChildren();
      const dl=document.createElement('dl');dl.className='row gb-detalle-dl';
      const info=[['ID',b.id_barrio],['Nombre',b.nombre],['Localidad',b.localidad_nombre],['ID localidad',b.id_localidad],['Estado',b.estado],['Activo',Number(b.activo)?'Sí':'No'],['ID DBGRAL',b.id_origen_dbgral],['Código localidad anterior',b.codigo_localidad_legacy],['Código dependencia anterior',b.codigo_dependencia_legacy],['Clasificación',b.clasificacion],['Descripción',b.descripcion]];
      info.forEach(([campo,valor])=>{const dt=document.createElement('dt');dt.className='col-sm-5';dt.textContent=campo;const dd=document.createElement('dd');dd.className='col-sm-7';dd.textContent=texto(valor);dl.append(dt,dd);});
      cuerpo.appendChild(dl);
      function listado(titulo,filas,toText) {
        const h=document.createElement('h3');h.className='fs-6 border-top pt-3 mt-3';h.textContent=titulo;cuerpo.appendChild(h);
        const ul=document.createElement('ul');ul.className='list-group list-group-flush';
        if(!filas?.length){const li=document.createElement('li');li.className='list-group-item text-body-secondary';li.textContent='Sin registros';ul.appendChild(li);}
        else filas.forEach(f=>{const li=document.createElement('li');li.className='list-group-item';li.textContent=toText(f);ul.appendChild(li);});
        cuerpo.appendChild(ul);
      }
      listado('Barrios históricos asociados',b.historicos_asociados,f=>
        f.id_barrio+' — '+f.nombre+' · '+f.tipo_relacion+' · DBGRAL '+texto(f.id_origen_dbgral));
      listado('Gobernante asociado (si este registro es histórico)',b.gobernantes,f=>
        f.id_barrio+' — '+f.nombre);
      listado('Jurisdicciones registradas',b.jurisdicciones,f=>(f.nombre_dependencia||'Organismo')+
        ' · ID '+f.gen_organismos_id+' · '+(Number(f.es_actual)?'vigente confirmada':'vigencia territorial sin confirmar'));

      listado('Equivalencias SURC (solo VALIDADA habilita cruces con denuncias)',b.equivalencias_surc,f=>
        'Código SURC '+f.barrio_codigo_surc+' · '+(f.nombre_surc||'Sin nombre')+' · Localidad SURC '+(f.localidad_surc||'?')+' · '+f.estado);
      listado('Versiones geométricas',b.geometrias,f=>'Versión '+f.version+' · '+f.estado+' · '+f.origen);
    }catch(e){cuerpo.textContent='No se pudo cargar el detalle: '+e.message;}
  });
 });
})();
