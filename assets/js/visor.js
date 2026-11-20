(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const el = id => document.getElementById(id);
    const nodo = el('mapaTerritorio');
    if (!nodo) return;
    const base = String(window.APP_BASE_URL || '/territorio').replace(/\/$/, '');
    const estado = el('visorEstado'), buscador = el('visorBuscar'), resultados = el('visorResultados');
    const identificacion = el('visorIdentificacion');
    const panelResultado = el('visorResultadoPanel');
    const latInput = el('visorLat'), lonInput = el('visorLon');
    const botonConsulta = el('visorConsultar');
    const informar = t => { if (estado) estado.textContent = t; };
    if (!window.L) { informar('Leaflet no se encuentra cargado.'); return; }
    const map = L.map(nodo, {preferCanvas:true}).setView([-24.7821,-65.4232], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution:'&copy; OpenStreetMap contributors', maxZoom:19
    }).addTo(map);
    // La jerarquía visual no determina qué aparece al hacer clic: se consultan TODAS las capas activas.
    map.createPane('visor18-dep'); map.getPane('visor18-dep').style.zIndex = 400;
    map.createPane('visor18-barrio'); map.getPane('visor18-barrio').style.zIndex = 410;
    const capas = {
      dep: L.geoJSON(null,{pane:'visor18-dep',style:f=>({color:colorSeguro(f.properties?.color_mapa,'#245FC0'),weight:3.6,opacity:1,fillColor:colorSeguro(f.properties?.color_mapa,'#245FC0'),fillOpacity:.13}),onEachFeature:enlazar}),
      barrio: L.geoJSON(null,{pane:'visor18-barrio',style:f=>({color:colorSeguro(f.properties?.color_mapa,'#28846A'),weight:3,opacity:1,fillColor:colorSeguro(f.properties?.color_mapa,'#28846A'),fillOpacity:.14}),onEachFeature:enlazar})
    };
    ['dep','barrio'].forEach(k=>capas[k].addTo(map));
    const checks={barrio:el('visorCapaBarrios'),dep:el('visorCapaDependencias')};
    const indices={barrio:[],dep:[]};
    let marcador=null, timerBusqueda=0, ultimoTexto='';
    function colorSeguro(c,def) { return /^#[\da-fA-F]{6}$/.test(c||'') ? c : def; }
    function normalizar(s) { return String(s??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').trim(); }
    function categoria(k) {return k==='barrio'?'Barrio aprobado':'Jurisdicción policial aprobada';}
    function prop(f) {return f?.properties||{};}
    function idObjeto(k,p) {return k==='barrio'?p.id_barrio:p.id_jurisdiccion;}
    function enlazar(feature,layer) {
      // Capturar el punto en el que se pulsó y consultar todas las capas visibles, no sólo el objeto superior.
      layer.on('click',e=>{ if(e.originalEvent) L.DomEvent.stopPropagation(e.originalEvent); seleccionarPunto(e.latlng); });
    }
    function vistaInicial() {
      const geos=[...indices.barrio,...indices.dep];
      if(!geos.length) return;
      const b=L.latLngBounds([]);
      geos.forEach(i=>b.extend(i.layer.getBounds()));
      // En cargas grandes no hacer zoom automático a toda la provincia salvo que el usuario lo solicite.
      if(b.isValid() && geos.length<=4) map.fitBounds(b,{maxZoom:13,padding:[25,25]});
    }
    function activar(k,activo) {if(activo&&!map.hasLayer(capas[k]))map.addLayer(capas[k]);if(!activo&&map.hasLayer(capas[k]))map.removeLayer(capas[k]);}
    Object.entries(checks).forEach(([k,ch])=>ch?.addEventListener('change',()=>{
      activar(k,ch.checked);if(marcador) seleccionarPunto(marcador.getLatLng(),false);
    }));
    function esVisible(k) {return map.hasLayer(capas[k]);}
    // GeoJSON coordinates are lon,lat; supports Polygon, MultiPolygon and holes.
    const EPS=1e-10;
    function puntoSegmento(x,y,a,b) {
      const dx=b[0]-a[0], dy=b[1]-a[1];
      const cr=(x-a[0])*dy-(y-a[1])*dx;
      return Math.abs(cr)<=EPS && x>=Math.min(a[0],b[0])-EPS && x<=Math.max(a[0],b[0])+EPS && y>=Math.min(a[1],b[1])-EPS && y<=Math.max(a[1],b[1])+EPS;
    }
    function enAnillo(x,y,ring) {
      let dentro=false;
      for(let i=0,j=ring.length-1;i<ring.length;j=i++) {
        const a=ring[i],b=ring[j];
        if(puntoSegmento(x,y,a,b)) return 2;
        if(((a[1]>y)!==(b[1]>y)) && x< (b[0]-a[0])*(y-a[1])/(b[1]-a[1])+a[0]) dentro=!dentro;
      }
      return dentro?1:0;
    }
    function enPoligono(x,y,rings) {
      if(!rings?.length) return false;
      const ext=enAnillo(x,y,rings[0]);
      if(ext===0)return false;
      for(const hole of rings.slice(1)) {const z=enAnillo(x,y,hole);if(z===1)return false;}
      return true;
    }
    function cubre(f,latlng) {
      const g=f.geometry;
      if(!g) return false;
      const x=latlng.lng,y=latlng.lat;
      if(g.type==='Polygon')return enPoligono(x,y,g.coordinates);
      if(g.type==='MultiPolygon')return g.coordinates.some(poly=>enPoligono(x,y,poly));
      return false;
    }
    function tarjeta(item) {
      const {k,feature}=item, p=prop(feature);
      const out=document.createElement('article');out.className='visor18-result';
      const cat=document.createElement('div');cat.className='visor18-kind visor18-kind-'+k;cat.textContent=categoria(k);out.appendChild(cat);
      const nombre=document.createElement('h6');nombre.textContent=p.nombre||'Sin denominación';out.appendChild(nombre);
      const detalle=document.createElement('p');detalle.className='text-body-secondary';
      detalle.textContent=(k==='barrio'?`ID barrio: ${p.id_barrio??'—'} · Versión ${p.version??'—'}`:`Territorio ID ${p.id_jurisdiccion??'—'} · RRHH ${p.id_rrhh??'—'} · Versión ${p.version??'—'}`);
      out.appendChild(detalle);
      const boton=document.createElement('button');boton.type='button';boton.className='btn btn-sm btn-outline-secondary';boton.textContent='Centrar polígono';boton.addEventListener('click',()=>focalizar(item));out.appendChild(boton);
      return out;
    }
    function mostrarPanel(items,ll) {
      if(!identificacion)return;
      identificacion.replaceChildren();
      panelResultado?.classList.remove('d-none');
      const ubic=document.createElement('p');ubic.className='small text-body-secondary mb-2';
      ubic.textContent=ll?`${ll.lat.toFixed(6)}, ${ll.lng.toFixed(6)} · ${items.length} coincidencia(s) aprobada(s)`:`${items.length} elemento(s) encontrado(s)`;
      identificacion.appendChild(ubic);
      if(!items.length){const p=document.createElement('p');p.className='visor18-empty';p.textContent='No se identifican geometrías aprobadas en las capas visibles para este punto.';identificacion.appendChild(p);}
      items.forEach(i=>identificacion.appendChild(tarjeta(i)));
    }
    function seleccionarPunto(ll,marcar=true) {
      if(marcar){if(marcador)marcador.remove();marcador=L.circleMarker(ll,{radius:5,color:'#173f38',fillColor:'#fff',fillOpacity:1,weight:2}).addTo(map);}
      if(latInput)latInput.value=ll.lat.toFixed(7);if(lonInput)lonInput.value=ll.lng.toFixed(7);
      const encontrados=['barrio','dep'].filter(esVisible).flatMap(k=>indices[k].filter(x=>x.layer.getBounds().contains(ll)&&cubre(x.feature,ll)));
      mostrarPanel(encontrados,ll);informar(`${encontrados.length} coincidencias territoriales aprobadas en el punto seleccionado.`);
      registrar('CONSULTA',{origen:'visor',accion:'punto',lat:ll.lat,lon:ll.lng,coincidencias:encontrados.map(i=>({tipo:i.k,id:idObjeto(i.k,prop(i.feature))}))});
    }
    map.on('click',e=>seleccionarPunto(e.latlng));
    function focalizar(item) {const k=item.k;if(!esVisible(k)){if(checks[k])checks[k].checked=true;activar(k,true);}map.fitBounds(item.layer.getBounds(),{maxZoom:17,padding:[25,25]});mostrarPanel([item]);}
    function buscar() {
      const q=normalizar(buscador?.value);
      if(resultados)resultados.replaceChildren();
      if(q.length<2){return;}
      const encontrados=['barrio','dep'].flatMap(k=>indices[k].filter(i=>{
        const p=prop(i.feature);return [p.nombre,p.id_rrhh,p.id_barrio,p.id_jurisdiccion,p.numero_ddp].some(s=>normalizar(s).includes(q));
      })).slice(0,35);
      encontrados.forEach(item=>{
        const p=prop(item.feature);const b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action';
        b.textContent=`${p.nombre||'Sin nombre'} · ${categoria(item.k)}${p.id_rrhh?' · RRHH '+p.id_rrhh:''}`;
        b.addEventListener('click',()=>{focalizar(item);if(resultados)resultados.replaceChildren();registrar('CONSULTA',{origen:'visor',accion:'resultado_busqueda',tipo:item.k,id:idObjeto(item.k,p)});});
        resultados?.appendChild(b);
      });
      if(!encontrados.length && resultados){const n=document.createElement('div');n.className='list-group-item small text-body-secondary';n.textContent='Sin coincidencias entre las capas oficiales cargadas.';resultados.appendChild(n);}
      if(q!==ultimoTexto){ultimoTexto=q;registrar('BUSQUEDA',{origen:'visor',texto:q,resultados:encontrados.length});}
    }
    buscador?.addEventListener('input',()=>{clearTimeout(timerBusqueda);timerBusqueda=setTimeout(buscar,350);});
    botonConsulta?.addEventListener('click',()=>{
      const la=Number(String(latInput?.value||'').replace(',','.')),lo=Number(String(lonInput?.value||'').replace(',','.'));
      if(!latInput?.value.trim()||!lonInput?.value.trim()||!Number.isFinite(la)||!Number.isFinite(lo)||Math.abs(la)>90||Math.abs(lo)>180){informar('Ingresá latitud y longitud válidas.');return;}
      const ll=L.latLng(la,lo);map.setView(ll,Math.max(map.getZoom(),15));seleccionarPunto(ll);
    });
    el('visorLimpiarBusqueda')?.addEventListener('click',()=>{if(buscador)buscador.value='';resultados?.replaceChildren();ultimoTexto='';});
    el('visorLimpiarPunto')?.addEventListener('click',()=>{marcador?.remove();marcador=null;if(latInput)latInput.value='';if(lonInput)lonInput.value='';if(panelResultado)panelResultado.classList.add('d-none');});
    el('visorCerrarIdentificacion')?.addEventListener('click',()=>panelResultado?.classList.add('d-none'));
    el('visorLimpiarTodo')?.addEventListener('click',()=>{el('visorLimpiarBusqueda')?.click();el('visorLimpiarPunto')?.click();for(const [k,ch]of Object.entries(checks)){if(ch)ch.checked=true;activar(k,true);}map.setView([-24.7821,-65.4232],12);informar('Vista y filtros restablecidos.');});
    async function geojson(url){const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});const j=await r.json().catch(()=>({error:'Respuesta inválida'}));if(!r.ok||j.type!=='FeatureCollection'||!Array.isArray(j.features))throw Error(j.error||'La capa no contiene GeoJSON válido');return j;}
    async function cargar(k,url,contador){try{const data=await geojson(url);capas[k].clearLayers();capas[k].addData(data);indices[k]=capas[k].getLayers().map(layer=>({k,layer,feature:layer.feature}));if(contador)contador.textContent=`(${indices[k].length})`;return indices[k].length;}catch(e){informar('Error al cargar '+categoria(k)+': '+e.message);console.error('[Visor Oficial]',k,e);return 0;}}
    // Endpoint existente de Barrios y endpoint actual de Jurisdicciones: ningún borrador es consultado.
    (async()=>{
      const [barrios, juris]=await Promise.all([
        cargar('barrio',base+'/ajax/visor.php?accion=oficiales',el('visorCuentaBarrios')),
        geojson(base+'/ajax/visor_jurisdicciones.php?estado=APROBADO').catch(e=>{informar('No se pudieron cargar jurisdicciones: '+e.message);return {features:[]};})
      ]);
      const oficiales=juris.features.filter(f=>String(f.properties?.tipo||'').toUpperCase()!=='DDP');
      capas.dep.clearLayers();capas.dep.addData({type:'FeatureCollection',features:oficiales});
      indices.dep=capas.dep.getLayers().map(layer=>({k:'dep',layer,feature:layer.feature}));
      if(el('visorCuentaDependencias'))el('visorCuentaDependencias').textContent=`(${indices.dep.length})`;
      informar(`Cartografía oficial: ${barrios} barrios y ${indices.dep.length} jurisdicciones policiales. Seleccioná un punto para identificar las coberturas.`);
      vistaInicial();map.invalidateSize();
    })();
    // No bloquear el visor por errores de auditoría de consultas; el endpoint registra fallas en el log.
    function registrar(tipo,parametros){
      const token=nodo.dataset.visorCsrf||'';if(!token)return;
      fetch(base+'/ajax/visor_consulta_audit.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify({tipo,parametros})})
        .then(r=>{if(!r.ok)console.warn('[Visor] No se pudo auditar la consulta HTTP',r.status);}).catch(e=>console.warn('[Visor] Auditoría de consulta no disponible',e));
    }
  });
})();
