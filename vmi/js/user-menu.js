/**
 * User Menu Dropdown and Update Name Functionality
 */

(function() {
  'use strict';

  const UserMenu = {
    init: function() {
      this.setupDropdown();
      this.setupClickOutside();
    },

    setupDropdown: function() {
      const button = document.getElementById('userMenuButton');
      const dropdown = document.getElementById('userMenuDropdown');

      if (!button || !dropdown) {
        console.warn('UserMenu: Button or dropdown not found', { button: !!button, dropdown: !!dropdown });
        return;
      }

      // Check if already initialized
      if (button.dataset.initialized === 'true') {
        console.log('UserMenu: Already initialized, skipping');
        return;
      }
      button.dataset.initialized = 'true';

      button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('UserMenu: Button clicked');
        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        const shouldShow = !isExpanded;
        console.log('UserMenu: Toggling dropdown', { isExpanded, shouldShow });
        button.setAttribute('aria-expanded', shouldShow);
        if (shouldShow) {
          dropdown.classList.add('show');
        } else {
          dropdown.classList.remove('show');
        }
        console.log('UserMenu: Dropdown classes after toggle', dropdown.className);
      });

      // Close menu when clicking on navigation links (except theme toggle)
      const menuItems = dropdown.querySelectorAll('.user-menu-item:not(.theme-toggle-menu-item)');
      menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
          // Close menu after a short delay to allow navigation
          setTimeout(() => {
            UserMenu.closeMenu();
          }, 100);
        });
      });
    },

    setupClickOutside: function() {
      document.addEventListener('click', function(e) {
        const container = document.querySelector('.user-menu-container');
        const button = document.getElementById('userMenuButton');
        const dropdown = document.getElementById('userMenuDropdown');

        if (container && !container.contains(e.target)) {
          if (button) button.setAttribute('aria-expanded', 'false');
          if (dropdown) dropdown.classList.remove('show');
        }
      });
    },


    closeMenu: function() {
      const button = document.getElementById('userMenuButton');
      const dropdown = document.getElementById('userMenuDropdown');
      if (button) button.setAttribute('aria-expanded', 'false');
      if (dropdown) dropdown.classList.remove('show');
    },


  };

  // Initialize on DOM ready - with retry mechanism
  function initializeUserMenu() {
    const button = document.getElementById('userMenuButton');
    const dropdown = document.getElementById('userMenuDropdown');
    
    if (button && dropdown) {
      UserMenu.init();
    } else {
      // Retry after a short delay if elements aren't ready
      setTimeout(initializeUserMenu, 100);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeUserMenu);
  } else {
    // DOM already loaded, but elements might not be ready yet
    setTimeout(initializeUserMenu, 0);
  }

  // Expose for external use
  window.UserMenu = UserMenu;

})();

