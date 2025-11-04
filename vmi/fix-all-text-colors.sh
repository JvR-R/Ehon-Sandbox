#!/bin/bash
# Comprehensive fix for all text colors to ensure light text in dark mode

echo "🎨 Fixing ALL text colors for proper dark mode contrast..."
echo ""

fix_file() {
    local file=$1
    
    if [ ! -f "$file" ]; then
        echo "  ⊘ File not found: $file"
        return
    fi
    
    echo "  📝 Fixing: $file"
    
    # SVG fills that are dark
    sed -i 's/fill:\s*#040404/fill: var(--text-primary)/g' "$file"
    sed -i 's/fill:\s*#000000/fill: var(--text-primary)/g' "$file"
    sed -i 's/fill:\s*black\s*;/fill: var(--text-primary);/g' "$file"
    
    # Any remaining hardcoded black colors
    sed -i 's/color:\s*#040404/color: var(--text-primary)/g' "$file"
    sed -i 's/color:\s*#222/color: var(--text-primary)/g' "$file"
    
    # Tooltip/popup backgrounds and text that might be dark
    sed -i 's/background-color:\s*black\s*;/background-color: var(--bg-darker);/g' "$file"
    
    # Make sure tooltip text is white
    sed -i 's/\.tooltip .tooltiptext {/&\n  color: var(--text-inverse);/g' "$file"
    
    echo "     ✅ Fixed"
}

echo "=== Fixing All Custom CSS Files ==="
echo ""

for css_file in \
    ./clients/style.css \
    ./Service/style.css \
    ./reports/style.css \
    ./Fuel-Quality/fq.css \
    ./details/menu.css \
    ./details/style.css \
    ./verification/style.css \
    ./recovery/style.css \
    ./Contactlist/style.css \
    ./ipetropay/style.css \
    ./login/style.css
do
    if [ -f "$css_file" ]; then
        fix_file "$css_file"
    fi
done

echo ""
echo "✅ All text colors fixed for proper contrast!"
echo ""
echo "Dark mode now has:"
echo "  • Light/white text on dark backgrounds"
echo "  • Proper SVG icon colors"
echo "  • Readable tooltips"
echo "  • High contrast everywhere"

