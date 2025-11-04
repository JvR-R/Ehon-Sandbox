<?php
include('../db/dbh2.php');
include('../db/log.php');   
include('../db/border.php');  

// Get uid and tank_id from URL parameters
$uid = isset($_GET['uid']) ? intval($_GET['uid']) : null;
$tank_id = isset($_GET['tank_id']) ? intval($_GET['tank_id']) : null;

// Get available tanks for dropdown
$tanks_query = "
    SELECT DISTINCT 
        fq.uid, 
        fq.tank_id,
        CONCAT('Tank ', fq.tank_id, ' - UID: ', fq.uid) as display_name
    FROM fuel_quality fq
    JOIN Console_Asociation ca ON ca.uid = fq.uid
    WHERE ca.client_id = ? OR ca.reseller_id = ? OR ca.dist_id = ?
    ORDER BY fq.uid, fq.tank_id
";
$tanks_stmt = $conn->prepare($tanks_query);
$tanks_stmt->bind_param("iii", $companyId, $companyId, $companyId);
$tanks_stmt->execute();
$tanks_result = $tanks_stmt->get_result();
$available_tanks = [];
while($tank = $tanks_result->fetch_assoc()) {
    $available_tanks[] = $tank;
}

// If no uid/tank_id selected, use the first available
if ($uid === null || $tank_id === null) {
    if (count($available_tanks) > 0) {
        $uid = $available_tanks[0]['uid'];
        $tank_id = $available_tanks[0]['tank_id'];
    }
}
?>

<!DOCTYPE html>
<html data-wf-page="65014a9e5ea5cd2c6534f24f" data-wf-site="65014a9e5ea5cd2c6534f1c8">
<head>
  <meta charset="utf-8">
  <title>Real-Time Fuel Quality Graph</title>
  <meta property="og:type" content="website">
  <meta content="summary_large_image" name="twitter:card">
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <link href="fq.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/test-site-de674e.webflow.css" rel="stylesheet" type="text/css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.1/dist/chartjs-adapter-moment.min.js"></script>
  <style>
    .graph-container {
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin: 20px 0;
    }
    .controls {
      background: #f5f5f5;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      gap: 15px;
      align-items: center;
      flex-wrap: wrap;
    }
    .controls label {
      font-weight: bold;
      margin-right: 5px;
    }
    .controls select, .controls input {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }
    .controls button {
      padding: 8px 16px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
    }
    .controls button:hover {
      background: #0056b3;
    }
    .status {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
    }
    .status.connected {
      background: #28a745;
      color: white;
    }
    .status.disconnected {
      background: #dc3545;
      color: white;
    }
    .current-values {
      display: flex;
      gap: 20px;
      margin-top: 15px;
      justify-content: center;
    }
    .value-box {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 15px 25px;
      border-radius: 8px;
      text-align: center;
      min-width: 120px;
    }
    .value-box.iso4 {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .value-box.iso6 {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .value-box.iso14 {
      background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    .value-box .label {
      font-size: 12px;
      opacity: 0.9;
      margin-bottom: 5px;
    }
    .value-box .value {
      font-size: 32px;
      font-weight: bold;
    }
  </style>
</head>
<body>

<div style="opacity:1" class="page-wrapper">
  <div class="dashboard-main-section">
    <div class="sidebar-spacer"></div>
    <div class="sidebar-spacer2"></div>
    <div class="dashboard-content">
      <div class="dashboard-main-content">
        <div class="container-default w-container">
          <div class="mg-top-16px">
            
            <div class="controls">
              <div>
                <label for="tankSelect">Select Tank:</label>
                <select id="tankSelect" onchange="changeTank()">
                  <?php foreach($available_tanks as $tank): ?>
                    <option value="<?php echo $tank['uid']; ?>|<?php echo $tank['tank_id']; ?>"
                            <?php echo ($tank['uid'] == $uid && $tank['tank_id'] == $tank_id) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($tank['display_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div>
                <label for="refreshRate">Refresh Rate:</label>
                <select id="refreshRate" onchange="changeRefreshRate()">
                  <option value="2000">2 seconds</option>
                  <option value="5000" selected>5 seconds</option>
                  <option value="10000">10 seconds</option>
                  <option value="30000">30 seconds</option>
                  <option value="60000">1 minute</option>
                </select>
              </div>
              
              <div>
                <label for="timeWindow">Time Window:</label>
                <select id="timeWindow" onchange="updateChart()">
                  <option value="60">Last 1 minute</option>
                  <option value="300">Last 5 minutes</option>
                  <option value="600" selected>Last 10 minutes</option>
                  <option value="1800">Last 30 minutes</option>
                  <option value="3600">Last 1 hour</option>
                  <option value="14400">Last 4 hours</option>
                </select>
              </div>
              
              <button onclick="toggleAutoRefresh()" id="toggleBtn">Pause</button>
              
              <span class="status connected" id="statusIndicator">● Live</span>
            </div>

            <div class="current-values">
              <div class="value-box iso4">
                <div class="label">ISO 4μm</div>
                <div class="value" id="current-iso4">--</div>
              </div>
              <div class="value-box iso6">
                <div class="label">ISO 6μm</div>
                <div class="value" id="current-iso6">--</div>
              </div>
              <div class="value-box iso14">
                <div class="label">ISO 14μm</div>
                <div class="value" id="current-iso14">--</div>
              </div>
            </div>

            <div class="graph-container">
              <canvas id="fuelQualityChart"></canvas>
            </div>

          </div>
        </div>
      </div>    
    </div>                      
  </div>
</div>

<script>
let chart;
let refreshInterval;
let isAutoRefreshing = true;
let currentUid = <?php echo $uid ?? 'null'; ?>;
let currentTankId = <?php echo $tank_id ?? 'null'; ?>;
let refreshRate = 5000; // milliseconds

// Initialize the chart
function initChart() {
  const ctx = document.getElementById('fuelQualityChart').getContext('2d');
  chart = new Chart(ctx, {
    type: 'line',
    data: {
      datasets: [
        {
          label: 'ISO 4μm',
          borderColor: 'rgb(240, 147, 251)',
          backgroundColor: 'rgba(240, 147, 251, 0.1)',
          data: [],
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2
        },
        {
          label: 'ISO 6μm',
          borderColor: 'rgb(79, 172, 254)',
          backgroundColor: 'rgba(79, 172, 254, 0.1)',
          data: [],
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2
        },
        {
          label: 'ISO 14μm',
          borderColor: 'rgb(67, 233, 123)',
          backgroundColor: 'rgba(67, 233, 123, 0.1)',
          data: [],
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 2.5,
      scales: {
        x: {
          type: 'time',
          time: {
            unit: 'minute',
            displayFormats: {
              minute: 'HH:mm',
              hour: 'HH:mm'
            }
          },
          title: {
            display: true,
            text: 'Time'
          },
          grid: {
            display: true,
            color: 'rgba(0, 0, 0, 0.05)'
          }
        },
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'ISO Value'
          },
          grid: {
            display: true,
            color: 'rgba(0, 0, 0, 0.05)'
          }
        }
      },
      plugins: {
        legend: {
          display: true,
          position: 'top'
        },
        tooltip: {
          mode: 'index',
          intersect: false,
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          titleFont: {
            size: 14
          },
          bodyFont: {
            size: 13
          }
        }
      },
      interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false
      }
    }
  });
}

// Fetch data from the API
async function fetchData() {
  if (!currentUid || !currentTankId) {
    console.log('No tank selected');
    return;
  }

  const timeWindow = document.getElementById('timeWindow').value;
  
  try {
    const response = await fetch(
      `fq_graph_api.php?uid=${currentUid}&tank_id=${currentTankId}&time_window=${timeWindow}`
    );
    
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    
    const data = await response.json();
    updateChart(data);
    updateStatus(true);
  } catch (error) {
    console.error('Error fetching data:', error);
    updateStatus(false);
  }
}

// Update the chart with new data
function updateChart(data) {
  if (!data || !Array.isArray(data)) {
    console.log('No data received');
    return;
  }

  // Prepare data for Chart.js
  const iso4Data = [];
  const iso6Data = [];
  const iso14Data = [];

  data.forEach(reading => {
    const timestamp = new Date(reading.datetime);
    iso4Data.push({ x: timestamp, y: reading.particle_4um });
    iso6Data.push({ x: timestamp, y: reading.particle_6um });
    iso14Data.push({ x: timestamp, y: reading.particle_14um });
  });

  // Update chart datasets
  chart.data.datasets[0].data = iso4Data;
  chart.data.datasets[1].data = iso6Data;
  chart.data.datasets[2].data = iso14Data;
  chart.update('none'); // 'none' makes it update without animation for smoother real-time updates

  // Update current values display
  if (data.length > 0) {
    const latest = data[data.length - 1];
    document.getElementById('current-iso4').textContent = latest.particle_4um ?? '--';
    document.getElementById('current-iso6').textContent = latest.particle_6um ?? '--';
    document.getElementById('current-iso14').textContent = latest.particle_14um ?? '--';
  }
}

// Update status indicator
function updateStatus(connected) {
  const indicator = document.getElementById('statusIndicator');
  if (connected) {
    indicator.className = 'status connected';
    indicator.textContent = '● Live';
  } else {
    indicator.className = 'status disconnected';
    indicator.textContent = '● Disconnected';
  }
}

// Change tank selection
function changeTank() {
  const selection = document.getElementById('tankSelect').value.split('|');
  currentUid = parseInt(selection[0]);
  currentTankId = parseInt(selection[1]);
  
  // Update URL without reloading
  const url = new URL(window.location);
  url.searchParams.set('uid', currentUid);
  url.searchParams.set('tank_id', currentTankId);
  window.history.pushState({}, '', url);
  
  // Fetch new data immediately
  fetchData();
}

// Change refresh rate
function changeRefreshRate() {
  refreshRate = parseInt(document.getElementById('refreshRate').value);
  if (isAutoRefreshing) {
    stopAutoRefresh();
    startAutoRefresh();
  }
}

// Toggle auto-refresh
function toggleAutoRefresh() {
  if (isAutoRefreshing) {
    stopAutoRefresh();
    document.getElementById('toggleBtn').textContent = 'Resume';
    updateStatus(false);
  } else {
    startAutoRefresh();
    document.getElementById('toggleBtn').textContent = 'Pause';
    fetchData(); // Fetch immediately when resuming
  }
  isAutoRefreshing = !isAutoRefreshing;
}

// Start auto-refresh
function startAutoRefresh() {
  refreshInterval = setInterval(fetchData, refreshRate);
}

// Stop auto-refresh
function stopAutoRefresh() {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  initChart();
  fetchData(); // Initial fetch
  startAutoRefresh();
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
  stopAutoRefresh();
});
</script>

</body>
</html>
<?php
$tanks_stmt->close();
$conn->close();
?>

