<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Nodes Map</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
  />

  <style>
    html, body {
      height: 100%;
      margin: 0;
      font-family: "Inter", "Segoe UI", Arial, sans-serif;
      background-color: #f9f9fb;
      color: #222;
    }

    #app {
      display: grid;
      grid-template-columns: 380px 1fr;
      height: 100%;
    }

    #sidebar {
      padding: 16px;
      border-right: 1px solid #ddd;
      background-color: #fff;
      overflow: auto;
      display: flex;
      flex-direction: column;
    }

    #map {
      height: 100%;
      width: 100%;
    }

    h3 {
      margin-top: 0;
      font-size: 1.4rem;
      color: #1a7a6aff;
    }

    .filters {
      margin-bottom: 16px;
    }

    .filters input,
    .filters select,
    .filters button {
      width: 100%;
      margin-bottom: 10px;
      padding: 8px;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }

    .filters button {
      background-color: #1a7a6aff;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: background 0.2s;
    }

    .filters button:hover {
      background-color: #15584dff;
    }

    .details {
      border: 1px solid #e0e0e0;
      background: #fafafa;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .result {
      padding: 6px 4px;
      border-bottom: 1px solid #eee;
      cursor: pointer;
      transition: background 0.2s;
    }

    .result:hover {
      background: #f0f8ff;
    }

    .badge {
      display:inline-block;
      padding:3px 7px;
      border-radius:3px;
      margin:2px;
      font-size:12px;
      background-color:#e8eef9;
      border:1px solid #bcd0f7;
      color:#003580;
    }

    .pagination {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 8px;
    }

    .pagination button {
      background-color: #1a7a6aff;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 6px 10px;
      cursor: pointer;
    }

    .pagination button:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }

    .leaflet-popup-content {
      margin: 8px 12px;
      line-height: 1.25;
    }
    .leaflet-popup-content h4 {
      font-size: 15px;
      color: #1a7a6a;
    }

  </style>
</head>

<body>
  <div id="app">
    <div id="sidebar">
      <div class="filters">
        <input id="q" placeholder="Search address/name/city/country..." />
        <input id="vendor" placeholder="Vendor (exact)" />

        <!-- Interface dropdown -->
        <select id="iface">
          <option value="">All Interfaces</option>
          <option>GE</option>
          <option>10GE</option>
          <option>40GE</option>
          <option>100GE</option>
          <option>400GE</option>
          <option>WAVE</option>
          <option>LAG</option>
          <option>OTN</option>
          <option>Private Line</option>
        </select>

        <select id="continent">
          <option value="">All Continents</option>
          <option>North America</option>
          <option>South America</option>
          <option>Europe</option>
          <option>Asia</option>
          <option>Africa</option>
          <option>Oceania</option>
        </select>

        <button id="apply">Apply Filters</button>
      </div>

      <div id="details" class="details" style="display:none;"></div>
      <div id="list"></div>

      <div class="pagination">
        <button id="prevPage" disabled>← Prev</button>
        <span id="pageInfo">Page 1</span>
        <button id="nextPage">Next →</button>
      </div>
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
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let markersLayer = L.layerGroup().addTo(map);
    let markerById = new Map();
    let allData = [];
    let currentPage = 1;
    const pageSize = 5;

    // ---- FETCH NODES ----
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
      p.set('limit', '1000'); // safer limit

      const res = await fetch('/api/nodes?' + p.toString());
      const json = await res.json();
      return json.data || [];
    }

    // ---- PAGINATION ----
    function renderPage() {
      const start = (currentPage - 1) * pageSize;
      const end = start + pageSize;
      const pageData = allData.slice(start, end);
      renderList(pageData);
      document.getElementById('pageInfo').textContent = `Page ${currentPage}`;
      document.getElementById('prevPage').disabled = currentPage === 1;
      document.getElementById('nextPage').disabled = end >= allData.length;
    }

    document.getElementById('prevPage').onclick = () => {
      if (currentPage > 1) {
        currentPage--;
        renderPage();
      }
    };
    document.getElementById('nextPage').onclick = () => {
      if ((currentPage * pageSize) < allData.length) {
        currentPage++;
        renderPage();
      }
    };

    // ---- RENDER LIST ----
    function renderList(data) {
      const list = document.getElementById('list');
      list.innerHTML = '';
      data.forEach(n => {
        const div = document.createElement('div');
        div.className = 'result';
        div.innerHTML = `
          <div><strong>${n.name || '(no name)'}</strong></div>
          <div>${n.address || ''}</div>
          <div><em>${n.continent || ''}</em></div>
        `;
        div.onclick = () => focusNode(n.id);
        list.appendChild(div);
      });
    }

    // ---- RENDER MARKERS ----
    function renderMarkers(data) {
      markersLayer.clearLayers();
      markerById.clear();

      const coords = [];
      data.forEach(n => {
        const lat = parseFloat(n.lat);
        const lng = parseFloat(n.lng);

        if (
          lat === null ||
          lng === null ||
          isNaN(lat) ||
          isNaN(lng) ||
          lat === 0 && lng === 0
        ) {
          return; // skip un-geocoded or zeroed nodes
        }

        const m = L.marker([lat, lng]).addTo(markersLayer);
        m.on('click', () => showDetails(n.id));
        markerById.set(n.id, m);
        coords.push([lat, lng]);
      });

      if (coords.length) {
        const bounds = L.latLngBounds(coords);
        map.fitBounds(bounds.pad(0.15));
      } else {
        console.warn('No markers to display.');
      }
    }

    // ---- DETAILS VIEW + MAP POPUP ----
    async function showDetails(id) {
      const res = await fetch('/api/nodes/' + id);
      const n = await res.json();

      const html = `
        <div style="min-width:260px">
          <h4 style="margin:0 0 6px 0">${n.name || '(no name)'}</h4>
          <div><strong>Address:</strong> ${n.address || ''}</div>
          <div><strong>Continent:</strong> ${n.continent || ''}</div>
          <div><strong>Node-ID:</strong> ${n.node_id || ''}</div>
          <div><strong>ID:</strong> ${n.id}</div>
          <div><strong>Vendors:</strong> ${n.vendors.join(', ')}</div>
          <div><strong>Interfaces:</strong> ${n.interfaces.join(', ')}</div>
        </div>
      `;

      // Update the sidebar details panel
      const details = document.getElementById('details');
      details.innerHTML = html;
      details.style.display = 'block';

      // Also show a popup on the map (overlay)
      const marker = markerById.get(id);
      if (marker) {
        marker.bindPopup(html, { maxWidth: 420 }).openPopup();
      } else {
        // Fallback if the node has no coords/marker
        console.warn('No marker for node', id);
      }
    }


    // ---- REFRESH ----
    async function refresh() {
      allData = await fetchNodes();
      const withCoords = allData.filter(n => Number.isFinite(Number(n.lat)) && Number.isFinite(Number(n.lng)));
      console.log(`Fetched ${allData.length} nodes; ${withCoords.length} with coords`);

      currentPage = 1;
      renderPage();
      renderMarkers(withCoords);
    }

    document.getElementById('apply').onclick = refresh;
    refresh();
  </script>
</body>
</html>
