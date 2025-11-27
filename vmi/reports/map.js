/* /vmi/reports/map.js – Enhanced map with modern styling and animations */

// Initialize map with dark-themed tiles
const map = L.map('map', {
  zoomControl: false,  // We'll add custom positioned controls
  attributionControl: true
}).setView([-27.5, 136], 4);

// Add zoom control to top-right
L.control.zoom({
  position: 'topright'
}).addTo(map);

// Use CartoDB dark matter tiles for a sleek look
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
  maxZoom: 19,
  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
  subdomains: 'abcd'
}).addTo(map);

/* Helper: Get status class based on alert level */
function getStatusClass(alert) {
  switch (String(alert).toUpperCase()) {
    case 'CRITHIGH':
    case 'CRITLOW': return 'crit';
    case 'HIGH':
    case 'LOW': return 'warn';
    default: return 'ok';
  }
}

/* Helper: Get level class for tank percentage */
function getLevelClass(percent) {
  if (percent <= 20) return 'level-crit';
  if (percent <= 40) return 'level-warn';
  return 'level-ok';
}

/* Create custom HTML marker icon */
function createMarkerIcon(alert) {
  const status = getStatusClass(alert);
  
  return L.divIcon({
    className: 'map-marker-wrapper',
    html: `
      <div class="map-marker">
        <div class="map-marker-pin ${status}"></div>
        <div class="map-marker-shadow"></div>
      </div>
    `,
    iconSize: [32, 38],
    iconAnchor: [16, 38],
    popupAnchor: [0, -38]
  });
}

/* Build enhanced popup HTML */
function makePopup(loc) {
  const tankCount = loc.tanks.length;
  const avgLevel = tankCount > 0 
    ? Math.round(loc.tanks.reduce((sum, t) => sum + t.level, 0) / tankCount)
    : 0;
  
  const tankItems = loc.tanks.map(t => {
    const levelClass = getLevelClass(t.level);
    return `
      <li class="popup-tank-item">
        <span class="popup-tank-label">Tank ${t.id}</span>
        <span class="popup-tank-level ${levelClass}">${t.level}%</span>
      </li>
    `;
  }).join('');

  return `
    <div class="popup-header">
      <h4 class="popup-title">${loc.name}</h4>
    </div>
    <div class="popup-body">
      <ul class="popup-tank-list">
        ${tankItems}
      </ul>
      <div class="popup-stats">
        <div class="popup-stat">
          <div class="popup-stat-value">${tankCount}</div>
          <div class="popup-stat-label">Tanks</div>
        </div>
        <div class="popup-stat">
          <div class="popup-stat-value">${avgLevel}%</div>
          <div class="popup-stat-label">Avg Level</div>
        </div>
      </div>
    </div>
  `;
}

/* Create legend */
function createLegend() {
  const mapContainer = document.getElementById('map').parentElement;
  
  // Check if legend already exists
  if (mapContainer.querySelector('.map-legend')) return;
  
  const legend = document.createElement('div');
  legend.className = 'map-legend';
  legend.innerHTML = `
    <div class="map-legend-item">
      <span class="map-legend-dot ok"></span>
      <span>Normal</span>
    </div>
    <div class="map-legend-item">
      <span class="map-legend-dot warn"></span>
      <span>Warning</span>
    </div>
    <div class="map-legend-item">
      <span class="map-legend-dot crit"></span>
      <span>Critical</span>
    </div>
  `;
  mapContainer.appendChild(legend);
}

/* Create fullscreen toggle button */
function createFullscreenBtn() {
  const mapContainer = document.getElementById('map').parentElement;
  
  // Check if button already exists
  if (mapContainer.querySelector('.map-fullscreen-btn')) return;
  
  const btn = document.createElement('button');
  btn.className = 'map-fullscreen-btn';
  btn.title = 'Toggle fullscreen';
  btn.innerHTML = `
    <svg viewBox="0 0 24 24" fill="currentColor">
      <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
    </svg>
  `;
  
  btn.addEventListener('click', () => {
    const mapEl = document.getElementById('map');
    mapEl.classList.toggle('map-fullscreen');
    
    // Update button icon
    if (mapEl.classList.contains('map-fullscreen')) {
      btn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/>
        </svg>
      `;
      btn.title = 'Exit fullscreen';
    } else {
      btn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
        </svg>
      `;
      btn.title = 'Toggle fullscreen';
    }
    
    // Trigger map resize after animation
    setTimeout(() => map.invalidateSize(), 300);
  });
  
  mapContainer.appendChild(btn);
  
  // Handle escape key to exit fullscreen
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const mapEl = document.getElementById('map');
      if (mapEl.classList.contains('map-fullscreen')) {
        btn.click();
      }
    }
  });
}

/* Animate markers appearing */
function animateMarker(marker, delay) {
  const el = marker.getElement();
  if (el) {
    el.style.opacity = '0';
    el.style.transform = 'translateY(-20px) scale(0.5)';
    el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    
    setTimeout(() => {
      el.style.opacity = '1';
      el.style.transform = 'translateY(0) scale(1)';
    }, delay);
  }
}

/* Main: Fetch data and populate map */
fetch('gps_call', { method: 'POST' })
  .then(r => r.json())
  .then(({ locations }) => {
    if (!locations || locations.length === 0) {
      // Show empty state message
      const mapEl = document.getElementById('map');
      const emptyMsg = document.createElement('div');
      emptyMsg.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        color: #94a3b8;
        z-index: 500;
        pointer-events: none;
      `;
      emptyMsg.innerHTML = `
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; opacity: 0.5;">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
        </svg>
        <div style="font-size: 0.9rem;">No site locations available</div>
      `;
      mapEl.appendChild(emptyMsg);
      return;
    }

    const bounds = L.latLngBounds();
    
    locations.forEach((loc, index) => {
      const marker = L.marker(
        [loc.lat, loc.lng],
        { icon: createMarkerIcon(loc.alert) }
      ).addTo(map);
      
      // Bind popup with custom content
      marker.bindPopup(makePopup(loc), {
        maxWidth: 280,
        minWidth: 220,
        closeButton: true,
        className: 'custom-popup'
      });
      
      // Add hover effect
      marker.on('mouseover', function() {
        this.openPopup();
      });
      
      bounds.extend(marker.getLatLng());
      
      // Staggered animation for markers appearing
      marker.on('add', function() {
        animateMarker(this, index * 80);
      });
    });
    
    // Fit bounds with padding
    if (locations.length > 0) {
      map.fitBounds(bounds, { 
        padding: [60, 60],
        maxZoom: 12
      });
    }
    
    // Create UI elements
    createLegend();
    createFullscreenBtn();
  })
  .catch(err => {
    console.error('Map data fetch error:', err);
    
    // Show error state
    const mapEl = document.getElementById('map');
    const errorMsg = document.createElement('div');
    errorMsg.style.cssText = `
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
      color: #ef4444;
      z-index: 500;
    `;
    errorMsg.innerHTML = `
      <div style="font-size: 2rem; margin-bottom: 0.5rem;">⚠️</div>
      <div style="font-size: 0.9rem;">Failed to load map data</div>
    `;
    mapEl.appendChild(errorMsg);
  });
