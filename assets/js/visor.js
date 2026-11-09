(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const target = document.getElementById('mapaTerritorio');
    if (!target) return;
    const $ = id => document.getElementById(id);
    const estado = $('visorEstado'), buscador = $('visorBuscar');
    const resultados = $('visorResultados'), lat = $('visorLat'), lon = $('visorLon');
    const consultar = $('visorConsultar');
    const aviso = texto => { if (estado) estado.textContent = texto; };
    if (typeof L === 'undefined') {
      aviso('Leaflet no está disponible. Verificá la carga de su biblioteca JS y CSS.');
      return;
    }
    const mapa = L.map(target).setView([-24.7821, -65.4232], 12);
    const calles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(mapa);
    const pantallaMovil = window.matchMedia('(max-width: 767.98px)').matches;
    const pendientes = L.geoJSON(null, {
      style: feature => estilo(feature),
      onEachFeature: (feature, layer) => {
        layer.bindPopup(() => ficha(feature), {
          maxWidth: pantallaMovil ? 245 : 330,
          minWidth: pantallaMovil ? 190 : 220,
          maxHeight: pantallaMovil ? 190 : 330,
          autoPanPadding: L.point(18, 18)
        });
        layer.on('click', (ev) => {
          // Evitar que pulsar un polígono cree también el marcador del mapa.
          if (ev.originalEvent) L.DomEvent.stopPropagation(ev.originalEvent);
          marcarPoligono(layer);
        });
      }
    }).addTo(mapa);
    const controlCapas = L.control.layers(
      {'OpenStreetMap': calles},
      {'Municipales (sin publicar)': pendientes},
      {collapsed: pantallaMovil, position:'topright'}
    ).addTo(mapa);
    if (pantallaMovil) {
      controlCapas.getContainer()?.setAttribute('aria-label', 'Seleccionar capas del mapa');
    }

    let capas = [];
    let seleccionado = null;
    let marcador = null;
    const base = String(window.APP_BASE_URL || '/territorio').replace(/\/$/, '');
    const endpoint = base + '/ajax/visor.php';

    function estilo(feature) {
      const estado = feature?.properties?.estado || 'SIN_VINCULAR';
      const colores = {
        SIN_VINCULAR:'#D99A26', COINCIDENCIA_PROPUESTA:'#3B82F6',
        EN_REVISION:'#8B5CF6', VALIDADO:'#287A63',
        DESCARTADO:'#B95656', IMPORTADO:'#184E45'
      };
      const color = colores[estado] || '#64748B';
      return {color, weight:1.5, opacity:.85, fillColor:color, fillOpacity:.13};
    }
    function ficha(feature) {
      const p = feature.properties || {};
      const contenedor = document.createElement('div');
      const titulo = document.createElement('h3');
      titulo.className='visor-popup-titulo';
      titulo.textContent=p.nombre || 'Barrio sin nombre';
      contenedor.appendChild(titulo);
      const filas = [
        ['ID de importación', p.id_importacion],
        ['Código municipal', p.id_externo],
        ['Clasificación', p.clasificacion],
        ['Tipo', p.tipo],
        ['Año', p.anio],
        ['Estado preliminar', p.estado]
      ];
      filas.forEach(([nombre, valor]) => {
        if (valor === undefined || valor === null || valor === '') return;
        const fila = document.createElement('div');
        fila.className='visor-popup-dato';
        const etiqueta=document.createElement('strong');
        etiqueta.textContent=nombre+': ';
        fila.appendChild(etiqueta);
        fila.appendChild(document.createTextNode(String(valor)));
        contenedor.appendChild(fila);
      });
      const aviso = document.createElement('small');
      aviso.textContent = 'Fuente municipal. No constituye aprobación territorial.';
      contenedor.appendChild(aviso);
      return contenedor;
    }
    function marcarPoligono(layer) {
      if (seleccionado && seleccionado !== layer) seleccionado.setStyle(estilo(seleccionado.feature));
      seleccionado = layer;
      layer.setStyle({color:'#0F3D38',weight:4,fillOpacity:.30});
      if (layer.bringToFront) layer.bringToFront();
    }
    async function leer(params) {
      const response = await fetch(endpoint+'?'+new URLSearchParams(params), {
        credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'
      });
      const data = await response.json().catch(() => ({error:'La respuesta no es JSON'}));
      if (!response.ok) throw new Error(data.error || `Error HTTP ${response.status}`);
      return data;
    }
    function irACapa(layer) {
      if (!mapa.hasLayer(pendientes)) mapa.addLayer(pendientes);
      mapa.fitBounds(layer.getBounds(), {maxZoom:17,padding:[24,24]});
      marcarPoligono(layer);
      layer.openPopup();
      resultados.replaceChildren();
    }
    function buscar() {
      resultados.replaceChildren();
      const texto = buscador.value.trim().toLocaleLowerCase('es');
      if (texto.length < 2) return;
      const encontrados = capas.filter(c => String(c.feature?.properties?.nombre || '')
        .toLocaleLowerCase('es').includes(texto)).slice(0,30);
      encontrados.forEach(c => {
        const boton = document.createElement('button');
        boton.type='button';
        boton.className='list-group-item list-group-item-action py-2';
        boton.textContent=c.feature.properties.nombre;
        boton.addEventListener('click', () => irACapa(c));
        resultados.appendChild(boton);
      });
      if (!encontrados.length) aviso('No hay coincidencias municipales para ese nombre.');
    }
    buscador?.addEventListener('input',buscar);

    function situarMarcador(lt, ln) {
      if (marcador) marcador.remove();
      marcador=L.marker([lt,ln]).addTo(mapa);
      lat.value=lt.toFixed(7);
      lon.value=ln.toFixed(7);
    }
    mapa.on('click', evento => {
      situarMarcador(evento.latlng.lat, evento.latlng.lng);
      aviso('Coordenadas seleccionadas. Presioná Consultar para verificar los polígonos preliminares.');
    });
    consultar?.addEventListener('click', async () => {
      const lt=Number(String(lat.value).replace(',','.'));
      const ln=Number(String(lon.value).replace(',','.'));
      if (!lat.value.trim() || !lon.value.trim() || !Number.isFinite(lt) ||
          !Number.isFinite(ln) || lt < -90 || lt > 90 || ln < -180 || ln > 180) {
        aviso('Ingresá latitud y longitud válidas en grados decimales.');
        return;
      }
      situarMarcador(lt,ln);
      mapa.setView([lt,ln],Math.max(mapa.getZoom(),15));
      consultar.disabled=true;
      try {
        const data=await leer({accion:'punto',lat:lt,lon:ln});
        const hallados=Array.isArray(data.resultados)?data.resultados:[];
        const nombres=hallados.map(r=>r.nombre_original || 'Sin nombre');
        aviso(hallados.length
          ? `Coincidencias preliminares (${hallados.length}): ${nombres.join(' / ')}.`
          : 'Ningún polígono municipal contiene ese punto.');
        if (hallados.length && marcador) {
          const detalle=document.createElement('div');
          detalle.textContent='Cartografía municipal (sin validar): '+nombres.join(' / ');
          marcador.bindPopup(detalle).openPopup();
        }
      } catch(error) {
        aviso('Error al consultar: '+error.message);
      } finally { consultar.disabled=false; }
    });

    function limpiarBusqueda() {
      if (buscador) buscador.value = '';
      if (resultados) resultados.replaceChildren();
      if (seleccionado) {
        seleccionado.setStyle(estilo(seleccionado.feature));
        seleccionado = null;
      }
      mapa.closePopup();
    }
    function limpiarPunto() {
      if (marcador) { marcador.remove(); marcador = null; }
      if (lat) lat.value = '';
      if (lon) lon.value = '';
      mapa.closePopup();
    }
    $('visorLimpiarBusqueda')?.addEventListener('click', () => {
      limpiarBusqueda(); aviso('Búsqueda limpia.');
    });
    $('visorLimpiarPunto')?.addEventListener('click', () => {
      limpiarPunto(); aviso('Marcador y coordenadas eliminados.');
    });
    $('visorLimpiarTodo')?.addEventListener('click', () => {
      limpiarBusqueda(); limpiarPunto();
      if (capas.length && pendientes.getBounds().isValid()) {
        if (!mapa.hasLayer(pendientes)) mapa.addLayer(pendientes);
        mapa.fitBounds(pendientes.getBounds(),{padding:[20,20]});
      } else mapa.setView([-24.7821,-65.4232],12);
      aviso('Filtros y selecciones restablecidos.');
    });

    (async () => {
      try {
        const data=await leer({accion:'cartografia'});
        if (data.type !== 'FeatureCollection' || !Array.isArray(data.features))
          throw new Error('La cartografía no tiene formato FeatureCollection.');
        pendientes.clearLayers();
        pendientes.addData(data);
        capas=pendientes.getLayers();
        if (capas.length && pendientes.getBounds().isValid())
          mapa.fitBounds(pendientes.getBounds(),{padding:[20,20]});
        aviso(`${capas.length} polígonos municipales cargados. Son datos preliminares, sin publicar.`);
      } catch(error) { aviso('No se pudo cargar la cartografía: '+error.message); }
    })();
    requestAnimationFrame(()=>mapa.invalidateSize());
  });
})();
