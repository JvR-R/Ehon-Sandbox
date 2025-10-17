/* /vmi/js/childRow.js -------------------------------------------- *
 * Builds the expandable “child row” and handles all its internals  *
 * Split out of the legacy main.js and converted to an ES module.   *
 *------------------------------------------------------------------*/

import { byId, qs, fetchJSON } from './api.js';
import { navColor, toast }               from './ui.js';
import { drawChart, drawTempChart } from './charts.js';

/** tiny helpers – vanilla replacements for the few $.foo we still call */
const $one  = (sel, root = document) => root.querySelector(sel);
const $all  = (sel, root = document) => [...root.querySelectorAll(sel)];
const html  = (h) => { const t = document.createElement('template'); t.innerHTML = h.trim(); return t.content.firstElementChild; };
const tempLoaded = Object.create(null);

/*───────────────────────────────────────────────────────────────────
  0.  CHART-DATA FETCHERS (called from main.js)
───────────────────────────────────────────────────────────────────*/
export async function fetchChartData(uid, tank_no, row) {
  try {
// slow queries – allow 30 s instead of 10
  const r = await fetchJSON(
    '/vmi/clients/objects?' + qs({ uid, tank_no }),
    {},                       // no extra fetch‑options
    20_000                    // 30 000 ms timeout
  );
    fillinfo(r, row);
    drawChart(r.response2,  row);
    // drawTempChart(r.response3, row);
  } catch (e) {
    console.error('fetchChartData', e);
  }
}

export async function fetchChartDataMCS(
  uid, tank_no, cs_type, tank_name, site_name, site_id, row
) {
  try {
    const r = await fetchJSON('/vmi/clients/objectsap?' +
                              qs({ uid, tank_no, cs_type,
                                   tank_name, sitename: site_name, site_id }));
    drawChart(r.response2, row);
    fillinfo_mcs(r, row);
  } catch (e) {
    console.error('fetchChartDataMCS', e);
  }
}

/*───────────────────────────────────────────────────────────────────
  1.  FMS helpers (generate extra port rows inside Config tab)
───────────────────────────────────────────────────────────────────*/
async function populateFMSDevices(row, idx) {
  const sel = byId(`fms_type-${row}-${idx}`);
  sel.innerHTML = '';
  try {
    const r = await fetchJSON('/vmi/clients/dropdowns_config.php?case=get_fms_devices');
    (r.devices ?? []).forEach((d) => {
      if (+d.device_id < 200) {
        sel.appendChild(
          html(`<option value="${d.device_id}">${d.device_name}</option>`)
        );
      }
    });
  } catch (e) { console.error('FMS devices', e); }
}

function populateFMSPorts(row, idx) {
  const sel = document.getElementById(`fms_port-${row}-${idx}`);
  sel.innerHTML = '';
  sel.append(
    ...[
      [0,'NO DEVICE…'], [5,'Port A'], [6,'Port B'], [3,'Port C']
    ].map(([v,t]) => Object.assign(document.createElement('option'),{value:v,textContent:t}))
  );
}

export async function generateFMSSections(row, uid, n, fmsData = []) {
  const container = $one(`.fms-container-${row}`);
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
async function fetchTempChartData(uid, tank_no, row) {
  try {
    const r = await fetchJSON('/vmi/clients/objectsTemp?' +
                              qs({ uid, tank_no }));
    drawTempChart(r.response3, row);
  } catch (e) { console.error('temp fetch', e); }
}
/*───────────────────────────────────────────────────────────────────
  2.  Child-row factory (builds the entire expandable UI)
───────────────────────────────────────────────────────────────────*/
export function buildChild(d, ctx) {
  const { uid, cs_type, site_id, mcs_id, mcs_idpro, mcs_idlite,
          client_id, row } = ctx;

  const tank_no   = d.tank_id;
  const tank_name = d.tank_name;
  const capacity  = String(d.capacity).replace(/,/g, '');
  const prodName  = d.product_name;

  /*── NAV bar ─────────────────*/
  const nav = html(`
    <nav class="nav-items" role="navigation" style="display:flex;">
      <button class="navigation-item1${row}" style="color:red">Information</button>
      <button class="navigation-item2${row}">Alerts</button>
      <button class="navigation-item3${row}">Configuration</button>
      <button class="navigation-item4${row}">Temperature</button>
    </nav>`);
  nav.addEventListener('click', e => {
    const btn = e.target.closest('[class^="navigation-item"]');
    if (!btn) return;
    const n = btn.className.match(/navigation-item(\d)/)[1];
    showTab(row, n);
  });
  nav.dataset.uid     = uid;
  nav.dataset.tankNo  = tank_no;
  

  /*── Information tab ─────────*/
  const infoTab = html(`
    <section class="info-pane" style="display:flex;gap:1.5rem;">
  
      <div class="left-info">
        <div class="loading-overlay"><div class="spinner"></div></div>
        <div class="info_text" style="visibility:hidden">
          <div>Company Name:<strong> ${d.client_name}</strong></div>
          <div id="estimatedDaysLeft-${row}">
            Estimated days left: <strong>N/A</strong>
          </div>
          ${cs_type!=='MCS_PRO'&&cs_type!=='MCS_LITE'?`
            <div id="last-conn-${row}">Device Date:<strong>N/A</strong></div>
            <div id="last-conntime-${row}">Device Time:<strong>N/A</strong></div>`:''}
          ${cs_type==='MCS_PRO' ? `
              <div><a target="_blank"
                      href="https://new.mcstsm.com/sites/${mcs_idpro}/${mcs_id}">
                More Information</a></div>` : ''}
          ${cs_type==='MCS_LITE' ? `
              <div><a target="_blank"
                      href="https://mcs-connect.com/sites/${mcs_idlite}/details/${mcs_id}/">
                More Information</a></div>` : ''}
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
      </div> <!-- /.left-info -->
  
      <div class="right-info">
        <div class="chart1" id="chart-container-${row}">
          <canvas id="chart-${row}"></canvas>
        </div>
      </div> <!-- /.right-info -->
  
    </section>
  `);


  /*── Alerts tab ─────────────*/
  const alarmRow = (img,lab,id,lvl)=>`
    <label class="lab1"><img src="/vmi/images/${img}" alt="">${lab}: </label>
    <input class="recip" id="${id}-${row}" type="number" style="width:90%;margin-top:5px;">
    <select class="relay-select" id="relay-${lvl}-${row}">
      <option value="1">Relay 1</option><option value="2">Relay 2</option>
      <option value="3">Relay 3</option><option value="4">Relay 4</option>
    </select>`;

  const alertsTab = html(`
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


  /*── Config tab ─────────────*/
  product_select(prodName,row, 1);
  const cfgTab = html(` 
  <div class="tank_info${row}">
    <div class="loading-overlay"><div class="spinner"></div></div>
    <div style="display:flex">
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
            <select class="recip" id="product_name-${row}" name="product_name"><option>${prodName}</option></select>
            <label>FMS Number:</label>
            <select class="recip" id="fms_number-${row}" name="fms_number"
                    data-uid="${uid}" data-tank_no="${tank_no}">
              <option value="0">No FMS</option><option value="1">1</option><option value="2">2</option><option value="3">3</option>
            </select>
          </div>
        </div>
      </div>

      ${cs_type==='MCS_PRO'||cs_type==='MCS_LITE' ? '' : `
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
      </div>`}
    </div>

    <div class="fms-container-${row}"></div>
    <div class="tanks_div"></div>
    <div style="margin-bottom:4rem">
      <button class="button-js2"
              data-uid="${uid}" data-tank_no="${tank_no}"
              data-site_id="${site_id}" data-row-index="${row}">Update</button>
    </div>
  </div>`);
  cfgTab.style.display = 'none';

  /*── Temperature tab ─────────*/
  const tempTab = html(`
  <div class="temp_info${row}">
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
  tempTab.style.display = 'none';

  /*── Final assembly ─────────*/
  const wrap = html('<div class="child_details"></div>');
  const minfo = html('<div class="minfo"></div>');
 minfo.appendChild(infoTab);

 wrap.append(nav, minfo, alertsTab, cfgTab, tempTab);
  return wrap;

}

/*───────────────────────────────────────────────────────────────────
  3.  Tab switcher
───────────────────────────────────────────────────────────────────*/
export function showTab(row, n) {
  if (n === '4' && !tempLoaded[row]) {
    tempLoaded[row] = true;
    const nav = $one(`.navigation-item1${row}`).closest('nav');
    fetchTempChartData(nav.dataset.uid, nav.dataset.tankNo, row);
  }
  $all(`.alert_info${row}, .tank_info${row}, .temp_info${row}`)
      .forEach(el => el.style.display = 'none');

  const sel = { '2':`.alert_info${row}`, '3':`.tank_info${row}`, '4':`.temp_info${row}` }[n];
  const pane = $one(sel);
  if (pane) {
    pane.style.display = n === '4' ? 'flex' : 'block';
    if (n === '4') { pane.style.flexDirection = 'row'; pane.style.flexWrap = 'wrap'; }
  }
  navColor(row, n);
}

/*───────────────────────────────────────────────────────────────────
  4.  fillinfo / fillinfo_mcs  (populate all inputs after fetch)
───────────────────────────────────────────────────────────────────*/
function fillinfo(r,row){
    // Unwrap loading state
    const pane = document.querySelector(`.left-info .loading-overlay`);
    if(pane) pane.remove();
    document.querySelector(`.left-info .info_text`).style.visibility = '';
  
    byId(`email-${row}`     ).value = r.mail||'';
    byId(`vol_alert-${row}` ).value = r.volal||'';
    if(r.volal_type   ) byId(`alert_type-${row}`).value = r.volal_type;
    if(r.high_alarmr  ) byId(`higha-${row}` ).value = r.high_alarmr;
    if(r.crithigh_alarmr) byId(`chigha-${row}`).value = r.crithigh_alarmr;
    if(r.low_alarmr   ) byId(`lowa-${row}` ).value = r.low_alarmr;
    if(r.critlow_alarmr) byId(`clowa-${row}`).value = r.critlow_alarmr;
    if(r.estimatedDays){
      const rem = (+r.current_volume / +r.estimatedDays).toFixed(2);
      byId(`estimatedDaysLeft-${row}`).innerHTML =
        `Estimated days left: <strong>${rem}</strong>`;
    }
    if(r.lastconn){
      byId(`last-conn-${row}`   ).innerHTML = `Device Date: <strong>${r.lastconn}</strong>`;
      byId(`last-conntime-${row}`).innerHTML = `Device Time: <strong>${r.lastconn_time}</strong>`;
    }
    if(r.tc_volume>0){
      byId(`tc-vol-${row}`).innerHTML =
        `Temperature Corrected Vol: <strong>${(+r.tc_volume).toFixed(2)}L</strong>`;
    }

// ─── insert relaybox UI if supported ───────────────────────
  if (r.responsedev && r.responsedev.devices) {
    if ((r.device_id > 400 && r.device_id < 500) || +r.relay_uart > 0) {
    const cardDiv = document.querySelector(`.tank_info${row} .tanks_div`);
    if (cardDiv && !document.getElementById(`relaybox-${row}`)) {
      const relayboxdiv = document.createElement('div');
      relayboxdiv.innerHTML = `
        <div class="tankginfo_text" id="relaybox-${row}" style="display:block;"> 
          <div class="card pd-28px">                    
            <div class="grid-2-columns" style="display:grid;grid-template-columns:0.82fr 1fr;">
              <label>Relaybox Port:</label>
              <select class="recip" id="relaybox_port-${row}" name="relaybox_port">
                <option value="0">NO DEVICE</option>
              </select>
              <label>Relaybox Device:</label>
              <select class="recip" id="relaybox_type-${row}" name="relaybox_type">
              </select>
            </div>
          </div>
        </div>`;
      cardDiv.appendChild(relayboxdiv);

      // populate the device-type dropdown:
      const typeSel = document.getElementById(`relaybox_type-${row}`);
      r.responsedev.devices.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.device_id;
        opt.textContent = d.device_name;
        typeSel.appendChild(opt);
      });
    }
  }
}
  // ─── hide alert selects if no UART at all ───────────────────
  if (+r.relay_uart === 0) {
    const gridContainer = document.querySelector(`.alert_info${row} .grid-2-columns`);
    if (gridContainer) {
      gridContainer.style.gridTemplateColumns = '3fr 3.5fr 0';
    }
    document
      .querySelectorAll(`.alert_info${row} select`)
      .forEach(sel => (sel.style.visibility = 'hidden'));
  }
  if (r.tank_gauge_uart) {
    var selectElementtguart = document.getElementById('tg_port-' + row);
    var tg_uart = r.tank_gauge_uart;
    var tg_id = r.tank_gauge_id;
    // console.log("tguart1", tg_uart);
    // Check if tg_uart is 1 and update it
    if (tg_uart == 1) {
        tg_uart = tg_uart + '_' + tg_id;
    }
    // console.log("tguart2", tg_uart);
    // Clear existing options
    selectElementtguart.innerHTML = '';

    // Add options to the select element
    selectElementtguart.innerHTML += '<option value="0">NO DEVICE...</option>';
    selectElementtguart.innerHTML += '<option value="5">Port A</option>';
    selectElementtguart.innerHTML += '<option value="6">Port B</option>';
    selectElementtguart.innerHTML += '<option value="3">Port C</option>';
    selectElementtguart.innerHTML += '<option value="1_1">Port D</option>';
    selectElementtguart.innerHTML += '<option value="1_2">Port E</option>';

    // Iterate over options to set the selected one
    Array.from(selectElementtguart.options).forEach(function(option) {
        if (option.value == tg_uart) {
            option.selected = true;
        }
    });
  } 
  if(r.tank_gauge_type){
    var selectElementtg = document.getElementById('tg_type-' + row);
    // Clear existing options
    selectElementtg.innerHTML = '';

    // Iterate over the devices and append them as options
    if (r.responsedev && r.responsedev.devices) {
        r.responsedev.devices.forEach(function(device) {
            if((device.device_id>200 && device.device_id < 300)|| device.device_id == 0){
                var optionElement = document.createElement('option');
                optionElement.value = device.device_id;
                optionElement.textContent = device.device_name; // Adjust as needed
                if (device.device_id == r.tank_gauge_type) {
                    optionElement.selected = true;
                }
                selectElementtg.appendChild(optionElement);
            }
        });
    } else {
        console.error('Invalid structure for responsedev', r.responsedev);
    }
  }
  if(r.tank_gauge_id){
    document.getElementById('tg_id-' + row).value = r.tank_gauge_id || '';
  }
  if(r.tank_gauge_offset){
    document.getElementById('tg_offset-' + row).value = r.tank_gauge_offset || '';
  }  
  // ────────────────────────────────────────────────────────────────
    product_select(r.chart_id,row,2);
    ['alert_info','tank_info','temp_info'].forEach(kind => {
      const panel = document.querySelector(`.${kind}${row} .loading-overlay`);
      if (panel) panel.remove();
      const inner = document.querySelector(`.${kind}${row} > div:not(.loading-overlay)`);
      if (inner) inner.style.visibility = '';
    });
    // re-enable all of the “Update” buttons for this row
    document
      .querySelectorAll(`.button-js[data-row-index="${row}"],
                          .button-js2[data-row-index="${row}"],
                          .button-js3[data-row-index="${row}"]`)
      .forEach(btn => (btn.disabled = false));

  }

function fillinfo_mcs(r, row) {
  const pane = document.querySelector(`.left-info .loading-overlay`);
  if (pane) pane.remove();
  document.querySelector(`.left-info .info_text`).style.visibility = '';

  byId(`email-${row}`).value = r.mail || '';
  ['higha','chigha','lowa','clowa'].forEach(k => {
    if (r[`${k}_r`]) byId(`${k}-${row}`).value = r[`${k}_r`];
  });

  if (r.estimatedDays) {
    const rem = (+r.current_volume / +r.estimatedDays).toFixed(2);
    byId(`estimatedDaysLeft-${row}`).innerHTML =
      `Estimated days left: <strong>${rem}</strong>`;
  }

  ['alert_info','tank_info','temp_info'].forEach(kind => {
    const overlay = document.querySelector(`.${kind}${row} .loading-overlay`);
    if (overlay) overlay.remove();
    const inner = document.querySelector(
      `.${kind}${row} > div:not(.loading-overlay)`
    );
    if (inner) inner.style.visibility = '';
  });
  document
    .querySelectorAll(`.button-js[data-row-index="${row}"],
                       .button-js2[data-row-index="${row}"],
                       .button-js3[data-row-index="${row}"]`)
    .forEach(btn => (btn.disabled = false));
}

/*───────────────────────────────────────────────────────────────────
  5.  Product & chart dropdown helpers
───────────────────────────────────────────────────────────────────*/
async function product_select(data, row, cs) {
  try {
    const r = await fetchJSON('/vmi/clients/dropdowns_config?' +
                              qs({ rowIndex: row, case: cs }));
    if (r.products) product_dd(data, r.products, row);
    if (r.schart )  chart_dd  (data, r.schart , row);
    console.log(r.products);
  } catch (e) { console.error(e); }
}

function product_dd(curr, arr, row) {
  const sel = byId(`product_name-${row}`); sel.innerHTML = '';
  arr.forEach(a => {
    if (!a.product_id) return;
    const opt = document.createElement('option');
    opt.value = a.product_id;
    opt.textContent = a.product_name;
    if (a.product_name === curr) opt.selected = true;
    sel.appendChild(opt);
  });
}

function chart_dd(curr, arr, row) {
  const sel = byId(`chart_id-${row}`); sel.innerHTML = '';
  arr.forEach(a => {
    if (!a.chart_id) return;
    const opt = document.createElement('option');
    opt.value = a.chart_id;
    opt.textContent = a.chart_name;
    if (a.chart_id == curr) opt.selected = true;
    sel.appendChild(opt);
  });
}
