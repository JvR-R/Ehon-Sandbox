# EHON VMI - Light/Dark Mode Implementation Guide

## 🎨 Overview

A complete light/dark mode theme system has been implemented for the EHON VMI web application. This system provides:
- **Automatic theme persistence** (remembers user preference)
- **System preference detection** (respects OS dark mode settings)
- **Smooth transitions** between themes
- **Consistent styling** across all pages
- **Accessible toggle button** in the navigation sidebar

---

## 📁 Files Created/Modified

### New Files Created

1. **`/vmi/css/theme.css`**
   - Comprehensive CSS variables for light and dark themes
   - All color definitions centralized
   - Smooth transition effects

2. **`/vmi/js/theme-toggle.js`**
   - Theme switching logic
   - localStorage persistence
   - System preference detection
   - Event handling for toggle button

### Files Modified

3. **`/vmi/css/style_rep.css`**
   - Updated all hardcoded colors to use CSS variables
   - Navigation, buttons, forms, scrollbars, etc.

4. **`/vmi/css/webflow.css`**
   - Updated body and button colors to use CSS variables

5. **`/vmi/css/ehon-energy-1.webflow.css`**
   - Updated major UI components to use CSS variables
   - Links, buttons, sections, cards, etc.

6. **`/vmi/db/border.php`**
   - Added theme toggle button to navigation sidebar
   - Included theme-toggle.js script

7. **`/vmi/db/border2.php`**
   - Added theme toggle button (PDO version)
   - Included theme-toggle.js script

---

## 🚀 How to Use

### For End Users

1. **Toggle Theme**: Click the theme toggle button in the sidebar (between "Update Password" and "Logout")
2. **Icon Changes**: 
   - Light mode: ☀️ (sun icon)
   - Dark mode: 🌙 (moon icon)
3. **Persistence**: Your theme preference is automatically saved and remembered across sessions

### For Developers

#### Adding Theme Support to New Pages

To add theme support to a new PHP page:

```php
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Page</title>
  
  <!-- IMPORTANT: Include theme.css FIRST, before other stylesheets -->
  <link href="/vmi/css/theme.css" rel="stylesheet" type="text/css">
  
  <!-- Then include other CSS files -->
  <link href="/vmi/css/normalize.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/webflow.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/style_rep.css" rel="stylesheet" type="text/css">
  <link href="/vmi/css/ehon-energy-1.webflow.css" rel="stylesheet" type="text/css">
</head>
<body>
  <!-- Your content -->
  
  <!-- Include theme toggle script (if not using border.php) -->
  <script src="/vmi/js/theme-toggle.js"></script>
</body>
</html>
```

#### Using CSS Variables in New Styles

Instead of hardcoding colors:

```css
/* ❌ DON'T DO THIS */
.my-element {
  background-color: #ffffff;
  color: #1a1b1f;
  border: 1px solid #e2e2e2;
}

/* ✅ DO THIS */
.my-element {
  background-color: var(--bg-primary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}
```

---

## 🎨 Available CSS Variables

### Background Colors
```css
--bg-primary      /* Main background */
--bg-secondary    /* Secondary background */
--bg-tertiary     /* Tertiary background */
--bg-accent       /* Accent background (subtle) */
--bg-card         /* Card/panel backgrounds */
--bg-dark         /* Dark backgrounds */
--bg-darker       /* Darker backgrounds */
```

### Text Colors
```css
--text-primary    /* Main text color */
--text-secondary  /* Secondary text (labels, etc.) */
--text-tertiary   /* Tertiary text */
--text-inverse    /* Inverse text (for dark backgrounds) */
--text-muted      /* Muted/disabled text */
--text-link       /* Link color */
```

### Border Colors
```css
--border-color    /* Default borders */
--border-light    /* Light borders */
--border-dark     /* Dark borders */
```

### Brand/Accent Colors
```css
--accent-primary         /* Primary brand color (#6c72ff) */
--accent-primary-hover   /* Primary hover state */
--accent-secondary       /* Secondary accent (#57c3ff) */
--accent-danger          /* Error/danger color (#EC1C1C) */
--accent-success         /* Success color */
--accent-warning         /* Warning color */
```

### Button Colors
```css
--btn-primary-bg      /* Primary button background */
--btn-primary-hover   /* Primary button hover */
--btn-secondary-bg    /* Secondary button background */
--btn-secondary-hover /* Secondary button hover */
--btn-text            /* Button text color */
```

### Form/Input Colors
```css
--input-bg      /* Input background */
--input-text    /* Input text */
--input-border  /* Input border */
```

### Navigation Colors
```css
--nav-bg        /* Navigation background */
--nav-text      /* Navigation text */
--nav-hover     /* Navigation hover state */
```

### UI Elements
```css
--scrollbar-track       /* Scrollbar track */
--scrollbar-thumb       /* Scrollbar thumb */
--shadow-sm            /* Small shadow */
--shadow-md            /* Medium shadow */
--shadow-lg            /* Large shadow */
--divider-color        /* Divider lines */
```

### Status Colors
```css
--status-green-bg      /* Success badge background */
--status-green-text    /* Success badge text */
--status-red-bg        /* Error badge background */
--status-red-text      /* Error badge text */
```

### Chart Colors
```css
--chart-bar     /* Chart bar color */
--chart-line    /* Chart line color */
```

---

## 🔧 Customization

### Changing Theme Colors

Edit `/vmi/css/theme.css`:

```css
/* Light mode */
:root {
  --accent-primary: #6c72ff;  /* Change to your brand color */
  /* ... other variables ... */
}

/* Dark mode */
[data-theme="dark"] {
  --accent-primary: #8a8fff;  /* Adjusted for dark mode */
  /* ... other variables ... */
}
```

### Disabling Auto Theme Transitions

If you want to disable smooth transitions on certain elements:

```css
.my-element {
  /* Add this class to elements */
  @extend .no-theme-transition;
}
```

Or in HTML:
```html
<div class="my-element no-theme-transition">
  <!-- Content -->
</div>
```

---

## 🐛 Troubleshooting

### Theme not applying on a page

**Solution**: Ensure `theme.css` is included BEFORE other stylesheets:
```html
<link href="/vmi/css/theme.css" rel="stylesheet" type="text/css">
<!-- Must be first! -->
```

### Toggle button not working

**Solution**: Ensure `theme-toggle.js` is included:
```html
<script src="/vmi/js/theme-toggle.js"></script>
```

### Some elements not changing color

**Solution**: Check if the element uses hardcoded colors. Replace with CSS variables:
```css
/* Change this: */
color: #ffffff;

/* To this: */
color: var(--text-primary);
```

### Theme not persisting

**Solution**: Check browser localStorage is enabled and not blocked by privacy settings.

### Flash of unstyled content (FOUC)

The theme is applied immediately on page load, but if you see a flash:

**Solution**: The JavaScript runs as soon as possible. Ensure no other scripts are blocking execution.

---

## 🎯 Best Practices

1. **Always use CSS variables** for colors, never hardcode
2. **Include theme.css first** in your stylesheet order
3. **Test both themes** when creating new components
4. **Use semantic variable names** (e.g., `--text-primary` not `--color-black`)
5. **Maintain contrast ratios** for accessibility (WCAG AA standard)

---

## 📊 Browser Support

- ✅ Chrome/Edge (Modern)
- ✅ Firefox (Modern)
- ✅ Safari (Modern)
- ✅ Opera (Modern)
- ⚠️ IE11 (Limited - no CSS variables support)

For IE11, the site will default to light mode without theme switching capability.

---

## 🔄 Updating Existing Pages

To update pages that don't have theme support yet:

1. Add theme.css to the `<head>`:
   ```html
   <link href="/vmi/css/theme.css" rel="stylesheet" type="text/css">
   ```

2. If the page has custom inline styles or CSS file, update colors to use variables

3. If the page doesn't include border.php or border2.php, add the script:
   ```html
   <script src="/vmi/js/theme-toggle.js"></script>
   ```

---

## 📝 Current Implementation Status

### ✅ Fully Implemented
- Core theme system (theme.css, theme-toggle.js)
- Navigation sidebar (border.php, border2.php)
- Main stylesheets (style_rep.css, webflow.css)
- Major components (ehon-energy-1.webflow.css)
- Reports pages
- Fuel Quality pages
- Client management pages

### 🔄 May Need Additional Updates
- Pages with inline styles
- Third-party component libraries
- Chart/graph libraries (may need separate configuration)
- Custom dashboard widgets

---

## 🎨 Theme Color Reference

### Light Mode
- Primary Background: `#ffffff`
- Text: `#1a1b1f`
- Accent: `#6c72ff`

### Dark Mode
- Primary Background: `#1a1b1f`
- Text: `#ffffff`
- Accent: `#8a8fff`

---

## 📞 Support

If you encounter issues or need to add theme support to additional pages:

1. Check this guide
2. Review the existing implementation in `theme.css`
3. Ensure all color values use CSS variables
4. Test in both light and dark modes

---

## 🚀 Future Enhancements

Potential improvements:

- [ ] Add more theme options (e.g., high contrast, custom colors)
- [ ] Create theme preview panel
- [ ] Add keyboard shortcuts for theme toggle
- [ ] Implement theme-aware charts/graphs
- [ ] Add theme customization in user settings
- [ ] Create theme API for programmatic access

---

## 📄 License

This theme system is part of the EHON VMI application.

---

**Last Updated**: November 4, 2025  
**Version**: 1.0.0

