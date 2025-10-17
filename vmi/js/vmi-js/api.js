/* /vmi/js/api.js -------------------------------------------------- *
 * Network helpers, CSRF headers, and one-liner utilities           *
 *------------------------------------------------------------------*/

export const CSRF = document.querySelector('meta[name="csrf"]').content;

/*------------ fetchJSON / postJSON – all requests funnel here -----*/
export async function fetchJSON(url, opts = {}, timeout = 10_000) {
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

  if (!res.ok) {
    let msg = `${res.status} ${res.statusText}`;
    try { const p = await res.json(); if (p.error) msg = p.error; } catch {}
    throw new Error(msg);
  }
  return res.json();
}

export const postJSON = (url, data, t) =>
  fetchJSON(url, { method: 'POST', body: JSON.stringify(data) }, t);

/*------------ small helpers used app-wide -------------------------*/
// export const $          = window.jQuery || window.$;
export const byId       = id => document.getElementById(id);
export const qs         = o  => new URLSearchParams(o).toString();
export const numberFmt  = x  => (x == null ? '' : Number(x).toLocaleString());
export const allowedAcl = [1, 4, 6, 8];
/*--------------------------------------------------------------*
 *  Delegate helper – vanilla equivalent of $(document).on()    *
 *  on('click', '.btn', fn)                                     *
 *--------------------------------------------------------------*/
export const on = (type, selector, handler, opts) => {
  document.addEventListener(type, (evt) => {
    const target = evt.target.closest(selector);
    if (target) handler.call(target, evt);
  }, opts);
};