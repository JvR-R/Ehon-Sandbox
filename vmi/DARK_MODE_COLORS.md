# 🌙 Dark Mode Color Palette

## Current Dark Mode Colors

### Main Backgrounds
```css
--bg-primary: #121212        /* Main page background - Very dark gray */
--bg-secondary: #1e1e1e      /* Secondary areas - Dark gray */
--bg-tertiary: #252525       /* Tertiary sections - Medium dark */
--bg-card: #1e1e1e          /* Cards and panels - Dark gray */
--bg-dark: #0d0d0d          /* Extra dark - Near black */
--bg-darker: #000000        /* Pure black */
```

### Table Colors (NEW!)
```css
--table-main-bg: rgba(30, 30, 30, 0.95)       /* Table container */
--table-body-bg: rgba(25, 25, 25, 0.85)       /* Table body - Dark */
--table-header-bg: #2a2a2a                    /* Table headers - Darker */
--table-row-even: rgba(255, 255, 255, 0.03)   /* Alternating rows - Subtle */
--table-row-hover: rgba(100, 110, 255, 0.15)  /* Row hover - Blue tint */
```

### Text Colors
```css
--text-primary: #ffffff      /* Main text - Pure white */
--text-secondary: #b0b0b0    /* Secondary text - Light gray */
--text-tertiary: #d0d0d0     /* Tertiary text - Lighter gray */
--text-muted: #606060        /* Disabled/muted - Medium gray */
```

### Accent Colors
```css
--accent-primary: #8a8fff    /* Primary brand - Light purple */
--accent-danger: #ff4444     /* Error/red - Bright red */
--accent-success: #00e676    /* Success - Bright green */
--accent-warning: #ffa726    /* Warning - Orange */
```

### Borders & Shadows
```css
--border-color: #3a3b3f      /* Default borders */
--shadow-sm: rgba(0, 0, 0, 0.3)
--shadow-md: rgba(0, 0, 0, 0.5)
--shadow-lg: rgba(0, 0, 0, 0.7)
```

---

## What Changed

### Before (Old Light Colors in Dark Mode ❌)
```css
table body: #fffb  /* White! - Wrong for dark mode */
table main: #fff5  /* White! - Wrong for dark mode */  
backgrounds: #ffffff /* White! - Wrong for dark mode */
```

### After (Proper Dark Colors ✅)
```css
table body: rgba(25, 25, 25, 0.85)  /* Dark gray - Perfect! */
table main: rgba(30, 30, 30, 0.95)  /* Dark gray - Perfect! */
backgrounds: #121212 /* Dark gray - Perfect! */
```

---

## Visual Preview

### Light Mode ☀️
- Page: White (#ffffff)
- Tables: Light gray (#fffb)
- Text: Black (#1a1b1f)
- Headers: Medium gray (#d7d7d7)

### Dark Mode 🌙
- Page: Very dark gray (#121212)
- Tables: Dark gray (rgba(25, 25, 25, 0.85))
- Text: White (#ffffff)
- Headers: Dark (#2a2a2a)

---

## Test It Now!

Visit any page with tables:
- `/vmi/clients/` - Should have DARK table bodies
- `/vmi/Service/` - Should have DARK backgrounds
- `/vmi/reports/` - Should have DARK cards

**Toggle the theme and you should see:**
✅ Dark gray table backgrounds (#191919)
✅ Darker table headers (#2a2a2a)
✅ White text on dark backgrounds
✅ Subtle row alternation
✅ Blue-tinted hover states

---

## If You Want to Adjust Colors

Edit `/vmi/css/theme.css` in the `[data-theme="dark"]` section:

```css
[data-theme="dark"] {
  /* Make darker: Use lower numbers */
  --table-body-bg: rgba(15, 15, 15, 0.85);  /* Even darker! */
  
  /* Make lighter: Use higher numbers */
  --table-body-bg: rgba(40, 40, 40, 0.85);  /* Lighter gray */
  
  /* Fully opaque: Remove alpha */
  --table-body-bg: #1a1a1a;  /* Solid dark gray */
}
```

---

**Your tables and backgrounds are now PROPERLY DARK! 🌙**

