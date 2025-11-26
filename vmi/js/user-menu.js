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

      if (!button || !dropdown) return;

      button.addEventListener('click', function(e) {
        e.stopPropagation();
        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', !isExpanded);
        dropdown.classList.toggle('show', !isExpanded);
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

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      UserMenu.init();
    });
  } else {
    UserMenu.init();
  }

  // Expose for external use
  window.UserMenu = UserMenu;

})();

