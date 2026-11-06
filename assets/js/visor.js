(() => {
    'use strict';
    document.addEventListener('DOMContentLoaded', () => {
        const target = document.getElementById('mapaTerritorio');
        if (!target) return;
        const estado = document.getElementById('visorEstado');
        const buscador = document.getElementById('visorBuscar');
        const resultados = document.getElementById('visorResultados');
        const lat = document.getElementById('visorLat');
        const lon = document.getElementById('visorLon');
        const consultar = document.getElementById('visorConsultar');
        const aviso = mensaje => estado.textContent = mensaje;
        if (typeof L === 'undefined') { aviso('Leaflet no pudo cargarse. Revisar conexión a Internet o archivos locales.'); return; }
        const mapa = L.map(target).setView([-24.7821,-65.4232],12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom:19, attribution:'&copy; OpenStreetMap contributors'
        }).addTo(mapa);
        const grupo = L.geoJSON(null, {
            style: {color:'#287A63', weight:2, fillOpacity:0.16},
            onEachFeature(feature, capa) {
                const span = document.createElement('span');
                span.textContent = feature.properties?.nombre || 'Sin nombre';
                const descripcion = document.createElement('small');
                descripcion.textContent=' · Cartografía municipal en revisión';
                const contenedor = document.createElement('div');
                contenedor.append(span,document.createElement('br'),descripcion);
                capa.bindPopup(contenedor);
            }
        }).addTo(mapa);
        let capas = [], marcador = null;
        const base = window.APP_BASE_URL || '/territorio';
        const endpoint = base.replace(/\/$/,'') + '/ajax/visor.php';
        const leer = async params => {
            const r = await fetch(endpoint+'?'+new URLSearchParams(params), {credentials:'same-origin'});
            const data = await r.json().catch(()=>({error:'Respuesta no JSON'}));
            if (!r.ok) throw Error(data.error || `Error HTTP ${r.status}`);
            return data;
        };
        const buscar = () => {
            resultados.replaceChildren();
            const texto = buscador.value.trim().toLocaleLowerCase('es');
            if (!texto) return;
            const encontrados = capas.filter(c=>String(c.feature?.properties?.nombre||'').toLocaleLowerCase('es').includes(texto)).slice(0,25);
            for(const c of encontrados) {
                const b = document.createElement('button');
                b.type='button'; b.className='list-group-item list-group-item-action';
                b.textContent=c.feature.properties.nombre;
                b.addEventListener('click',()=>{mapa.fitBounds(c.getBounds(),{maxZoom:16});c.openPopup();resultados.replaceChildren();});
                resultados.appendChild(b);
            }
            if(!encontrados.length) aviso('Sin coincidencias en los polígonos cargados.');
        };
        buscador.addEventListener('input',buscar);
        mapa.on('click',e=>{
            if (marcador) marcador.remove();
            marcador=L.marker(e.latlng).addTo(mapa);
            lat.value=e.latlng.lat.toFixed(7); lon.value=e.latlng.lng.toFixed(7);
            consultar.disabled=false;
            aviso('Punto seleccionado. Consultá si se encuentra dentro de un polígono preliminar.');
        });
        consultar.addEventListener('click',async()=>{
            try {
                const data=await leer({accion:'punto',lat:lat.value,lon:lon.value});
                const nombres=(data.resultados||[]).map(x=>x.nombre_original);
                aviso(nombres.length ? nombres.join(' / ')+' (no oficial)' : 'No se encontró polígono para ese punto.');
            } catch(e){aviso('Error al consultar ubicación: '+e.message);}
        });
        (async()=>{
            try {
                const data=await leer({accion:'cartografia'});
                grupo.addData(data);capas=grupo.getLayers();
                if(capas.length) mapa.fitBounds(grupo.getBounds());
                aviso(`${capas.length} polígonos municipales disponibles para revisión (no oficiales).`);
            } catch(e){aviso('No se pudo cargar cartografía: '+e.message);}
        })();
        requestAnimationFrame(()=>mapa.invalidateSize());
    });
})();
