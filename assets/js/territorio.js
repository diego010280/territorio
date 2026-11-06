(() => {
  'use strict';
  const mapa = L.map('mapa').setView([-24.7821, -65.4232], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(mapa);
  const grupo = L.geoJSON(null, {
    style: {color:'#2563eb',weight:2,fillOpacity:0.18},
    onEachFeature: (f, layer) => {
      layer.bindPopup(`${esc(f.properties.nombre || 'Sin nombre')}<br><small>Dato municipal en revisión</small>`);
    }
  }).addTo(mapa);
  const buscador = document.getElementById('filtro');
  const resultados = document.getElementById('resultados');
  const mensaje = document.getElementById('mensaje');
  const boton = document.getElementById('consultar');
  const latInput = document.getElementById('lat');
  const lonInput = document.getElementById('lon');
  let marca = null, layers = [];
  function esc(s) {return String(s).replace(/[&<>"']/g, x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));}
  function aviso(s) {mensaje.textContent=s;}
  function mostrarBusqueda() {
    resultados.replaceChildren();
    const q = buscador.value.trim().toLocaleLowerCase('es');
    if (!q) return;
    const lista = layers.filter(l=>String(l.feature.properties.nombre||'').toLocaleLowerCase('es').includes(q)).slice(0,30);
    for(const layer of lista){
      const el=document.createElement('button');el.className='list-group-item list-group-item-action';
      el.type='button';el.textContent=layer.feature.properties.nombre;
      el.addEventListener('click',()=>{mapa.fitBounds(layer.getBounds(),{maxZoom:16});layer.openPopup();});
      resultados.append(el);
    }
    if (!lista.length) aviso('No se encontró el nombre entre los polígonos importados.');
  }
  buscador.addEventListener('input', mostrarBusqueda);
  mapa.on('click', e=>{
    const {lat,lng}=e.latlng;
    if(marca) mapa.removeLayer(marca);
    marca=L.marker([lat,lng]).addTo(mapa);
    latInput.value=lat.toFixed(7);lonInput.value=lng.toFixed(7);
    boton.disabled=false;
    aviso('Punto seleccionado. Consultá la ubicación en la cartografía preliminar.');
  });
  boton.addEventListener('click', async()=>{
    try {
      const q=new URLSearchParams({accion:'punto',lat:latInput.value,lon:lonInput.value});
      const r=await fetch('ajax/territorio.php?'+q.toString(),{credentials:'same-origin'});
      if(!r.ok) throw Error('HTTP '+r.status);
      const d=await r.json();
      aviso((d.resultados?.length ? d.resultados.map(x=>x.nombre_original).join(' / ') : 'Sin coincidencia')+' · Solo cartografía preliminar, no jurisdicción oficial.');
    } catch(e){aviso('No se pudo consultar el punto: '+e.message);}
  });
  async function cargar(){
    try {
      const r=await fetch('ajax/territorio.php?accion=cartografia',{credentials:'same-origin'});
      if(!r.ok) throw Error('HTTP '+r.status);
      const d=await r.json();
      grupo.addData(d);layers=grupo.getLayers();
      if(layers.length) mapa.fitBounds(grupo.getBounds());
      aviso(`${layers.length} polígonos de importación municipal disponibles (no oficiales).`);
    }catch(e){aviso('No se pudo cargar la cartografía. Revisar sesión, conexión y tabla de importación. '+e.message);}
  }
  cargar();
})();
