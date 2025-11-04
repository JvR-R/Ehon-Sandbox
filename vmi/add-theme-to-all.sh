#!/bin/bash
# Script to add theme.css to all VMI pages

echo "Adding theme.css to all VMI pages..."

# Function to add theme.css before first CSS link if not already present
add_theme_css() {
    local file=$1
    
    # Skip if already has theme.css
    if grep -q "theme.css" "$file"; then
        echo "  ✓ $file - already has theme.css"
        return
    fi
    
    # Check if file has <link or <style tag
    if ! grep -q "<link\|<style" "$file"; then
        echo "  ⊘ $file - no CSS links found, skipping"
        return
    fi
    
    # Create backup
    cp "$file" "${file}.backup"
    
    # Find first CSS link and insert theme.css before it
    # Look for patterns like: <link href="/vmi/css/ or <link rel="stylesheet"
    if grep -q '<link.*href="/vmi/css/' "$file"; then
        sed -i '0,/<link.*href="\/vmi\/css\//s||  <!-- THEME CSS - MUST BE FIRST -->\n  <link rel="stylesheet" href="/vmi/css/theme.css">\n  <!-- Other CSS files -->\n&|' "$file"
        echo "  ✓ $file - theme.css added"
    elif grep -q '<link.*rel="stylesheet"' "$file"; then
        sed -i '0,/<link.*rel="stylesheet"/s||  <!-- THEME CSS - MUST BE FIRST -->\n  <link rel="stylesheet" href="/vmi/css/theme.css">\n  <!-- Other CSS files -->\n&|' "$file"
        echo "  ✓ $file - theme.css added"
    elif grep -q '<link.*href.*\.css' "$file"; then
        sed -i '0,/<link.*href.*\.css/s||  <!-- THEME CSS - MUST BE FIRST -->\n  <link rel="stylesheet" href="/vmi/css/theme.css">\n  <!-- Other CSS files -->\n&|' "$file"
        echo "  ✓ $file - theme.css added"
    else
        echo "  ⚠ $file - couldn't find suitable CSS link pattern"
        rm "${file}.backup"
    fi
}

# Main index pages
echo ""
echo "=== Updating Main Section Pages ==="
for file in \
    ./clients/index.php \
    ./details/index.php \
    ./Service/index.php \
    ./Fuel-Quality/index.php \
    ./Fuel-tax/index.php \
    ./Reconciliation/index.php \
    ./verification/index.php \
    ./Calibration/index.php \
    ./Contactlist/index.php
do
    if [ -f "$file" ]; then
        add_theme_css "$file"
    fi
done

# Subdirectory pages
echo ""
echo "=== Updating Transaction Pages ==="
if [ -f "./reports/transactions/index.php" ]; then
    add_theme_css "./reports/transactions/index.php"
fi

echo ""
echo "=== Updating Total Deliveries Pages ==="
if [ -f "./reports/total_deliveries/index.php" ]; then
    add_theme_css "./reports/total_deliveries/index.php"
fi

echo ""
echo "=== Updating Management Pages ==="
if [ -f "./Manage/Company/index.php" ]; then
    add_theme_css "./Manage/Company/index.php"
fi

echo ""
echo "Done! Backups saved as *.backup"
echo ""
echo "To test: Visit any updated page and use browser console:"
echo "  document.documentElement.setAttribute('data-theme', 'dark');"

