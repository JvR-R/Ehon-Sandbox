# Tank View System Documentation

## Overview

A dual-view system for displaying tanks that automatically switches between a friendly card-based view (for smaller clients with ≤5 tanks) and the existing table view (for larger clients with >5 tanks).

## Files Created

### JavaScript Components

1. **`/vmi/js/vmi-js/tank-card-view.js`**
   - Creates and renders tank cards
   - Handles card creation with all tank data
   - Exports functions for rendering card grids

2. **`/vmi/js/vmi-js/view-switcher.js`**
   - Main controller that switches between card and table views
   - Monitors DataTable data changes
   - Automatically determines which view to use based on tank count
   - Opens modal popup when cards are clicked

3. **`/vmi/js/vmi-js/tank-modal.js`**
   - Modal popup component for displaying tank details
   - Handles modal open/close animations
   - Manages chart cleanup and event handlers
   - Keyboard navigation (Escape to close)

### Styles

3. **`/vmi/css/tank-card-view.css`**
   - Modern, responsive card design
   - Smooth animations and hover effects
   - Dark theme support
   - Mobile-responsive layout

4. **`/vmi/css/tank-modal.css`**
   - Modal popup styles
   - Smooth open/close animations
   - Backdrop blur effect
   - Responsive design for mobile
   - Optimized styling for child row content

## Files Modified

1. **`/vmi/js/vmi-js/main.js`**
   - Integrated view switcher initialization
   - Works seamlessly with existing table functionality

2. **`/vmi/clients/index.php`**
   - Added CSS links for tank-card-view.css and tank-modal.css

## How It Works

### Automatic View Selection

- **≤5 tanks**: Card view with large, friendly icons
- **>5 tanks**: Standard table view

The system automatically switches when:
- Data is loaded
- Filters are applied
- Search is performed
- Group filters change

### Card View Features

- **Visual Tank Icon**: SVG representation showing tank level
- **Progress Bar**: Color-coded (red/yellow/green) based on capacity percentage
- **Key Information**: Product name, current volume, capacity, ullage, last reading
- **Status Icons**: Same status icons as table view (alarms, offline, etc.)
- **Modal Details**: Click "View Details" to open a beautiful modal popup with full tank information (charts, FMS, configuration, etc.)
- **Smooth Animations**: Cards fade in sequentially with smooth transitions

### Table View Features

- Unchanged from original implementation
- All existing functionality preserved
- Search, filter, and sorting work as before

## Design Principles

1. **Clean Separation**: Each view style has its own files for easy maintenance
2. **Modern UX**: Card view uses contemporary design patterns with excellent feedback
3. **Consistent Data**: Both views show the same tank information
4. **Seamless Integration**: Works with existing DataTable, search, and filter systems
5. **Responsive**: Adapts to different screen sizes

## Customization

### Changing the Threshold

To change when the system switches from cards to table, edit `TANK_COUNT_THRESHOLD` in `/vmi/js/vmi-js/view-switcher.js`:

```javascript
const TANK_COUNT_THRESHOLD = 5; // Change this number
```

### Styling

Card styles can be customized in `/vmi/css/tank-card-view.css`. The design uses CSS custom properties (variables) that integrate with your existing theme system.

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Uses standard ES6 modules
- CSS Grid for responsive layout
- CSS Custom Properties for theming

## Modal Features

- **Backdrop Blur**: Modern backdrop with blur effect
- **Smooth Animations**: Fade and scale animations on open/close
- **Keyboard Navigation**: Press Escape to close
- **Click Outside**: Click backdrop to close
- **Scrollable Content**: Long content scrolls within modal
- **Responsive**: Adapts to mobile screens (full screen on small devices)
- **Chart Support**: All charts render properly in modal
- **Tab Navigation**: All tabs (Information, Alerts, Configuration, etc.) work as in table view

## Notes

- The DataTable instance continues to work in the background even when cards are displayed
- Search and filter functionality works with both views
- Modal popup uses the same child row system as table rows for consistency
- All existing functionality (charts, FMS, alerts, configuration) works identically in both views
- Modal automatically cleans up charts and event handlers when closed

