#!/usr/bin/env python3
"""Update existing paper_sheets records by parsing Description field."""

import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

from db import get_connection
from import_excel import parse_description_sheet

def update_sheets_data(batch_id=None):
    """Update papertype, gramature, dimension from description for sheets."""
    
    conn = get_connection()
    try:
        cur = conn.cursor()
        
        # Get records that need updating
        if batch_id:
            query = """
                SELECT id, description_raw, papertype, gramature, dimension
                FROM paper_sheets 
                WHERE import_batch_id = %s
                AND description_raw IS NOT NULL
            """
            cur.execute(query, (batch_id,))
        else:
            query = """
                SELECT id, description_raw, papertype, gramature, dimension
                FROM paper_sheets 
                WHERE description_raw IS NOT NULL
            """
            cur.execute(query)
        
        records = cur.fetchall()
        print(f"Found {len(records)} sheet records to process")
        
        updated = 0
        for record in records:
            record_id, description, papertype, gramature, dimension = record
            
            # Parse description using sheet logic
            parsed = parse_description_sheet(description)
            
            # Determine what needs updating
            updates = []
            params = []
            
            if not papertype and parsed['papertype']:
                updates.append("papertype = %s")
                params.append(parsed['papertype'])
            
            if not gramature and parsed['gramature']:
                updates.append("gramature = %s")
                params.append(parsed['gramature'])
            
            if not dimension and parsed['dimension']:
                updates.append("dimension = %s")
                params.append(parsed['dimension'])
            
            if updates:
                params.append(record_id)
                update_sql = f"UPDATE paper_sheets SET {', '.join(updates)} WHERE id = %s"
                cur.execute(update_sql, params)
                updated += 1
        
        conn.commit()
        print(f"✓ Updated {updated} sheet records successfully")
        
    finally:
        conn.close()

if __name__ == "__main__":
    batch_id = int(sys.argv[1]) if len(sys.argv) > 1 else None
    update_sheets_data(batch_id)
