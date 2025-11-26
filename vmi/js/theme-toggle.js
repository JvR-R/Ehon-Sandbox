/**
 * EHON VMI - Theme Toggle System
 * Handles light/dark mode switching and persistence
 */

(function() {
  'use strict';

  const THEME_KEY = 'ehon-vmi-theme';
  const THEME_LIGHT = 'light';
  const THEME_DARK = 'dark';

  /**
   * Theme Manager Object
   */
  const ThemeManager = {
  /**
   * Initialize the theme system
   */
  init: function() {
    // Check if theme was already initialized by theme-init.js
    const alreadyInitialized = document.documentElement.hasAttribute('data-theme-initialized');
    
    if (!alreadyInitialized) {
      // Apply saved theme or default to system preference
      const savedTheme = this.getSavedTheme();
      this.applyTheme(savedTheme);
      document.documentElement.setAttribute('data-theme-initialized', 'true');
    } else {
      // Theme already set, just ensure toggle button state is correct
      const currentTheme = this.getCurrentTheme();
      this.updateToggleButton(currentTheme);
    }

    // Listen for theme toggle events
    this.attachEventListeners();

    // Listen for system theme changes (only if user hasn't manually set preference)
    this.listenToSystemTheme();
  },

  /**
   * Get the saved theme from localStorage
   */
  getSavedTheme: function() {
    const saved = localStorage.getItem(THEME_KEY);
    
    // If no saved preference, check system preference
    if (!saved) {
      return this.getSystemTheme();
    }
    
    return saved === THEME_DARK ? THEME_DARK : THEME_LIGHT;
  },

    /**
     * Get system/OS theme preference
     */
    getSystemTheme: function() {
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return THEME_DARK;
      }
      return THEME_LIGHT;
    },

    /**
     * Apply theme to the document
     */
    applyTheme: function(theme) {
      const html = document.documentElement;
      
      if (theme === THEME_DARK) {
        html.setAttribute('data-theme', 'dark');
      } else {
        html.removeAttribute('data-theme');
      }

      // Save preference
      localStorage.setItem(THEME_KEY, theme);

      // Update toggle button state
      this.updateToggleButton(theme);
    },

    /**
     * Toggle between light and dark themes
     */
    toggleTheme: function() {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? THEME_LIGHT : THEME_DARK;
      
      this.applyTheme(newTheme);

      // Dispatch custom event for other scripts to listen to
      window.dispatchEvent(new CustomEvent('themeChanged', { 
        detail: { theme: newTheme } 
      }));
    },

    /**
     * Update the toggle button UI
     */
    updateToggleButton: function(theme) {
      const toggleBtns = document.querySelectorAll('.theme-toggle-btn');
      const icons = document.querySelectorAll('.theme-toggle-icon');
      
      toggleBtns.forEach(function(btn) {
        if (theme === THEME_DARK) {
          btn.classList.add('dark');
        } else {
          btn.classList.remove('dark');
        }
      });

      icons.forEach(function(icon) {
        if (theme === THEME_DARK) {
          icon.className = 'theme-toggle-icon theme-icon-moon';
        } else {
          icon.className = 'theme-toggle-icon theme-icon-sun';
        }
      });
    },

    /**
     * Attach event listeners to toggle buttons
     */
    attachEventListeners: function() {
      const self = this;
      
      // Listen for clicks on theme toggle buttons
      document.addEventListener('click', function(e) {
        if (e.target.closest('.theme-toggle-btn') || e.target.closest('.button-link.theme-toggle')) {
          e.preventDefault();
          self.toggleTheme();
        }
      });

      // Keyboard accessibility - Enter or Space to toggle
      document.addEventListener('keydown', function(e) {
        const toggleElement = e.target.closest('.theme-toggle-btn');
        if (toggleElement && (e.key === 'Enter' || e.key === ' ')) {
          e.preventDefault();
          self.toggleTheme();
        }
      });
    },

    /**
     * Listen to system theme changes
     */
    listenToSystemTheme: function() {
      const self = this;
      
      if (window.matchMedia) {
        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        // Modern browsers
        if (darkModeQuery.addEventListener) {
          darkModeQuery.addEventListener('change', function(e) {
            // Only auto-switch if user hasn't manually set a preference
            if (!localStorage.getItem(THEME_KEY)) {
              self.applyTheme(e.matches ? THEME_DARK : THEME_LIGHT);
            }
          });
        }
        // Legacy browsers
        else if (darkModeQuery.addListener) {
          darkModeQuery.addListener(function(e) {
            if (!localStorage.getItem(THEME_KEY)) {
              self.applyTheme(e.matches ? THEME_DARK : THEME_LIGHT);
            }
          });
        }
      }
    },

    /**
     * Get current theme
     */
    getCurrentTheme: function() {
      return document.documentElement.getAttribute('data-theme') === 'dark' 
        ? THEME_DARK 
        : THEME_LIGHT;
    }
  };

  /**
   * Initialize on DOM ready
   */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      ThemeManager.init();
    });
  } else {
    // DOM already loaded
    ThemeManager.init();
  }

  /**
   * Expose ThemeManager globally for debugging/external use
   */
  window.ThemeManager = ThemeManager;

})();

