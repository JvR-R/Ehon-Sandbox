/* /vmi/js/gateway_cfg.js ------------------------------------------ *
 * Wires the Gateway Configuration tab "Update" buttons and posts    *
 * to /vmi/api/gateway_update.php?op=basic|tanks|ports|alerts        *
 *------------------------------------------------------------------*/

import { byId } from './api.js';
import { toast } from './ui.js';
import { afterTanksSaved, afterPortsSaved, setSyncingState, clearSyncingState, invalidateRowCache } from './childRow.js';

const wiredRoots = new WeakSet();

/**
 * Call this once after the gateway cfg or alerts tab has been rendered.
 * Idempotent; safe to call from multiple places.
 * @param {number|string} row  The row index used in element ids (e.g. capacity-<row>)
 */
export function initGatewayCfg(row) {
  const nav = document.querySelector(`.navigation-item1${row}`)?.closest('nav');
  if (!nav) return;
  // pick a real DOM root to guard against
  const rootCfg    = document.querySelector(`.tank_info${row}`);
  const rootAlerts = document.querySelector(`.alert_info${row}`);
  const root = rootCfg || rootAlerts;
  if (!root) return;
  if (wiredRoots.has(root)) return;          // already wired THIS DOM
  wiredRoots.add(root);

  const uid  = Number(nav.dataset.uid);
  const td   = Number(nav.dataset.tankDeviceId);         // tank_device_id (gateway)
  const site = nav.dataset.siteId ? Number(nav.dataset.siteId) : null;

  // inside initGatewayCfg(row) — replace the whole post() function
  async function post(op, payload, btn, { toastOnSuccess = true, successMessage = 'Saved.' } = {}) {
    try {
      if (btn) btn.disabled = true;

      const url = `/vmi/api/gateway_update.php?op=${encodeURIComponent(op)}`;
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid, tank_device_id: td, site_id: site, ...payload })
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.error) throw new Error(data.error || res.statusText);
      if (toastOnSuccess) toast(successMessage, 'success');
      return data;

    } catch (err) {
      console.error('gateway_update', err);
      toast(`Save failed: ${err.message}`, 'error');
      throw err;

    } finally {
      if (btn) btn.disabled = false;
    }
  }

  // Minimal helper: send one key via MQTT and wait for the device ACK
  async function sendRawCommand(body) {
    const res = await fetch('/backend/gateway/command/', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRFToken': (typeof getCookie === 'function' ? getCookie('csrftoken') : '')
      },
      body: JSON.stringify(body)
    });
    const text = await res.text();
    if (!res.ok) throw new Error(text || res.statusText);
    try { return JSON.parse(text); } catch { return { ok: true }; }
  }

  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  async function waitForHttp200(url, { timeoutMs = 8000, intervalMs = 300 } = {}) {
    const until = Date.now() + timeoutMs;
    while (Date.now() < until) {
      const bust = (url.includes('?') ? '&' : '?') + '_ts=' + Date.now();
      try {
        const r = await fetch(url + bust, { method: 'HEAD', cache: 'no-store' });
        if (r.ok) return true;
      } catch {}
      await new Promise(r => setTimeout(r, intervalMs));
    }
    throw new Error('TANKS.json not reachable yet');
  }

  // Send ONLY TANKS.json to device (used after alerts saved)
  async function sendTanksOnly(row) {
    const uid = Number(nav.dataset.uid);
    const deviceId = nav.dataset.deviceId || null; // may be set by loader
    const tankDeviceId = Number(nav.dataset.tankDeviceId || 0);

    const tanksPath = `gateway/cfg/${uid}/TANKS.json`;
    const tanksUrl  = `https://ehon.com.au/api-v1/download.php?f=${tanksPath}`;

    setSyncingState(row, true, 'Verifying tank configuration...');
    try {
      // ensure file is reachable before asking device to fetch
      await waitForHttp200(`${location.origin}/api-v1/download.php?f=${encodeURIComponent(tanksPath)}`);

      const addr = deviceId
        ? { device_id: deviceId }
        : { uid, tank_device_id: tankDeviceId || undefined };

      setSyncingState(row, true, 'Sending tank configuration...');
      const payloadRaw = JSON.stringify({ tanks: tanksUrl }, ['tanks']);
      const resp = await sendRawCommand({ ...addr, payload_raw: payloadRaw, wait_for: 'tanks', wait_timeout: 25 });
      if (resp?.matched === false) throw new Error(resp?.reply?.payload || 'Device reported failure');
      if (resp?.ok !== true) throw new Error('No ACK on tanks');
      clearSyncingState(row);
      return true;
    } catch (e) {
      clearSyncingState(row);
      throw e;
    }
  }


  async function onClickSaveTanks(row, body) {
    // 1) save tanks (server writes TANKS.json)
    const r = await fetch('/vmi/api/gateway_update.php?op=tanks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!r.ok) throw new Error(await r.text());
    const data = await r.json();
    if (!data?.ok) throw new Error('Save failed');

    // 2) now send the one JSON message in guaranteed order
    await afterTanksSaved(row);
    // no toast here; callers will show “Saved.” after command success
    return data;
  }


  /* 1) BASIC: tank number/name/capacity/product */
  if (rootCfg) {
    const btnBasic = rootCfg.querySelector('.button-gwupd');
    if (btnBasic) {
      btnBasic.addEventListener('click', async () => {
        const tank_number = rootCfg.querySelector('input[name="tank_number"]')?.value?.trim();
        const tank_name   = rootCfg.querySelector('input[name="tank_name"]')?.value?.trim();
        const capacity    = Number(byId(`capacity-${row}`)?.value || 0);
        const product_id  = Number(byId(`product_name-${row}`)?.value || 0) || null;

        setSyncingState(row, true, 'Saving tank details...');
        try {
          await post('basic', { tank_number, tank_name, capacity, product_id }, btnBasic);
          invalidateRowCache(row); // Clear cache so reopening shows fresh data
        } finally {
          clearSyncingState(row);
        }
      });
    }

    /* 2) TANK GEOMETRY: shape/height/width/depth/offset */
    // Replace the whole .button-gwtanks click handler
    const btnTank = rootCfg.querySelector('.button-gwtanks');
    if (btnTank) {
      btnTank.addEventListener('click', async () => {
        const shape  = Number(byId(`tank_shape-${row}`)?.value || 0);
        const height = Number(byId(`height-${row}`)?.value || 0);
        const width  = Number(byId(`width-${row}`)?.value || 0);
        const depth  = Number(byId(`depth-${row}`)?.value || 0);
        const offset = Number(byId(`offsetgw-${row}`)?.value || 0);
        const chartEl = byId(`stchart-${row}`) || byId(`stchart_id-${row}`);
        const chart_id = chartEl ? Number(chartEl.value || 0) : 0;

        const body = { uid, tank_device_id: td, site_id: site, shape, height, width, depth, offset, chart_id };


        try {
          // DB save first…
          await onClickSaveTanks(row, body);
          invalidateRowCache(row); // Clear cache so reopening shows fresh data
          // …then console sync feedback
          // toast('Syncing console…', 'info');
          // afterTanksSaved() already ran inside onClickSaveTanks; if no exception, we're done
          // toast('Saved.', 'success');
        } catch (err) {
          console.error(err);
          toast(`Save/send failed: ${err.message}`, 'error');
        }
      });
    }



    /* 3) PORTS: mindex_0/1 + fmsindex_0/1 */
    const btnPorts = rootCfg.querySelector('.button-gwports');
    if (btnPorts) {
      btnPorts.addEventListener('click', async () => {
        const mindex_0   = Number(byId(`mindex_0-${row}`)?.value || 0);
        const fmsindex_0 = Number(byId(`fms_index0-${row}`)?.value || 0);
        const mindex_1   = Number(byId(`mindex_1-${row}`)?.value || 0);
        const fmsindex_1 = Number(byId(`fms_index1-${row}`)?.value || 0);
        const tank_no    = Number(nav.dataset.tankNo || 0);

        try {
          // 1) Save ports (server writes PORTS.json)
          await post('ports',
            { mindex_0, fmsindex_0, mindex_1, fmsindex_1, tank_no },
            btnPorts /* keep default options */);

          // 2) After save OK: poll PORTS.json and send one JSON to device
          await afterPortsSaved(row);
          invalidateRowCache(row); // Clear cache so reopening shows fresh data
          // afterPortsSaved() handles syncing state management

        } catch (err) {
          console.error(err);
          toast(`Ports save/send failed: ${err.message}`, 'error');
        }
      });
    }

  }

  /* 4) ALERTS (gateway): high/low/crit* + enable; no relays */
  if (rootAlerts) {
    const btnAlerts = rootAlerts.querySelector('.button-gwalert');
    if (btnAlerts) {
      btnAlerts.addEventListener('click', async () => {
        const tank_id = Number(nav.dataset.tankNo);
        const client_id = Number(nav.dataset.clientId || 0);
        const cap = (() => {
          try { return Math.max(0, Number(String(nav.dataset.capacity || '0').replace(/,/g, '')) || 0); }
          catch { return 0; }
        })();
        const high  = Number(byId(`higha-${row}`)?.value  || 0);
        const low   = Number(byId(`lowa-${row}`)?.value   || 0);
        const chigh = Number(byId(`chigha-${row}`)?.value || 0);
        const clow  = Number(byId(`clowa-${row}`)?.value  || 0);
        if (cap > 0 && (high > cap || chigh > cap)) {
          toast(`High and Critical High cannot exceed capacity (${cap}).`, 'error');
          return;
        }
        if (low < 0 || clow < 0) {
          toast('Low and Critical Low cannot be below 0.', 'error');
          return;
        }
        const payload = {
          tank_id,
          client_id,
          high_alarm:     high,
          low_alarm:      low,
          crithigh_alarm: chigh,
          critlow_alarm:  clow,
          alarm_enable:   (document.getElementById(`alarm_enable-${row}`)?.checked ? 1 : 0)
        };
        try {
          // Lock UI during save + send
          setSyncingState(row, true, 'Saving alerts...');
          // 1) Save alerts → server refreshes TANKS.json
          await post('alerts', payload, btnAlerts, { toastOnSuccess: false });
          // 2) Send only TANKS.json via MQTT (manages overlay messages/clear)
          await sendTanksOnly(row);
          invalidateRowCache(row); // Clear cache so reopening shows fresh data
          toast('Alerts updated and sent', 'success');
        } catch (err) {
          console.error(err);
          clearSyncingState(row);
          toast(`Alerts save/send failed: ${err.message}`, 'error');
        }
      });
    }
  }

  // Replace the whole .button-gwsave click handler
  const btnMerged = rootCfg.querySelector('.button-gwsave');
  if (btnMerged) {
    btnMerged.addEventListener('click', async () => {
      const tank_number = rootCfg.querySelector('input[name="tank_number"]')?.value?.trim();
      const tank_name   = rootCfg.querySelector('input[name="tank_name"]')?.value?.trim();

      const capacity    = Number(byId(`capacity-${row}`)?.value || 0);
      const product_id  = Number(byId(`product_name-${row}`)?.value || 0) || null;

      const shape  = Number(byId(`tank_shape-${row}`)?.value || 0);
      const height = Number(byId(`height-${row}`)?.value || 0);
      const width  = Number(byId(`width-${row}`)?.value || 0);
      const depth  = Number(byId(`depth-${row}`)?.value || 0);
      const offset = Number(byId(`offsetgw-${row}`)?.value || 0);
      const chartEl = byId(`stchart-${row}`) || byId(`stchart_id-${row}`);
      const chart_id = chartEl ? Number(chartEl.value || 0) : 0;
      const no_tg = byId(`no_tg-${row}`)?.checked ? 1 : 0;

      // 1) Save basic WITHOUT toasting "Saved."
      try {
        // 1) Save basic WITHOUT toasting "Saved."
        await post('basic', { tank_number, tank_name, capacity, product_id, chart_id, no_tg }, btnMerged, { toastOnSuccess: false });
        // 2) Save tanks + send command, with the new toast sequence handled in afterTanksSaved
        await onClickSaveTanks(row, { uid, tank_device_id: td, site_id: site, shape, height, width, depth, offset, chart_id });
        invalidateRowCache(row); // Clear cache so reopening shows fresh data
      } catch (err) {
        console.error(err);
        toast(`Save/send failed: ${err.message}`, 'error');
      }
    });
  }

}
