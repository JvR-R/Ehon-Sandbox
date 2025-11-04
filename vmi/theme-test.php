<?php
session_start();
// Mock session data for testing
$_SESSION['companyId'] = 15100;
$_SESSION['accessLevel'] = 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Theme Test Page</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- THEME CSS - MUST BE FIRST! -->
  <link href="/vmi/css/theme.css" rel="stylesheet" type="text/css">
  
  <!-- Other CSS files -->
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/ehon-energy-1.webflow.css" rel="stylesheet" type="text/css">
  
  <style>
    .test-container {
      padding: 20px;
      margin: 20px;
      max-width: 1200px;
    }
    .test-box {
      background-color: var(--bg-card);
      border: 2px solid var(--border-color);
      border-radius: 10px;
      padding: 30px;
      margin: 20px 0;
    }
    .test-box h2 {
      color: var(--text-primary);
      margin-top: 0;
    }
    .test-box p {
      color: var(--text-secondary);
    }
    .color-samples {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
      margin-top: 20px;
    }
    .color-sample {
      padding: 15px;
      border-radius: 5px;
      text-align: center;
      font-size: 12px;
    }
    .bg-primary { background-color: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); }
    .bg-secondary { background-color: var(--bg-secondary); color: var(--text-primary); }
    .accent-primary { background-color: var(--accent-primary); color: var(--btn-text); }
    .accent-danger { background-color: var(--accent-danger); color: var(--btn-text); }
    .accent-success { background-color: var(--accent-success); color: var(--btn-text); }
    
    .theme-status {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 10px 20px;
      background: var(--accent-primary);
      color: var(--btn-text);
      border-radius: 5px;
      font-weight: bold;
      z-index: 1000;
    }
  </style>
</head>
<body>

<?php include('db/border.php'); ?>

<div class="test-container" style="margin-left: 200px;">
  <div class="theme-status" id="theme-status">
    Loading theme...
  </div>
  
  <h1 style="color: var(--accent-primary);">🎨 Theme System Test Page</h1>
  
  <div class="test-box">
    <h2>✅ Theme Toggle Instructions</h2>
    <p><strong>Look at the sidebar on the left!</strong></p>
    <p>You should see a toggle button between "Update Password" and "Logout"</p>
    <p>Click it to switch between light and dark mode!</p>
    <ul>
      <li>☀️ Sun icon = Light Mode (default)</li>
      <li>🌙 Moon icon = Dark Mode</li>
    </ul>
  </div>
  
  <div class="test-box">
    <h2>🎨 Theme Color Samples</h2>
    <p>These should all change when you toggle the theme:</p>
    <div class="color-samples">
      <div class="color-sample bg-primary">Primary Background</div>
      <div class="color-sample bg-secondary">Secondary Background</div>
      <div class="color-sample accent-primary">Primary Accent</div>
      <div class="color-sample accent-danger">Danger/Red</div>
      <div class="color-sample accent-success">Success/Green</div>
    </div>
  </div>
  
  <div class="test-box">
    <h2>🔘 Button Samples</h2>
    <p>
      <button class="btn-primary">Primary Button</button>
      <button class="btn-secondary">Secondary Button</button>
    </p>
  </div>
  
  <div class="test-box">
    <h2>📝 Form Elements</h2>
    <p>
      <input type="date" value="2024-11-04">
      <select class="form-select form-select-lg">
        <option>Option 1</option>
        <option>Option 2</option>
        <option>Option 3</option>
      </select>
    </p>
  </div>
  
  <div class="test-box">
    <h2>✅ Files Loaded</h2>
    <ul>
      <li>✅ theme.css</li>
      <li>✅ theme-toggle.js</li>
      <li>✅ border.php (with theme toggle button)</li>
      <li>✅ CSS variables applied</li>
    </ul>
  </div>
</div>

<script>
// Update theme status indicator
function updateThemeStatus() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const status = document.getElementById('theme-status');
  if (isDark) {
    status.textContent = '🌙 Dark Mode Active';
  } else {
    status.textContent = '☀️ Light Mode Active';
  }
}

// Update on load
updateThemeStatus();

// Update when theme changes
window.addEventListener('themeChanged', updateThemeStatus);

// Poll for changes (backup)
setInterval(updateThemeStatus, 500);
</script>

</body>
</html>

