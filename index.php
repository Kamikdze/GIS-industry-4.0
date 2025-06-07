<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title>Карта университетов России</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <style>
    body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }
    #map { height: 100vh; width: 100vw; }
    .search-box {
      position: absolute;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: white;
      padding: 12px 16px;
      z-index: 1002;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .sidebar {
      position: absolute;
      top: 0;
      height: 100%;
      width: 500px;
      background: linear-gradient(to bottom right, #ffffff, #eef2f7);
      display: none;
      z-index: 1001;
      overflow-y: auto;
      box-shadow: -6px 0 20px rgba(0,0,0,0.2);
      padding: 24px;
      transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
      opacity: 0.98;
      border-top-right-radius: 12px;
      border-bottom-right-radius: 12px;
    }
    .sidebar.left {
      left: 0;
      right: auto;
    }
    .sidebar.right {
      right: 0;
      left: auto;
    }
    .sidebar h3 { margin-top: 0; font-size: 22px; color: #1a2b3c; }
    .sidebar button {
      background: linear-gradient(to right, #4facfe, #00f2fe);
      color: white;
      border: none;
      padding: 10px 16px;
      margin: 8px 8px 16px 0;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.3s ease;
    }
    .sidebar button:hover {
      background: linear-gradient(to right, #00c6ff, #0072ff);
    }
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }
    .data-table th, .data-table td {
      padding: 10px 12px;
      border: 1px solid #ddd;
      text-align: left;
    }
    .data-table th {
      background-color: #f5f7fa;
      font-weight: 600;
    }
    .highlight-green { background-color: #e6f9ed; color: #2e7d32; }
    .highlight-red { background-color: #fdecea; color: #c62828; }
    .controls {
      position: absolute;
      bottom: 20px;
      left: 20px;
      z-index: 1003;
    }
    .controls button {
      display: block;
      margin-bottom: 8px;
      padding: 10px 14px;
      background: #ffffff;
      border: 1px solid #ccc;
      border-radius: 6px;
      cursor: pointer;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
      transition: all 0.2s ease-in-out;
    }
    .controls button:hover { background: #f4f4f4; }
    .bottom-panel {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 60vh; /* Use viewport height units */
  max-height: 80vh;
  background: white;
  z-index: 1004;
  padding: 20px;
  box-shadow: 0 -4px 12px rgba(0,0,0,0.2);
  display: none;
  overflow-y: auto;
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
  box-sizing: border-box;
  transition: height 0.3s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .bottom-panel {
    height: 70vh; /* Larger on mobile */
    padding: 15px;
  }
}

@media (max-height: 600px) {
  .bottom-panel {
    height: 80vh; /* Taller on short screens */
  }
}

#historyChart {
  width: 100% !important;
  height: calc(100% - 100px) !important; /* Account for header space */
  min-height: 200px;
}

.history-header {
  position: sticky;
  top: 0;
  background: white;
  padding-bottom: 15px;
  z-index: 2;
}

.history-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 15px;
}

.history-controls select {
  flex: 1;
  min-width: 200px;
  padding: 8px;
  border-radius: 4px;
  border: 1px solid #ddd;
}

.history-controls button {
  padding: 8px 16px;
  background: #f44336;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  white-space: nowrap;
}
  </style>
</head>
<body>
  <div class="search-box">
    <input type="text" id="searchInput" placeholder="Поиск по УГН(С)..." />
  </div>
  <div id="map"></div>
  <div class="controls">
    <button onclick="showAllMarkers()">Показать все</button>
    <button onclick="hideAllMarkers()">Скрыть все</button>
  </div>
  <div class="sidebar left" id="sidebar-left"></div>
  <div class="sidebar right" id="sidebar-right"></div>
  <div class="bottom-panel" id="history-panel"></div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const map = L.map('map').setView([61, 90], 3);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

    let historicalData = {};
    let currentHistoryChart = null;
    let universityData = [];
    let directionsData = [];
    let historyData = {}; // keyed by year
    let universityMarkers = [];
    let selectedUniversity = null;
    let comparisonMode = false;

    function getIcon(name) {
      const base = 'https://xn--d1amqcgedd.xn--p1ai/media/icons/';
      if (/строитель|архитект|градостро|проект/i.test(name)) return base + '1_SSO.png';
      if (/педагог|учитель|образование/i.test(name)) return base + '2_SPO.png';
      if (/транспорт|логистика|авиа|железно/i.test(name)) return base + '10_SOZHT_1.png';
      if (/машино|механи|робот/i.test(name)) return base + '3_SOP.png';
      if (/сервис|туризм|гостиниц|гуман/i.test(name) && !/финанс/i.test(name)) return base + '4_SServO.png';
      if (/эконом|бизнес|финанс/i.test(name)) return base + '4_SServO.png';
      if (/аграр|сель|земледелие/i.test(name)) return base + '5_SSkhO.png';
      if (/мед|врач|здрав/i.test(name)) return base + '6_SMO.png';
      if (/техни|инжен|энерг|ядер/i.test(name)) return base + '8_SPrO.png';
      return base + '9_Spetcializirovannye.png';
    }

    function parseNumber(val) {
      return parseFloat(val.replace(/\s/g, '').replace(',', '.'));
    }

    function createTable(u1, u2 = null) {
  // Get directions data for both universities
  const directions1 = directionsData.find(d => d.name === u1.name);
  const directions2 = u2 ? directionsData.find(d => d.name === u2.name) : null;

  // Function to format directions list
  const formatDirections = (dirData, isComparison = false) => {
    if (!dirData || !dirData.analis_reg_data) return '<p>Нет данных о направлениях</p>';
    
    const columnCount = isComparison ? 1 : 2;
    let html = `
      <div style="margin: 15px 0;">
        <h4>Направления подготовки</h4>
        <ul style="columns: ${columnCount}; column-gap: 20px; margin-top: 10px;">
    `;
    
    dirData.analis_reg_data.forEach(item => {
      const code = item['Реализуемые УГН(С)'] || 'Не указано';
      html += `<li style="margin-bottom: 5px; break-inside: avoid;">${code}</li>`;
    });
    
    html += `</ul></div>`;
    return html;
  };

  // Function to create comparison rows
  const rowsFromArray = (array1 = [], array2 = []) => {
    return array1.map(d => {
      const value1 = parseNumber(d.Значение);
      let value2 = null;
      let class1 = '', class2 = '';
      
      if (u2 && array2.length) {
        const match = array2.find(e => e.Описание === d.Описание);
        value2 = match ? parseNumber(match.Значение) : null;
        
        if (!isNaN(value1) && !isNaN(value2)) {
          if (value1 > value2) {
            class1 = 'highlight-green';
            class2 = 'highlight-red';
          } else if (value1 < value2) {
            class1 = 'highlight-red';
            class2 = 'highlight-green';
          }
        }
      }
      
      return `
        <tr>
          <td>${d.Описание}</td>
          <td class="${class1}">${d.Значение}</td>
          ${u2 ? `<td class="${class2}">${value2 ?? '-'}</td>` : ''}
        </tr>
      `; 
    }).join('');
  };

  // Generate table sections
  const rowsNapde = rowsFromArray(u1.napde_data || [], u2?.napde_data || []);
  const rowsDop = rowsFromArray(u1.analis_dop_data || [], u2?.analis_dop_data || []);

  return `
    <div style="position: sticky; top: 0; background: linear-gradient(to bottom right, #ffffff, #eef2f7); padding-bottom: 15px; z-index: 1;">
      <h3 style="margin-top: 0;">${u1.name}</h3>
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="closeSidebars()">Закрыть</button>
        ${!u2 ? `
          <button onclick="startComparison()">Сравнить</button>
          <button onclick="showHistory('${u1.name}')">История</button>
        ` : ''}
      </div>
    </div>
    
    ${formatDirections(directions1, !!u2)}
    
    <div style="margin-top: 20px;">
      <h4>Основные показатели</h4>
      <table class="data-table">
        <thead>
          <tr>
            <th>Показатель</th>
            <th>${u1.name}</th>
            ${u2 ? `<th>${u2.name}</th>` : ''}
          </tr>
        </thead>
        <tbody>${rowsNapde}</tbody>
      </table>
      
      <h4 style="margin-top: 20px;">Дополнительные данные</h4>
      <table class="data-table">
        <thead>
          <tr>
            <th>Показатель</th>
            <th>${u1.name}</th>
            ${u2 ? `<th>${u2.name}</th>` : ''}
          </tr>
        </thead>
        <tbody>${rowsDop}</tbody>
      </table>
    </div>
  `;
}

    function openSidebar(u, side) {
      const el = document.getElementById(`sidebar-${side}`);
      el.innerHTML = createTable(u, side === 'right' ? selectedUniversity : null);
      el.style.display = 'block';
      if (!selectedUniversity && side === 'left') {
        selectedUniversity = u;
      }
    }

    function startComparison() {
      comparisonMode = true;
    }

    function closeSidebars() {
      document.getElementById('sidebar-left').style.display = 'none';
      document.getElementById('sidebar-right').style.display = 'none';
      document.getElementById('history-panel').style.display = 'none';
      selectedUniversity = null;
      comparisonMode = false;
    }

    function addMarkers(data) {
      universityMarkers.forEach(m => map.removeLayer(m));
      universityMarkers = [];
      data.forEach(u => {
        if (!u.coordinates) return;
        const coords = u.coordinates.split(',').map(s => parseFloat(s.trim())).reverse();
        if (isNaN(coords[0]) || isNaN(coords[1])) return;
        const icon = L.icon({ iconUrl: getIcon(u.name), iconSize: [32, 32] });
        const marker = L.marker(coords, { icon });
        marker.on('click', () => {
          if (comparisonMode && selectedUniversity) {
            openSidebar(u, 'right');
            comparisonMode = false;
          } else {
            openSidebar(u, 'left');
          }
        });
        marker.addTo(map);
        universityMarkers.push(marker);
      });
    }

    function showAllMarkers() {
      universityMarkers.forEach(m => m.addTo(map));
    }

    function hideAllMarkers() {
      universityMarkers.forEach(m => map.removeLayer(m));
    }

    function showHistory(name) {
    const panel = document.getElementById('history-panel');
  panel.innerHTML = `
    <h3>История изменений для ${name}</h3>
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
      <select id="metricSelect" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
        <option value="">Выберите показатель...</option>
      </select>
      <button onclick="closeHistoryPanel()" style="padding: 8px 16px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;">
        Закрыть
      </button>
    </div>
    <div style="height: 70%;">
      <canvas id="historyChart"></canvas>
    </div>
  `;
  panel.style.display = 'block';
  
  loadHistoricalData(name).then(() => {
    populateMetricSelect(name);
    // Default to showing the first available metric
    if (document.getElementById('metricSelect').options.length > 1) {
      document.getElementById('metricSelect').selectedIndex = 1;
      updateHistoryChart(name, document.getElementById('metricSelect').value);
    }
  });
}

function closeHistoryPanel() {
  document.getElementById('history-panel').style.display = 'none';
  if (currentHistoryChart) {
    currentHistoryChart.destroy();
    currentHistoryChart = null;
  }
}

async function loadHistoricalData(universityName) {
  historicalData = {};
  
  // Load data for each year from 2015 to 2023
  for (let year = 2015; year <= 2023; year++) {
    try {
      const response = await fetch(`parsed_all_data/universities_parsed_data${year}.json`);
      const data = await response.json();
      
      // Find the university in this year's data
      const university = data.find(u => u.name === universityName);
      if (university) {
        historicalData[year] = {
          napde_data: university.napde_data || [],
          analis_dop_data: university.analis_dop_data || []
        };
      }
    } catch (error) {
      console.error(`Error loading data for year ${year}:`, error);
    }
  }
}

function populateMetricSelect(universityName) {
  const select = document.getElementById('metricSelect');
  
  // Clear existing options except the first one
  while (select.options.length > 1) {
    select.remove(1);
  }
  
  // Get all unique metric names from all years
  const allMetrics = new Set();
  
  Object.entries(historicalData).forEach(([year, data]) => {
    data.napde_data.forEach(item => allMetrics.add(item.Описание));
    data.analis_dop_data.forEach(item => allMetrics.add(item.Описание));
  });
  
  // Add metrics to select
  allMetrics.forEach(metric => {
    const option = document.createElement('option');
    option.value = metric;
    option.textContent = metric;
    select.appendChild(option);
  });
  
  // Add event listener for metric selection
  select.addEventListener('change', () => {
    updateHistoryChart(universityName, select.value);
  });
}

function updateHistoryChart(universityName, metric) {
  const ctx = document.getElementById('historyChart').getContext('2d');
  
  // Destroy previous chart if it exists
  if (currentHistoryChart) {
    currentHistoryChart.destroy();
  }
  
  // Prepare data for the chart
  const years = [];
  const values = [];
  
  Object.entries(historicalData).forEach(([year, data]) => {
    // Try to find the metric in napde_data first
    let metricData = data.napde_data.find(item => item.Описание === metric);
    
    // If not found, try analis_dop_data
    if (!metricData && data.analis_dop_data) {
      metricData = data.analis_dop_data.find(item => item.Описание === metric);
    }
    
    if (metricData) {
      years.push(year);
      values.push(parseNumber(metricData.Значение));
    }
  });
  
  // Create the chart
  currentHistoryChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: years,
      datasets: [{
        label: metric,
        data: values,
        backgroundColor: 'rgba(54, 162, 235, 0.7)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: false,
          title: {
            display: true,
            text: 'Значение'
          }
        },
        x: {
          title: {
            display: true,
            text: 'Год'
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function(context) {
              return `${context.dataset.label}: ${context.raw}`;
            }
          }
        }
      }
    }
  });
}

    document.getElementById('searchInput').addEventListener('input', e => {
      const val = e.target.value.trim();
      const filtered = universityData.filter(u => {
        const dir = directionsData.find(d => d.name === u.name);
        const codes = dir?.analis_reg_data?.map(x => x['Реализуемые УГН(С)']) || [];
        return codes.some(code => code.includes(val));
      });
      addMarkers(filtered);
    });

    fetch('parsed_all_data/universities_parsed_data2023.json').then(res => res.json()).then(data => {
      universityData = data;
      addMarkers(data);
    });

    fetch('parsed_all_data/universities_directions.json').then(res => res.json()).then(data => {
      directionsData = data;
    });

    fetch('data.json').then(res => res.json()).then(geojson => {
      L.geoJSON(geojson, {
        style: { color: '#3388ff', weight: 1, fillOpacity: 0.1 },
        onEachFeature: (feature, layer) => {
          layer.on('click', () => {
            const bounds = layer.getBounds();
            const markersInRegion = universityMarkers.filter(m => bounds.contains(m.getLatLng()));
            hideAllMarkers();
            markersInRegion.forEach(m => m.addTo(map));
            map.fitBounds(bounds);
          });
        }
      }).addTo(map);
    });
  </script>
</body>
</html>
