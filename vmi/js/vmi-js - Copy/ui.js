/* /vmi/js/ui.js --------------------------------------------------- *
 * Pure-DOM helpers – no business logic here                        *
 *------------------------------------------------------------------*/

/*-------- toast / snackbar ---------------------------------------*/
export function toast(msg, v = 'info') {
  const el = document.createElement('div');
  el.className = `toast toast--${v}`;
  el.textContent = msg;
  document.body.append(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => {
    el.classList.remove('show');
    setTimeout(() => el.remove(), 300);
  }, 4000);
}

/*-------- inline error under a form field ------------------------*/
export function showInlineError(el, msg) {
  const prev = el.parentNode.querySelector('.inline-error');
  if (prev) prev.remove();
  const err = document.createElement('div');
  err.className = 'inline-error';
  err.textContent = msg;
  el.parentNode.insertBefore(err, el.nextSibling);
}

/*-------- coloured progress bar ----------------------------------*/
export function progressBar(p) {
  if (p == null) return '';
  const val = Number(p).toFixed(2);
  const cls = p <= 33 ? 'redprogress' : (p < 67 ? 'yellowprogress' : 'greenprogress');
  return `<div class="progress-bar">
            <div class="${cls}" style="width:${val}%"></div>
            <div class="percentage">${val}%</div>
          </div>`;
}

/*-------- row-level status icon ----------------------------------*/
export function buildStatusIcon(d) {
  const icon = (f, a) =>
    `<div class="tooltip"><img src="/vmi/images/${f}" alt=""><span class="tooltiptext">${a}</span></div>`;

  if (d.flagdv == 1) return icon('flag_dv_icon.png', 'A device has been disconnected');
  if ((!d.last_conndate && d.device_type != 201) ||
      (d.last_conndate && Date.parse(d.last_conndate) <= Date.now() - 2 * 864e5))
                     return icon('console_offline.png', 'Console Offline');
  if (!d.dipr_date ||
      (Date.parse(d.dipr_date) <= Date.now() - 3 * 864e5))
                     return icon('dip_offline.png', 'Dip Out-of-Sync');
  if (d.current_volume >= d.crithigh_alarm)
                     return icon('crithigh_icon.png', 'Critical High Alarm');
  if (d.current_volume <= d.critlow_alarm)
                     return icon('critlow_icon.png',  'Critical Low Alarm');
  if (d.current_volume >= d.high_alarm)
                     return icon('higha_icon.png',    'High Alarm');
  if (d.current_volume <= d.low_alarm)
                     return icon('lowa_icon.png',     'Low Alarm');
  return '';
}

/*-------- highlight nav button -----------------------------------*/
export function navColor(row, active) {
  document.querySelectorAll(`.navigation-item1${row},
                              .navigation-item2${row},
                              .navigation-item3${row},
                              .navigation-item4${row}`)
    .forEach(btn => {
      btn.style.color = (btn.className.includes(`navigation-item${active}`))
                          ? 'red' : '#222';
    });
}