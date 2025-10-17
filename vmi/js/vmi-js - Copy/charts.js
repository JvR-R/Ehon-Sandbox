/* /vmi/js/charts.js ---------------------------------------------- *
 * Owns every interaction with Chart.js                             *
 *------------------------------------------------------------------*/

import { byId } from './api.js';

const registry   = Object.create(null);   // canvas-id → Chart instance
let   chartReady = null;                  // singleton loader promise

/* 0. destroyChart – clean re-draw -------------------------------- */
export function destroyChart(id) {
  if (registry[id]) { registry[id].destroy(); delete registry[id]; }
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

/* 2. volume / delivery combo chart --------------------------------*/
export async function drawChart(data, row) {
  await ensureChartJs();

  const id     = `chart-${row}`;
  const canvas = byId(id);

  /* ► retry next frame until a 2-d context is available */
  if (!(canvas instanceof HTMLCanvasElement) || !canvas.getContext?.('2d')) {
    requestAnimationFrame(() => drawChart(data, row));
    return;
  }

  /* … data-massaging logic unchanged … */
  let dAvg = [], vAvg = [], dTc = [], vTc = [], dDel = [], del = [];
  if (data) {
    if (Array.isArray(data.averageVolumeData)) {
      dAvg = data.averageVolumeData.map(x => x.transaction_date);
      vAvg = data.averageVolumeData.map(x => +x.average_volume);
    }
    if (Array.isArray(data.averagetcData)) {
      dTc = data.averagetcData.map(x => x.transaction_date);
      vTc = data.averagetcData.map(x => +x.tc_volume);
    }
    if (Array.isArray(data.deliveryData)) {
      dDel = data.deliveryData.map(x => x.transaction_datedel);
      del  = data.deliveryData.map(x => +x.delivery_sum);
    }
  }

  const all = [...new Set([...dAvg, ...dTc, ...dDel])].sort();
  const pad = () => Array(all.length).fill(null);
  const aVol = pad().map((_, i) => vAvg[dAvg.indexOf(all[i])] ?? null);
  const tVol = pad().map((_, i) => vTc[dTc.indexOf(all[i])] ?? null);
  const dVol = pad().map((_, i) => del [dDel.indexOf(all[i])] ?? null);

  destroyChart(id);

  registry[id] = new Chart(canvas, {
    type : 'line',
    data : {
      labels   : all,
      datasets : [
        { label: 'Minimum Volume',   data: aVol },
        { label: 'Corrected Volume', data: tVol },
        { label: 'Deliveries',       data: dVol }
      ]
    },
    options : { responsive: true }
  });
}

/* 3. temperature chart -------------------------------------------*/
export async function drawTempChart(data, row) {
  await ensureChartJs();
  if (!data || !Array.isArray(data.averagetempData)) return;

  const id     = `tempchart-${row}`;
  const canvas = byId(id);

  /* ► same guard as above */
  if (!(canvas instanceof HTMLCanvasElement) || !canvas.getContext?.('2d')) {
    requestAnimationFrame(() => drawTempChart(data, row));
    return;
  }

  const sorted = [...data.averagetempData]
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
