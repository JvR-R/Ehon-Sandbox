/* /vmi/js/main.js  (entry file, <script type="module">) ----------- */
import { byId } from './api.js';
import { initTable }                from './table.js';
import { drawChart, drawTempChart, destroyChart } from './charts.js';
import {
      buildChild,
      fetchChartData,
      fetchChartDataMCS,
      fetchGatewayData,
    } from './childRow.js';

import './events.js';      // just executes and wires global events


const hd = byId('hidden-data');          // <div id="hidden-data" …>

window.companyId       = +(hd?.dataset.companyId   ?? 0);
window.userAccessLevel = +(hd?.dataset.accessLevel ?? 0);
/*———— boot once DOM is ready ———————————————*/
document.addEventListener('DOMContentLoaded', async () => {   
  const table = await initTable();

  /* open/close child rows (uses the imported build Child) */
  document
  .getElementById('customers_table')
  .addEventListener('click', async (ev) => {
    const cell = ev.target.closest('td.dt-control');
    if (!cell) return;                         // click was somewhere else

    const tr   = cell.parentElement;           // the data row  <tr>
    const row  = table.row(tr);                // DataTables API
    const data = row.data();                   // original row data
    const idx  = row.index();

    if (row.child.isShown()) {                 // toggle closed
      destroyChart(`chart-${idx}`);      // main “volume” chart
      destroyChart(`tempchart-${idx}`);  // temperature chart (no‑op if never drawn)
      row.child.hide();
      tr.classList.remove('shown');
      tr.nextElementSibling?.classList.remove('expanded-details');
      return;
    }

    /* gather per-row attrs that rowCallback stored earlier */
    const ctx = {
      uid        : tr.dataset.uid,
      cs_type    : tr.dataset.cs_type,
      tank_device_id : tr.dataset.tankDeviceId,
      site_id    : tr.dataset.site_id,
      mcs_id     : tr.dataset.mcs_id,
      mcs_idpro  : tr.dataset.mcs_idpro,
      mcs_idlite : tr.dataset.mcs_idlite,
      client_id  : tr.dataset.client_id,
      row        : idx
    };

    // Check if we already have a child row built for this index
    let childUI = row.child();
    if (childUI && childUI.length > 0) {
      // Reuse existing child row - just show it and rehydrate
      row.child.show();
      tr.classList.add('shown');
      tr.nextElementSibling?.classList.add('expanded-details');
      
      // Rehydrate the existing child row (this will re-apply config, rewire, redraw charts)
      if (ctx.cs_type === 'MCS_PRO' || ctx.cs_type === 'MCS_LITE') {
        fetchChartDataMCS(
          data.uid, data.tank_id, ctx.cs_type,
          data.tank_name, data.site_name, data.Site_id, idx
        );
      } else if (ctx.cs_type === 'EHON_GATEWAY') {
        fetchGatewayData(ctx.uid, ctx.tank_device_id, idx);
      } else {
        fetchChartData(data.uid, data.tank_id, idx);
      }
      return;
    }

    /* build & show the child UI */
    childUI = buildChild(data, ctx);
    
    // Check if buildChild returned a valid element
    if (!childUI || !(childUI instanceof Element)) {
      console.error('buildChild returned invalid element:', childUI);
      console.error('data:', data);
      console.error('ctx:', ctx);
      return;
    }
    
    row.child(childUI).show();
    tr.classList.add('shown');
    tr.nextElementSibling?.classList.add('expanded-details');

    /* disable all update buttons until async work finishes */
    childUI.querySelectorAll('.button-js, .button-js2, .button-js3')
           .forEach(btn => (btn.disabled = true));

    /* fetch charts + FMS once the UI is mounted */
    if (ctx.cs_type === 'MCS_PRO' || ctx.cs_type === 'MCS_LITE') {
      fetchChartDataMCS(
        data.uid, data.tank_id, ctx.cs_type,
        data.tank_name, data.site_name, data.Site_id, idx
      );
    } else if (ctx.cs_type === 'EHON_GATEWAY') {
      fetchGatewayData(ctx.uid, ctx.tank_device_id, idx);
    } else {
      fetchChartData(data.uid, data.tank_id, idx);
    }
  });
});
