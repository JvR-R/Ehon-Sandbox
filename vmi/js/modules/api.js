/* tiny wrapper around fetch() that always returns parsed JSON,
   sends the CSRF token, and throws on HTTP errors             */

   export async function fetchJSON(url, body = null) {

    const opts = {
      method  : body ? 'POST' : 'GET',
      headers : {
        'X-CSRF'       : document.querySelector('meta[name=csrf]').content,
      }
    };
  
    if (body) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
  
    const res = await fetch(url, opts);
    if (!res.ok)
        throw new Error(`${res.status} ${res.statusText}`);
    return res.json();
  }
  