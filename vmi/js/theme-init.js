/**
 * EHON VMI - Theme Initialization
 * This script should be included in the <head> section BEFORE theme.css
 * to prevent flash of wrong theme on page load.
 * 
 * Usage: <script src="/vmi/js/theme-init.js"></script>
 * Place this BEFORE the theme.css link in the <head>
 */
(function() {
  'use strict';
  
  const THEME_KEY = 'ehon-vmi-theme';
  const html = document.documentElement;
  
  // Function to apply theme
  function applyTheme(isDark) {
    if (isDark) {
      html.setAttribute('data-theme', 'dark');
      localStorage.setItem(THEME_KEY, 'dark');
    } else {
      html.removeAttribute('data-theme');
      localStorage.setItem(THEME_KEY, 'light');
    }
  }
  
  // Step 1: Quick initialization to prevent flash
  // Check localStorage first (fastest)
  const saved = localStorage.getItem(THEME_KEY);
  
  if (saved === 'dark') {
    applyTheme(true);
  } else if (saved === 'light') {
    applyTheme(false);
  } else {
    // No saved preference - detect browser preference
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      applyTheme(true);
    } else {
      applyTheme(false);
    }
  }
  
  // Step 2: Check database preference (async, will update if different)
  // Only check if user might be logged in (check for session cookie or similar indicator)
  fetch('/vmi/db/get_dark_mode.php', {
    method: 'GET',
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success && data.dark_mode !== null) {
      const dbDarkMode = data.dark_mode === 1;
      const currentDarkMode = html.getAttribute('data-theme') === 'dark';
      
      // Update if database preference differs from current
      if (dbDarkMode !== currentDarkMode) {
        applyTheme(dbDarkMode);
      }
    }
  })
  .catch(error => {
    // Silently fail - use localStorage/browser preference
    console.debug('Could not fetch dark mode from database:', error);
  });
  
  // Mark as initialized to prevent duplicate initialization
  html.setAttribute('data-theme-initialized', 'true');
})();

