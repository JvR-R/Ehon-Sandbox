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
  
  // Check for saved preference
  const saved = localStorage.getItem(THEME_KEY);
  
  if (saved === 'dark') {
    // User has explicitly chosen dark mode
    html.setAttribute('data-theme', 'dark');
  } else if (!saved) {
    // No saved preference - detect browser preference
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      html.setAttribute('data-theme', 'dark');
      localStorage.setItem(THEME_KEY, 'dark');
    }
  } else {
    // User has explicitly chosen light mode
    html.removeAttribute('data-theme');
  }
  
  // Mark as initialized to prevent duplicate initialization
  html.setAttribute('data-theme-initialized', 'true');
})();

