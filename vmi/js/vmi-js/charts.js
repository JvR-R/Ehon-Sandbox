/* /vmi/js/charts.js ---------------------------------------------- *
 * Owns every interaction with Chart.js                             *
 *------------------------------------------------------------------*/

import { byId } from './api.js';

const registry   = Object.create(null);   // canvas-id → Chart instance
const chartModes = Object.create(null);   // row → 'volume' | 'deliveries'
let   chartReady = null;                  // singleton loader promise
const chartCreating = Object.create(null); // Track charts being created to prevent duplicates

/* Check if a chart is currently being created for a row */
export function isChartCreating(row) {
  return !!chartCreating[`chart-${row}`];
}

/* 0. destroyChart – clean re-draw -------------------------------- */
export function destroyChart(id) {
  if (registry[id]) { 
    registry[id].destroy(); 
    delete registry[id]; 
  }
  // Clean up any pending retry timers
  delete resizeRetries[id];
}

// Track resize retry attempts to prevent infinite loops
const resizeRetries = Object.create(null);

/* 0.b. resizeChart – force chart to resize -------------------------------- */
export function resizeChart(id, retryCount = 0) {
  if (!registry[id]) {
    return;
  }
  
  // Prevent infinite retry loops - max 5 attempts
  if (retryCount > 5) {
    delete resizeRetries[id];
    return;
  }
  
  try {
    const chart = registry[id];
    const canvas = chart.canvas;
    
    if (!canvas || !canvas.parentElement) {
      delete resizeRetries[id];
      return;
    }
    
    // Check if container is actually in a visible modal
    const modalOverlay = canvas.closest('.tank-modal-overlay');
    if (modalOverlay && !modalOverlay.classList.contains('tank-modal-overlay--visible')) {
      delete resizeRetries[id];
      return;
    }
    
    // Check if container has dimensions
    const container = canvas.closest('.chart1') || canvas.parentElement;
    const rect = container.getBoundingClientRect();
    
    if (rect.width === 0 || rect.height === 0) {
      // Only retry if we haven't exceeded max retries
      if (retryCount < 5) {
        resizeRetries[id] = (resizeRetries[id] || 0) + 1;
        setTimeout(() => resizeChart(id, retryCount + 1), 100);
      } else {
        delete resizeRetries[id];
      }
      return;
    }
    
    // Clear retry counter on success
    delete resizeRetries[id];
    
    // Resize the chart
    chart.resize();
    
    // Force an update to ensure chart renders properly
    chart.update('none'); // 'none' mode = don't animate
  } catch (e) {
    delete resizeRetries[id];
  }
}

/* 0.c. resizeChartByRow – resize chart by row number -------------------------------- */
export function resizeChartByRow(row) {
  resizeChart(`chart-${row}`);
  resizeChart(`tempchart-${row}`);
}

/* 1. lazy-load Chart.js ------------------------------------------ */
async function ensureChartJs() {
  if (window.Chart) return;                       // already loaded

  if (!chartReady) {                              // first time → inject
    chartReady = new Promise((res, rej) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
      s.onload  = () => res();
      s.onerror = () => rej(new Error('Chart.js failed to load'));
      document.head.appendChild(s);
    });
  }
  return chartReady;
}

/* 2. main volume-&-delivery chart -------------------------------- */
export async function drawChart(data, row) {
  const id = `chart-${row}`;
  await ensureChartJs();

  const canvas = byId(id);

  /* wait until the <canvas> is actually in the DOM */
  if (!(canvas instanceof HTMLCanvasElement) || !canvas.getContext?.('2d')) {
    // Clear creating flag since we're retrying (new call will set it)
    delete chartCreating[id];
    requestAnimationFrame(() => drawChart(data, row));
    return;
  }
  
  /* wait until the container has dimensions (modal might still be animating) */
  const container = canvas.closest('.chart1') || canvas.parentElement;
  if (container) {
    const rect = container.getBoundingClientRect();
    const hasDimensions = rect.width > 0 && rect.height > 0;
    const isVisible = container.offsetParent !== null && 
                     window.getComputedStyle(container).visibility !== 'hidden' &&
                     window.getComputedStyle(container).display !== 'none';
    
    if (!hasDimensions || !isVisible) {
      // Clear creating flag since we're retrying (new call will set it)
      delete chartCreating[id];
      setTimeout(() => drawChart(data, row), 100);
      return;
    }
  }
  
  // Check if chart is already being created - if so, skip this call
  if (chartCreating[id]) {
    return;
  }
  
  // Set creating flag now that we're past the retry checks and ready to create
  chartCreating[id] = true;

  /* ─────────────── unpack with compatibility for non-gateway shape ─────── */
  let dMin = [], vMin = [], dMax = [], vMax = [], dDel = [], del = [];
  if (data) {
    const getDate = (x) => (
      x?.d ?? x?.transaction_date ?? x?.transaction_datedel ?? x?.tdate ?? null
    );
    const getNum  = (v) => (v == null ? null : +v);

    // Gateway format: minVolumeData/maxVolumeData with { d, v }
    if (Array.isArray(data.minVolumeData) && Array.isArray(data.maxVolumeData)) {
      dMin = data.minVolumeData.map(x => getDate(x)).filter(Boolean);
      vMin = data.minVolumeData.map(x => getNum(x?.v ?? x?.min_v)).filter(v => v != null);
      dMax = data.maxVolumeData.map(x => getDate(x)).filter(Boolean);
      vMax = data.maxVolumeData.map(x => getNum(x?.v ?? x?.max_v)).filter(v => v != null);
    } else if (Array.isArray(data.averageVolumeData)) {
      // Non-gateway format: averageVolumeData with { transaction_date, average_volume }
      const dAvg = data.averageVolumeData.map(x => getDate(x)).filter(Boolean);
      const vAvg = data.averageVolumeData.map(x => getNum(x?.average_volume)).filter(v => v != null);
      // To keep the same chart appearance (two points per day), duplicate as min/max
      dMin = dAvg.slice();
      vMin = vAvg.slice();
      dMax = dAvg.slice();
      vMax = vAvg.slice();
    }

    // Deliveries: accept { d, delivery_sum } or { transaction_datedel, delivery_sum }
    if (Array.isArray(data.deliveryData)) {
      dDel = data.deliveryData.map(x => getDate(x));
      del  = data.deliveryData.map(x => getNum(x?.delivery_sum));
    }
  }

  /* limit to last 6 months */
  const cutoff = new Date();
  cutoff.setMonth(cutoff.getMonth() - 6);
  const filterPairs = (dates, values) => {
    const nd = [], nv = [];
    for (let i = 0; i < dates.length; i++) {
      const t = new Date(dates[i]);
      if (!isNaN(t) && t >= cutoff) { nd.push(dates[i]); nv.push(values[i]); }
    }
    return [nd, nv];
  };
  [dMin, vMin] = filterPairs(dMin, vMin);
  [dMax, vMax] = filterPairs(dMax, vMax);
  [dDel, del ] = filterPairs(dDel, del);

  /* master list of all date labels (sorted ASC) */
  const all = [...new Set([...dMin, ...dMax, ...dDel])]
                .sort((a,b) => new Date(a) - new Date(b));

  /* build one scatter dataset that contains *two* points per day */
  const scatterData = [];
  all.forEach(d => {
    const iMin = dMin.indexOf(d);
    if (iMin !== -1) scatterData.push({ x: d, y: vMin[iMin] });

    const iMax = dMax.indexOf(d);
    if (iMax !== -1) scatterData.push({ x: d, y: vMax[iMax] });
  });

  /* deliveries: align with the same X-axis labels */
  const deliveries = all.map(date => {
    const j = dDel.indexOf(date);
    return j !== -1 ? del[j] : null;
  });

  // Only destroy existing chart if it exists and we have valid data to replace it with
  // If chart exists and we have no data, just resize it instead
  if (registry[id]) {
    // If we have valid data points, destroy and recreate
    if (scatterData.length > 0 || deliveries.some(d => d != null)) {
      destroyChart(id);
    } else {
      delete chartCreating[id]; // Clear flag since we're not creating
      resizeChart(id);
      return;
    }
  }

  // Compute dynamic Y range
  const volumeMax = Math.max(
    0,
    ...(vMin.length ? vMin : [0]),
    ...(vMax.length ? vMax : [0])
  );
  const volumeMin = Math.min(
    ...(vMin.length ? vMin : [Infinity]),
    ...(vMax.length ? vMax : [Infinity])
  );
  const deliveriesMax = Math.max(0, ...deliveries.filter(v => v != null).map(v => +v));

  // Try to read capacity from the row's nav (set by buildChild)
  let capacity = 0;
  try {
    const nav = document.querySelector(`.navigation-item1${row}`)?.closest('nav');
    if (nav?.dataset?.capacity) {
      capacity = Number(String(nav.dataset.capacity).replace(/,/g, '')) || 0;
    }
  } catch {}

  const mode = chartModes[row] || 'volume';
  const isDeliveries = mode === 'deliveries';
  let ySuggestedMax = undefined;
  let ySuggestedMin = undefined;
  const yScaleOptions = {};
  if (isDeliveries) {
    yScaleOptions.beginAtZero = true;
    ySuggestedMax = Number.isFinite(deliveriesMax) ? Math.ceil(deliveriesMax * 1.1) : undefined;
  } else {
    yScaleOptions.beginAtZero = false;
    if (Number.isFinite(volumeMin) && volumeMin !== Infinity) {
      ySuggestedMin = Math.max(0, Math.floor(volumeMin * 0.95));
    }
    if (Number.isFinite(volumeMax)) ySuggestedMax = Math.ceil(volumeMax * 1.05);
  }
  if (Number.isFinite(ySuggestedMax)) yScaleOptions.suggestedMax = ySuggestedMax;
  if (Number.isFinite(ySuggestedMin)) yScaleOptions.suggestedMin = ySuggestedMin;

  registry[id] = new Chart(canvas, {
    data : {
      datasets : [
        {
          type       : 'line',
          label      : 'Volume (Min & Max)',
          data       : scatterData,
          pointRadius: 3,
          parsing    : { xAxisKey: 'x', yAxisKey: 'y' },  
          borderColor     : 'rgb( 54,162,235)',      // teal line
          backgroundColor : 'rgba(54,162,235,0.15)', // light fill under points
          tension    : 0.1,
          hidden     : (mode === 'deliveries')
        },
        {
          type : 'bar',                // change to 'line' if you prefer
          label: 'Deliveries',
          data : deliveries,
          backgroundColor : 'rgba(255,159,64,0.35)', // orange bars
          borderColor     : 'rgb(255,159,64)',       // bar outline
          borderWidth     : 1,
          hidden          : (mode !== 'deliveries')
        }
      ]
    },
    options : {               // because scatter objects carry x & y
      responsive: true,
      maintainAspectRatio: false,
      resizeDelay: 200,  // Debounce resize to handle modal animations
      scales  : {
        x : { type:'category', labels: all },
        y : yScaleOptions
      },
      plugins : {
        legend:{
          position:'top',
          onClick: (e, legendItem, legend) => {
            const idx = legendItem?.datasetIndex ?? 0;
            const mode = (idx === 1) ? 'deliveries' : 'volume';
            setChartMode(row, mode);
          }
        }
      }
    }
  });
  // Clear the creating flag now that chart is done
  delete chartCreating[id];
}


/* 2.b. runtime toggle -------------------------------------------------- */
export function setChartMode(row, mode) {
  const normalized = (mode === 'deliveries') ? 'deliveries' : 'volume';
  chartModes[row] = normalized;
  const id = `chart-${row}`;
  const chart = registry[id];
  if (!chart) return;
  const isDeliveries = normalized === 'deliveries';
  if (Array.isArray(chart.data?.datasets)) {
    if (chart.data.datasets[0]) chart.data.datasets[0].hidden = isDeliveries;      // volume line
    if (chart.data.datasets[1]) chart.data.datasets[1].hidden = !isDeliveries;     // deliveries bars
  }
  // Adjust axis policy when toggling
  if (chart.options?.scales?.y) {
    if (isDeliveries) {
      chart.options.scales.y.beginAtZero = true;
      delete chart.options.scales.y.suggestedMin;
    } else {
      chart.options.scales.y.beginAtZero = false;
      // suggestedMin/Max will be recomputed on next draw, but keep previous if set
    }
  }
  try { chart.update('none'); } catch { chart.update(); }
}


/* 3. temperature chart -------------------------------------------*/
export async function drawTempChart(data, row) {
  await ensureChartJs();
  // Accept both flat { averagetempData } and nested { response3: { averagetempData } }
  const series = Array.isArray(data?.averagetempData)
    ? data.averagetempData
    : (Array.isArray(data?.response3?.averagetempData) ? data.response3.averagetempData : null);
  if (!series) return;

  const id     = `tempchart-${row}`;
  const canvas = byId(id);

  /* ► same guard as above */
  if (!(canvas instanceof HTMLCanvasElement) || !canvas.getContext?.('2d')) {
    requestAnimationFrame(() => drawTempChart(data, row));
    return;
  }
  
  /* wait until the container has dimensions (modal might still be animating) */
  const container = canvas.closest('.chart1') || canvas.parentElement;
  if (container) {
    const rect = container.getBoundingClientRect();
    const hasDimensions = rect.width > 0 && rect.height > 0;
    const isVisible = container.offsetParent !== null && 
                     window.getComputedStyle(container).visibility !== 'hidden' &&
                     window.getComputedStyle(container).display !== 'none';
    
    if (!hasDimensions || !isVisible) {
      setTimeout(() => drawTempChart(data, row), 100);
      return;
    }
  }

  const cutoff = new Date();
  cutoff.setMonth(cutoff.getMonth() - 6);
  const sorted = [...series]
    .filter(x => {
      const t = new Date(x.transaction_date);
      return !isNaN(t) && t >= cutoff;
    })
    .sort((a,b) => new Date(a.transaction_date) - new Date(b.transaction_date));

  destroyChart(id);

  registry[id] = new Chart(canvas, {
    type : 'line',
    data : {
      labels   : sorted.map(x => x.transaction_date),
      datasets : [{
        label : 'Temperature',
        data  : sorted.map(x => +x.average_temperature)
      }]
    },
    options : { responsive:true }
  });
}
