/* /vmi/js/vmi-js/tank-card-view.js
 * Card/Icon view for tanks (when ≤5 tanks)
 * Modern, friendly UI with all tank data
 */

import { buildStatusIcon, progressBar } from './ui.js';
import { numberFmt } from './api.js';

/**
 * Creates a tank card element from tank data
 * @param {Object} tankData - Tank data object from API
 * @param {number} index - Index of the tank for unique IDs
 * @returns {HTMLElement} - Tank card element
 */
export function createTankCard(tankData, index) {
  const card = document.createElement('div');
  card.className = 'tank-card';
  card.dataset.uid = tankData.uid;
  card.dataset.tankId = tankData.tank_id;
  card.dataset.siteId = tankData.Site_id;
  card.dataset.index = index;
  
  // Store all data attributes needed for child row functionality
  card.dataset.csType = 
    tankData.device_type == 201 ? 'MCS_PRO' :
    tankData.device_type == 200 ? 'MCS_LITE' :
    tankData.device_type == 30 ? 'EHON_GATEWAY' :
    tankData.device_type == 20 ? 'EHON_Link' : '';
  card.dataset.tankDeviceId = tankData.tank_device_id ?? '';
  card.dataset.mcsId = tankData.mcs_id || '';
  card.dataset.clientId = tankData.client_id;
  card.dataset.mcsIdpro = tankData.mcs_clientid || '';
  card.dataset.mcsIdlite = tankData.mcs_liteid || '';
  
  // Store full tank data as JSON in a data attribute for easy access
  // This ensures we have all fields needed for child row expansion
  try {
    card.dataset.tankData = JSON.stringify(tankData);
  } catch (e) {
    console.warn('Could not stringify tank data:', e);
  }

  const percent = parseFloat(tankData.current_percent || 0);
  const statusIcon = buildStatusIcon(tankData);
  
  // Determine card status class based on percentage
  let statusClass = 'status-normal';
  if (percent <= 33) statusClass = 'status-low';
  else if (percent < 67) statusClass = 'status-medium';
  else statusClass = 'status-high';

  // Format dates/times
  const dipDate = tankData.dipr_date || 'N/A';
  const dipTime = tankData.dipr_time || '';

  card.innerHTML = `
    <div class="tank-card__header">
      <div class="tank-card__icon">
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <rect x="10" y="30" width="80" height="60" rx="5" fill="currentColor" opacity="0.2"/>
          <rect x="15" y="35" width="70" height="50" rx="3" fill="none" stroke="currentColor" stroke-width="2"/>
          <rect x="20" y="40" width="60" height="${60 - (percent / 100) * 40}" rx="2" fill="currentColor" opacity="0.6"/>
          <path d="M 45 20 L 50 15 L 55 20" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round"/>
          <line x1="50" y1="15" x2="50" y2="30" stroke="currentColor" stroke-width="2"/>
        </svg>
      </div>
      <div class="tank-card__status-icon">
        ${statusIcon}
      </div>
      <div class="tank-card__title">
        <h3 class="tank-card__tank-id">Tank ${tankData.tank_id || 'N/A'}</h3>
        <p class="tank-card__site-name">${tankData.site_name || 'Unknown Site'}</p>
      </div>
    </div>

    <div class="tank-card__body">
      <div class="tank-card__progress ${statusClass}">
        <div class="tank-card__progress-label">
          <span>Capacity</span>
          <span class="tank-card__percent">${percent.toFixed(1)}%</span>
        </div>
        <div class="tank-card__progress-bar-wrapper">
          ${progressBar(percent)}
        </div>
      </div>

      <div class="tank-card__details">
        <div class="tank-card__detail-row">
          <span class="tank-card__detail-label">Product</span>
          <span class="tank-card__detail-value">${tankData.product_name || 'N/A'}</span>
        </div>
        <div class="tank-card__detail-row">
          <span class="tank-card__detail-label">Current Volume</span>
          <span class="tank-card__detail-value">${numberFmt(tankData.current_volume)} L</span>
        </div>
        <div class="tank-card__detail-row">
          <span class="tank-card__detail-label">Capacity</span>
          <span class="tank-card__detail-value">${numberFmt(tankData.capacity)} L</span>
        </div>
        <div class="tank-card__detail-row">
          <span class="tank-card__detail-label">Ullage</span>
          <span class="tank-card__detail-value">${numberFmt(tankData.ullage)} L</span>
        </div>
        <div class="tank-card__detail-row">
          <span class="tank-card__detail-label">Last Reading</span>
          <span class="tank-card__detail-value">${dipDate} ${dipTime ? dipTime.substring(0, 5) : ''}</span>
        </div>
      </div>
    </div>

    <div class="tank-card__footer">
      <button class="tank-card__expand-btn" aria-label="View details">
        <span>View Details</span>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  `;

  return card;
}

/**
 * Renders all tank cards in a container
 * @param {Array} tanksData - Array of tank data objects
 * @param {HTMLElement} container - Container element to render cards into
 */
export function renderTankCards(tanksData, container) {
  // Clear container
  container.innerHTML = '';
  
  if (!tanksData || tanksData.length === 0) {
    container.innerHTML = '<div class="tank-cards-empty">No tanks found</div>';
    return;
  }

  // Create cards grid
  const grid = document.createElement('div');
  grid.className = 'tank-cards-grid';
  
  tanksData.forEach((tank, index) => {
    const card = createTankCard(tank, index);
    grid.appendChild(card);
  });

  container.appendChild(grid);
  
  // Add animation for cards appearing
  requestAnimationFrame(() => {
    const cards = grid.querySelectorAll('.tank-card');
    cards.forEach((card, idx) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      setTimeout(() => {
        card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, idx * 100);
    });
  });
}

/**
 * Gets the tank data object from a card element
 * Reconstructs the data object from card data attributes
 */
export function getTankDataFromCard(cardElement) {
  return {
    uid: cardElement.dataset.uid,
    tank_id: cardElement.dataset.tankId,
    Site_id: cardElement.dataset.siteId,
    // Add other fields as needed from the original data
    // This is a simplified version - you may need to store more data
  };
}
