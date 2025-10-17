# VMI Service Map - SCADA Control Room Interface

A real-time monitoring interface for VMI console status and alerts with a professional SCADA-style design.

## Features

### 🗺️ Interactive Map
- **Full-screen map display** with console positions
- **Color-coded markers** based on console status:
  - 🟢 Green: Normal operation
  - 🟡 Yellow: Warning conditions
  - 🔴 Red: Critical alerts
  - ⚫ Gray: Offline consoles
  - 🔴 Blinking: Disconnected devices

### 📊 Service Alerts Panel
- **Real-time alert monitoring** with severity classification
- **Alert filtering** by type (All, Critical, Warning, Offline)
- **Detailed alert information** including timestamps and descriptions
- **Quick statistics** showing console counts and alert summaries

### 🖥️ SCADA-Style Interface
- **Dark theme** optimized for control room environments
- **Real-time clock** with Australian timezone
- **Auto-refresh** every 30 seconds
- **Fullscreen support** for dedicated monitoring displays
- **Responsive design** that works on different screen sizes

## Alert Types

### Console Status Alerts
- **Device Disconnected**: Hardware disconnection detected
- **Console Offline**: No communication for >27 hours
- **Dip Out of Sync**: Tank readings out of sync for >3 days

### Tank Level Alerts
- **Critical High**: Volume above critical high threshold
- **Critical Low**: Volume below critical low threshold
- **High Warning**: Volume above high threshold
- **Low Warning**: Volume below low threshold

## Navigation

### Map Controls
- **Click markers** to view console details
- **Zoom controls** in bottom right
- **Legend** in top left shows status color coding
- **Auto-fit** to show all console locations

### Alert Panel
- **Filter buttons** to view specific alert types
- **Click alerts** to view detailed console information
- **Statistics** show total consoles and alert counts
- **System status** shows last update time and refresh rate

### Keyboard Shortcuts
- **F5**: Manual refresh
- **Escape**: Close modal dialogs
- **Fullscreen button**: Toggle fullscreen mode

## Technical Details

### Data Sources
- **Console GPS data**: From existing `gps_call.php`
- **Console status**: From new `console_status.php` endpoint
- **Real-time updates**: Automatic refresh every 30 seconds

### Files Structure
```
service_map/
├── index.html          # Main interface
├── styles.css          # SCADA-style CSS
├── service-map.js      # Core functionality
├── console_status.php  # Status API endpoint
└── README.md          # This documentation
```

### Database Integration
- Uses existing VMI database tables:
  - `console` - Console hardware information
  - `Sites` - Site locations and details
  - `Tanks` - Tank levels and status
  - `alarms_config` - Alert thresholds
  - `Console_Asociation` - Client associations

## Browser Compatibility
- Modern browsers with ES6+ support
- Tested on Chrome, Firefox, Safari, Edge
- Mobile responsive design

## Installation
1. Upload files to `/vmi/reports/service_map/`
2. Ensure proper database permissions
3. Access via `/vmi/reports/service_map/`

## Customization
- Modify `styles.css` for color scheme changes
- Adjust refresh rate in `service-map.js`
- Add custom alert types in `console_status.php`
