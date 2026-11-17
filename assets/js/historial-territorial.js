/* Historial geométrico de solo lectura - Gestión Barrios */
(() => {
 'use strict';
 const endpoint='/territorio/ajax/historial_territorial.php';
 let instancia=null;
 const crear=(tag,cls,txt)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(txt!==undefined)e.textContent=String(txt);return e;};
 const fecha=x=>x?String(x):'—';
 const opcion=(g)=>'Versión '+g.version+' · '+g.estado+' · '+g.origen;
 const cargar=async(url)=>{const r=await fetch(url,{credentials:'same-origin',cache:'no-store'});const j=await r.json();if(!r.ok)throw Error(j.error||'HTTP '+r.status);if(j._auditoria?.registrada===false){console.warn('[Territorio] Consulta de historial sin auditoría confirmada. Revisar log PHP.');document.querySelector('#terrHistSeccion .terr-hist-aud-warning')?.classList.remove('d-none');}return j;};
 const url=(params)=>endpoint+'?'+new URLSearchParams(params);
 async function montar(cuerpo,id){
   if(instancia?.map){instancia.map.remove();instancia=null;}
   const seccion=crear('section','border-top pt-3 mt-3');seccion.id='terrHistSeccion';
   const titulo=crear('h3','fs-6','Historial territorial y comparación de versiones');seccion.append(titulo);
   const ayuda=crear('p','small text-body-secondary','Consulta de solo lectura. Las geometrías históricas no se modifican.');seccion.append(ayuda);
   const estado=crear('div','small text-body-secondary mb-2','Cargando versiones...');estado.setAttribute('role','status');seccion.append(estado);
   cuerpo.append(seccion);
   const advertenciaAud=crear('div','alert alert-warning py-2 small d-none terr-hist-aud-warning','La consulta se mostró, pero no se pudo registrar su auditoría. Informá al administrador para revisar el log del servidor.');
   seccion.append(advertenciaAud);
   try {
     const data=await cargar(url({accion:'listar',id_barrio:id}));
     if(!seccion.isConnected)return;
     estado.textContent='';
     const geos=data.geometrias||[];
     if(!geos.length){estado.textContent='Este barrio no tiene geometrías versionadas.';return;}
     const tb=crear('div','table-responsive mb-3');const t=crear('table','table table-sm table-striped align-middle');
     const head=crear('thead');const tr=crear('tr');['Versión','Origen','Estado','Creada','Aprobada','Usuario aprobador'].forEach(x=>tr.append(crear('th','',x)));head.append(tr);t.append(head);
     const body=crear('tbody');geos.forEach(g=>{const r=crear('tr');[g.version,g.origen,g.estado,fecha(g.fecha_creacion),fecha(g.fecha_aprobacion),g.usuario_aprobacion??'—'].forEach(x=>r.append(crear('td','',x)));body.append(r);});t.append(body);tb.append(t);seccion.append(tb);
     const actual=geos.find(g=>g.estado==='APROBADO')||geos[0];
     const previo=geos.find(g=>g.estado==='HISTORICO')||geos.find(g=>g.id_geometria!==actual.id_geometria)||actual;
     const controles=crear('div','row g-2 mb-2');
     function selector(label,initial){const col=crear('div','col-md-6');const l=crear('label','form-label small',label);const s=crear('select','form-select form-select-sm');geos.forEach(g=>{const o=new Option(opcion(g),String(g.id_geometria));s.add(o);});s.value=String(initial.id_geometria);col.append(l,s);controles.append(col);return s;}
     const selA=selector('Polígono A (azul)',actual),selB=selector('Polígono B (naranja)',previo);seccion.append(controles);
     const leyenda=crear('p','small mb-2','Azul: polígono A · Naranja: polígono B. Podés activar ambas versiones para compararlas.');seccion.append(leyenda);
     const mapNode=crear('div','terr-hist-mapa border rounded');mapNode.style.height='360px';mapNode.style.width='100%';seccion.append(mapNode);
     const resumen=crear('p','small text-body-secondary mt-2','');resumen.setAttribute('role','status');seccion.append(resumen);
     if(typeof L==='undefined'){resumen.textContent='Leaflet no está cargado; se muestra únicamente el historial tabular.';return;}
     const map=L.map(mapNode,{scrollWheelZoom:false});instancia={map,container:seccion};
     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors',maxZoom:19}).addTo(map);
     let layers=[];let secuencia=0;
     const dibujar=async()=>{
       const token=++secuencia;resumen.textContent='Cargando comparación cartográfica...';
       try{
         const ids=[Number(selA.value),Number(selB.value)];
         const v=await Promise.all([...new Set(ids)].map(i=>cargar(url({accion:'geometria',id_barrio:id,id_geometria:i}))));
         if(token!==secuencia||!seccion.isConnected)return;
         const index=new Map(v.map(x=>[Number(x.id_geometria),x]));
         layers.forEach(x=>map.removeLayer(x));layers=[];const bounds=[];
         [[ids[0],'#2463d3',0.14],[ids[1],'#ef8909',0.12]].forEach(([geomId,color,opacity])=>{
           const g=index.get(geomId);if(!g)return;
           const layer=L.geoJSON({type:'Feature',geometry:g.geometry,properties:{version:g.version}},{style:{color,weight:3,fillColor:color,fillOpacity:opacity}}).addTo(map);
           layers.push(layer);if(layer.getBounds().isValid())bounds.push(layer.getBounds());
         });
         if(bounds.length){const all=bounds[0];bounds.slice(1).forEach(x=>all.extend(x));map.fitBounds(all.pad(0.12));}
         resumen.textContent='A: versión '+index.get(ids[0])?.version+' · B: versión '+index.get(ids[1])?.version+' · SRID 4326. Las capas son independientes y no editables.';
       }catch(e){resumen.textContent='No se pudo cargar la comparación: '+e.message;}
     };
     selA.addEventListener('change',dibujar);selB.addEventListener('change',dibujar);
     await dibujar();setTimeout(()=>{if(seccion.isConnected)map.invalidateSize();},120);
     const resol=data.resoluciones||[];
     const hr=crear('h4','fs-6 border-top pt-3 mt-3','Resoluciones administrativas del barrio');seccion.append(hr);
     if(!resol.length){seccion.append(crear('p','small text-body-secondary','Todavía no hay resoluciones registradas en el módulo de solicitudes.'));}
     else {const lista=crear('div','list-group');resol.forEach(r=>{
       const li=crear('div','list-group-item');li.append(crear('div','fw-semibold','Solicitud N.º '+r.id_solicitud+' · '+r.decision+' · '+fecha(r.fecha_resolucion)));
       li.append(crear('div','small','Revisor: '+r.usuario_resolutor+' · Versión base: '+(r.version_base??'—')+' · Cambio geométrico: '+(Number(r.hubo_cambio_geometria)?'Sí':'No')));
       li.append(crear('div','small','Fundamento: '+(r.motivo||'—')));
       li.append(crear('div','small text-body-secondary','Correlación: '+(r.correlacion_id||'—')));lista.append(li);
     });seccion.append(lista);}
   } catch(e){estado.className='alert alert-warning py-2';estado.textContent='No se pudo recuperar el historial: '+e.message;}
 }
 document.addEventListener('hidden.bs.modal',event=>{if(event.target?.id==='gbModal'&&instancia?.map){instancia.map.remove();instancia=null;}});
 window.TERR_HISTORIAL={montar};
})();
