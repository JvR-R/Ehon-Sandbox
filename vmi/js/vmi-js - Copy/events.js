/* /vmi/js/events.js ----------------------------------------------- *
 * All global delegated handlers – **vanilla-JS** edition            *
 *------------------------------------------------------------------*/

import { on, qs, postJSON, fetchJSON, allowedAcl } from './api.js';
import { toast }                                   from './ui.js';
import { generateFMSSections }                     from './childRow.js';

/* helper: turn “…-row-idx” id into idx number */
const splitIdx = (elId, pos = 1) => elId.split('-')[pos];

/*───────────────────────────────────────────────────────────────────
  1.  .button-js   – e-mail / volume-alert settings
───────────────────────────────────────────────────────────────────*/
on('click', '.button-js', async function () {
  if (!allowedAcl.includes(+window.userAccessLevel)) {
    toast('Not enough privileges', 'error'); return;
  }

  const { uid, tank_no, site_id, rowIndex } = this.dataset;
  const root = this.closest('.child_details');

  const data = {
    case       : 1,
    uid, tank_no, site_id,
    vol_alert  : root.querySelector(`#vol_alert-${rowIndex}`).value,
    alert_type : root.querySelector(`#alert_type-${rowIndex}`).value,
    email      : root.querySelector(`#email-${rowIndex}`).value
  };

  try {
    await postJSON('/vmi/clients/update', data);
    toast('Update successful', 'success');
  } catch (e) {
    console.error(e); toast('Update failed', 'error');
  }
});

/*───────────────────────────────────────────────────────────────────
  2.  .button-js2  – configuration (tank, TG, FMS)
───────────────────────────────────────────────────────────────────*/
on('click', '.button-js2', async function () {
  if (!allowedAcl.includes(+window.userAccessLevel)) {
    toast('Not enough privileges', 'error'); return;
  }

  const root   = this.closest('.child_details');
  const rowIdx = this.dataset.rowIndex;

  const sel = q => root.querySelector(q)?.value ?? '';

  const data = {
    case         : 2,
    uid          : this.dataset.uid,
    tank_no      : this.dataset.tank_no,
    site_id      : this.dataset.site_id,
    product_name : sel('select[name="product_name"]'),
    tank_number  : sel('input[name="tank_number"]'),
    tank_name    : sel('input[name="tank_name"]'),
    capacity     : sel(`#capacity-${rowIdx}`).replace(/,/g, ''),
    tg_port      : sel('select[name="tg_port"]'),
    tg_type      : sel('select[name="tg_type"]'),
    tg_id        : sel('input[name="tg_id"]'),
    tg_offset    : sel('input[name="tg_offset"]'),
    chart_id     : sel('select[name="chart_id"]'),
    relaybox_port: sel('select[name="relaybox_port"]'),
    relaybox_type: sel('select[name="relaybox_type"]'),
    fms_data     : []
  };

  root.querySelectorAll(`.fms-container-${rowIdx} [id^="fms-${rowIdx}-"]`)
      .forEach(div => {
        const i = splitIdx(div.id, 2);
        data.fms_data.push({
          fms_port : document.getElementById(`fms_port-${rowIdx}-${i}`).value,
          fms_type : document.getElementById(`fms_type-${rowIdx}-${i}`).value,
          fms_id   : document.getElementById(`fms_id-${rowIdx}-${i}`).value
        });
      });

  /* port “1_1 / 1_2” alias handling */
  if (data.tg_port === '1_1') { data.tg_port = 1; data.tg_id = 1; }
  if (data.tg_port === '1_2') { data.tg_port = 1; data.tg_id = 2; }

  try {
    const r = await postJSON('/vmi/clients/update', data);
    if (r.idduplicate)      toast('Error: duplicate ID', 'error');
    else if (r.dvduplicate) toast('Port is associated to a different tank!', 'error');
    else                    toast('Update successful!', 'success');
  } catch (e) {
    console.error(e); toast('Update failed', 'error');
  }
});

/*───────────────────────────────────────────────────────────────────
  3.  .button-js3  – volume-threshold alarms
───────────────────────────────────────────────────────────────────*/
on('click', '.button-js3', async function () {
  if (!allowedAcl.includes(+window.userAccessLevel)) {
    toast('Not enough privileges', 'error'); return;
  }

  const { uid, tank_no, site_id, client_id, capacity, rowIndex } = this.dataset;
  const root = this.closest('.child_details');

  const val = id => root.querySelector(id)?.value ?? '';

  const data = {
    case  : 3,
    uid, tank_no, site_id, client_id, capacity,
    higha   : val(`#higha-${rowIndex}`),
    lowa    : val(`#lowa-${rowIndex}`),
    chigha  : val(`#chigha-${rowIndex}`),
    clowa   : val(`#clowa-${rowIndex}`),
    relay_hh: val(`#relay-hh-${rowIndex}`),
    relay_h : val(`#relay-h-${rowIndex}`),
    relay_l : val(`#relay-l-${rowIndex}`),
    relay_ll: val(`#relay-ll-${rowIndex}`)
  };

  try {
    await postJSON('/vmi/clients/update', data);
    toast('Update successful', 'success');
  } catch (e) {
    console.error(e); toast('Update failed', 'error');
  }
});

/*───────────────────────────────────────────────────────────────────
  4.  onchange helpers (custom ports, FMS count, …)
───────────────────────────────────────────────────────────────────*/

/* FMS port → auto-select device */
on('change', 'select[id^="fms_port-"]', async function () {
  const [, row, idx] = this.id.split('-');        // “fms_port-row-idx”
  const uid  = this.dataset.uid;
  const val  = this.value;
  if (+val === 0) return;

  try {
    const r = await fetchJSON('/vmi/clients/dropdowns_config.php?' +
                              qs({ selectedValue: val, case: 3, uid }));
    const sel = document.getElementById(`fms_type-${row}-${idx}`);
    sel.value = (r.newValue < 200) ? r.newValue : 0;
  } catch (e) { console.error(e); }
});

/* TG port → device & ID logic */
on('change', 'select[id^="tg_port-"]', async function () {
  const row = splitIdx(this.id);           // tg_port-<row>
  const uid = this.dataset.uid;
  let  val  = this.value;

  if (val === '1_1') { val = 11; document.getElementById(`tg_id-${row}`).value = 1; }
  if (val === '1_2') { val = 12; document.getElementById(`tg_id-${row}`).value = 2; }
  if (+val === 0) return;

  try {
    const r = await fetchJSON('/vmi/clients/dropdowns_config.php?' +
                              qs({ selectedValue: val, case: 3, uid }));
    document.getElementById(`tg_type-${row}`).value =
      (r.newValue > 200 && r.newValue < 300) ? r.newValue : 0;
  } catch (e) { console.error(e); }
});

/* TG device → enable / disable chart dropdown */
on('change', 'select[id^="tg_type-"]', function () {
  const row = splitIdx(this.id);
  document.getElementById(`tg_id-${row}`).disabled    = true;
  document.getElementById(`chart_id-${row}`).disabled = (this.value === '202');
});

/* FMS number change → add / remove port-config sections */
on('change', 'select[name="fms_number"]', async function () {
  const row     = splitIdx(this.id);
  const uid     = this.dataset.uid;
  const tank_no = this.dataset.tank_no;
  const n       = +this.value || 0;

  if (n === 0) {
    document.querySelector(`.fms-container-${row}`).innerHTML = '';
    return;
  }

  this.disabled = true;
  try {
    const r = await fetchJSON('/vmi/clients/dropdowns_config.php?' +
                              qs({ case: 5, uid, tank_no, fms_number: n }));
    await generateFMSSections(row, uid, n, r.fmsData || []);
  } catch (e) {
    console.error(e); toast('FMS request failed', 'error');
  } finally {
    this.disabled = false;
  }
});
