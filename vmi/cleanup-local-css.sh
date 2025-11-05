#!/bin/bash
# Remove duplicate styles from local CSS files now that they're in vmi-tables.css

echo "🧹 Cleaning up duplicate CSS from local files..."
echo ""

cleanup_css() {
    local file=$1
    local backup="${file}.before-cleanup"
    
    if [ ! -f "$file" ]; then
        echo "  ⊘ File not found: $file"
        return
    fi
    
    echo "  📝 Cleaning: $file"
    
    # Create backup
    cp "$file" "$backup"
    echo "     💾 Backup created: $backup"
    
    # Remove HTML5 reset section (lines 1-23)
    sed -i '1,23d' "$file"
    
    # Remove universal reset section (* { margin: 0; padding: 0; ... })
    sed -i '/^\* {$/,/^}$/d' "$file"
    
    # Remove body styling (the general one, not specific overrides)
    sed -i '/^body {$/,/^}$/{
        /min-height: auto/d
        /background: var(--bg-primary)/d
        /display: flex/d
        /justify-content: center/d
        /align-items: center/d
        /overflow: hidden/d
    }' "$file"
    
    # Remove button-js styles
    sed -i '/^\.button-js {$/,/^}$/d' "$file"
    sed -i '/^\.button-js:hover{$/,/^}$/d' "$file"
    sed -i '/^\.button-js2 {$/,/^}$/d' "$file"
    sed -i '/^\.button-js2:hover{$/,/^}$/d' "$file"
    sed -i '/^\.button-js3 {$/,/^}$/d' "$file"
    sed -i '/^\.button-js3:hover{$/,/^}$/d' "$file"
    
    # Remove main.table styling
    sed -i '/^main\.table {$/,/^}$/d' "$file"
    
    # Remove .table__header styling
    sed -i '/^\.table__header {$/,/^}$/d' "$file"
    sed -i '/^\.table__header .input-group {$/,/^}$/d' "$file"
    
    # Remove .table__body and scrollbar
    sed -i '/^\.table__body {$/,/^}$/d' "$file"
    sed -i '/^\.table__body::-webkit-scrollbar{$/,/^}$/d' "$file"
    sed -i '/^\.table__body::-webkit-scrollbar-thumb{$/,/^}$/d' "$file"
    sed -i '/^\.table__body:hover::-webkit-scrollbar-thumb{$/,/^}$/d' "$file"
    
    # Remove generic table styling
    sed -i '/^table {$/,/^}$/{
        /width: 100%;/d
    }' "$file"
    
    # Remove h1 styling (the general ones)
    sed -i '/^h1{$/,/^}$/d' "$file"
    sed -i '/^h1 img {$/,/^}$/d' "$file"
    
    # Remove table, th, td reset
    sed -i '/^table, th, td {$/,/^}$/d' "$file"
    
    # Remove thead th styling
    sed -i '/^thead th {$/,/^}$/d' "$file"
    sed -i '/^thead th\.active,tbody td\.active {$/,/^}$/d' "$file"
    
    # Remove tbody tr styling
    sed -i '/^tbody tr:nth-child(even):not(\.expanded-details) {$/,/^}$/d' "$file"
    sed -i '/^tbody tr {$/,/^}$/d' "$file"
    sed -i '/^tbody tr\.hide {$/,/^}$/d' "$file"
    sed -i '/^tbody tr:not(\.expanded-details):hover {$/,/^}$/d' "$file"
    sed -i '/^tbody tr td,$/,/^}$/d' "$file"
    sed -i '/^tbody tr\.hide td,$/,/^}$/d' "$file"
    sed -i '/^td img{$/,/^}$/d' "$file"
    
    # Remove .child_details
    sed -i '/^\.child_details{$/,/^}$/d' "$file"
    
    # Remove menu_items
    sed -i '/^\.menu_items{$/,/^}$/d' "$file"
    
    # Remove info panels
    sed -i '/^\[class\^="alert_info"\] {$/,/^}$/d' "$file"
    sed -i '/^\[class\^="tank_info"\] {$/,/^}$/d' "$file"
    sed -i '/^\[class\^="temp_info"\] {$/,/^}$/d' "$file"
    
    # Remove nav-items
    sed -i '/^\.nav-items{$/,/^}$/d' "$file"
    
    # Remove DataTables control styles if they exist
    sed -i '/^table\.dataTable tbody td\.dt-control:before {$/,/^}$/d' "$file"
    sed -i '/^table\.dataTable tbody tr\.shown td\.dt-control:before {$/,/^}$/d' "$file"
    
    # Remove empty lines at the beginning
    sed -i '/./,$!d' "$file"
    
    # Add comment at top of file
    sed -i '1i /* Page-specific styles - Common styles moved to /vmi/css/vmi-tables.css */' "$file"
    sed -i '1i\\' "$file"
    
    echo "     ✅ Cleaned"
    echo "     📊 Before: $(wc -l < "$backup") lines → After: $(wc -l < "$file") lines"
}

echo "=== Cleaning Service CSS ==="
cleanup_css "./Service/style.css"

echo ""
echo "=== Cleaning Clients CSS ==="
cleanup_css "./clients/style.css"

echo ""
echo "✅ Cleanup complete!"
echo ""
echo "Next step: Add vmi-tables.css link to index.php files"

