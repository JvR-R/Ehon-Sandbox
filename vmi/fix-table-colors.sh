#!/bin/bash
# Fix table and semi-transparent colors to use proper dark mode variables

echo "🎨 Fixing table and transparent colors for proper dark mode..."
echo ""

fix_file() {
    local file=$1
    
    if [ ! -f "$file" ]; then
        echo "  ⊘ File not found: $file"
        return
    fi
    
    echo "  📝 Fixing: $file"
    
    # Semi-transparent whites used in tables
    sed -i 's/#fff5/var(--table-main-bg)/g' "$file"
    sed -i 's/#fffb/var(--table-body-bg)/g' "$file"
    sed -i 's/#fff8/var(--bg-card)/g' "$file"
    sed -i 's/#fff6/var(--bg-secondary)/g' "$file"
    
    # Table header backgrounds
    sed -i 's/background-color:\s*#d7d7d7bf/background-color: var(--table-header-bg)/g' "$file"
    sed -i 's/background-color:\s*#d7d7d7/background-color: var(--table-header-bg)/g' "$file"
    
    # Table row hover
    sed -i 's/#bbc7ff65/var(--table-row-hover)/g' "$file"
    
    # Other specific colors
    sed -i 's/#0004/var(--scrollbar-thumb)/g' "$file"
    sed -i 's/#0002/var(--shadow-sm)/g' "$file"
    sed -i 's/#0005/var(--shadow-md)/g' "$file"
    
    echo "     ✅ Fixed"
}

echo "=== Fixing Custom CSS Files ==="
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
    ./Contactlist/style.css
do
    if [ -f "$css_file" ]; then
        fix_file "$css_file"
    fi
done

echo ""
echo "✅ All table colors fixed!"
echo "🧪 Test your pages now - tables should be properly dark!"

