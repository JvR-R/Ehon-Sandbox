/* /vmi/js/childRow.js — single-shot loader on child-row open
 * Builds UI and loads EVERYTHING once (per row) right after the row is inserted.
 * After that, tab switches are instant (no extra network).
 */

import { byId, qs, fetchJSON } from './api.js';
import { navColor, toast } from './ui.js';
import { drawChart, drawTempChart, setChartMode } from './charts.js';
import { initGatewayCfg } from './gateway_cfg.js';

/* tiny helpers */
const $one = (sel, root = document) => root.querySelector(sel);
const $all = (sel, root = document) => [...root.querySelectorAll(sel)];
const html = (h) => {
  const t = document.createElement('template');
  t.innerHTML = h.trim();
  return t.content.firstElementChild;
};

/* caches so a row loads only once */
const gwCfgCache   = Object.create(null);   // gateway_config bundle (incl. last_tx)
const chartCache   = Object.create(null);   // gateway_chart or objects.response2
const tempCache    = Object.create(null);   // objectsTemp.response3
const txCache      = Object.create(null);   // last transactions for MCS/GW
// Promise guards (one-shot per row / per resource)
const loaderPromise = Object.create(null);
const ddCache = Object.create(null);  // dropdowns_config cache (by case)

/* Helper function to generate unique cache key for a tank */
function getCacheKey(nav) {
  const uid = nav.dataset.uid;
  const tankNo = nav.dataset.tankNo;
  const tankDeviceId = nav.dataset.tankDeviceId;
  const csType = nav.dataset.csType;
  
  let cacheKey;
  if (csType === 'EHON_GATEWAY' && tankDeviceId) {
    cacheKey = `${uid}-${tankDeviceId}`;
  } else {
    cacheKey = `${uid}-${tankNo}`;
  }
  
  // console.log(`[Cache Debug] Generated cache key: ${cacheKey} for UID: ${uid}, Tank: ${tankNo}, DeviceId: ${tankDeviceId}, Type: ${csType}`);
  return cacheKey;
}

/* Invalidate cache for a specific row */
export function invalidateRowCache(row) {
  const firstNavItem = document.querySelector(`.navigation-item1${row}`);
  const nav = firstNavItem ? firstNavItem.closest('nav') : null;
  if (!nav) return;
  
  const cacheKey = getCacheKey(nav);
  // console.log(`[Cache] Invalidating cache for row ${row}, key: ${cacheKey}`);
  
  delete gwCfgCache[cacheKey];
  delete chartCache[cacheKey];
  delete tempCache[cacheKey];
  delete txCache[cacheKey];
  delete loaderPromise[cacheKey];
}

/* Syncing state management */
const syncingState = Object.create(null); // Track syncing state per row

// Syncing state management functions
export function setSyncingState(row, isSyncing, message = '') {
  if (isSyncing) {
    syncingState[row] = { isSyncing, message, startTime: Date.now() };
    setupSyncingTimeout(row);
  } else {
    cancelSyncingTimeout(row);
    syncingState[row] = { isSyncing, message, startTime: Date.now() };
  }
  updateSyncingUI(row);
}

export function getSyncingState(row) {
  return syncingState[row] || { isSyncing: false, message: '', startTime: 0 };
}

export function clearSyncingState(row) {
  // console.log(`Clearing syncing state for row ${row}`);
  cancelSyncingTimeout(row);
  delete syncingState[row];
  updateSyncingUI(row);
  // console.log(`Syncing state cleared for row ${row}`);
}

// Timeout handler for syncing operations
export function handleSyncingTimeout(row, operation = 'sync') {
  clearSyncingState(row);
  toast(`${operation} operation timed out`, 'error');
}

// Check if syncing is in progress for a row
export function isSyncing(row) {
  return getSyncingState(row).isSyncing;
}

// Cleanup function to clear all syncing states (useful for page unload)
export function cleanupAllSyncing() {
  Object.keys(syncingState).forEach(row => {
    clearSyncingState(row);
  });
}

// Set up page unload cleanup
if (typeof window !== 'undefined') {
  window.addEventListener('beforeunload', () => {
    cleanupAllSyncing();
  });
}

// Debug function to test syncing state (can be removed in production)
export function testSyncingState(row) {
  // console.log('Current syncing state:', getSyncingState(row));
  // console.log('Is syncing:', isSyncing(row));
}

// Set up a global timeout for syncing operations (5 minutes)
function setupSyncingTimeout(row) {
  const timeoutId = setTimeout(() => {
    const state = getSyncingState(row);
    if (state.isSyncing) {
      handleSyncingTimeout(row, 'Device sync');
    }
  }, 5 * 60 * 1000); // 5 minutes
  
  // Store timeout ID for potential cancellation
  if (syncingState[row]) {
    syncingState[row].timeoutId = timeoutId;
  }
}

// Cancel syncing timeout for a row
function cancelSyncingTimeout(row) {
  const state = getSyncingState(row);
  if (state.timeoutId) {
    clearTimeout(state.timeoutId);
    delete state.timeoutId;
  }
}

function updateSyncingUI(row) {
  const state = getSyncingState(row);
  const nav = document.querySelector(`.navigation-item1${row}`)?.closest('nav');
  if (!nav) return;

  // console.log(`Updating UI for row ${row}, syncing: ${state.isSyncing}`);

  // Update button states - select buttons specifically for this row
  const buttons = document.querySelectorAll(`.child_details [data-row-index="${row}"]`);
  // console.log(`Found ${buttons.length} buttons for row ${row}`);
  
  // Also try to find buttons within the specific row context
  const allChildDetails = document.querySelectorAll('.child_details');
  let contextButtons = [];
  for (const childDetail of allChildDetails) {
    if (childDetail.querySelector(`[data-row-index="${row}"]`)) {
      contextButtons = childDetail.querySelectorAll('button');
      break;
    }
  }
  // console.log(`Found ${contextButtons.length} context buttons for row ${row}`);
  
  const allButtons = [...buttons, ...contextButtons];
  allButtons.forEach(btn => {
    if (state.isSyncing) {
      btn.disabled = true;
      // Only store original text if we haven't already
      if (!btn.dataset.originalText) {
        btn.dataset.originalText = btn.textContent;
      }
      btn.textContent = 'Syncing...';
    } else {
      btn.disabled = false;
      // Always restore original text when syncing is done
      if (btn.dataset.originalText) {
        // console.log(`Restoring original text for button: ${btn.dataset.originalText}`);
        btn.textContent = btn.dataset.originalText;
        delete btn.dataset.originalText;
      } else {
        // Fallback: restore common button texts based on button class
        // console.log(`Using fallback text for button class: ${btn.className}`);
        if (btn.classList.contains('button-js')) {
          btn.textContent = 'Update';
        } else if (btn.classList.contains('button-js2')) {
          btn.textContent = 'Update';
        } else if (btn.classList.contains('button-js3')) {
          btn.textContent = 'Update';
        } else if (btn.classList.contains('button-gwsave')) {
          btn.textContent = 'Update';
        } else if (btn.classList.contains('button-gwports')) {
          btn.textContent = 'Update';
        } else if (btn.classList.contains('button-gwalert')) {
          btn.textContent = 'Update';
        } else if (btn.classList.contains('button-gwtanks')) {
          btn.textContent = 'Update';
        }
      }
    }
  });

  // Show/hide syncing overlay
  const overlay = document.querySelector(`.syncing-overlay-${row}`);
  if (state.isSyncing) {
    if (!overlay) {
      const newOverlay = html(`
        <div class="syncing-overlay-${row}" style="
          position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
          background: rgba(0,0,0,0.5); z-index: 9999; display: flex; 
          align-items: center; justify-content: center; color: white; font-size: 18px;">
          <div style="text-align: center;">
            <div class="spinner" style="margin: 0 auto 20px;"></div>
            <div>${state.message || 'Syncing with device...'}</div>
          </div>
        </div>
      `);
      document.body.appendChild(newOverlay);
    } else {
      const messageEl = overlay.querySelector('div:last-child');
      if (messageEl) messageEl.textContent = state.message || 'Syncing with device...';
    }
  } else {
    if (overlay) {
      overlay.remove();
    }
  }
}

/*───────────────────────────────────────────────────────────────────
  0) Fetchers + back-compat shims
  (These are what unifiedLoadOnce() uses; shims keep main.js working.)
───────────────────────────────────────────────────────────────────*/
async function fetchGatewayBundle(uid, tankDeviceId, clientId) {
  return fetchJSON('/vmi/api/gateway_config.php?' +
    qs({ uid, tank_device_id: tankDeviceId, client_id: clientId }));
}

async function fetchGatewayChart(uid, tankId) {
  // return chart dataset; unifiedLoadOnce() calls drawChart() itself
  return fetchJSON('/vmi/api/gateway_chart.php?' + qs({ uid, tank_id: tankId }));
}

async function fetchObjects(uid, tank_no) {
  return fetchJSON('/vmi/clients/objects?' + qs({ uid, tank_no }), {}, 20_000);
}

async function fetchObjectsTemp(uid, tank_no) {
  return fetchJSON('/vmi/clients/objectsTemp?' + qs({ uid, tank_no }));
}

/* Back-compat exports expected by main.js — now just trigger the
   unified one-shot loader; caches prevent any re-fetching. */
export async function fetchChartData(uid, tank_no, row) {
  await unifiedLoadOnce(row);
}
export async function fetchChartDataMCS(uid, tank_no, cs_type, tank_name, site_name, site_id, row) {
  await unifiedLoadOnce(row);
}
export async function fetchGatewayData(uid, tankDeviceId, row) {
  await unifiedLoadOnce(row);
}

/* Optional: keep the old name around if something else imports it.
   It draws immediately; safe to leave, not used by unified loader. */
async function fetchGatewayChartData(uid, tankId, row) {
  const data = await fetchJSON('/vmi/api/gateway_chart.php?' + qs({ uid, tank_id: tankId }));
  drawChart(data, row);
}
// === ADD: exact-order sender (Option B) + guards ===

async function waitForHttp200(url, { timeoutMs = 8000, intervalMs = 300 } = {}) {
  const until = Date.now() + timeoutMs;
  while (Date.now() < until) {
    const bust = (url.includes("?") ? "&" : "?") + "_ts=" + Date.now();
    try {
      const res = await fetch(url + bust, { method: "HEAD", cache: "no-store" });
      if (res.ok) return true;
    } catch { /* ignore */ }
    await new Promise(r => setTimeout(r, intervalMs));
  }
  throw new Error("TANKS.json not reachable yet");
}

async function sendRawCommand(body) {
  const res = await fetch("/backend/gateway/command/", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRFToken": (typeof getCookie === "function" ? getCookie("csrftoken") : "")
    },
    body: JSON.stringify(body)
  });
  const text = await res.text();
  if (!res.ok) throw new Error(text);
  try {
    return JSON.parse(text);
  } catch {
    return { ok: true };
  }
}

export async function afterTanksSaved(row) {
  // Row context
  const firstNav = document.querySelector(`.navigation-item1${row}`);
  const nav = firstNav && firstNav.closest("nav");
  if (!nav) throw new Error("Row context not found");

  const uid = Number(nav.dataset.uid);
  const tankDeviceId = Number(nav.dataset.tankDeviceId || 0);

  // Start syncing state
  setSyncingState(row, true, 'Preparing tank configuration...');

  try {
    // Dropdown-selected chart name → safe file slug
    const chartSel = document.getElementById(`stchart-${row}`) || document.getElementById(`stchart_id-${row}`);
    const chartName = chartSel?.options[chartSel.selectedIndex]?.text?.trim() || "";
    const chartPath = `Charts/${chartName}.json`;          // note: lowercase 'charts'
    const productsPath = `gateway/cfg/${uid}/products.json`;
    const tanksPath    = `gateway/cfg/${uid}/TANKS.json`;

    // Public URLs (HTTPS to avoid mixed content)
    const base = "https://ehon.com.au/api-v1/download.php?f=";
    const chartUrl    = base + chartPath;
    const productsUrl = base + productsPath;
    const tanksUrl    = base + tanksPath;

    // Ensure products.json exists (and override ULP if needed)
    const prodSel = document.getElementById(`product_name-${row}`);
    const productId = Number(prodSel?.value || 0);
    setSyncingState(row, true, 'Preparing product configuration...');
    {
      const res = await fetch('/vmi/api/gateway_update.php?op=products', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid, product_id: productId })
      });
      const text = await res.text();
      if (!res.ok) throw new Error(text);
      let data;
      try { data = JSON.parse(text); } catch {}
      if (!data || data.ok !== true) throw new Error('products.json write failed');
    }

    // Sanity: make sure the files we control are reachable before sending
    setSyncingState(row, true, 'Verifying configuration files...');
    await waitForHttp200(`${location.origin}/api-v1/download.php?f=${encodeURIComponent(productsPath)}`);
    await waitForHttp200(`${location.origin}/api-v1/download.php?f=${encodeURIComponent(tanksPath)}`);

    // Prefer MAC if we have it; otherwise send ids the backend can resolve
    let deviceId = null;
    try {
      const cacheKey = getCacheKey(nav);
      const bundle = (window.gwCfgCache && gwCfgCache[cacheKey]) ? await gwCfgCache[cacheKey] : null;
      deviceId = getDeviceIdFromBundle(bundle) || nav.dataset.deviceId || null;
    } catch {}

    const addr = deviceId ? { device_id: deviceId } : { uid, tank_device_id: tankDeviceId || undefined };

    // Helper: send one key and wait for a specific topic suffix
    const sendOne = async (key, url, suffix, timeout = 30) => {
      setSyncingState(row, true, `Sending ${key} configuration...`);
      const payloadRaw = JSON.stringify({ [key]: url }, [key]);
      const resp = await sendRawCommand({ ...addr, payload_raw: payloadRaw, wait_for: suffix, wait_timeout: timeout });
      if (resp?.matched === false) throw new Error(resp?.reply?.payload || `${suffix} failed`);
      if (resp?.ok !== true) throw new Error(`No ACK on ${suffix}`);
      return resp;
    };

    // 1) chart → wait for CHARTS_ENQ_OK on gateway/<mac>/chart
    setSyncingState(row, true, 'Sending chart configuration...');
    await sendOne("chart", chartUrl, "chart", 25);

    // 2) products → wait for PRODUCTS_OK on gateway/<mac>/products
    setSyncingState(row, true, 'Sending products configuration...');
    await sendOne("products", productsUrl, "products", 25);

    // 3) tanks → wait for TANKS_OK (if you want the final gate), else drop wait_for
    setSyncingState(row, true, 'Sending tank configuration...');
    await sendOne("tanks", tanksUrl, "tanks", 25);

    // Success - clear syncing state
    clearSyncingState(row);
    toast('Tank configuration synced successfully', 'success');
    return true;

  } catch (error) {
    // Error - clear syncing state and show error
    clearSyncingState(row);
    toast(`Tank sync failed: ${error.message}`, 'error');
    throw error;
  }
}


// === NEW: exported hook to call right after op=ports succeeds ===
export async function afterPortsSaved(row) {
  const nav = document.querySelector(`.navigation-item1${row}`)?.closest("nav");
  if (!nav) throw new Error("Row context not found");
  const uid = Number(nav.dataset.uid);

  // Start syncing state
  setSyncingState(row, true, 'Preparing port configuration...');

  try {
    setSyncingState(row, true, 'Verifying port configuration file...');
    const urlCheck = `${location.origin}/api-v1/download.php?f=${encodeURIComponent(`gateway/cfg/${uid}/ports.json`)}`;
    await waitForHttp200(urlCheck);
    
    setSyncingState(row, true, 'Sending port configuration...');
    const payloadRaw = JSON.stringify({
      ports: `https://ehon.com.au/api-v1/download.php?f=gateway/cfg/${uid}/ports.json`
    }, ["ports"]);

    const body = { uid, payload_raw: payloadRaw, wait_for: "ports", wait_timeout: 30 };
    const resp = await sendRawCommand(body);
    if (resp?.matched === false) throw new Error(resp?.reply?.payload || "Device reported failure");
    if (resp?.ok === false) throw new Error("No ACK from device");
    
    // Success - clear syncing state
    clearSyncingState(row);
    toast('Port configuration synced successfully', 'success');
    return resp;

  } catch (error) {
    // Error - clear syncing state and show error
    clearSyncingState(row);
    toast(`Port sync failed: ${error.message}`, 'error');
    throw error;
  }
}


function getDeviceIdFromBundle(r) {
  if (!r || typeof r !== "object") return null;

  const isLikelyMac = (s) => {
    if (typeof s !== "string") return false;
    const n = s.replace(/[:\-]/g, "").toUpperCase();  // strip : -
    // Only accept 6 or 12 chars, hex, and MUST include at least one A–F (rejects pure digits like 20250812)
    return (n.length === 6 || n.length === 12) && /^[0-9A-F]+$/.test(n) && /[A-F]/.test(n);
  };

  // Only check well-known fields; do NOT deep-scan arbitrary strings
  const cands = [
    r.device_id, r.deviceId, r.DeviceId, r.mac,
    r.console?.device_id, r.console?.deviceId, r.console?.mac,
    r.gateway?.device_id, r.gateway?.deviceId, r.gateway?.mac,
    r.config?.console?.device_id, r.config?.console?.deviceId,
  ];
  for (const v of cands) {
    if (isLikelyMac(v)) return v.replace(/[:\-]/g, "").toUpperCase();
  }
  return null;
}



/*───────────────────────────────────────────────────────────────────
  1) FMS dropdown helpers (used by Config tab)
───────────────────────────────────────────────────────────────────*/
async function populateFMSDevices(row, idx) {
  const sel = byId(`fms_type-${row}-${idx}`); if (!sel) return;
  sel.innerHTML = '';
  try {
    const r = await fetchJSON('/vmi/clients/dropdowns_config.php?case=get_fms_devices');
    (r.devices ?? []).forEach((d) => {
      if (+d.device_id < 200) {
        const o = document.createElement('option');
        o.value = d.device_id;
        o.textContent = d.device_name;
        sel.appendChild(o);
      }
    });
  } catch (e) { console.error('FMS devices', e); }
}
function populateFMSPorts(row, idx) {
  const sel = document.getElementById(`fms_port-${row}-${idx}`); if (!sel) return;
  sel.innerHTML = '';
  [[0,'NO DEVICE…'], [5,'Port A'], [6,'Port B'], [3,'Port C']].forEach(([v,t]) => {
    const o = document.createElement('option'); o.value = v; o.textContent = t; sel.appendChild(o);
  });
}
export async function generateFMSSections(row, uid, n, fmsData = []) {
  const container = $one(`.fms-container-${row}`); if (!container) return;
  container.innerHTML = '';
  for (let i = 1; i <= n; i++) {
    container.insertAdjacentHTML('beforeend', `
      <div class="tankginfo_text" id="fms-${row}-${i}" style="display:block;margin-bottom:10px;">
        <div class="card pd-28px">
          <div class="grid-2-columns" style="display:grid;grid-template-columns:0.82fr 1fr;">
            <label>FMS Port ${i}:</label>
            <select class="recip" style="max-width:10rem;" id="fms_port-${row}-${i}" name="fms_port" data-uid="${uid}"></select>
            <label>FMS Device ${i}:</label>
            <select class="recip" style="max-width:10rem;" id="fms_type-${row}-${i}" name="fms_type"></select>
            <label style="margin-bottom:10px">FMS ID ${i}:</label>
            <input class="recip" style="max-width:10rem;" id="fms_id-${row}-${i}" name="fms_id" type="number" value="0">
          </div>
        </div>
      </div>`);
    populateFMSPorts(row, i);
    await populateFMSDevices(row, i);
    const f = fmsData[i - 1] || {};
    byId(`fms_port-${row}-${i}`).value = f.fms_port ?? 0;
    byId(`fms_type-${row}-${i}`).value = f.fms_type ?? 0;
    byId(`fms_id-${row}-${i}`).value   = f.fms_id   ?? 0;
  }
}
function wireChartDisable(row) {
  const sel    = byId(`stchart-${row}`) || byId(`stchart_id-${row}`);
  const shape  = byId(`tank_shape-${row}`);
  const height = byId(`height-${row}`);
  const width  = byId(`width-${row}`);
  const depth  = byId(`depth-${row}`);
  const setDis = (on) => [shape, height, width, depth].forEach(el => { if (el) el.disabled = !!on; });
  const check  = () => setDis(sel && sel.value && sel.value !== '0');
  
  if (sel) {
    let previousValue = sel.value;
    
    sel.addEventListener('change', (e) => {
      // Check if a chart is being selected (not "0")
      if (sel.value && sel.value !== '0') {
        // Check if any shape dimensions have non-zero values
        const hasNonZeroValues = 
          (height && parseFloat(height.value) !== 0) ||
          (width && parseFloat(width.value) !== 0) ||
          (depth && parseFloat(depth.value) !== 0);
        
        if (hasNonZeroValues) {
          const confirmed = confirm('Selecting a strapping chart will disable the tank shape');
          if (confirmed) {
            // Reset values to 0
            if (height) height.value = 0;
            if (width) width.value = 0;
            if (depth) depth.value = 0;
            // Disable fields
            check();
            previousValue = sel.value;
          } else {
            // Revert to previous selection
            sel.value = previousValue;
            return;
          }
        } else {
          check();
          previousValue = sel.value;
        }
      } else {
        check();
        previousValue = sel.value;
      }
    });
    
    check(); // initial state
  }
}

/*───────────────────────────────────────────────────────────────────
  2) Child-row factory
───────────────────────────────────────────────────────────────────*/
export function buildChild(d, ctx) {
  const { uid, cs_type, site_id, mcs_id, mcs_idpro, mcs_idlite, client_id, row } = ctx;
  const isGw      = cs_type === 'EHON_GATEWAY';
  const isMcs     = cs_type === 'MCS_PRO' || cs_type === 'MCS_LITE';
  const tank_no   = d.tank_id;
  const tank_name = d.tank_name;
  const capacity  = String(d.capacity).replace(/,/g, '');
  const prodName  = d.product_name;

  /* NAV bar */
  const nav = html(`
    <nav class="nav-items" role="navigation" style="display:flex;">
      <button class="navigation-item1${row}" style="color:red">Information</button>
      <button class="navigation-item2${row}">Alerts</button>
      <button class="navigation-item3${row}">Configuration</button>
      ${(isGw || isMcs) ? `<button class="navigation-item4${row}">Transactions</button>`
                        : `<button class="navigation-item4${row}">Temperature</button>`}
    </nav>`);
  nav.addEventListener('click', (e) => {
    const btn = e.target.closest('[class^="navigation-item"]');
    if (!btn) return;
    const n = btn.className.match(/navigation-item(\d)/)[1];
    showTab(row, n);
  });
  // stash context for single-shot loader
  nav.dataset.uid           = uid;
  nav.dataset.tankNo        = tank_no;
  nav.dataset.tankDeviceId  = ctx.tank_device_id || '';
  nav.dataset.csType        = cs_type;
  nav.dataset.prodName      = prodName;
  nav.dataset.siteId        = site_id;
  nav.dataset.clientId      = client_id;
  // Provide capacity for y-axis scaling
  nav.dataset.capacity      = capacity;

  /* Information tab */
  const infoTab = html(`
    <section class="info-pane" style="display:flex;gap:1.5rem;">
      <div class="left-info">
        <div class="loading-overlay"><div class="spinner"></div></div>
        <div class="info_text" style="visibility:hidden">
          <div>Company Name:<strong> ${d.client_name}</strong></div>
          <div id="estimatedDaysLeft-${row}">Estimated days left: <strong>N/A</strong></div>
          ${cs_type==='MCS_PRO' ? `
            <div><a target="_blank" href="https://new.mcstsm.com/sites/${mcs_idpro}/${mcs_id}">More Information</a></div>` : ''}
          ${cs_type==='MCS_LITE' ? `
            <div><a target="_blank" href="https://mcs-connect.com/sites/${mcs_idlite}/details/${mcs_id}/">More Information</a></div>` : ''}
        </div>

        <div class="alert-inputs">
          <div class="alert-upd">
            <label>Alert Type:</label>
            <select class="recip" id="alert_type-${row}">
              <option value="0">None</option>
              <option value="1">Falling level</option>
              <option value="2">Raising level</option>
            </select>
            <label>Alert level:</label>
            <input class="recip" id="vol_alert-${row}" type="number" value="0">
            <label>Email:</label>
            <input class="recip" id="email-${row}" type="email">
          </div>
        </div>

        <button class="button-js"
                data-uid="${uid}" data-tank_no="${tank_no}"
                data-site_id="${site_id}" data-row-index="${row}">
          Update
        </button>
      </div>

      <div class="right-info">
        <div class="chart1" id="chart-container-${row}">
          <canvas id="chart-${row}"></canvas>
        </div>
      </div>
    </section>
  `);

  /* Alerts tab */
  const alarmNoRelay = (lab, id) => `
    <label class="lab1">${lab}:</label>
    <input class="recip" id="${id}-${row}" type="number" min="0" style="width:90%;margin-top:5px;">
  `;
  const alarmRow = (img, lab, id, lvl) => `
    <label class="lab1"><img src="/vmi/images/${img}" alt="">${lab}: </label>
    <input class="recip" id="${id}-${row}" type="number" min="0" style="width:90%;margin-top:5px;">
    <select class="relay-select" id="relay-${lvl}-${row}">
      <option value="1">Relay 1</option><option value="2">Relay 2</option>
      <option value="3">Relay 3</option><option value="4">Relay 4</option>
    </select>`;

  let alertsTab;
  if (isGw) {
    alertsTab = html(`
    <div class="alert_info${row}" style="display:none">
      <div class="loading-overlay"><div class="spinner"></div></div>
      <div class="alerts_div" style="visibility:hidden">
        <div class="card pd-28px">
          <div class="grid-2-columns" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:center;">
            ${alarmNoRelay('Critical High Alarm','chigha')}
            ${alarmNoRelay('High Alarm','higha')}
            ${alarmNoRelay('Low Alarm','lowa')}
            ${alarmNoRelay('Critical Low Alarm','clowa')}
          </div>
          <button class="button-gwalert"
            data-uid="${uid}" data-tank_no="${tank_no}"
            data-site_id="${site_id}" data-client_id="${client_id}"
            data-row-index="${row}">
            Update
          </button>
        </div>
      </div>
    </div>`);
  } else if (isMcs) {
    // MCS devices: alerts with NO relay-selects
    alertsTab = html(`
      <div class="alert_info${row}" style="display:none">
        <div class="loading-overlay"><div class="spinner"></div></div>
        <div class="alerts_div" style="visibility:hidden">
          <div class="grid-container">
            <div class="card pd-28px">
              <div class="grid-2-columns" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:center;">
                ${alarmNoRelay('Critical High Alarm','chigha')}
                ${alarmNoRelay('High Alarm','higha')}
                ${alarmNoRelay('Low Alarm','lowa')}
                ${alarmNoRelay('Critical Low Alarm','clowa')}
              </div>
            </div>
          </div>
        </div>
        <button class="button-js3"
                data-uid="${uid}" data-tank_no="${tank_no}"
                data-site_id="${site_id}" data-client_id="${client_id}"
                data-capacity="${capacity}" data-row-index="${row}">
          Update
        </button>
      </div>`);
  } else {
    alertsTab = html(`
      <div class="alert_info${row}" style="display:none">
        <div class="loading-overlay"><div class="spinner"></div></div>
        <div class="alerts_div" style="visibility:hidden">
          <div class="grid-container">
            <div class="grid-2-columns" style="display:grid;grid-template-columns:3fr 3.5fr 1.5fr;justify-items:start;margin-top:.5rem;">
              ${alarmRow('crithigh_icon.png','Critical High Alarm','chigha','hh')}
              ${alarmRow('higha_icon.png'   ,'High Alarm','higha','h')}
              ${alarmRow('lowa_icon.png'    ,'Low Alarm' ,'lowa','l')}
              ${alarmRow('critlow_icon.png' ,'Critical Low Alarm','clowa','ll')}
            </div>
          </div>
        </div>
        <button class="button-js3"
                data-uid="${uid}" data-tank_no="${tank_no}"
                data-site_id="${site_id}" data-client_id="${client_id}"
                data-capacity="${capacity}" data-row-index="${row}">
          Update
        </button>
      </div>`);
  }

  const isGateway = cs_type === 'EHON_GATEWAY';

  /* Configuration tab */
  if (!isGateway) {
    product_select(prodName, row, 1);
  }

  let cfgTab;
  if (isGateway) {
    cfgTab = html(`
      <div class="tank_info${row}" style="display:none">
        <div class="loading-overlay"><div class="spinner"></div></div>
          <div class="card pd-28px" style="min-height:8rem; display:flex; margin-top:1rem; gap:3rem;">
            <div class="gw-tanksinfo">
              <div class="gw-merged">              
                <div style="display: grid; grid-template-columns: 0.82fr 1fr;">
                  <label>Tank Number:</label>
                  <input class="recip" name="tank_number" type="number" value="${tank_no}">
                  <label>Tank Name:</label>
                  <input class="recip" name="tank_name" type="text" value="${tank_name}">
                  <label>Capacity:</label>
                  <input class="recip" id="capacity-${row}" name="capacity" type="number" value="${capacity}">
                  <label>Select Product:</label>
                  <select class="recip" id="product_name-${row}" name="product_name"></select>
                  <label>Strapping Chart:</label>
                  <select class="recip" id="stchart-${row}" name="chart">
                  </select>
                  <label>No TG:</label>
                  <input type="checkbox" id="no_tg-${row}" name="no_tg" style="width:auto;margin-top:5px;">
                </div>
                <div style="display: grid; grid-template-columns: 0.82fr 1fr;">
                  <label>Tank Shape:</label>
                  <select class="recip" id="tank_shape-${row}" name="tank_shape">
                    <option value="0">Vertical</option>
                    <option value="1">Horizontal</option>
                    <option value="2">Rectangular</option>
                  </select>
                  <label>Height:</label>
                  <input class="recip" id="height-${row}" name="height" type="number" value="">
                  <label>Width:</label>
                  <input class="recip" id="width-${row}" name="width" type="number" value="">
                  <label>Depth:</label>
                  <input class="recip" id="depth-${row}" name="depth" type="number" value="">
                </div>
              </div>
              <div style="display:flex;justify-content:center;">
                <button class="button-gwsave"
                  data-uid="${uid}" data-tank_no="${tank_no}"
                  data-site_id="${site_id}" data-row-index="${row}" disabled>Update
                </button>
              </div>
            </div>
            <div class="gw-ports">
              <div style="display:grid;grid-template-columns:0.82fr 1fr;max-width:20rem;">
                <label>Port A Mode:</label>
                <select class="recip" id="mindex_0-${row}" name="mindex_0">
                  <option value="0">DISABLE</option>
                  <option value="1">OCIO</option>
                  <option value="2">MODBUS</option>
                  <option value="3">PIUSI</option>
                  <option value="4">Relay</option>
                  <option value="5">CC</option>
                </select>
                <label>PIUSI ID:</label>
                <input class="recip" id="fms_index0-${row}" name="fms_index0"   type="number"   value="">
                <label>Port B Mode:</label>
                <select class="recip" id="mindex_1-${row}" name="mindex_1">
                  <option value="0">DISABLE</option>
                  <option value="1">OCIO</option>
                  <option value="2">MODBUS</option>
                  <option value="3">PIUSI</option>
                  <option value="5">CC</option>
                </select>
                <label>PIUSI ID:</label>
                <input class="recip" id="fms_index1-${row}" name="fms_index1" type="number" value="">
              </div>
              <button class="button-gwports"
                data-uid="${uid}" data-tank_no="${tank_no}"
                data-site_id="${site_id}" data-row-index="${row}" disabled>Update
              </button>
            </div>
          </div>
        </div>
      </div>
    `);
  } else if (isMcs) {
    // MCS devices: show only informational text in Config tab
    cfgTab = html(`
      <div class="tank_info${row}" style="display:none">
        <div class="loading-overlay"><div class="spinner"></div></div>
        <div style="display:flex;">
          <div class="info_text" style="max-width:28rem;">
            <div class="card pd-28px">
              <div style="display:flex;flex-direction:column;gap:8px;">
                <div><strong>Configuration</strong></div>
                <div>Configuration for MCS devices is managed in the MCS portal.</div>
                <div>Use the Alerts tab to set thresholds. Relay selection is not applicable.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `);
  } else {
    cfgTab = html(`
      <div class="tank_info${row}" style="display:none">
        <div class="loading-overlay"><div class="spinner"></div></div>
        <div style="display:flex;">
          <div class="info_text" style="max-width:20rem;max-height:13.5rem">
            <div class="card pd-28px">
              <div class="grid-2-columns" style="display:grid;grid-template-columns:0.82fr 1fr;">
                <label>Tank Number:</label>
                <input class="recip" name="tank_number" type="number" value="${tank_no}">
                <label>Tank Name:</label>
                <input class="recip" name="tank_name"   type="text"   value="${tank_name}">
                <label>Capacity:</label>
                <input class="recip" id="capacity-${row}" name="capacity" type="number" value="${capacity}">
                <label>Select Product:</label>
                <select class="recip" id="product_name-${row}" name="product_name"></select>
                <label>FMS Number:</label>
                <select class="recip" id="fms_number-${row}" name="fms_number"
                        data-uid="${uid}" data-tank_no="${tank_no}">
                  <option value="0">No FMS</option>
                  <option value="1">1</option><option value="2">2</option><option value="3">3</option>
                </select>
              </div>
            </div>
          </div>

          <div class="right-info">
            <div class="tankginfo_text" id="tg-${row}">
              <div class="card pd-28px">
                <div class="grid-2-columns" style="display:grid;grid-template-columns:0.82fr 1fr;">
                  <label>TG Port:</label>
                  <select class="recip" id="tg_port-${row}" name="tg_port" data-uid="${uid}"></select>
                  <label>TG Device:</label>
                  <select class="recip" id="tg_type-${row}" name="tg_type" disabled></select>
                  <label>Tank Gauge ID:</label>
                  <input class="recip" id="tg_id-${row}"  name="tg_id" type="number" value="0" disabled>
                  <label>Strapping Chart:</label>
                  <select class="recip" id="chart_id-${row}" name="chart_id"></select>
                  <label>Offset:</label>
                  <input class="recip" id="tg_offset-${row}" name="tg_offset" type="number" value="0">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="fms-container-${row}"></div>
        <div class="tanks_div"></div>
        <div style="margin-bottom:4rem">
          <button class="button-js2"
                  data-uid="${uid}" data-tank_no="${tank_no}"
                  data-site_id="${site_id}" data-row-index="${row}" disabled>Update</button>
        </div>
      </div>
    `);
  }

  /* Temperature / Transactions tab */
  const tempTab = html(`
    <div class="temp_info${row}" style="display:none">
      <div class="loading-overlay"><div class="spinner"></div></div>
      <div class="left-info" style="visibility:hidden">
        <div class="info_text" style="max-width:285px">
          <div>Temperature:<strong> ${d.temperature} ºC</strong></div>
          <div id="tc-vol-${row}">Temperature Corrected Vol: <strong>N/A</strong></div>
        </div>
      </div>
      <div class="right-info">
        <div class="chart1" id="tempchart-container-${row}"><canvas id="tempchart-${row}"></canvas></div>
      </div>
    </div>`);

  const txTab = html(`
    <div class="tx_info${row}" style="display:none">
      <div class="loading-overlay"><div class="spinner"></div></div>
      <table id="tx-table-${row}" class="tx-table">
        <thead><tr><th>Date</th><th>Time</th><th>Volume (L)</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>`);

  /* Final assembly */
  const wrap  = html('<div class="child_details"></div>');
  const minfo = html(`<div class="minfo${row}"></div>`);
  minfo.appendChild(infoTab);
  const showTxTab = (isGw || isMcs);
  wrap.append(nav, minfo, alertsTab, cfgTab, (showTxTab ? txTab : tempTab));

  // Fire single unified load on next frame (row is likely already appended)
  requestAnimationFrame(() => unifiedLoadOnce(row));

  return wrap;
}

/*───────────────────────────────────────────────────────────────────
  3) Tab switcher — now only toggles panes (NO network)
───────────────────────────────────────────────────────────────────*/
export function showTab(row, n) {
  const firstNavItem = $one(`.navigation-item1${row}`);
  const nav = firstNavItem ? firstNavItem.closest('nav') : null;
  if (!nav) return;

  const isGw = !!$one(`.navigation-item4${row}`) && $one(`.navigation-item4${row}`).textContent.includes('Transactions');

  // just show requested pane
  $all(`.minfo${row}, .alert_info${row}, .tank_info${row}, .temp_info${row}, .tx_info${row}`)
    .forEach(el => { el.style.display = 'none'; });

  const sel = {
    '1': `.minfo${row}`,
    '2': `.alert_info${row}`,
    '3': `.tank_info${row}`,
    '4': isGw ? `.tx_info${row}` : `.temp_info${row}`
  }[n];

  const pane = $one(sel);
  if (pane) pane.style.display = (isGw && n === '4') ? 'block' : (n === '4' ? 'flex' : 'block');
  navColor(row, n);
}

/*───────────────────────────────────────────────────────────────────
  4) ONE unified loader per row
───────────────────────────────────────────────────────────────────*/

async function unifiedLoadOnce(row) {
  const firstNavItem = $one(`.navigation-item1${row}`);
  const nav = firstNavItem ? firstNavItem.closest('nav') : null;
  if (!nav) return;
  
  const cacheKey = getCacheKey(nav);
  
  // If already loading/loaded, reuse the same promise
  // if (loaderPromise[cacheKey]) return loaderPromise[cacheKey];
  // If already loading/loaded, re-apply config, rewire, redraw, and unmask
  if (loaderPromise[cacheKey]) {
    (async () => {
      const csType = nav.dataset.csType;
      try {
        if (csType === 'EHON_GATEWAY') {
          // 1) Re-apply config UI + dropdowns + TX table
          const r = gwCfgCache[cacheKey] ? await gwCfgCache[cacheKey] : null;
          if (r) {
            if (Array.isArray(r.schart)) chart_dd(null, r.schart, row);
            await product_select(nav.dataset.prodName, row, 1);
            applyGatewayConfig(r, row, nav);
            initGatewayCfg(row);
            // Rebuild transactions table
            const tbody = $one(`#tx-table-${row} tbody`);
            if (tbody) {
              const list = (typeof r.last_tx === 'string') ? JSON.parse(r.last_tx) : (r.last_tx || []);
              tbody.innerHTML = list.map(tx =>
                `<tr><td>${tx.date}</td><td>${tx.time}</td><td>${(+tx.volume).toFixed(2)}</td></tr>`
              ).join('');
            }
          }
          // 2) Redraw charts from cache
          const chartData = chartCache[cacheKey] ? await chartCache[cacheKey] : null;
          if (chartData) drawChart(chartData, row);
          // 3) Unmask panes
          unmaskRow(row, ['minfo','alert_info','tank_info','tx_info']);
        } else {
          if (csType === 'MCS_PRO' || csType === 'MCS_LITE') {
            const series = await chartCache[cacheKey];
            if (series) drawChart(series, row);
            // Fill tx table from cache if present
            const tbody = $one(`#tx-table-${row} tbody`);
            if (tbody) {
              const list = Array.isArray(txCache[cacheKey]) ? txCache[cacheKey] : [];
              tbody.innerHTML = list.map(tx =>
                `<tr><td>${tx.date}</td><td>${tx.time}</td><td>${(+tx.volume).toFixed(2)}</td></tr>`
              ).join('');
            }
            unmaskRow(row, ['minfo','alert_info','tank_info','tx_info']);
          } else {
            const series = await chartCache[cacheKey];
            if (series) drawChart(series, row);
            const tseries = await tempCache[cacheKey];
            if (tseries) drawTempChart(tseries, row);
            unmaskRow(row, ['minfo','alert_info','tank_info','temp_info']);
          }
        }
      } catch (e) {
        console.error('rehydrate-on-reopen', e);
      }
    })();
    return loaderPromise[cacheKey];
  }

  loaderPromise[cacheKey] = (async () => {
    const uid          = nav.dataset.uid;
    const csType       = nav.dataset.csType;
    const tankNo       = Number(nav.dataset.tankNo);
    const tankDeviceId = nav.dataset.tankDeviceId;
    const clientId     = nav.dataset.clientId;

    try {
      if (csType === 'EHON_GATEWAY') {
        // config bundle — cache the PROMISE
        if (!gwCfgCache[cacheKey]) gwCfgCache[cacheKey] = fetchGatewayBundle(uid, tankDeviceId, clientId);
        const r = await gwCfgCache[cacheKey];
        const mac = getDeviceIdFromBundle(r);
        if (mac) nav.dataset.deviceId = mac;

        if (Array.isArray(r.schart)) {
          chart_dd(null, r.schart, row);
        }
        // dropdowns (cached by case) + apply config
        await product_select(nav.dataset.prodName, row, 1);
        applyGatewayConfig(r, row, nav);
        initGatewayCfg(row);

        // transactions table (from r.last_tx)
        const tbody = $one(`#tx-table-${row} tbody`);
        if (tbody) {
          const list = (typeof r.last_tx === 'string') ? JSON.parse(r.last_tx) : (r.last_tx || []);
          tbody.innerHTML = list.map(tx => `<tr><td>${tx.date}</td><td>${tx.time}</td><td>${(+tx.volume).toFixed(2)}</td></tr>`).join('');
        }

        // chart — cache the PROMISE
        if (!chartCache[cacheKey]) chartCache[cacheKey] = fetchGatewayChart(uid, tankNo);
        const chartData = await chartCache[cacheKey];
        drawChart(chartData, row);

        unmaskRow(row, ['minfo','alert_info','tank_info','tx_info']);
      } else {
        // non-gateway
        if (!chartCache[cacheKey]) {
          const r = await fetchObjects(uid, tankNo);
          try { fillinfo(r, row); } catch (e) { console.error('fillinfo', e); }
          chartCache[cacheKey] = Promise.resolve(r.response2); // normalize to promise
          // Cache last transactions for MCS
          if (r && (Array.isArray(r.last_tx) || typeof r.last_tx === 'string')) {
            try { txCache[cacheKey] = Array.isArray(r.last_tx) ? r.last_tx : JSON.parse(r.last_tx); }
            catch { txCache[cacheKey] = []; }
          } else {
            txCache[cacheKey] = [];
          }
        }
        const series = await chartCache[cacheKey];
        drawChart(series, row);

        // For MCS devices, do NOT fetch or render temperature; show Transactions tab instead
        if (csType === 'MCS_PRO' || csType === 'MCS_LITE') {
          // Populate tx table
          const tbody = $one(`#tx-table-${row} tbody`);
          if (tbody) {
            const list = Array.isArray(txCache[cacheKey]) ? txCache[cacheKey] : [];
            tbody.innerHTML = list.map(tx => `<tr><td>${tx.date}</td><td>${tx.time}</td><td>${(+tx.volume).toFixed(2)}</td></tr>`).join('');
          }
          unmaskRow(row, ['minfo','alert_info','tank_info','tx_info']);
        } else {
          if (!tempCache[cacheKey]) tempCache[cacheKey] = fetchObjectsTemp(uid, tankNo);
          const tseries = await tempCache[cacheKey];
          drawTempChart(tseries, row);
          unmaskRow(row, ['minfo','alert_info','tank_info','temp_info']);
        }
      }
    } catch (err) {
      console.error('unifiedLoadOnce', err);
      toast('Failed to load device data', 'error');
      unmaskRow(row, ['minfo','alert_info','tank_info','tx_info','temp_info']);
    }
  })();

  return loaderPromise[cacheKey];
}


/* remove loading overlays and enable buttons for a row */
function unmaskRow(row, kinds) {
  kinds.forEach(kind => {
    const ov = $one(`.${kind}${row} .loading-overlay`);
    if (ov && ov.parentNode) ov.parentNode.removeChild(ov);
  });
  // make info block visible (row-scoped)
  const info = $one(`.minfo${row} .left-info .info_text`);
  if (info) info.style.visibility = '';
  // show alerts content
  const alertsDiv = $one(`.alert_info${row} .alerts_div`);
  if (alertsDiv) alertsDiv.style.visibility = '';
  // enable any update buttons for this row
  document
    .querySelectorAll(`.child_details [data-row-index="${row}"]`)
    .forEach(btn => { btn.disabled = false; });
}

/*───────────────────────────────────────────────────────────────────
  5) Product & chart dropdown loaders (used by config)
  (SINGLE definition — do not duplicate)
───────────────────────────────────────────────────────────────────*/
async function product_select(data, row, cs) {
  try {
    const nav = document.querySelector(`.navigation-item1${row}`)?.closest('nav');
    const client_id = nav?.dataset?.clientId || '';
    const key = `${cs}:${client_id}`; // cache per case+client
    if (!ddCache[key]) {
      ddCache[key] = fetchJSON('/vmi/clients/dropdowns_config?' + qs({ rowIndex: row, case: cs, client_id }));
    }
    const r = await ddCache[key];
    if (r.products) product_dd(data, r.products, row);
    if (r.schart )  {
      // Populate both gateway and non-gateway chart dropdowns
      chart_dd  (/*curr*/ null, r.schart , row); // stchart / stchart_id
      chart_dd2 (/*curr*/ null, r.schart , row); // chart_id (non-gateway)
    }
  } catch (e) { console.error(e); }
}

function product_dd(curr, arr, row) {
  const sel = byId(`product_name-${row}`); if (!sel) return;
  sel.innerHTML = '';
  arr.forEach(a => {
    if (!a.product_id) return;
    const opt = document.createElement('option');
    opt.value = a.product_id;
    opt.textContent = a.product_name;
    if (a.product_name === curr || a.product_id == curr) opt.selected = true;
    sel.appendChild(opt);
  });
}
function chart_dd2(curr, arr, row) {
  const sel = byId(`chart_id-${row}`); if (!sel) return;
  sel.innerHTML = '';
  arr.forEach(a => {
    if (!a.chart_id) return;
    const opt = document.createElement('option');
    opt.value = a.chart_id;
    opt.textContent = a.chart_name;
    if (a.chart_id == curr) opt.selected = true;
    sel.appendChild(opt);
  });
}
function chart_dd(curr, arr, row) {
  const sel = byId(`stchart-${row}`) || byId(`stchart_id-${row}`);
  if (!sel) return;
  sel.innerHTML = '';
  // default "no chart" (manual geometry)
  const none = document.createElement('option');
  none.value = '0';
  none.textContent = '— No strapping chart —';
  sel.appendChild(none);
  // allow different shapes from backend
  arr.forEach(a => {
    const id   = a.chart_id ?? a.id ?? a.chartId;
    const name = a.chart_name ?? a.name ?? a.chartName;
    if (!id || !name) return;
    const opt = document.createElement('option');
    opt.value = String(id);
    opt.textContent = name;
    sel.appendChild(opt);
  });
  if (curr != null) sel.value = String(curr); // optional preselect
}



/*───────────────────────────────────────────────────────────────────
  6) Fillers for non-config info (unchanged)
───────────────────────────────────────────────────────────────────*/
function fillinfo(r, row) {
  const overlay = document.querySelector(`.left-info .loading-overlay`);
  if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);

  const info = document.querySelector(`.left-info .info_text`);
  if (info) info.style.visibility = '';

  const email = byId(`email-${row}`); if (email) email.value = r.mail || '';
  const volal = byId(`vol_alert-${row}`); if (volal) volal.value = r.volal || '';
  const typeSel = byId(`alert_type-${row}`); if (typeSel && r.volal_type) typeSel.value = r.volal_type;

  if (r.high_alarmr)     { const el = byId(`higha-${row}`);  if (el) el.value = r.high_alarmr; }
  if (r.crithigh_alarmr) { const el = byId(`chigha-${row}`); if (el) el.value = r.crithigh_alarmr; }
  if (r.low_alarmr)      { const el = byId(`lowa-${row}`);   if (el) el.value = r.low_alarmr; }
  if (r.critlow_alarmr)  { const el = byId(`clowa-${row}`);  if (el) el.value = r.critlow_alarmr; }

  if (r.estimatedDays) {
    const rem = (+r.current_volume / +r.estimatedDays).toFixed(2);
    const dl = byId(`estimatedDaysLeft-${row}`);
    if (dl) dl.innerHTML = `Estimated days left: <strong>${rem}</strong>`;
  }
  if (r.lastconn) {
    const d1 = byId(`last-conn-${row}`); if (d1) d1.innerHTML = `Device Date: <strong>${r.lastconn}</strong>`;
    const d2 = byId(`last-conntime-${row}`); if (d2) d2.innerHTML = `Device Time: <strong>${r.lastconn_time}</strong>`;
  }
  if (r.tc_volume > 0) {
    const tcv = byId(`tc-vol-${row}`); if (tcv) tcv.innerHTML = `Temperature Corrected Vol: <strong>${(+r.tc_volume).toFixed(2)}L</strong>`;
  }

  // hide alert selects if no relay UART
  if (+r.relay_uart === 0) {
    const grid = document.querySelector(`.alert_info${row} .grid-2-columns`);
    if (grid) grid.style.gridTemplateColumns = '3fr 3.5fr';
    document.querySelectorAll(`.alert_info${row} select`).forEach(s => { s.style.display = 'none'; });
  }
  const alertsDiv = document.querySelector(`.alert_info${row} .alerts_div`);
  if (alertsDiv) alertsDiv.style.visibility = '';

  // ── Populate TG Port / Device / ID / Offset (non-gateway) ─────────
  const tgPortSel = byId(`tg_port-${row}`);
  if (tgPortSel) {
    tgPortSel.innerHTML = '';
    const addOpt = (v, t) => { const o = document.createElement('option'); o.value = String(v); o.textContent = t; tgPortSel.appendChild(o); };
    addOpt(0, 'NO DEVICE...');
    addOpt(5, 'Port A');
    addOpt(6, 'Port B');
    addOpt(3, 'Port C');
    addOpt('1_1', 'Port D');
    addOpt('1_2', 'Port E');

    let tgVal = r.tank_gauge_uart;
    const tgId  = r.tank_gauge_id;
    if (String(tgVal) === '1') tgVal = `1_${tgId || 0}`;
    [...tgPortSel.options].forEach(opt => { if (String(opt.value) === String(tgVal)) opt.selected = true; });
  }

  const tgTypeSel = byId(`tg_type-${row}`);
  if (tgTypeSel && r.responsedev && Array.isArray(r.responsedev.devices)) {
    tgTypeSel.innerHTML = '';
    r.responsedev.devices.forEach(d => {
      const id = +d.device_id;
      if ((id > 200 && id < 300) || id === 0) {
        const o = document.createElement('option');
        o.value = String(d.device_id);
        o.textContent = d.device_name;
        tgTypeSel.appendChild(o);
      }
    });
    if (r.tank_gauge_type != null) tgTypeSel.value = String(r.tank_gauge_type);
  }

  const tgIdInput = byId(`tg_id-${row}`);
  if (tgIdInput && r.tank_gauge_id != null) tgIdInput.value = r.tank_gauge_id;
  const tgOffInput = byId(`tg_offset-${row}`);
  if (tgOffInput && (r.tank_gauge_offset != null || r.raw_bias_counts != null)) {
    tgOffInput.value = r.tank_gauge_offset ?? r.raw_bias_counts ?? 0;
  }

  // Ensure strapping chart dropdown populated and selected (non-gateway)
  if (byId(`chart_id-${row}`)) {
    try { product_select(r.chart_id, row, 2); } catch (_) {}
    const chSel = byId(`chart_id-${row}`);
    if (chSel && r.chart_id != null) chSel.value = String(r.chart_id);
  }

  // re-enable all Update buttons for this row
  document
    .querySelectorAll(`.button-js[data-row-index="${row}"], .button-js2[data-row-index="${row}"], .button-js3[data-row-index="${row}"]`)
    .forEach(btn => { btn.disabled = false; });

  // remove any remaining overlays
  ['alert_info','tank_info','temp_info'].forEach(kind => {
    const ov = document.querySelector(`.${kind}${row} .loading-overlay`);
    if (ov && ov.parentNode) ov.parentNode.removeChild(ov);
  });
}

/*───────────────────────────────────────────────────────────────────
  7) Gateway config applier (unchanged)
───────────────────────────────────────────────────────────────────*/
function applyGatewayConfig(api, row, nav) {
  const setVal = (id, v) => { const el = byId(id); if (!el || v == null) return; el.value = v; };

  const guessTankNo = () => {
    const tnInput = document.querySelector(`.tank_info${row} input[name="tank_number"]`);
    return Number(nav?.dataset?.tankNo || (tnInput && tnInput.value) || 1);
  };
  const tankNo = guessTankNo();

  const t = (Array.isArray(api?.tanks) ? api.tanks.find(x => Number(x.id) === tankNo) : null) || {};
  const g = t.geometry || {};
  const a = t.alarms   || {};
  const p = api?.ports || {};

  const r = {
    tank_id:          t.id ?? tankNo,
    tank_name:        t.name ?? '',
    name:             t.name ?? '',
    capacity:         t.capacity ?? 0,
    current_volume:   t.current_volume ?? 0,
    estimatedDays:    t.estimatedDays ?? null,
    product_id:       t.product_id ?? null,
    product_name:     t.product ?? null,
    enabled:          t.enabled ? 1 : 0,

    shape:            g.shape ?? 2,
    height:           g.height ?? 0,
    width:            g.width ?? 0,
    depth:            g.depth ?? 0,
    raw_bias_counts:  g.offset ?? t.raw_bias_counts ?? 0,
    offset:           g.offset ?? 0,

    mindex_0:         p.mindex_0 ?? 0,
    fmsindex_0:       p.fmsindex_0 ?? 0,
    mindex_1:         p.mindex_1 ?? 0,
    fmsindex_1:       p.fmsindex_1 ?? 0,

    crithigh_alarm:   a.high_high ?? 0,
    high_alarm:       a.high ?? 0,
    low_alarm:        a.low ?? 0,
    critlow_alarm:    a.low_low ?? 0,
    alarm_enable:     a.enabled ?? 0,
  };

  const tn = document.querySelector(`.tank_info${row} input[name="tank_number"]`);
  if (tn && r.tank_id != null) tn.value = r.tank_id;

  const tname = document.querySelector(`.tank_info${row} input[name="tank_name"]`);
  if (tname && (r.tank_name || r.name)) tname.value = r.tank_name || r.name;

  if (r.capacity != null) setVal(`capacity-${row}`, String(r.capacity).replace(/,/g,''));

  const prodSel = byId(`product_name-${row}`);
  if (prodSel) {
    if (r.product_id != null) { prodSel.value = r.product_id; }
    else if (r.product_name) {
      const opt = [...prodSel.options].find(o => o.textContent === r.product_name);
      if (opt) prodSel.value = opt.value;
    }
  }

  if (r.shape  != null) setVal(`tank_shape-${row}`, r.shape);
  if (r.height != null) setVal(`height-${row}`,     r.height);
  if (r.width  != null) setVal(`width-${row}`,      r.width);
  if (r.depth  != null) setVal(`depth-${row}`,      r.depth);
  if (r.raw_bias_counts != null) setVal(`offsetgw-${row}`, r.raw_bias_counts);

  // const t = (Array.isArray(api?.tanks) ? api.tanks.find(x => Number(x.id) === tankNo) : null) || {};


  const chartSel = byId(`stchart-${row}`) || byId(`stchart_id-${row}`);
  if (chartSel && t.chart_id != null) chartSel.value = String(t.chart_id);
  
  // Set No TG checkbox based on tank_gauge_type
  const noTgCheckbox = byId(`no_tg-${row}`);
  if (noTgCheckbox && t.tank_gauge_type != null) {
    noTgCheckbox.checked = (Number(t.tank_gauge_type) === 999);
  }
  
  wireChartDisable(row);  // disable/enable geometry based on chart selection

  if (r.mindex_0    != null) setVal(`mindex_0-${row}`,   r.mindex_0);
  if (r.fmsindex_0  != null) setVal(`fms_index0-${row}`, r.fmsindex_0);
  if (r.mindex_1    != null) setVal(`mindex_1-${row}`,   r.mindex_1);
  if (r.fmsindex_1  != null) setVal(`fms_index1-${row}`, r.fmsindex_1);

  if (r.crithigh_alarm != null) setVal(`chigha-${row}`, r.crithigh_alarm);
  if (r.high_alarm     != null) setVal(`higha-${row}`,  r.high_alarm);
  if (r.low_alarm      != null) setVal(`lowa-${row}`,   r.low_alarm);
  if (r.critlow_alarm  != null) setVal(`clowa-${row}`,  r.critlow_alarm);
  const alarmCk = byId(`alarm_enable-${row}`);
  if (alarmCk && r.alarm_enable != null) alarmCk.checked = !!r.alarm_enable;

  // Update estimated days left display
  if (r.estimatedDays && r.current_volume) {
    const rem = (+r.current_volume / +r.estimatedDays).toFixed(2);
    const dl = byId(`estimatedDaysLeft-${row}`);
    if (dl) dl.innerHTML = `Estimated days left: <strong>${rem}</strong>`;
  }

  // Populate Information tab fields (email, vol_alert, alert_type) from tank data
  if (t.mail != null) setVal(`email-${row}`, t.mail);
  if (t.volal != null) setVal(`vol_alert-${row}`, t.volal);
  if (t.volal_type != null) {
    const alertTypeSel = byId(`alert_type-${row}`);
    if (alertTypeSel) alertTypeSel.value = t.volal_type;
  }

  ['.button-gwsave', '.button-gwports'].forEach(sel => {
    const btn = document.querySelector(`.tank_info${row} ${sel}`);
    if (btn) btn.disabled = false;
  });
  const alertsDiv = document.querySelector(`.alert_info${row} .alerts_div`);
  if (alertsDiv) alertsDiv.style.visibility = '';
}
