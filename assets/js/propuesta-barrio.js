(() => {
 'use strict';
 document.addEventListener('DOMContentLoaded', () => {
  const form=document.getElementById('pbForm'); if(!form) return;
  const el=id=>document.getElementById(id);
  const base=window.APP_BASE_URL||'/territorio';
  const endpoint=base.replace(/\/$/,'')+'/ajax/propuesta_barrio.php';
  const id=Number(form.dataset.id)||0;
  const status=txt=>el('pbEstado').textContent=txt;
  const botonesModo={dibujar:'pbDibujar',editar:'pbEditar'};
  let modo='consulta';
  function setModo(nuevo){
    modo=nuevo;
    Object.entries(botonesModo).forEach(([nombre,idBoton])=>{
      const boton=el(idBoton);if(!boton)return;
      boton.classList.toggle('geo-herramienta-activa',nombre===nuevo);
      boton.setAttribute('aria-pressed',nombre===nuevo?'true':'false');
    });
    if(el('pbModoActual'))el('pbModoActual').textContent={
      consulta:'Modo: consulta · Seleccioná una herramienta',
      dibujar:'Modo: dibujando · Tocá el mapa para agregar puntos',
      editar:'Modo: editando · Arrastrá vértices, agregá puntos intermedios o seleccioná uno para eliminarlo'
    }[nuevo]||'Modo: consulta';
  }
  const geoStatus=txt=>el('pbGeoEstado').textContent=txt;
  if(!window.L) {status('No se encuentra Leaflet. Comprobar CSS y JS de Leaflet en las plantillas.');return;}
  const map=L.map('pbMapa').setView([-24.7821,-65.4232],13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
  let feature=null, polygonLayer=null, drawing=false, editing=false, vertices=[];
  let selectedVertex=-1;
  const draftLine=L.polyline([],{color:'#1b6c59',dashArray:'5,5'}).addTo(map);
  const pointLayers=L.layerGroup().addTo(map);
  const editHandles=L.layerGroup().addTo(map);
  const labelEdit=()=>{
    el('pbEliminarVertice').disabled=!editing||selectedVertex<0;
    el('pbFinEdicion').disabled=!editing;
    el('pbEditar').disabled=editing || !feature || !esPoligonoSimple(feature);
  };
  function normalizarGeometry(g) {
    if(!g || !['Polygon','MultiPolygon'].includes(g.type)) throw Error('Solo se admiten Polygon o MultiPolygon.');
    const mp=g.type==='Polygon'?[g.coordinates]:g.coordinates;
    if(!Array.isArray(mp)||!mp.length||mp.length>100) throw Error('Geometría no válida o demasiado compleja.');
    let puntos=0;
    mp.forEach(poly=>{if(!Array.isArray(poly)||!poly.length)throw Error('Polígono sin anillos.');poly.forEach(r=>{
      if(!Array.isArray(r)||r.length<4)throw Error('Anillo con menos de 3 vértices.');
      puntos+=r.length;if(puntos>30000)throw Error('Geometría demasiado compleja.');
      r.forEach(p=>{if(!Array.isArray(p)||p.length<2||!Number.isFinite(p[0])||!Number.isFinite(p[1])||Math.abs(p[0])>180||Math.abs(p[1])>90)throw Error('Coordenadas inválidas.');});
      const a=r[0],b=r[r.length-1];if(a[0]!==b[0]||a[1]!==b[1])throw Error('El anillo no está cerrado.');
    });});
    return {type:'MultiPolygon',coordinates:mp};
  }
  function esPoligonoSimple(g){return g?.type==='MultiPolygon' && g.coordinates.length===1 && g.coordinates[0].length===1;}
  function getRing(){return feature.coordinates[0][0].slice(0,-1).map(p=>L.latLng(p[1],p[0]));}
  function saveRing(points){
    if(points.length<3)throw Error('Un polígono necesita al menos 3 vértices.');
    const r=points.map(p=>[p.lng,p.lat]); r.push([...r[0]]);
    feature=normalizarGeometry({type:'Polygon',coordinates:[r]});
    renderPolygon(false);
  }
  function renderPolygon(fit=true){
    if(polygonLayer)polygonLayer.remove();
    if(!feature)return;
    polygonLayer=L.geoJSON({type:'Feature',properties:{},geometry:feature},{style:{color:'#26765c',weight:3,fillOpacity:.15}}).addTo(map);
    if(fit&&polygonLayer.getBounds().isValid())map.fitBounds(polygonLayer.getBounds(),{padding:[18,18]});
    labelEdit();
  }
  function setFeature(g){
    feature=normalizarGeometry(g);drawing=false;editing=false;vertices=[];selectedVertex=-1;setModo('consulta');
    updateDraft(); editHandles.clearLayers();renderPolygon();
    geoStatus('Geometría propuesta lista. Para ajustar vértices pulsá «Editar vértices».');
  }
  function updateDraft(){
    draftLine.setLatLngs(vertices);pointLayers.clearLayers();
    vertices.forEach((p,i)=>{
      const m=L.circleMarker(p,{radius:6,color:'#0f5132',fillColor:'#fff',fillOpacity:1}).addTo(pointLayers);
      m.on('click',e=>{L.DomEvent.stopPropagation(e);if(drawing){vertices.splice(i,1);updateDraft();}});
    });
    if(drawing)geoStatus(vertices.length+' vértices de dibujo. Tocá un vértice para quitarlo; cerrá cuando tengas al menos tres.');
  }
  function renderHandles(){
    editHandles.clearLayers(); if(!editing)return;
    const points=getRing();
    points.forEach((p,i)=>{
      const m=L.marker(p,{draggable:true,autoPan:true,icon:L.divIcon({className:'geo-vertex'+(selectedVertex===i?' is-selected':''),iconSize:[18,18],iconAnchor:[9,9]})}).addTo(editHandles);
      m.on('click',e=>{L.DomEvent.stopPropagation(e);selectedVertex=i;renderHandles();geoStatus('Vértice '+(i+1)+' seleccionado. Podés arrastrarlo o eliminarlo.');});
      m.on('dragend',()=>{const actual=getRing();actual[i]=m.getLatLng();saveRing(actual);renderHandles();geoStatus('Vértice desplazado. La propuesta todavía no se guardó.');});
    });
    points.forEach((p,i)=>{
      const next=points[(i+1)%points.length];
      const medio=L.latLng((p.lat+next.lat)/2,(p.lng+next.lng)/2);
      const mark=L.marker(medio,{icon:L.divIcon({className:'geo-midpoint',iconSize:[14,14],iconAnchor:[7,7]})}).addTo(editHandles);
      mark.bindTooltip('Agregar vértice aquí',{direction:'top'});
      mark.on('click',e=>{L.DomEvent.stopPropagation(e);const actual=getRing();actual.splice(i+1,0,medio);selectedVertex=i+1;saveRing(actual);renderHandles();geoStatus('Vértice agregado. Arrastralo hasta la posición deseada.');});
    });
    labelEdit();
  }
  map.on('click',e=>{if(!drawing||editing)return;vertices.push(e.latlng);updateDraft();});
  el('pbDibujar').addEventListener('click',()=>{
    if(feature && !confirm('El nuevo dibujo reemplazará la geometría propuesta actual. ¿Continuar?'))return;
    if(polygonLayer)polygonLayer.remove();polygonLayer=null;feature=null;editing=false;editHandles.clearLayers();
    drawing=true;vertices=[];selectedVertex=-1;setModo('dibujar');updateDraft();labelEdit();
  });
  el('pbDeshacer').addEventListener('click',()=>{if(drawing&&vertices.length){vertices.pop();updateDraft();}else geoStatus('Deshacer vértice solo funciona durante el dibujo. Para un polígono cerrado usá «Editar vértices».');});
  el('pbTerminar').addEventListener('click',()=>{
    if(!drawing)return geoStatus('Primero presioná Dibujar.');
    if(vertices.length<3)return geoStatus('Se requieren al menos tres vértices.');
    try{const points=vertices.slice();drawing=false;saveRing(points);vertices=[];updateDraft();setModo('consulta');geoStatus('Polígono cerrado. Pulsá Editar vértices para corregirlo.');}
    catch(e){geoStatus(e.message);}
  });
  el('pbEditar').addEventListener('click',()=>{
    if(!feature)return geoStatus('Primero dibujá o importá una geometría.');
    if(!esPoligonoSimple(feature))return geoStatus('Este archivo tiene varios polígonos o huecos: la edición de vértices de geometrías complejas requerirá Geoman. Podés reemplazarlo dibujando uno nuevo.');
    drawing=false;editing=true;selectedVertex=-1;setModo('editar');renderHandles();geoStatus('Arrastrá vértices; tocá los puntos intermedios para agregar; seleccioná un vértice para eliminarlo.');
  });
  el('pbEliminarVertice').addEventListener('click',()=>{
    if(!editing||selectedVertex<0)return;
    const points=getRing();if(points.length<=3)return geoStatus('No se puede reducir un polígono a menos de tres vértices.');
    points.splice(selectedVertex,1);selectedVertex=-1;saveRing(points);renderHandles();geoStatus('Vértice eliminado.');
  });
  el('pbFinEdicion').addEventListener('click',()=>{editing=false;selectedVertex=-1;setModo('consulta');editHandles.clearLayers();labelEdit();geoStatus('Edición finalizada. La geometría permanece como propuesta no oficial.');});
  el('pbBorrar').addEventListener('click',()=>{feature=null;drawing=false;editing=false;selectedVertex=-1;vertices=[];setModo('consulta');updateDraft();editHandles.clearLayers();if(polygonLayer)polygonLayer.remove();polygonLayer=null;labelEdit();geoStatus('Sin geometría propuesta.');});
  el('pbArchivo').addEventListener('change',async e=>{
    const file=e.target.files?.[0];if(!file)return;
    if(file.size>3*1024*1024){geoStatus('El archivo supera 3 MB.');return;}
    try{const json=JSON.parse(await file.text());const g=json.type==='FeatureCollection'?(json.features.length===1?json.features[0].geometry:null):json.type==='Feature'?json.geometry:json;if(!g)throw Error('El archivo debe contener una sola entidad.');setFeature(g);}
    catch(err){geoStatus('Error de GeoJSON: '+err.message);}e.target.value='';
  });
  labelEdit();setModo('consulta');
  // Clasificación se obtiene del catálogo controlado; no se permite escritura libre.
  let clasificacionesListas=fetch(endpoint+'?accion=clasificaciones',{credentials:'same-origin'})
    .then(async r=>{const d=await r.json();if(!r.ok)throw Error(d.error||'HTTP '+r.status);return d.data||[];})
    .then(items=>{const select=el('pbClasificacion');select.replaceChildren(new Option('Seleccionar clasificación',''));
      for(const item of items)select.add(new Option(item.nombre,item.codigo));return items;})
    .catch(err=>{status('No se pudo cargar el catálogo de clasificaciones: '+err.message);return [];});
  let searchTimer=null, localidadReq=0;
  const localResults=el('pbLocalidadResultados');
  function setLocalidad(f){
    const select=el('pbLocalidad');select.replaceChildren(new Option('Seleccionar localidad',''));
    const extra=` · Municipio ${f.municipio_id||'s/d'} · Departamento ${f.departamento_id||'s/d'}`;
    select.add(new Option(f.nombre+extra,String(f.id)));select.value=String(f.id);
    el('pbLocalidadBusqueda').value=f.nombre;localResults.replaceChildren();
  }
  el('pbLocalidadBusqueda').addEventListener('input',()=>{
    clearTimeout(searchTimer);const s=el('pbLocalidadBusqueda').value.trim();
    el('pbLocalidad').replaceChildren(new Option('Seleccionar localidad',''));
    localResults.replaceChildren();const req=++localidadReq;
    if(s.length<2)return;
    searchTimer=setTimeout(async()=>{
      try{
        const r=await fetch(endpoint+'?accion=localidades&q='+encodeURIComponent(s),{credentials:'same-origin'});
        const v=await r.json();if(!r.ok)throw Error(v.error||'Error');if(req!==localidadReq)return;
        (v.data||[]).forEach(f=>{
          const boton=document.createElement('button');boton.type='button';boton.className='list-group-item list-group-item-action text-start';
          boton.textContent=`${f.nombre} · Municipio ${f.municipio_id||'s/d'} · Departamento ${f.departamento_id||'s/d'}`;
          boton.addEventListener('click',()=>{++localidadReq;setLocalidad(f);});localResults.appendChild(boton);
        });
        if(!(v.data||[]).length)status('No se encontraron localidades para: '+s);
      }catch(e){status('Error buscando localidades: '+e.message);}
    },260);
  });
  // Advertir cuando el nombre existe en otras localidades; NO asignar barrio automáticamente.
  const coincidencias=el('pbCoincidencias');let barrioReq=0, barrioTimer=null;
  el('pbNombre').addEventListener('input',()=>{
    clearTimeout(barrioTimer);coincidencias.replaceChildren();const texto=el('pbNombre').value.trim();const req=++barrioReq;
    if(texto.length<2)return;
    barrioTimer=setTimeout(async()=>{
      try{
        const res=await fetch(endpoint+'?accion=coincidencias&q='+encodeURIComponent(texto),{credentials:'same-origin'});
        const data=await res.json();if(!res.ok)throw Error(data.error||'Error');if(req!==barrioReq)return;
        if(!(data.data||[]).length)return;
        const aviso=document.createElement('div');aviso.className='list-group-item small fw-semibold text-warning-emphasis bg-warning-subtle';aviso.textContent='Barrios existentes con nombre parecido (verificá la localidad antes de crear uno nuevo):';coincidencias.appendChild(aviso);
        (data.data||[]).slice(0,8).forEach(f=>{
          const b=document.createElement('div');b.className='list-group-item small';b.textContent=`${f.nombre} — ${f.localidad_nombre||'Localidad no identificada'} (ID barrio ${f.id_barrio})`;coincidencias.appendChild(b);
        });
      }catch(e){status('No se pudo comprobar coincidencias: '+e.message);}
    },300);
  });
  async function cargarEdicion(){if(!id)return;try{await clasificacionesListas;const r=await fetch(endpoint+'?accion=detalle&id='+id,{credentials:'same-origin'});const d=await r.json();if(!r.ok)throw Error(d.error||'Error');el('pbNombre').value=d.nombre||'';el('pbClasificacion').value=d.clasificacion||'';if(d.clasificacion&&!el('pbClasificacion').value)status('La clasificación histórica no figura en el catálogo vigente: seleccioná una categoría antes de enviar.');el('pbDescripcion').value=d.descripcion||'';if(d.id_localidad){const select=el('pbLocalidad');select.add(new Option((d.localidad_nombre||'Localidad')+' (ID '+d.id_localidad+')',String(d.id_localidad)));select.value=String(d.id_localidad);el('pbLocalidadBusqueda').value=d.localidad_nombre||'';}
   if(d.geometry){setFeature(d.geometry);geoStatus('Geometría aprobada copiada a la propuesta. Editar o reemplazar si corresponde.');}
  }catch(e){status('No se pudo cargar el barrio: '+e.message);}}
  form.addEventListener('submit',async e=>{e.preventDefault();const nombre=el('pbNombre').value.trim(),motivo=el('pbMotivo').value.trim(),loc=el('pbLocalidad').value;if(!nombre||!loc||!motivo){status('Completar nombre, localidad y motivo.');return;}const btn=el('pbGuardar');btn.disabled=true;status('Registrando solicitud…');try{const res=await fetch(endpoint+'?accion=guardar',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':form.querySelector('[name="csrf"]').value},body:JSON.stringify({id_barrio:id||null,nombre,id_localidad:loc,clasificacion:el('pbClasificacion').value.trim(),descripcion:el('pbDescripcion').value.trim(),motivo,geometry:feature})});const out=await res.json();if(!res.ok)throw Error(out.error||'No se pudo registrar.');status('Solicitud PENDIENTE registrada con ID '+out.id_solicitud+'. No se modificó el barrio oficial.');btn.textContent='Solicitud enviada';}catch(err){status('No se registró: '+err.message);btn.disabled=false;}});
  cargarEdicion();setTimeout(()=>map.invalidateSize(),250);
 });
})();
