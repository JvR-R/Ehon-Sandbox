/* /vmi/js/vmi-js/view-switcher.js
 * Controller that switches between card view (≤5 tanks) and table view (>5 tanks)
 */

import { initTable, dataTable } from './table.js';
import { renderTankCards, getTankDataFromCard } from './tank-card-view.js';
import { showTankModal, closeTankModal, isModalOpen } from './tank-modal.js';

const TANK_COUNT_THRESHOLD = 5;
let currentView = null; // 'card' or 'table'
let allTanksData = []; // Store all tank data for filtering
let tanksDataMap = new Map(); // Map to quickly find tank data by uid+tank_id

/**
 * Gets user's tank view preference from localStorage
 * @returns {'auto'|'card'|'table'} - User's preferred view mode
 */
export function getUserViewPreference() {
  return localStorage.getItem('tankViewMode') || 'auto';
}

/**
 * Determines which view to use based on tank count and user preference
 * @param {number} tankCount - Number of tanks
 * @returns {'card'|'table'} - View type to use
 */
export function determineViewType(tankCount) {
  const userPreference = getUserViewPreference();
  
  // If user has a specific preference, use it
  if (userPreference === 'card') {
    return 'card';
  }
  if (userPreference === 'table') {
    return 'table';
  }
  
  // Otherwise, use automatic mode (based on tank count)
  return tankCount <= TANK_COUNT_THRESHOLD ? 'card' : 'table';
}

/**
 * Initializes the view switcher and sets up the appropriate view
 */
export async function initViewSwitcher() {
  const container = document.getElementById('test');
  const tableElement = document.getElementById('customers_table');
  
  if (!container) {
    console.error('Container #test not found');
    return;
  }

  // Create container for card view
  let cardContainer = document.getElementById('tank-cards-container');
  if (!cardContainer) {
    cardContainer = document.createElement('div');
    cardContainer.id = 'tank-cards-container';
    cardContainer.className = 'tank-cards-container';
    container.appendChild(cardContainer);
  }

  // Initially hide table, we'll show it after determining view
  if (tableElement) {
    tableElement.style.display = 'none';
  }
  cardContainer.style.display = 'none';

  // Set up DataTable first (needed for data fetching)
  const table = await initTable();
  
  // Listen for data updates from DataTable
  setupDataTableListener(table, cardContainer, tableElement);
  
  // Also check initial data count
  checkAndSwitchView(table, cardContainer, tableElement);
  
  return { table, cardContainer };
}

/**
 * Sets up listener for DataTable data updates
 */
function setupDataTableListener(table, cardContainer, tableElement) {
  if (!table) return;

  // Listen for draw events (when data is loaded/updated)
  table.on('draw', () => {
    checkAndSwitchView(table, cardContainer, tableElement);
  });

  // Listen for search events
  table.on('search', () => {
    checkAndSwitchView(table, cardContainer, tableElement);
  });
}

/**
 * Checks tank count and switches to appropriate view
 */
async function checkAndSwitchView(table, cardContainer, tableElement) {
  if (!table) return;
  
  // Don't re-render cards while a modal is open - it can cause issues
  if (isModalOpen()) {
    return;
  }

  try {
    // Get current data from DataTable (after filtering/search)
    const data = table.rows({ search: 'applied' }).data().toArray();
    allTanksData = data;
    
    // Build map for quick lookup
    tanksDataMap.clear();
    data.forEach((tank, index) => {
      const key = `${tank.uid}-${tank.tank_id}`;
      tanksDataMap.set(key, { ...tank, _index: index });
    });

    const tankCount = data.length;
    const viewType = determineViewType(tankCount);

    if (viewType === 'card' && currentView !== 'card') {
      await switchToCardView(data, cardContainer, tableElement);
      currentView = 'card';
    } else if (viewType === 'table' && currentView !== 'table') {
      switchToTableView(cardContainer, tableElement);
      currentView = 'table';
    } else if (viewType === 'card' && currentView === 'card') {
      // Update card view with new data
      renderTankCards(data, cardContainer);
      setupCardClickHandlers(cardContainer);
    }
  } catch (error) {
    console.error('Error checking/switching view:', error);
  }
}

/**
 * Switches to card view
 */
async function switchToCardView(tanksData, cardContainer, tableElement) {
  // Hide table but keep DataTable wrapper visible for search/filter controls
  if (tableElement) {
    // Hide the table element itself
    const wrapper = tableElement.closest('.dataTables_wrapper');
    if (wrapper) {
      // Hide the scroll body/table area but keep the top controls (search, etc.)
      const scrollBody = wrapper.querySelector('.dataTables_scrollBody');
      const scroll = wrapper.querySelector('.dataTables_scroll');
      if (scrollBody) scrollBody.style.display = 'none';
      if (scroll) scroll.style.display = 'none';
      
      // Also hide the table element
      tableElement.style.display = 'none';
      
      // Ensure top controls (search box) remain visible
      const topControls = wrapper.querySelector('.dataTables_filter, .dt-search');
      if (topControls) {
        topControls.style.display = '';
        // Position it appropriately for card view
        topControls.style.marginBottom = '1rem';
      }
    } else {
      tableElement.style.display = 'none';
    }
  }

  // Show card container
  cardContainer.style.display = 'block';
  
  // Render cards
  renderTankCards(tanksData, cardContainer);

  // Set up card click handlers for expanding details
  setupCardClickHandlers(cardContainer);
}

/**
 * Switches to table view
 */
function switchToTableView(cardContainer, tableElement) {
  // Hide card container
  cardContainer.style.display = 'none';

  // Show table
  if (tableElement) {
    tableElement.style.display = 'table';
    const wrapper = tableElement.closest('.dataTables_wrapper');
    if (wrapper) {
      wrapper.style.display = 'block';
      // Show table containers that were hidden
      const scrollBody = wrapper.querySelector('.dataTables_scrollBody');
      const scroll = wrapper.querySelector('.dataTables_scroll');
      if (scrollBody) scrollBody.style.display = '';
      if (scroll) scroll.style.display = '';
      
      // Reset search box margin
      const topControls = wrapper.querySelector('.dataTables_filter, .dt-search');
      if (topControls) {
        topControls.style.marginBottom = '';
      }
    }
  }
}

/**
 * Sets up click handlers for tank cards to show details in a modal
 */
function setupCardClickHandlers(cardContainer) {
  // Remove existing listeners to avoid duplicates
  cardContainer.querySelectorAll('.tank-card').forEach(card => {
    const btn = card.querySelector('.tank-card__expand-btn');
    if (btn) {
      // Clone and replace to remove old listeners
      const newBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newBtn, btn);
    }
  });

  // Add new click listeners
  cardContainer.addEventListener('click', async (ev) => {
    const expandBtn = ev.target.closest('.tank-card__expand-btn');
    if (!expandBtn) return;

    const card = expandBtn.closest('.tank-card');
    if (!card) return;

    // Try to get tank data from card's stored data first, then fallback to map
    let tankData = null;
    let rowIndex = parseInt(card.dataset.index);
    
    try {
      if (card.dataset.tankData) {
        tankData = JSON.parse(card.dataset.tankData);
      }
    } catch (e) {
      console.warn('Could not parse tank data from card:', e);
    }
    
    // Fallback to map lookup to get the current index
    const uid = card.dataset.uid;
    const tankId = card.dataset.tankId;
    const key = `${uid}-${tankId}`;
    const tankDataEntry = tanksDataMap.get(key);
    
    if (tankDataEntry) {
      // Use the current index from the map
      rowIndex = tankDataEntry._index !== undefined ? tankDataEntry._index : rowIndex;
      // Get full tank data (without the _index property)
      if (!tankData) {
        const { _index, ...data } = tankDataEntry;
        tankData = data;
      }
    }
    
    if (!tankData) {
      console.error('Tank data not found for card:', key);
      return;
    }

    // Build context for modal
    const ctx = {
      uid: card.dataset.uid,
      cs_type: card.dataset.csType,
      tank_device_id: card.dataset.tankDeviceId,
      site_id: card.dataset.siteId,
      mcs_id: card.dataset.mcsId,
      mcs_idpro: card.dataset.mcsIdpro,
      mcs_idlite: card.dataset.mcsIdlite,
      client_id: card.dataset.clientId,
      row: rowIndex
    };

    // Show modal with tank details
    showTankModal(tankData, ctx);
  });
}

/**
 * Gets the current view type
 */
export function getCurrentView() {
  return currentView;
}

/**
 * Manually refresh the current view
 */
export async function refreshView() {
  if (!dataTable) return;
  
  const cardContainer = document.getElementById('tank-cards-container');
  const tableElement = document.getElementById('customers_table');
  
  await checkAndSwitchView(dataTable, cardContainer, tableElement);
}
