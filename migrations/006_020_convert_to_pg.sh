#!/bin/bash
# Script to convert remaining SQLite migrations to PostgreSQL
# This converts migrations 006 to 020

# List of migrations to convert
migrations=(
    "009_add_piece_fields_prestations"
    "010_category_colors"
    "011_drop_category_colors"
    "012_create_categories"
    "013_client_note"
    "014_client_note_no_tx"
    "015_add_notes_to_tickets"
    "016_create_accounting_exports_table"
    "017_create_company_profile"
    "018_create_planning"
    "019_remove_estimated_minutes_from_planning"
    "020_add_ready_status_to_tickets"
)

# Convert each migration
for migration in "${migrations[@]}"; do
    input_file="migrations/${migration}.sql"
    output_file="migrations/${migration}_pg.sql"
    
    if [ ! -f "$input_file" ]; then
        echo "Skipping $migration (file not found)"
        continue
    fi
    
    echo "Converting $migration to PostgreSQL..."
    
    # Create the PostgreSQL version
    cat > "$output_file" << 'EOF'
-- PostgreSQL version of this migration
-- Note: This file may need manual adjustments for specific PostgreSQL syntax

EOF
    
    # Convert SQLite to PostgreSQL syntax
    sed -e 's/INTEGER PRIMARY KEY AUTOINCREMENT/SERIAL PRIMARY KEY/g' \
        -e 's/DATETIME/TIMESTAMP/g' \
        -e 's/TEXT DEFAULT NULL/TIMESTAMP DEFAULT NULL/g' \
        -e 's/REAL/NUMERIC/g' \
        -e 's/PRAGMA.*$//g' \
        -e 's/BEGIN TRANSACTION;/BEGIN;/g' \
        -e 's/PRAGMA foreign_keys = OFF;//g' \
        -e 's/PRAGMA foreign_keys = ON;//g' \
        "$input_file" >> "$output_file"
    
    echo "Created $output_file"
done

echo "Conversion complete!"
echo "Review and test each migration file before running."