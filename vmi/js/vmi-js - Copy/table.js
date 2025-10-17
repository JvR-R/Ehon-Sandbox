/* /vmi/js/table.js ----------------------------------------------- */
import { fetchJSON, postJSON, qs, numberFmt } from './api.js';
import { buildStatusIcon, progressBar, toast } from './ui.js';

/* lazy‑load the vanilla build (UMD) once – same CDN as before */
let dtReady = null;
async function ensureDataTable() {
  if (window.DataTable) return;
  if (!dtReady) {
    dtReady = new Promise((res, rej) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.datatables.net/v/bs5/dt-2.0.3/datatables.min.js';
      s.onload = res;  s.onerror = () => rej(new Error('DT load failed'));
      document.head.appendChild(s);
    });
  }
  return dtReady;
}

let currentSiteIds = [];
export let dataTable = null;        // exported for other modules


export async function initTable() {
    await ensureDataTable();
    dataTable = new DataTable('#customers_table', {
    paging     : false,
    serverSide : true,
    layout : {
      topStart   : 'search',   // search input
      topEnd     : null,
      bottomEnd  : 'info',     // “Showing 1–10 of …”
      bottomStart: 'paging'
    },
    ajax : {
      url  : '/vmi/api/tanks.php',
      method : 'POST',
      data : d => { d.site_ids = currentSiteIds; return d; },
      src  : 'data'
    },
    columns : [
      { data:null, defaultContent:'', className:'dt-control' },
      { data:'client_name',  className:'hide-on-mobile' },
      { data:'dipr_date' },
      { data:'dipr_time',className:'hide-on-mobile' },
      { data:'site_name' },
      // { data:'tank_name',    className:'hide-on-mobile' },
      { data:'tank_id' },
      { data:'product_name', className:'hide-on-mobile' },
      { data:'capacity',     render:numberFmt },
      { data:'current_volume',render:numberFmt },
      { data:null, className:'hide-on-mobile', render:buildStatusIcon },
      { data:'temperature',  className:'hide-on-mobile' },
      { data:'ullage',       render:numberFmt },
      { data:'current_percent',render:progressBar }
    ],
      rowCallback : (row,d)=>{
        row.dataset.uid        = d.uid;
        row.dataset.cs_type    = d.device_type==201?'MCS_PRO':'';
        row.dataset.site_id    = d.Site_id;
        row.dataset.mcs_id     = d.mcs_id||'';
        row.dataset.client_id  = d.client_id;
        row.dataset.mcs_idpro  = d.mcs_clientid||'';
        row.dataset.mcs_idlite = d.mcs_liteid||'';
      }
  });

  document.getElementById('group_filter').addEventListener('change', async e => {
    const g = e.target.value;
    if (g === 'def') {
      dataTable.columns([4,5]).search('').draw();
      currentSiteIds = []; dataTable.ajax.reload(); return;
    }

    try {
      const { response: sites = [] } =
        await postJSON('/vmi/clients/updte_table', { group_id: g });

      if (!sites.length) {
        dataTable.columns([4,5]).search('').draw();
        currentSiteIds = []; dataTable.ajax.reload(); return;
      }

      const nameRE = sites.map(s => `^${s.site_name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`).join('|');
      const idRE   = sites.map(s => `^${s.site_id}$`).join('|');

      dataTable.column(4).search(nameRE, { regex:true, smart:false });
      dataTable.column(5).search(idRE,   { regex:true, smart:false });

      currentSiteIds = sites.map(s => s.site_id);
      dataTable.ajax.reload();
    } catch (err) {
      console.error('group filter', err);
      toast('Couldn’t load group data', 'error');
    }
  });

  return dataTable;
}
