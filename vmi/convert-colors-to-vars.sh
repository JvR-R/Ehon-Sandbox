#!/bin/bash
# Automated script to convert hardcoded colors to CSS variables
# in all custom style files

echo "🎨 Converting colors to CSS variables in all custom CSS files..."
echo ""

# Color mapping: hardcoded color -> CSS variable
declare -A color_map=(
    # Whites and light backgrounds
    ["#fff"]="var(--bg-primary)"
    ["#ffffff"]="var(--bg-primary)"
    ["white"]="var(--bg-primary)"
    ["#fff5"]="var(--bg-card)"
    ["#fff8"]="var(--bg-card)"
    ["#fffb"]="var(--bg-card)"
    ["#fff6"]="var(--bg-secondary)"
    ["#f4f4f4"]="var(--bg-secondary)"
    ["#f2f2f2"]="var(--bg-secondary)"
    ["#f0f0f0"]="var(--bg-tertiary)"
    
    # Grays and neutral backgrounds
    ["#d7d7d7bf"]="var(--bg-tertiary)"
    ["#d7d7d7"]="var(--border-color)"
    ["#e1e1e1"]="var(--border-color)"
    ["#cfcfcf6e"]="var(--bg-accent)"
    ["#0000000b"]="var(--bg-accent)"
    
    # Dark backgrounds (company brand)
    ["#002e60"]="var(--bg-dark)"
    ["#002F60"]="var(--bg-darker)"
    ["#011a37"]="var(--input-bg)"
    
    # Text colors
    ["#222222"]="var(--text-primary)"
    ["#000000"]="var(--text-primary)"
    ["black"]="var(--text-primary)"
    ["#040404"]="var(--text-primary)"
    
    # Accent/Brand colors
    ["#EC1C1C"]="var(--accent-danger)"
    ["#F7901E"]="var(--accent-warning)"
    ["#e57915"]="var(--accent-warning)"
    
    # Success/Status colors
    ["#86e49d"]="var(--accent-success)"
    ["#4caf50"]="var(--accent-success)"
    ["#2e7d32"]="var(--accent-success)"
    
    # Error/Danger
    ["red"]="var(--accent-danger)"
    ["#c62828"]="var(--accent-danger)"
    
    # Info/Links
    ["#002F60"]="var(--bg-darker)"
    
    # Group filter
    ["#454545"]="var(--input-border)"
)

# Function to backup and convert a file
convert_file() {
    local file=$1
    
    if [ ! -f "$file" ]; then
        echo "  ⊘ File not found: $file"
        return
    fi
    
    echo "  📝 Converting: $file"
    
    # Create backup
    cp "$file" "${file}.pre-theme-backup"
    
    # Apply color replacements
    # Use temporary file to avoid sed issues
    local tmp_file="${file}.tmp"
    cp "$file" "$tmp_file"
    
    # Replace each color pattern
    # Background colors
    sed -i 's/background-color:\s*white\s*;/background-color: var(--bg-primary);/g' "$tmp_file"
    sed -i 's/background-color:\s*#fff\s*;/background-color: var(--bg-primary);/g' "$tmp_file"
    sed -i 's/background-color:\s*#ffffff\s*;/background-color: var(--bg-primary);/g' "$tmp_file"
    sed -i 's/background-color:\s*#f2f2f2\s*;/background-color: var(--bg-secondary);/g' "$tmp_file"
    sed -i 's/background-color:\s*#f4f4f4\s*;/background-color: var(--bg-secondary);/g' "$tmp_file"
    sed -i 's/background-color:\s*#d7d7d7bf\s*;/background-color: var(--bg-tertiary);/g' "$tmp_file"
    sed -i 's/background-color:\s*#cfcfcf6e\s*;/background-color: var(--bg-accent);/g' "$tmp_file"
    sed -i 's/background-color:\s*#0000000b\s*;/background-color: var(--bg-accent);/g' "$tmp_file"
    sed -i 's/background-color:\s*#002e60\s*;/background-color: var(--bg-dark);/g' "$tmp_file"
    sed -i 's/background-color:\s*#002F60\s*;/background-color: var(--bg-darker);/g' "$tmp_file"
    sed -i 's/background-color:\s*#011a37\s*;/background-color: var(--input-bg);/g' "$tmp_file"
    
    # Background: (without -color)
    sed -i 's/background:\s*white\([^-]\)/background: var(--bg-primary)\1/g' "$tmp_file"
    sed -i 's/background:\s*#fff\([^0-9a-f]\)/background: var(--bg-primary)\1/g' "$tmp_file"
    sed -i 's/background:\s*#ffffff\([^0-9a-f]\)/background: var(--bg-primary)\1/g' "$tmp_file"
    sed -i 's/background:\s*#454545\s*;/background: var(--input-border);/g' "$tmp_file"
    
    # Text colors
    sed -i 's/color:\s*black\s*;/color: var(--text-primary);/g' "$tmp_file"
    sed -i 's/color:\s*#000000\s*;/color: var(--text-primary);/g' "$tmp_file"
    sed -i 's/color:\s*#222222\s*;/color: var(--text-primary);/g' "$tmp_file"
    sed -i 's/color:\s*white\s*;/color: var(--text-inverse);/g' "$tmp_file"
    sed -i 's/color:\s*#fff\s*;/color: var(--text-inverse);/g' "$tmp_file"
    sed -i 's/color:\s*#ffffff\s*;/color: var(--text-inverse);/g' "$tmp_file"
    sed -i 's/color:\s*#EC1C1C\s*;/color: var(--accent-danger);/g' "$tmp_file"
    sed -i 's/color:\s*#F7901E\s*;/color: var(--accent-warning);/g' "$tmp_file"
    
    # Move temp file to original
    mv "$tmp_file" "$file"
    
    echo "     ✅ Converted (backup: ${file}.pre-theme-backup)"
}

# Convert all custom CSS files
echo "=== Converting Custom CSS Files ==="
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
        convert_file "$css_file"
    fi
done

echo ""
echo "=== Conversion Complete! ==="
echo ""
echo "✅ All custom CSS files have been updated with CSS variables"
echo "📁 Original files backed up as *.pre-theme-backup"
echo ""
echo "🧪 Test your pages now - they should fully support light/dark mode!"
echo ""
echo "To restore a backup:"
echo "  cp ./path/to/file.pre-theme-backup ./path/to/file"

