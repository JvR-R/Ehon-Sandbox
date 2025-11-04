#!/bin/bash
# Fix buttons, headers, and boxes in nested folders (reports, ipetropay, details, etc.)

echo "🎨 Fixing buttons, headers, and boxes in nested VMI folders..."
echo ""

fix_css_file() {
    local file=$1
    
    if [ ! -f "$file" ]; then
        echo "  ⊘ File not found: $file"
        return
    fi
    
    echo "  📝 Fixing: $file"
    
    # Button backgrounds
    sed -i 's/background-color: #002e60/background-color: var(--bg-dark)/g' "$file"
    sed -i 's/background-color:#002e60/background-color: var(--bg-dark)/g' "$file"
    sed -i 's/background-color: #002F60/background-color: var(--bg-dark)/g' "$file"
    
    # Button text colors
    sed -i 's/color: white;/color: var(--text-inverse);/g' "$file"
    sed -i 's/color:white;/color: var(--text-inverse);/g' "$file"
    
    # Body backgrounds
    sed -i 's/background: white center/background: var(--bg-primary) center/g' "$file"
    sed -i 's/background: white;/background: var(--bg-primary);/g' "$file"
    sed -i 's/background-color: white;/background-color: var(--bg-primary);/g' "$file"
    
    # Table main background (#fff5, #fffb, etc.)
    sed -i 's/background-color: #fff5/background-color: var(--table-main-bg)/g' "$file"
    sed -i 's/background-color: #fffb/background-color: var(--table-main-bg)/g' "$file"
    sed -i 's/background-color:#fff5/background-color: var(--table-main-bg)/g' "$file"
    sed -i 's/background-color:#fffb/background-color: var(--table-main-bg)/g' "$file"
    sed -i 's/background-color: #ffff/background-color: var(--table-main-bg)/g' "$file"
    sed -i 's/background-color: #ffffff/background-color: var(--table-main-bg)/g' "$file"
    
    # Table header backgrounds (#d7d7d7bf, #d7d7d7, etc.)
    sed -i 's/background-color: #d7d7d7bf/background-color: var(--table-header-bg)/g' "$file"
    sed -i 's/background-color: #d7d7d7/background-color: var(--table-header-bg)/g' "$file"
    sed -i 's/background-color:#d7d7d7bf/background-color: var(--table-header-bg)/g' "$file"
    sed -i 's/background-color:#d7d7d7/background-color: var(--table-header-bg)/g' "$file"
    
    # Shadow colors
    sed -i 's/box-shadow: 0 \.4rem \.8rem #0005/box-shadow: 0 .4rem .8rem var(--shadow-md)/g' "$file"
    sed -i 's/box-shadow: 0 \.2rem \.4rem #0003/box-shadow: 0 .2rem .4rem var(--shadow-sm)/g' "$file"
    
    # Input group backgrounds
    sed -i 's/background-color: #fff;/background-color: var(--bg-card);/g' "$file"
    sed -i 's/background: #fff;/background: var(--bg-card);/g' "$file"
    
    # Border colors
    sed -i 's/border: 1px solid #fff/border: 1px solid var(--border-light)/g' "$file"
    sed -i 's/border-color: #fff/border-color: var(--border-light)/g' "$file"
    
    echo "     ✅ Fixed"
}

echo "=== Fixing Reports Subfolders ==="
fix_css_file "./reports/transactions/style.css"
fix_css_file "./reports/total_deliveries/style.css"

echo ""
echo "=== Fixing iPetroPay Subfolders ==="
fix_css_file "./ipetropay/payment/users/users-information/style.css"
fix_css_file "./ipetropay/payment/Contactlist/style.css"
fix_css_file "./ipetropay/payment/show/style.css"
fix_css_file "./ipetropay/registration/style.css"

echo ""
echo "=== Fixing Details Subfolders ==="
fix_css_file "./details/style.css"
fix_css_file "./details/strapping_chart/style.css"
fix_css_file "./details/user/style.css"

echo ""
echo "✅ All nested folders fixed!"
echo ""
echo "Fixed elements:"
echo "  ✅ Button backgrounds (dark blue → theme variable)"
echo "  ✅ Button text (white → theme variable)"
echo "  ✅ Table backgrounds (white → theme variable)"
echo "  ✅ Table headers (gray → theme variable)"
echo "  ✅ Box shadows (hardcoded → theme variable)"
echo "  ✅ Input groups (white → theme variable)"
echo "  ✅ Borders (white → theme variable)"

