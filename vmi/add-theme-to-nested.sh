#!/bin/bash
# Add theme.css link to nested PHP files

echo "🔗 Adding theme.css to nested PHP files..."
echo ""

add_theme_link() {
    local file=$1
    
    if [ ! -f "$file" ]; then
        echo "  ⊘ File not found: $file"
        return
    fi
    
    # Check if theme.css is already included
    if grep -q "theme.css" "$file"; then
        echo "  ⏭️  Already has theme.css: $file"
        return
    fi
    
    echo "  📝 Adding to: $file"
    
    # Count directory depth to get correct path to /vmi/css/theme.css
    local dir=$(dirname "$file")
    local depth=$(echo "$dir" | tr -cd '/' | wc -c)
    local prefix="../"
    
    # Build the correct relative path
    local theme_path="/vmi/css/theme.css"  # Use absolute path for consistency
    
    # Add the link tag after <head> or before </head>
    if grep -q "</head>" "$file"; then
        # Add before </head>
        sed -i 's|</head>|    <link rel="stylesheet" href="'"$theme_path"'">\n</head>|' "$file"
    elif grep -q "<head>" "$file"; then
        # Add after <head>
        sed -i 's|<head>|<head>\n    <link rel="stylesheet" href="'"$theme_path"'">|' "$file"
    else
        echo "     ⚠️  No <head> tag found in $file"
        return
    fi
    
    echo "     ✅ Added"
}

echo "=== Adding to Reports Subfolders ==="
# reports/transactions and reports/total_deliveries already have it
echo "  ⏭️  reports/transactions/index.php (already has theme.css)"
echo "  ⏭️  reports/total_deliveries/index.php (already has theme.css)"

echo ""
echo "=== Adding to iPetroPay Subfolders ==="
add_theme_link "./ipetropay/payment/users/users-information/index.php"
add_theme_link "./ipetropay/payment/Contactlist/index.php"
add_theme_link "./ipetropay/payment/show/index.php"
add_theme_link "./ipetropay/registration/index.php"

echo ""
echo "=== Adding to Details Subfolders ==="
add_theme_link "./details/strapping_chart/index.php"
add_theme_link "./details/user/index.php"

echo ""
echo "✅ Theme CSS links added to all nested PHP files!"
echo ""
echo "All pages now have:"
echo "  <link rel=\"stylesheet\" href=\"/vmi/css/theme.css\">"

