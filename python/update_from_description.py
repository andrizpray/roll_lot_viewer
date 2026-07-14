#!/usr/bin/env python3
"""Update existing roll_lots records by parsing Description field."""

import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

from db import get_connection
from import_excel import parse_description

def update_existing_data(batch_id=None):
    """Update papertype, gramature, playbond, width from description."""
    
    conn = get_connection()
    try:
        cur = conn.cursor()
        
        # Get records that need updating
        if batch_id:
            query = """
                SELECT id, description_raw, papertype, gramature, playbond, width
                FROM roll_lots 
                WHERE import_batch_id = %s
                AND description_raw IS NOT NULL
            """
            cur.execute(query, (batch_id,))
        else:
            query = """
                SELECT id, description_raw, papertype, gramature, playbond, width
                FROM roll_lots 
                WHERE description_raw IS NOT NULL
            """
            cur.execute(query)
        
        records = cur.fetchall()
        print(f"Found {len(records)} records to process")
        
        updated = 0
        for record in records:
            record_id, description, papertype, gramature, playbond, width = record
            
            # Parse description
            parsed = parse_description(description)
            
            # Determine what needs updating
            updates = []
            params = []
            
            if not papertype and parsed['papertype']:
                updates.append("papertype = %s")
                params.append(parsed['papertype'])
            
            if not gramature and parsed['gramature']:
                updates.append("gramature = %s")
                params.append(parsed['gramature'])
            
            if not playbond and parsed['playbond']:
                updates.append("playbond = %s")
                params.append(parsed['playbond'])
            
            if not width and parsed['width']:
                updates.append("width = %s")
                params.append(parsed['width'])
            
            if updates:
                params.append(record_id)
                update_sql = f"UPDATE roll_lots SET {', '.join(updates)} WHERE id = %s"
                cur.execute(update_sql, params)
                updated += 1
        
        conn.commit()
        print(f"Updated {updated} records successfully")
        
    finally:
        conn.close()

if __name__ == "__main__":
    batch_id = int(sys.argv[1]) if len(sys.argv) > 1 else None
    update_existing_data(batch_id)
