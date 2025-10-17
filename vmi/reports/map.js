/* /vmi/js/map.js – coloured CSS pins + tank-level popup */

const map = L.map('map').setView([-27.5, 136], 4);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19, attribution: '© OpenStreetMap'
}).addTo(map);

/* helper builds the popup HTML */
function makePopup(loc){
  return `<strong>${loc.name}</strong><br>
          <table>
            ${loc.tanks.map(
                t => `<tr><td>Tank ${t.id}</td><td>${t.level}%</td></tr>`
            ).join('')}
          </table>`;
}

/* Map alert to CSS bucket: ok → green, warn → yellow, crit → red */
function alertToClass(alert){
  switch (String(alert).toUpperCase()) {
    case 'CRITHIGH':
    case 'CRITLOW': return 'crit';
    case 'HIGH':
    case 'LOW':     return 'warn';
    default:        return 'ok';
  }
}

/* CSS-coloured pin icon */
function colourIcon(alert){
  return L.divIcon({
    className: 'pin pin--' + alertToClass(alert),   // pin--ok / warn / crit
    iconSize : [26,41],
    iconAnchor : [13,41],
    popupAnchor: [0,-32]
  });
}

fetch('gps_call', {method: 'POST'})
  .then(r => r.json())
  .then(({locations}) => {
      const bounds = L.latLngBounds();
      locations.forEach(loc => {
          const marker = L.marker(
              [loc.lat, loc.lng],
              {icon: colourIcon(loc.alert)}
          ).addTo(map)
           .bindPopup(makePopup(loc));

          bounds.extend(marker.getLatLng());
      });
      if (locations.length) map.fitBounds(bounds, {padding:[50,50]});
  })
  .catch(console.error);
