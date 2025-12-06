/* /vmi/js/vmi-js/tank-modal.js
 * Modal popup for displaying tank details
 * Clean, modern popup with smooth animations
 */

import { buildChild } from './childRow.js';
import { fetchChartData, fetchChartDataMCS, fetchGatewayData } from './childRow.js';
import { destroyChart, resizeChartByRow } from './charts.js';

let currentModal = null;
let currentRowIndex = null;
let escapeHandler = null;
let modalRowCounter = 10000; // Start at high number to avoid conflicts with table rows

// Global close function will be set after closeTankModal is defined (see end of file)

/**
 * Creates and shows a modal with tank details
 * @param {Object} tankData - Tank data object
 * @param {Object} ctx - Context object with uid, cs_type, etc.
 */
export function showTankModal(tankData, ctx) {
  // Close any existing modal first (synchronously remove from DOM)
  if (currentModal) {
    // Force immediate close - remove from DOM right away
    const existingOverlay = currentModal;
    currentModal = null;
    currentRowIndex = null;
    if (existingOverlay && existingOverlay.parentNode) {
      existingOverlay.parentNode.removeChild(existingOverlay);
    }
    // Clean up charts immediately
    const existingRowIdx = existingOverlay?.getAttribute('data-row-index');
    if (existingRowIdx) {
      destroyChart(`chart-${existingRowIdx}`);
      destroyChart(`tempchart-${existingRowIdx}`);
    }
  }
  
  // Use a unique row index for modal to avoid conflicts with table rows
  // This ensures element IDs like 'alert_type-${row}' don't conflict with table rows
  const modalRow = modalRowCounter++;
  ctx.row = modalRow; // Override the row index for this modal

  // Create modal overlay
  const overlay = document.createElement('div');
  overlay.className = 'tank-modal-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-labelledby', 'tank-modal-title');

  // Create modal container
  const modal = document.createElement('div');
  modal.className = 'tank-modal';
  
  // Create modal header
  const header = document.createElement('div');
  header.className = 'tank-modal__header';
  
  const titleSection = document.createElement('div');
  titleSection.className = 'tank-modal__title-section';
  titleSection.innerHTML = `
    <h2 id="tank-modal-title" class="tank-modal__title">
      Tank ${tankData.tank_id} - ${tankData.site_name || 'Unknown Site'}
    </h2>
    <p class="tank-modal__subtitle">${tankData.product_name || 'N/A'}</p>
  `;
  
  // Create close button directly (not via innerHTML) so we can attach handler immediately
  const closeBtn = document.createElement('button');
  closeBtn.className = 'tank-modal__close';
  closeBtn.setAttribute('aria-label', 'Close modal');
  closeBtn.type = 'button';
  closeBtn.innerHTML = `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  `;
  
  // Attach close handler with closure to capture overlay reference
  // This ensures the handler always has the correct modal reference
  const handleClose = (function(overlayRef) {
    return function(e) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
      }
      
      // Close using the captured overlay reference
      if (overlayRef && overlayRef.parentNode) {
        closeTankModalDirect(overlayRef);
      } else {
        closeTankModal();
      }
      return false;
    };
  })(overlay); // Pass overlay as closure variable
  
  closeBtn.addEventListener('click', handleClose, true); // Capture phase
  closeBtn.addEventListener('click', handleClose, false); // Bubble phase
  closeBtn.onclick = handleClose;
  closeBtn.onmousedown = function(e) {
    e.stopPropagation(); // Prevent any other handlers
  };
  
  // Also add data attribute for easier selection
  closeBtn.setAttribute('data-modal-close', 'true');
  
  header.appendChild(titleSection);
  header.appendChild(closeBtn);

  // Create modal body
  const body = document.createElement('div');
  body.className = 'tank-modal__body';
  
  // Build child content (same as table row expansion)
  const childContent = buildChild(tankData, ctx);
  if (!childContent || !(childContent instanceof Element)) {
    console.error('buildChild returned invalid element');
    return;
  }

  // Mark this content as being in a modal so loading can be delayed
  childContent.setAttribute('data-in-modal', 'true');
  childContent.setAttribute('data-modal-row', modalRow.toString());

  // Wrap child content in a container for better styling
  const contentWrapper = document.createElement('div');
  contentWrapper.className = 'tank-modal__content';
  contentWrapper.appendChild(childContent);

  body.appendChild(contentWrapper);

  // Create modal footer (optional - can add actions here)
  const footer = document.createElement('div');
  footer.className = 'tank-modal__footer';
  footer.style.display = 'none'; // Hidden for now, can be used later

  // Assemble modal
  modal.appendChild(header);
  modal.appendChild(body);
  modal.appendChild(footer);
  overlay.appendChild(modal);

  // Add to DOM first
  document.body.appendChild(overlay);
  currentModal = overlay;
  currentRowIndex = modalRow; // Store the modal-specific row index
  
  // Store modal row index on overlay for childRow.js to detect
  overlay.setAttribute('data-row-index', modalRow.toString());

  // Verify button exists and is clickable after DOM is ready
  setTimeout(() => {
    const btn = overlay.querySelector('.tank-modal__close');
    if (!btn) {
      return;
    }
    
    // Force re-attach handler one more time as final fallback
    btn.onclick = function(e) {
      console.log('Final fallback handler fired!');
      e.preventDefault();
      e.stopPropagation();
      closeTankModal();
      return false;
    };
  }, 100);

  // Disable buttons until async work finishes
  contentWrapper.querySelectorAll('.button-js, .button-js2, .button-js3, .button-gwsave, .button-gwports, .button-gwalert')
         .forEach(btn => (btn.disabled = true));

  // Close when clicking backdrop (overlay) - use closure to capture overlay reference
  overlay.addEventListener('click', (function(overlayRef) {
    return function(e) {
      // Don't do anything if clicking the close button - it has its own handler
      if (e.target.closest('.tank-modal__close')) {
        return;
      }
      
      // Only close if clicking directly on overlay, not on modal or its children
      if (e.target === overlayRef) {
        closeTankModalDirect(overlayRef);
      }
    };
  })(overlay));

  // Close on Escape key - find modal even if currentModal is null
  escapeHandler = (e) => {
    if (e.key === 'Escape') {
      const visibleModal = currentModal || document.querySelector('.tank-modal-overlay.tank-modal-overlay--visible');
      if (visibleModal) {
        closeTankModalDirect(visibleModal);
      }
    }
  };
  document.addEventListener('keydown', escapeHandler);

  // Animate in
  requestAnimationFrame(() => {
    overlay.classList.add('tank-modal-overlay--visible');
    modal.classList.add('tank-modal--visible');
    
    // Listen for when the modal animation completes
    const handleAnimationEnd = (e) => {
      // Only handle the transform transition (ignore other transitions)
      if (e.target === modal && e.propertyName === 'transform') {
        modal.removeEventListener('transitionend', handleAnimationEnd);

        // Remove any loading overlays that might be covering charts
        const loadingOverlays = overlay.querySelectorAll('.loading-overlay');
        loadingOverlays.forEach(ov => {
          ov.remove();
        });

        // Ensure chart containers are visible
        const chartContainers = overlay.querySelectorAll('.chart1');
        chartContainers.forEach(container => {
          container.style.visibility = 'visible';
          container.style.display = '';
        });

        // Resize charts now that modal is fully visible
        resizeChartByRow(modalRow);
        window.dispatchEvent(new Event('resize'));

        // One extra resize a bit later to be safe
        setTimeout(() => {
          resizeChartByRow(modalRow);
        }, 300);
      }
    };
    
    modal.addEventListener('transitionend', handleAnimationEnd);
    
    // Fallback timeout in case transitionend doesn't fire
    setTimeout(() => {
      // Only resize if this modal is still visible
      if (overlay.classList.contains('tank-modal-overlay--visible') && currentModal === overlay) {
        resizeChartByRow(modalRow);
        window.dispatchEvent(new Event('resize'));
      }
    }, 400);
  });

  // Prevent body scroll when modal is open
  document.body.style.overflow = 'hidden';

  // NOTE: buildChild already schedules unifiedLoadOnce via requestAnimationFrame
  // So we don't need to call fetch functions again - that would cause double loading
  // Just wait for the automatic loading to complete
}

/**
 * Closes a specific modal overlay directly
 */
function closeTankModalDirect(overlayElement) {
  if (!overlayElement || !overlayElement.parentNode) {
    return;
  }

  // Try to extract row index from modal content
  let rowIdx = currentRowIndex;
  if (rowIdx === null) {
    const chartContainer = overlayElement.querySelector('[id^="chart-container-"]');
    if (chartContainer) {
      const match = chartContainer.id.match(/chart-container-(\d+)/);
      if (match) {
        rowIdx = parseInt(match[1]);
      }
    }
  }
  
  if (rowIdx !== null) {
    destroyChart(`chart-${rowIdx}`);
    destroyChart(`tempchart-${rowIdx}`);
  }

  // Animate out
  overlayElement.classList.remove('tank-modal-overlay--visible');
  const modal = overlayElement.querySelector('.tank-modal');
  if (modal) {
    modal.classList.remove('tank-modal--visible');
  }

  // Remove from DOM after animation
  setTimeout(() => {
    if (overlayElement && overlayElement.parentNode) {
      overlayElement.parentNode.removeChild(overlayElement);
    }
    
    // Clear currentModal if this was the current one
    if (currentModal === overlayElement) {
      currentModal = null;
      currentRowIndex = null;
    }

    // Restore body scroll if no more modals
    if (!document.querySelector('.tank-modal-overlay')) {
      document.body.style.overflow = '';
    }

    // Remove escape handler if no more modals
    if (!document.querySelector('.tank-modal-overlay') && escapeHandler) {
      document.removeEventListener('keydown', escapeHandler);
      escapeHandler = null;
    }
  }, 300); // Match animation duration
}

/**
 * Closes the tank modal and cleans up
 */
export function closeTankModal() {
  // Find modal in DOM if currentModal is null (fallback)
  let modalToClose = currentModal;
  if (!modalToClose) {
    modalToClose = document.querySelector('.tank-modal-overlay.tank-modal-overlay--visible');
  }
  
  if (!modalToClose) {
    return;
  }

  closeTankModalDirect(modalToClose);
}

/**
 * Checks if modal is currently open
 */
export function isModalOpen() {
  return currentModal !== null;
}

// Make close function globally accessible after it's defined
window.closeTankModalGlobal = function() {
  closeTankModal();
};
