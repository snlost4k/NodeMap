<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Compass Nodes Map</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
  />
  <style>
    html, body { height: 100%; margin: 0; }
    #app { display: grid; grid-template-columns: 360px 1fr; height: 100%; }
    #sidebar { padding: 12px; border-right: 1px solid #ddd; overflow: auto; }
    #map { height: 100%; }
    .result { padding: 6px 4px; border-bottom: 1px solid #eee; cursor: pointer; }
    .result:hover { background: #f8f8f8; }
    .filters input, .filters select { width: 100%; margin-bottom: 8px; padding: 6px; }
    .badge { display:inline-block; padding:2px 6px; border:1px solid #ccc; border-radius:3px; margin:2px; font-size:12px;}
  </style>
</head>
<body>
  <div id="app">
    <div id="sidebar">
      <h3>Nodes</h3>
      <div class="filters">
        <input id="q" placeholder="Search address/name/city/country..." />
        <input id="vendor" placeholder="Vendor (exact)" />
        <input id="iface" placeholder="Interface (e.g., 10GE)" />
        <select id="continent">
          <option value="">All continents</option>
          <option>North America</option>
          <option>South America</option>
          <option>Europe</option>
          <option>Asia</option>
          <option>Africa</option>
          <option>Oceania</option>
        </select>
        <button id="apply">Apply Filters</button>
      </div>
      <div id="list"></div>
    </div>
    <div id="map"></div>
  </div>

  <script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
  ></script>
  <script>
    const map = L.map('map').setView([20, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let markersLayer = L.layerGroup().addTo(map);
    let lastData = [];

    async function fetchNodes() {
      const p = new URLSearchParams();
      const q = document.getElementById('q').value.trim();
      const vendor = document.getElementById('vendor').value.trim();
      const iface = document.getElementById('iface').value.trim();
      const continent = document.getElementById('continent').value;

      if (q) p.set('q', q);
      if (vendor) p.set('vendor', vendor);
      if (iface) p.set('interface', iface);
      if (continent) p.set('continent', continent);
      p.set('limit', '1000');

      const res = await fetch('/api/nodes?' + p.toString());
      const json = await res.json();
      return json.data || [];
    }

    function renderList(data) {
      const list = document.getElementById('list');
      list.innerHTML = '';
      data.forEach(n => {
        const div = document.createElement('div');
        div.className = 'result';
        div.innerHTML = `
          <div><strong>${n.name || '(no name)'}</strong></div>
          <div>${n.address || ''}</div>
          <div>${n.continent || ''}</div>
          <div>${n.vendors.map(v => `<span class="badge">${v}</span>`).join(' ')}</div>
          <div>${n.interfaces.map(i => `<span class="badge">${i}</span>`).join(' ')}</div>
        `;
        div.onclick = () => focusNode(n.id);
        list.appendChild(div);
      });
    }

    let markerById = new Map();

    function renderMarkers(data) {
    markersLayer.clearLayers();
    markerById.clear();

    const coords = [];

    data.forEach(n => {
        const lat = Number(n.lat);
        const lng = Number(n.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const m = L.marker([lat, lng]).addTo(markersLayer);
        m.on('click', () => showDetails(n.id));
        markerById.set(n.id, m);
        coords.push([lat, lng]);
    });

    if (coords.length) {
        const bounds = L.latLngBounds(coords);
        map.fitBounds(bounds.pad(0.15));
    } else {
        console.warn('No markers to display yet.');
    }
    }

    async function showDetails(id) {
      const res = await fetch('/api/nodes/' + id);
      const n = await res.json();

      const content = `
        <div><strong>${n.name || '(no name)'}</strong></div>
        <div>${n.address || ''}</div>
        <div><em>${n.continent || ''}</em></div>
        <div><strong>Vendors:</strong> ${n.vendors.join(', ')}</div>
        <div><strong>Interfaces:</strong> ${n.interfaces.join(', ')}</div>
        <div><strong>Node-ID:</strong> ${n.node_id || ''}</div>
        <div><strong>ID:</strong> ${n.id}</div>
      `;

      const marker = markerById.get(id);
      if (marker) {
        marker.bindPopup(content).openPopup();
      } else {
        alert(content.replace(/<[^>]+>/g, '')); // fallback if no coords
      }
    }

    function focusNode(id) {
      const marker = markerById.get(id);
      if (marker) {
        map.setView(marker.getLatLng(), 14);
        marker.fire('click');
      } else {
        showDetails(id);
      }
    }

    async function refresh() {
        const data = await fetchNodes();

        // Count how many have usable coords (debug)
        const withCoords = data.filter(n => Number.isFinite(Number(n.lat)) && Number.isFinite(Number(n.lng)));
        console.log(`Fetched ${data.length} nodes; ${withCoords.length} with coords`);

        renderList(data);
        renderMarkers(data);
    }

    document.getElementById('apply').onclick = refresh;
    refresh();
  </script>
</body>
</html>
