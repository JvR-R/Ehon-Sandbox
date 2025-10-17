/* /vmi/js/main.js  -------------------------------------------------- *
 * Vanilla-JS + jQuery (DataTables) front-end for the VMI dashboard.  *
 * All network traffic now goes through fetchJSON / postJSON helpers  *
 * that inject CSRF automatically.                                    *
 *-------------------------------------------------------------------*/

/*====================================================================*
 * 0 — IMPORTS & GLOBAL SHORTCUTS
 *===================================================================*/
const CSRF          = document.querySelector('meta[name="csrf"]').content;
let   currentSiteIds = [];                        // ← “active group” filter
// keep track of our charts so we can destroy them later
const chartRegistry = {};
// ───────────────── fetch wrapper with cache-bypass & timeout ─────────
async function fetchJSON(url, opts = {}, timeout = 10_000) {
  const ctrl = new AbortController();
  const id   = setTimeout(() => ctrl.abort(), timeout);

  const res  = await fetch(url, {
    cache       : 'no-store',
    credentials : 'same-origin',
    headers     : {
      'Content-Type' : 'application/json',
      'X-CSRF-Token' : CSRF,
      ...(opts.headers || {})
    },
    signal : ctrl.signal,
    ...opts
  }).finally(() => clearTimeout(id));

  // if it’s not OK, try to parse JSON and re-throw that error
  if (!res.ok) {
    let errMsg = `${res.status} ${res.statusText}`;
    try {
      const payload = await res.json();
      if (payload.error) errMsg = payload.error;
    } catch {}
    throw new Error(errMsg);
  }

  return res.json();
}


const postJSON = (url, data, t) =>
  fetchJSON(url, { method:'POST', body: JSON.stringify(data) }, t);

// ───────── misc one-liners we use everywhere ─────────
const $          = window.jQuery || window.$;
const byId       = id => document.getElementById(id);
const qs         = o  => new URLSearchParams(o).toString();
const numberFmt  = x  => (x == null ? '' : Number(x).toLocaleString());
const allowedAcl = [1,4,6,8];

/*====================================================================*
 * 1 — UI HELPERS
 *===================================================================*/
const buildStatusIcon = d => {
  const icon = (f,a)=>
    `<div class="tooltip"><img src="/vmi/images/${f}" alt=""><span class="tooltiptext">${a}</span></div>`;
  if (d.flagdv==1)                                                return icon('flag_dv_icon.png','A device has been disconnected');
  if ((!d.last_conndate && d.device_type!=201) ||
      (d.last_conndate && Date.parse(d.last_conndate)<=Date.now()-2*864e5)) return icon('console_offline.png','Console Offline');
  if (!d.dipr_date ||
      (Date.parse(d.dipr_date)<=Date.now()-3*864e5))              return icon('dip_offline.png','Dip Out-of-Sync');
  if (d.current_volume>=d.crithigh_alarm)                         return icon('crithigh_icon.png','Critical High Alarm');
  if (d.current_volume<=d.critlow_alarm)                          return icon('critlow_icon.png' ,'Critical Low Alarm');
  if (d.current_volume>=d.high_alarm)                             return icon('higha_icon.png'    ,'High Alarm');
  if (d.current_volume<=d.low_alarm)                              return icon('lowa_icon.png'     ,'Low Alarm');
  return '';
};
const progressBar = p=>{
  if(p==null) return '';
  const val = Number(p).toFixed(2);
  const cls = p<=33?'redprogress':(p<67?'yellowprogress':'greenprogress');
  return `<div class="progress-bar"><div class="${cls}" style="width:${val}%"></div><div class="percentage">${val}%</div></div>`;
};
const navColor=(row,a)=>['1','2','3','4'].forEach(n=>
  $(`.navigation-item${n}${row}`).css('color',n===a?'red':'#222'));
function toast(msg,v='info'){
  const el=document.createElement('div');
  el.className=`toast toast--${v}`; el.textContent=msg; document.body.append(el);
  requestAnimationFrame(()=>el.classList.add('show'));
  setTimeout(()=>{el.classList.remove('show');setTimeout(()=>el.remove(),300);},4000);
}
function showInlineError(el, msg) {
  // remove existing
  const prev = el.parentNode.querySelector('.inline-error');
  if (prev) prev.remove();
  const err = document.createElement('div');
  err.className = 'inline-error';
  err.textContent = msg;
  el.parentNode.insertBefore(err, el.nextSibling);
}

/*====================================================================*
 * 2 — DOM READY
 *===================================================================*/
document.addEventListener('DOMContentLoaded',async()=>{

/*------------------------------------------------------------------*
 * 2.0  hidden-data globals
 *-----------------------------------------------------------------*/
const hd               = byId('hidden-data')||{};
window.companyId       = +hd.getAttribute?.('data-company-id')  ||0;
window.userAccessLevel = +hd.getAttribute?.('data-access-level')||0;

/*------------------------------------------------------------------*
 * 2.1  DataTable
 *-----------------------------------------------------------------*/
const table = $('#customers_table').DataTable({
  paging     : false,
  serverSide : true,
  ajax : {
    url  : '/vmi/api/tanks',
    type : 'POST',
    data : d => { d.site_ids = currentSiteIds; },  // ← custom param
    dataSrc : 'data'
  },
  columns : [
    { data:null, defaultContent:'', className:'dt-control' },           // 0
    { data:'client_name',  className:'hide-on-mobile' },                // 1
    { data:'last_conndate' },                                           // 2
    { data:'last_conntime',className:'hide-on-mobile' },                // 3
    { data:'site_name' },                                               // 4
    { data:'tank_name',    className:'hide-on-mobile' },                // 5
    { data:'tank_id' },                                                 // 6
    { data:'product_name', className:'hide-on-mobile' },                // 7
    { data:'capacity',     render:numberFmt },                          // 8
    { data:'current_volume',render:numberFmt },                         // 9
    { data:null, className:'hide-on-mobile', render:buildStatusIcon },  //10
    { data:'temperature',  className:'hide-on-mobile' },                //11
    { data:'ullage',       render:numberFmt },                          //12
    { data:'current_percent',render:progressBar }                       //13
  ],
  rowCallback : (row,d)=>{
    $(row)
      .attr('data-uid',d.uid)
      .attr('data-cs_type',d.device_type==201?'MCS_PRO':'')
      .attr('data-site_id',d.Site_id)
      .attr('data-mcs_id',d.mcs_id||'')
      .attr('data-client_id',d.client_id)
      .attr('data-mcs_idpro',d.mcs_clientid||'')
      .attr('data-mcs_idlite',d.mcs_liteid||'');
  }
});

/*------------------------------------------------------------------*
 * 2.2  group filter
 *-----------------------------------------------------------------*/
byId('group_filter').addEventListener('change', async e => {
  const group = e.target.value;

  if(group==='def'){
    table.columns([4,5]).search('').draw();
    currentSiteIds = [];
    table.ajax.reload();   // keep one call, lose the other
    return;
  }

  try{
    const { response: sites=[] } =
      await postJSON('/vmi/clients/updte_table',{ group_id:group });

    if(!sites.length){
      table.columns([4,5]).search('').draw();
      currentSiteIds=[]; table.ajax.reload(); return;
    }

    const nameRE = sites.map(s=>`^${s.site_name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}$`).join('|');
    const idRE   = sites.map(s=>`^${s.site_id}$`).join('|');

    table.column(4).search(nameRE,true,false);
    table.column(5).search(idRE  ,true,false);
    // table.draw();

    currentSiteIds = sites.map(s=>s.site_id);
    table.ajax.reload();
  }catch(err){
    console.error('Group filter fetch failed',err);
    toast('Couldn’t load group data','error');
  }
});


  /*------------------------------------------------------------------*
   * 2.3  open / close child rows
   *-----------------------------------------------------------------*/
  $('#customers_table tbody').on('click', 'td.dt-control', async function () {

    const tr   = $(this).closest('tr');
    const row  = table.row(tr);
    const d    = row.data();
    const idx  = row.index();

    if (row.child.isShown()) { row.child.hide(); tr.removeClass('shown'); return; }

    const $childUI = buildChild(
      d, tr.data('uid'), tr.data('cs_type'), tr.data('site_id'),
      tr.data('mcs_id'), tr.data('mcs_idpro'), tr.data('mcs_idlite'),
      tr.data('client_id'), idx
    );

    row.child($childUI).show();
      // disable buttons until data arrives
    $childUI.find('.button-js, .button-js2, .button-js3').prop('disabled', true);
    tr.addClass('shown').next().addClass('expanded-details');

    /* fetch FMS data ------------------------------------------------*/
    try {
      const r   = await fetchJSON('/vmi/clients/dropdowns_config.php?' +
                                  qs({ uid:d.uid, tank_no:d.tank_id, case:4 }));
      const n   = +r.fms_number || 0;
      $('#fms_number-'+idx).val(n);
      if (n>0) {
        await generateFMSSections(idx, d.uid, n, r.fmsData||[]);
        (r.fmsData||[]).forEach((f,i)=>{
          const k = i+1;
          $(`#fms_port-${idx}-${k}`).val(f.fms_port);
          $(`#fms_type-${idx}-${k}`).val(f.fms_type);
          $(`#fms_id-${idx}-${k}`  ).val(f.fms_id);
        });
      }
    } catch(e){ console.error(e); }

    /* charts + db info ---------------------------------------------*/
    if (tr.data('cs_type')==='MCS_PRO' || tr.data('cs_type')==='MCS_LITE')
         fetchChartDataMCS(d.uid,d.tank_id,tr.data('cs_type'),
                           d.tank_name,d.site_name,d.Site_id,idx);
    else fetchChartData(d.uid,d.tank_id,idx);
  });

  /*==================================================================*
   * 3 — BUILD CHILD DETAIL ROW
   *=================================================================*/
  function buildChild(d, uid, cs_type, site_id,
                      mcs_id, mcs_idpro, mcs_idlite, client_id, row) {

    const tank_no   = d.tank_id;
    const tank_name = d.tank_name;
    const capacity  = String(d.capacity).replace(/,/g,'');
    const prodName  = d.product_name;

    /* ---------------- NAV ----------------------------------------- */
    const $nav = $(`
      <nav class="nav-items" role="navigation" style="display:flex;">
        <button class="navigation-item1${row}" style="color:red">Information</button>
        <button class="navigation-item2${row}">Alerts</button>
        <button class="navigation-item3${row}">Configuration</button>
        <button class="navigation-item4${row}">Temperature</button>
      </nav>`);

    $(document).off(`click.nav${row}`).on(
      `click.nav${row}`,
      `.navigation-item1${row},.navigation-item2${row},.navigation-item3${row},.navigation-item4${row}`,
      e => {
        const n = e.currentTarget.className.match(/navigation-item(\d)/)[1];
        showTab(row,n);
      });

    /* ---------------- info tab ------------------------------------ */
    const infoTab = $(`
      <div class="left-info">
      <div class="loading-overlay"><div class="spinner"></div></div>
        <div class="info_text" style="visibility:hidden">
          <div>Company Name:<strong> ${d.client_name}</strong></div>
          <div id="estimatedDaysLeft-${row}">Estimated days left: <strong>N/A</strong></div>
          ${cs_type!=='MCS_PRO' && cs_type!=='MCS_LITE'
              ? `<div id="last-conn-${row}">Device Date:<strong>N/A</strong></div>
                 <div id="last-conntime-${row}">Device Time:<strong>N/A</strong></div>` : ''}
          ${cs_type==='MCS_PRO'
              ? `<div><a target="_blank" href="https://new.mcstsm.com/sites/${mcs_idpro}/${mcs_id}">More Information</a></div>`:''}
          ${cs_type==='MCS_LITE'
              ? `<div><a target="_blank" href="https://mcs-connect.com/sites/${mcs_idlite}/details/${mcs_id}/">More Information</a></div>`:''}
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
                data-uid="${uid}" data-tank_no="${tank_no}" data-site_id="${site_id}" data-row-index="${row}">
          Update
        </button>
      </div>

      <div class="right-info">
        <div class="chart1" id="chart-container-${row}">
          <canvas id="chart-${row}"></canvas>
        </div>
      </div>`);

    /* ---------------- alerts tab ---------------------------------- */
    const alarmRow = (img, lab, id, lvl) => `
      <label class="lab1"><img src="/vmi/images/${img}" alt="">${lab}: </label>
      <input class="recip" id="${id}-${row}" type="number" style="width:90%;margin-top:5px;">
      <select class="relay-select" id="relay-${lvl}-${row}">
        <option value="1">Relay 1</option><option value="2">Relay 2</option>
        <option value="3">Relay 3</option><option value="4">Relay 4</option>
      </select>`;

    const $alerts = $(`
      <div class="alert_info${row}">
      <div class="loading-overlay"><div class="spinner"></div></div>
        <div class="alerts_div" style="visibility:hidden">
          <div class="grid-container">
            <div class="grid-2-columns"
                 style="display:grid;grid-template-columns:3fr 3.5fr 1.5fr;justify-items:start;margin-top:.5rem;">
              ${alarmRow('crithigh_icon.png','Critical High Alarm','chigha','hh')}
              ${alarmRow('higha_icon.png'   ,'High Alarm'         ,'higha','h')}
              ${alarmRow('lowa_icon.png'    ,'Low Alarm'          ,'lowa','l')}
              ${alarmRow('critlow_icon.png' ,'Critical Low Alarm' ,'clowa','ll')}
            </div>
          </div>
        </div>
        <button class="button-js3"
          data-uid="${uid}" data-tank_no="${tank_no}"
          data-site_id="${site_id}" data-client_id="${client_id}"
          data-capacity="${capacity}" data-row-index="${row}">
          Update
        </button>
      </div>`).hide();

    /* ---------------- configuration tab --------------------------- */
    const $cfg = $(`
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
                  data-site_id="${site_id}" data-row-index="${row}">
            Update
          </button>
        </div>
      </div>`).hide();

    /* ---------------- temperature tab ----------------------------- */
    const $temp = $(`
      <div class="temp_info${row}">
      <div class="loading-overlay"><div class="spinner"></div></div>
        <div class="left-info" style="visibility:hidden">
          <div class="info_text" style="max-width:285px">
            <div>Temperature:<strong> ${d.temperature} ºC</strong></div>
            <div id="tc-vol-${row}">Temperature Corrected Vol: <strong>N/A</strong></div>
          </div>
        </div>
        <div class="right-info">
          <div class="chart1" id="tempchart-container-${row}">
            <canvas id="tempchart-${row}"></canvas>
          </div>
        </div>
      </div>`).hide();

    /* ---------------- assemble ------------------------------------ */
    return $('<div class="child_details"></div>')
             .append($nav)
             .append($('<div class="minfo"></div>').append(infoTab))
             .append($alerts, $cfg, $temp);
  }

  /*==================================================================*
   * 4 — TAB SHOW/HIDE
   *=================================================================*/
  function showTab(row,n){
    $(`.alert_info${row}, .tank_info${row}, .temp_info${row}`).hide();
    if(n==='2') $(`.alert_info${row}`).show();
    if(n==='3') $(`.tank_info${row}`).show();
    if(n==='4') {
      // first show() so that any inline "display: none" is cleared,
      // then immediately force it to flex
      $(`.temp_info${row}`)
        .show()
        .css({
          display: 'flex',
          'flex-direction': 'row',      // or column, whatever your layout needs
          wrap: 'wrap'                  // optional
        });
    }
    navColor(row,n);
  }

  /*==================================================================*
   * 5 — FMS HELPERS
   *=================================================================*/
  async function generateFMSSections(row, uid, n, fmsData=[]){
    const c = $(`.fms-container-${row}`).empty();
    for(let i=1;i<=n;i++){
      c.append(`
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
      populateFMSPorts(row,i);
      await populateFMSDevices(row,i);
      const f = fmsData[i-1]||{};
      $(`#fms_port-${row}-${i}`).val(f.fms_port||0);
      $(`#fms_type-${row}-${i}`).val(f.fms_type||0);
      $(`#fms_id-${row}-${i}`).val(f.fms_id  ||0);
    }
  }
  const populateFMSPorts = (row,i)=>
    $(`#fms_port-${row}-${i}`).empty()
      .append('<option value="0">NO DEVICE...</option>')
      .append('<option value="5">Port A</option>')
      .append('<option value="6">Port B</option>')
      .append('<option value="3">Port C</option>');

  async function populateFMSDevices(row,i){
    const sel = $(`#fms_type-${row}-${i}`).empty();
    try{
      const r = await fetchJSON('/vmi/clients/dropdowns_config.php?case=get_fms_devices');
      (r.devices||[]).forEach(d=>{
        if(d.device_id<200) sel.append(`<option value="${d.device_id}">${d.device_name}</option>`);
      });
    }catch(e){ console.error('FMS device list error',e); }
  }

  /*==================================================================*
   * 6 — EVENT HANDLERS
   *=================================================================*/

  /*---------------------------------------------------------------*
   * 6.1  FMS port  → auto-select device
   *--------------------------------------------------------------*/
  $(document).on('change','select[id^="fms_port-"]',async function(){
    const [, row, idx]  = this.id.split('-');            // fms_port-row-idx
    const uid            = $(this).data('uid');
    const val            = this.value;
    if(+val===0) return;
    try{
      const r = await fetchJSON('/vmi/clients/dropdowns_config.php?'+
                 qs({ selectedValue: val, case:3, uid }));
      if(r.newValue<200) $(`#fms_type-${row}-${idx}`).val(r.newValue);
      else               $(`#fms_type-${row}-${idx}`).val(0);
    }catch(e){ console.error(e); }
  });

  /*---------------------------------------------------------------*
   * 6.2  TG port   → auto-select device + ID logic
   *--------------------------------------------------------------*/
  $(document).on('change','select[id^="tg_port-"]',async function(){
    const row = this.id.split('-')[1];
    const uid = $(this).data('uid');
    let val   = this.value;
    if(val==='1_1'){ val=11; $('#tg_id-'+row).val(1); }
    if(val==='1_2'){ val=12; $('#tg_id-'+row).val(2); }
    if(+val===0) return;

    try{
      const r = await fetchJSON('/vmi/clients/dropdowns_config.php?'+
                 qs({ selectedValue: val, case:3, uid }));
      if(r.newValue>200 && r.newValue<300)
        $(`#tg_type-${row}`).val(r.newValue);
      else $(`#tg_type-${row}`).val(0);
    }catch(e){ console.error(e); }
  });

  /*---------------------------------------------------------------*
   * 6.3  TG type   → enable / disable chart dropdown
   *--------------------------------------------------------------*/
  $(document).on('change','select[id^="tg_type-"]',function(){
    const row=this.id.split('-')[1];
    const sel=this.value;
    $('#tg_id-'+row   ).prop('disabled', true);
    $('#chart_id-'+row).prop('disabled', sel==='202');
  });

  /*---------------------------------------------------------------*
   * 6.4  fms_number change → add/remove sections on the fly
   *--------------------------------------------------------------*/
  $(document).on('change','select[name="fms_number"]',async function(){
    const row     = this.id.split('-')[1];
    const uid     = $(this).data('uid');
    const tank_no = $(this).data('tank_no');
    const n       = +this.value||0;
    if(n===0){ $(`.fms-container-${row}`).empty(); return; }

    $(this).prop('disabled',true);
    try{
      const r=await fetchJSON('/vmi/clients/dropdowns_config.php?'+
                 qs({case:5, uid, tank_no, fms_number:n}));
      await generateFMSSections(row, uid, n, r.fmsData||[]);
    }catch(e){ console.error(e); toast('FMS request failed','error'); }
    finally{ $(this).prop('disabled',false); }
  });

  /*---------------------------------------------------------------*
   * 6.5  Update buttons (alerts, config, thresholds)
   *--------------------------------------------------------------*/
  $(document).on('click', '.button-js', async function () {
    if (!allowedAcl.includes(+window.userAccessLevel)) {
      toast('Not enough privileges', 'error');
      return;
    }
  
    const { uid, tank_no, site_id, rowIndex } = this.dataset;   // ← get it here
    const det = $(this).closest('.child_details');
  
    const data = {
      case: 1,
      uid,
      tank_no,
      site_id,
      vol_alert : det.find(`#vol_alert-${rowIndex}`).val(),
      alert_type: det.find(`#alert_type-${rowIndex}`).val(),
      email     : det.find(`#email-${rowIndex}`).val()
    };
  
    try {
      await postJSON('/vmi/clients/update', data);
      toast('Update successful', 'success');
    } catch (e) {
      console.error(e);
      toast('Update failed', 'error');
    }
  });
  

  $(document).on('click','.button-js2',async function(){
    if(!allowedAcl.includes(+window.userAccessLevel)){
      toast('Not enough privileges', 'error'); return;
    }
    const det    = $(this).closest('.child_details');
    const rowIdx = this.dataset.rowIndex;
    const data   = {
      case         :2,
      uid          :this.dataset.uid,
      tank_no      :this.dataset.tank_no,
      site_id      :this.dataset.site_id,
      product_name : det.find('select[name="product_name"]').val(),
      tank_number  : det.find('input[name="tank_number"]').val(),
      tank_name    : det.find('input[name="tank_name"]').val(),
      capacity     : det.find(`#capacity-${rowIdx}`).val().replace(/,/g,''),
      tg_port      : det.find('select[name="tg_port"]').val(),
      tg_type      : det.find('select[name="tg_type"]').val(),
      tg_id        : det.find('input[name="tg_id"]').val(),
      tg_offset    : det.find('input[name="tg_offset"]').val(),
      chart_id     : det.find('select[name="chart_id"]').val(),
      relaybox_port: det.find('select[name="relaybox_port"]').val(),
      relaybox_type: det.find('select[name="relaybox_type"]').val(),
      fms_data     : []
    };
    det.find(`.fms-container-${rowIdx} [id^="fms-${rowIdx}-"]`).each(function(){
      const i=this.id.split('-')[2];
      data.fms_data.push({
        fms_port:$(`#fms_port-${rowIdx}-${i}`).val(),
        fms_type:$(`#fms_type-${rowIdx}-${i}`).val(),
        fms_id  :$(`#fms_id-${rowIdx}-${i}`).val()
      });
    });
    if(data.tg_port==='1_1'){ data.tg_port=1; data.tg_id=1; }
    if(data.tg_port==='1_2'){ data.tg_port=1; data.tg_id=2; }

    try{
      const r=await postJSON('/vmi/clients/update',data);
      if(r.idduplicate)      toast('Error, duplicate ID', 'error');
      else if(r.dvduplicate) toast('Port is associated to a different tank!', 'error');
      else                   toast('Update successful!', 'success');
    }catch(e){ console.error(e); toast('Update failed', 'error'); }
  });

  $(document).on('click','.button-js3',async function(){
    if(!allowedAcl.includes(+window.userAccessLevel)){
      toast('Not enough privileges', 'error'); return;
    }
    const { uid,tank_no,site_id,client_id,capacity,rowIndex } = this.dataset;
    const row = rowIndex;          
    const det  = $(this).closest('.child_details');
    const data = {
      case     :3,
      uid, tank_no, site_id, client_id, capacity,
      higha    : det.find(`#higha-${row}` ).val(),
      lowa     : det.find(`#lowa-${row}`  ).val(),
      chigha   : det.find(`#chigha-${row}`).val(),
      clowa    : det.find(`#clowa-${row}`).val(),
      relay_hh : det.find(`#relay-hh-${row}`).val(),
      relay_h  : det.find(`#relay-h-${row}` ).val(),
      relay_l  : det.find(`#relay-l-${row}` ).val(),
      relay_ll : det.find(`#relay-ll-${row}`).val()
    };
    console.log(data,uid,tank_no,site_id,client_id,capacity)
    try{ await postJSON('/vmi/clients/update',data); toast('Update successful', 'success'); }
    catch(e){ console.error(e); toast('Update failed', 'error'); }
  });

  /*==================================================================*
   * 7 — CHART & INFO HELPERS
   *=================================================================*/
  async function fetchChartData(uid,tank_no,row){
    try{
      const r = await fetchJSON('/vmi/clients/objects?'+qs({uid,tank_no}));
      fillinfo(r,row);
      drawChart(r.response2,row);
      drawtempChart(r.response3,row);
    }catch(e){ console.error('fetchChartData',e); }
  }
  async function fetchChartDataMCS(uid,tank_no,cs_type,tank_name,sitename,site_id,row){
    try{
      const r = await fetchJSON('/vmi/clients/objectsap?'+
                 qs({uid,tank_no,cs_type,tank_name,sitename,site_id}));
      drawChart(r.response2,row);
      fillinfo_mcs(r,row);
    }catch(e){ console.error('fetchChartDataMCS',e); }
  }

  /*---------------------------------------------------------------*
   * 7.2  Lazy load chart data
   *--------------------------------------------------------------*/
  let chartJsLoader = null;
  function loadChartJs() {
    if (window.Chart) return Promise.resolve();  // already there
    if (!chartJsLoader) {
      chartJsLoader = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src   = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
        script.onload  = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Chart.js'));
        document.head.appendChild(script);
      });
    }
    return chartJsLoader;
  }

   /*---------------------------------------------------------------*
   * 7.3  drawChart
   *--------------------------------------------------------------*/
   async function drawChart(data, row) {
    await loadChartJs();  
    // 1) re-produce your existing data-prep logic
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
    const makeArr = () => Array(all.length).fill(null);
    const avgArr = makeArr().map((_, i) => {
      const idx = dAvg.indexOf(all[i]);
      return idx > -1 ? vAvg[idx] : null;
    });
    const tcArr = makeArr().map((_, i) => {
      const idx = dTc.indexOf(all[i]);
      return idx > -1 ? vTc[idx] : null;
    });
    const delArr = makeArr().map((_, i) => {
      const idx = dDel.indexOf(all[i]);
      return idx > -1 ? del[idx] : null;
    });

    // 2) grab the canvas and destroy any existing chart
    const canvasId = `chart-${row}`;
    const ctx = byId(canvasId);
    if (chartRegistry[canvasId]) {
      chartRegistry[canvasId].destroy();
    }

    // 3) create & register the new chart
    chartRegistry[canvasId] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: all,
        datasets: [
          { label: 'Minimum Volume',   data: avgArr },
          { label: 'Corrected Volume', data: tcArr },
          { label: 'Deliveries',       data: delArr }
        ]
      },
      options: { responsive: true }
    });
  }

/*---------------- drawtempChart ---------------------------------*/
async function drawtempChart(data, row) {
  await loadChartJs();

  if (!data || !Array.isArray(data.averagetempData)) return;

  // prep your temp arrays
  const sorted = [...data.averagetempData]
    .sort((a,b) => new Date(a.transaction_date) - new Date(b.transaction_date));
  const dates = sorted.map(x => x.transaction_date);
  const temps = sorted.map(x => +x.average_temperature);

  // destroy any existing chart on this canvas
  const canvasId = `tempchart-${row}`;
  const ctx = byId(canvasId);
  if (chartRegistry[canvasId]) {
    chartRegistry[canvasId].destroy();
  }

  // register the new one
  chartRegistry[canvasId] = new Chart(ctx, {
    type: 'line',
    data: {
      labels: dates,
      datasets: [{ label: 'Temperature', data: temps }]
    },
    options: { responsive: true }
  });
}


  /*---------------- fillinfo (console) ----------------------------*/
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
    $(`.alert_info${row}`).find('select').css('visibility', 'hidden');
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
    $(`.button-js[data-row-index="${row}"],
      .button-js2[data-row-index="${row}"],
      .button-js3[data-row-index="${row}"]`)
    .prop('disabled', false);

  }

  /*---------------- fillinfo_mcs (MCS consoles) --------------------*/
  function fillinfo_mcs(r,row){
    // Unwrap loading state
    const pane = document.querySelector(`.left-info .loading-overlay`);
    if(pane) pane.remove();
    document.querySelector(`.left-info .info_text`).style.visibility = '';
  
    byId(`email-${row}`).value = r.mail||'';
    ['higha','chigha','lowa','clowa'].forEach(k=>{
      if(r[k+'_r']) byId(`${k}-${row}`).value = r[k+'_r'];
    });
    if(r.estimatedDays){
      const rem = (+r.current_volume / +r.estimatedDays).toFixed(2);
      byId(`estimatedDaysLeft-${row}`).innerHTML =
        `Estimated days left: <strong>${rem}</strong>`;
    }
    ['alert_info','tank_info','temp_info'].forEach(kind => {
      const panel = document.querySelector(`.${kind}${row} .loading-overlay`);
      if (panel) panel.remove();
      const inner = document.querySelector(`.${kind}${row} > div:not(.loading-overlay)`);
      if (inner) inner.style.visibility = '';
    });
  }

  /*---------------- product / chart dropdowns ----------------------*/
  async function product_select(data,row,cs){
    try{
      const r=await fetchJSON('/vmi/clients/dropdowns_config?'+qs({rowIndex:row,case:cs}));
      if(r.products) product_dd(data,r.products,row);
      if(r.schart )  chart_dd  (data,r.schart ,row);
    }catch(e){ console.error(e); }
  }
  function product_dd(curr, arr,row){
    const sel=byId(`product_name-${row}`); sel.innerHTML='';
    arr.forEach(a=>{
      if(a.product_id){
        const o=document.createElement('option');
        o.value=a.product_id; o.textContent=a.product_name;
        if(a.product_name===curr) o.selected=true;
        sel.appendChild(o);
      }
    });
  }
  function chart_dd(curr, arr,row){
    const sel=byId(`chart_id-${row}`); sel.innerHTML='';
    arr.forEach(a=>{
      if(a.chart_id){
        const o=document.createElement('option');
        o.value=a.chart_id; o.textContent=a.chart_name;
        if(a.chart_id==curr) o.selected=true;
        sel.appendChild(o);
      }
    });
    // re-enable all of the “Update” buttons for this row
  $(`.button-js[data-row-index="${row}"],
    .button-js2[data-row-index="${row}"],
    .button-js3[data-row-index="${row}"]`)
  .prop('disabled', false);

  }

  /*---------------- Tooltip behaviour ------------------------------*/
  document.body.addEventListener('mouseover', e => {
  const tip = e.target.closest('.tooltip');
  if (!tip) return;
  tip.querySelector('.tooltiptext').style.visibility = 'visible';
  tip.querySelector('.tooltiptext').style.opacity    = 1;
});

document.body.addEventListener('mouseout', e => {
  const tip = e.target.closest('.tooltip');
  if (!tip) return;
  tip.querySelector('.tooltiptext').style.visibility = 'hidden';
  tip.querySelector('.tooltiptext').style.opacity    = 0;
});


});
/*====================================================================*
 * END of main.js
 *===================================================================*/
