"""Import roll lots or paper sheets from an Excel file into the database."""
import os
import sys
import datetime

sys.path.insert(0, os.path.dirname(__file__))

import openpyxl
from db import execute_query, execute_many, get_connection
from config import IMPORT_BATCH_SIZE, UPLOAD_DIR


# Column mapping: Excel header → DB column
# Support both formats: with spaces and without
COLUMN_MAP = {
    # Common columns (Roll & Sheet)
    "Lot ID": "lot_id",
    "LotID": "lot_id",
    "Item ID": "item_id",
    "ItemID": "item_id",
    "Weight": "weight",
    "Description": "description_raw",
    "Date": "source_tr_date",
    "TrDate": "source_tr_date",
    "Time": "source_tr_time",
    "TrTime": "source_tr_time",
    "Comments": "comments",
    
    # Roll-specific columns
    "endqty": "weight",
    "Paper Type": "papertype",
    "PaperType": "papertype",
    "Gramature": "gramature",
    "Play Bond": "playbond",
    "PlayBond": "playbond",
    "Width": "width",
    "Rew ID": "rew_id",
    "RewID": "rew_id",
    "Grade": "grade",
    "Diameter": "diameter",
    "Thickness": "thickness",
    
    # Sheet-specific columns — listed here for reference only (not valid in roll_lots table)
    # These are intentionally mapped to None so they get skipped if found in a roll file
    "Qty": None,
    "Keterangan": None,
    "Qty_Pack": None,
    "Dimension": None,
}

# Numeric DB columns — bad values are nulled instead of failing the whole batch
NUMERIC_COLS = {"weight", "diameter"}

# Columns that exist in roll_lot_histories table
HISTORY_COLUMNS = [
    "lot_id", "item_id", "weight", "papertype", "gramature",
    "playbond", "width", "rew_id", "grade", "comments",
    "diameter", "thickness", "description_raw",
    "source_tr_date", "source_tr_time", "import_batch_id",
]


def parse_description(description):
    """
    Parse description to extract papertype, gramature, playbond, and width.
    FOR ROLL LOTS ONLY.
    
    Example: "B Kraft BK125 E150 690"
    - papertype: "B Kraft" (first 1-2 words)
    - gramature: "BK125" (word before playbond)
    - playbond: "E150" (word before width)
    - width: "690" (last word)
    
    Returns: dict with keys papertype, gramature, playbond, width (values can be None)
    """
    import re
    
    result = {
        'papertype': None,
        'gramature': None,
        'playbond': None,
        'width': None,
    }
    
    if not description or not isinstance(description, str):
        return result
    
    # Clean up: remove text in parentheses (e.g., "(Item Blocked)")
    # This prevents suffixes from interfering with parsing
    description = re.sub(r'\s*\([^)]*\)', '', description)
    
    # Split by whitespace and filter empty strings
    parts = [p.strip() for p in description.split() if p.strip()]
    
    if len(parts) == 0:
        return result
    
    # Width is the last token (if it looks like a number)
    if len(parts) >= 1:
        last = parts[-1]
        # Check if it's numeric or contains digits
        if any(c.isdigit() for c in last):
            result['width'] = last
            parts = parts[:-1]  # Remove width from parts
    
    # PlayBond is the second-to-last token (pattern: E + number)
    if len(parts) >= 1:
        playbond_candidate = parts[-1]
        result['playbond'] = playbond_candidate
        parts = parts[:-1]
    
    # Gramature is the third-to-last token (pattern: BK/NK + number)
    if len(parts) >= 1:
        gramature_candidate = parts[-1]
        result['gramature'] = gramature_candidate
        parts = parts[:-1]
    
    # PaperType is everything remaining (1-2 words at the start)
    if len(parts) >= 1:
        result['papertype'] = ' '.join(parts)
    
    return result


def parse_description_sheet(description):
    """
    Parse description to extract papertype, gramature, and dimension.
    FOR PAPER SHEETS ONLY.
    
    Example: "BK350 590x840"
    - First word "BK350": 
      - Prefix "BK" = papertype code → "B Kraft"
      - Number "350" = gramature
    - Second word "590x840" = dimension
    
    Edge case: "40007433 CB350G 770x650"
    - Skip numeric prefix (40007433)
    - Parse "CB350G" as gramature (with G suffix)
    
    Paper Type Codes:
    - BK = B Kraft
    - GB = Gray Board
    - BSP = B Kraft Sheet PE
    - CB = ChipBoard
    - CO = CoreBoard
    - YB = YellowBoard
    
    Returns: dict with keys papertype, gramature, dimension
    """
    import re
    
    result = {
        'papertype': None,
        'gramature': None,
        'dimension': None,
    }
    
    if not description or not isinstance(description, str):
        return result
    
    # Paper type code mapping
    papertype_map = {
        'BK': 'B Kraft',
        'GB': 'Gray Board',
        'BSP': 'B Kraft Sheet PE',
        'CB': 'ChipBoard',
        'CO': 'CoreBoard',
        'YB': 'YellowBoard',
        'BB': 'Brown Board',
        'DS': 'Duplex Sheet',
        'KBD': 'Kraft Back Duplex',
        'NK': 'Natural Kraft',
        'NSP': 'Natural Kraft Sheet PE',
        'DPS': 'Duplex PE Sheet',
    }
    
    parts = description.strip().split()
    
    if len(parts) == 0:
        return result
    
    # Skip first word if it's pure numeric (edge case: item code prefix)
    start_idx = 0
    if parts[0].isdigit():
        start_idx = 1
    
    if start_idx >= len(parts):
        return result
    
    # First meaningful word: extract papertype code + gramature
    # Pattern: 2-3 letters + numbers + optional letter suffix (e.g., BK350, CB350G, GB1000)
    first_word = parts[start_idx]
    match = re.match(r'^([A-Z]{2,3})(\d+[A-Z]?)', first_word, re.IGNORECASE)
    
    if match:
        code = match.group(1).upper()
        gramature_full = match.group(2)
        
        # Map code to full paper type name
        if code in papertype_map:
            result['papertype'] = papertype_map[code]
        else:
            result['papertype'] = code  # Use code as-is if not in map
        
        # Store full gramature with code (e.g., "BK350", "CB350G")
        result['gramature'] = code + gramature_full
    
    # Next word: dimension (pattern: NUMxNUM or NUM X NUM)
    next_idx = start_idx + 1
    if next_idx < len(parts):
        dimension_candidate = parts[next_idx]
        # Check if it matches dimension pattern (digits x digits, case insensitive)
        if re.search(r'\d+\s*[xX×]\s*\d+', dimension_candidate):
            result['dimension'] = dimension_candidate
    
    return result


# --- Column maps for sheet/roll mode ---
ROLL_COLUMN_MAP = COLUMN_MAP  # Same as the main COLUMN_MAP

SHEET_COLUMN_MAP = {
    "Lot ID": "lot_id",
    "LotID": "lot_id",
    "Item ID": "item_id",
    "ItemID": "item_id",
    "Weight": "weight",
    "endqty": "weight",
    "Qty": "qty",
    "Paper Type": "papertype",
    "PaperType": "papertype",
    "Gramature": "gramature",
    "Dimension": "dimension",
    "Content Pack": "content_pack",
    "Content Pallet": "content_pallet",
    "Qty_Pack": "content_pack",
    "Description": "description_raw",
    "Date": "source_tr_date",
    "TrDate": "source_tr_date",
    "Time": "source_tr_time",
    "TrTime": "source_tr_time",
    "DateTime": "source_tr_date",
    "Comments": "comments",
    "Keterangan": "keterangan",
    "LocationID": None,  # location_id column does not exist in paper_sheets schema
    "No": None,
}


def detect_type_from_headers(headers):
    """Auto-detect import type from Excel headers."""
    header_set = {h.strip() for h in headers if h and isinstance(h, str)}
    # Sheet-specific headers
    if header_set & {"Dimension", "Content Pack", "Qty_Pack", "Keterangan"}:
        return "sheet"
    # Roll-specific headers
    if header_set & {"Rew ID", "RewID", "Grade", "Diameter", "Width"}:
        return "roll"
    return "roll"  # default


def _import_sheet_rows(job_id, filepath, wb, ws, headers):
    """Import paper sheets using positional column mapping."""
    col_map_list = []
    for h in headers:
        if h and str(h).strip() in SHEET_COLUMN_MAP:
            col_map_list.append((h, SHEET_COLUMN_MAP[str(h).strip()]))
        else:
            col_map_list.append((h, None))

    db_columns = [db_col for _, db_col in col_map_list if db_col]

    # Add derived columns from description parsing if missing
    derived_cols = []
    for col in ['papertype', 'gramature', 'dimension']:
        if col not in db_columns:
            derived_cols.append(col)
            db_columns.append(col)

    placeholders = ", ".join(["%s"] * len(db_columns))
    columns = ", ".join(['"' + c + '"' for c in db_columns])
    update_cols = ", ".join([f'"{c}" = EXCLUDED."{c}"' for c in db_columns])
    insert_sql = (
        f'INSERT INTO paper_sheets ({columns}, import_batch_id) '
        f'VALUES ({placeholders}, %s) '
        f'ON CONFLICT (lot_id) DO UPDATE SET {update_cols}, import_batch_id = EXCLUDED.import_batch_id'
    )

    # Build value index map: db_col -> position in values list
    val_idx = {}
    pos = 0
    for _, db_col in col_map_list:
        if db_col:
            val_idx[db_col] = pos
            pos += 1

    total = success = failed = 0
    errors = []

    # Kumpulkan semua values dulu — satu koneksi, satu transaksi
    batch = []  # list of tuples, setiap tuple = satu baris values

    for row in ws.iter_rows(min_row=2, values_only=True):
        if all(cell is None for cell in row):
            continue
        total += 1
        row_number = total + 1
        lot_id = None
        try:
            values = []
            for i, (h, db_col) in enumerate(col_map_list):
                if db_col is None:
                    continue
                val = row[i] if i < len(row) else None
                if isinstance(val, datetime.time):
                    val = val.strftime("%H:%M:%S")
                elif isinstance(val, datetime.date) and not isinstance(val, datetime.datetime):
                    val = val.strftime("%Y-%m-%d")
                elif isinstance(val, datetime.datetime):
                    val = val.strftime("%Y-%m-%d %H:%M:%S")
                elif val == "" or val == "-":
                    val = None
                # Cap numeric overflow (Excel cell corruption)
                elif isinstance(val, (int, float)) and abs(val) >= 10**13:
                    val = None
                values.append(val)

            # Cast string columns that might arrive as int from Excel
            for col_name in ("lot_id", "item_id"):
                if col_name in val_idx and values[val_idx[col_name]] is not None:
                    values[val_idx[col_name]] = str(values[val_idx[col_name]])

            # Extract lot_id from val_idx (NOT hardcoded row[1]) for accurate error logging
            if "lot_id" in val_idx and values[val_idx["lot_id"]] is not None:
                lot_id = str(values[val_idx["lot_id"]])
            else:
                lot_id = None

            # Parse description for missing fields
            desc_idx = next((i for i, (h, dc) in enumerate(col_map_list) if dc == "description_raw"), None)
            description_raw = row[desc_idx] if desc_idx is not None and desc_idx < len(row) else None

            if description_raw:
                parsed = parse_description_sheet(description_raw)
                # Fill existing columns
                for field in ("papertype", "gramature", "dimension"):
                    if field in val_idx and not values[val_idx[field]]:
                        values[val_idx[field]] = parsed[field]
                # Append derived columns
                for col in derived_cols:
                    values.append(parsed.get(col))
            elif derived_cols:
                for _ in derived_cols:
                    values.append(None)

            values.append(job_id)
            batch.append(tuple(values))

        except Exception as exc:
            failed += 1
            errors.append((row_number, lot_id, None, str(exc)))

        if total % IMPORT_BATCH_SIZE == 0:
            _update_progress(job_id, total, success, failed)

    wb.close()

    # --- Satu transaksi: BEGIN -> executemany batch -> COMMIT -> delete stale ---
    # ISI 2: batch insert (executemany per IMPORT_BATCH_SIZE baris)
    # ISI 3: _delete_stale() HANYA dipanggil setelah semua baris berhasil di-commit
    insert_ok = False
    conn = get_connection()
    try:
        cur = conn.cursor()
        for i in range(0, len(batch), IMPORT_BATCH_SIZE):
            chunk = batch[i:i + IMPORT_BATCH_SIZE]
            cur.executemany(insert_sql, chunk)
        conn.commit()  # <-- commit dulu, baru delete stale
        success = len(batch)
        insert_ok = True
    except Exception as exc:
        conn.rollback()
        failed = len(batch)
        errors.append((0, None, None, f"Batch insert failed, all rolled back: {exc}"))
        if errors:
            _log_errors(job_id, errors)
        _update_progress(job_id, total, 0, failed, completed=True)
        print(f"[import] sheet job {job_id}: ROLLBACK — {exc}")
        return 0
    finally:
        conn.close()

    if not insert_ok:
        return 0

    # ISI 3: delete stale SETELAH semua baris berhasil di-commit
    # Guard: jangan delete_stale kalau batch kosong — akan hapus semua row di tabel!
    if not batch:
        _update_progress(job_id, total, 0, 0, completed=True)
        print(f"[import] sheet job {job_id}: empty file — skipping delete_stale to prevent data wipe")
        return 0
    _delete_stale("paper_sheets", job_id)

    if errors:
        _log_errors(job_id, errors)
    _update_progress(job_id, total, success, failed, completed=True)
    print(f"[import] sheet job {job_id}: imported {success}/{total} rows ({failed} errors)")
    return success


def import_roll_lots(job_id, filepath):
    """Import roll lots or paper sheets from an Excel file."""
    if not os.path.isfile(filepath):
        raise FileNotFoundError(f"Import file not found: {filepath}")

    # Determine target table and file path from import_jobs
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute("SELECT type, filename FROM import_jobs WHERE id = %s", (job_id,))
        result = cur.fetchone()
        job_type = result[0] if result else "roll"
        stored_path = result[1] if result else None
    finally:
        conn.close()

    # Resolve actual file path
    if stored_path and os.path.isfile(stored_path):
        filepath = stored_path
    elif stored_path:
        # storage_path is relative hash — join with UPLOAD_DIR
        candidate = os.path.join(UPLOAD_DIR, stored_path)
        if os.path.isfile(candidate):
            filepath = candidate

    wb = openpyxl.load_workbook(filepath, read_only=True, data_only=True)
    ws = wb.active
    if ws is None:
        raise ValueError("Workbook has no active sheet")

    # Read headers from first row
    headers = [cell.value for cell in next(ws.iter_rows(min_row=1, max_row=1))]

    # Auto-detect type from headers if PHP got it wrong
    detected = detect_type_from_headers(headers)
    if detected != job_type:
        print(f"[import] job {job_type} -> detected '{detected}' from headers, overriding")
        job_type = detected
        # Update DB
        conn2 = get_connection()
        try:
            cur2 = conn2.cursor()
            cur2.execute("UPDATE import_jobs SET type = %s WHERE id = %s", (job_type, job_id))
            conn2.commit()
        finally:
            conn2.close()

    table = "paper_sheets" if job_type == "sheet" else "roll_lots"
    column_map = SHEET_COLUMN_MAP if job_type == "sheet" else ROLL_COLUMN_MAP

    # Handle sheet mode with positional indexing (duplicate headers)
    if job_type == "sheet":
        return _import_sheet_rows(job_id, filepath, wb, ws, headers)

    # Roll mode: map headers to DB columns (skip None-mapped / sheet-only columns)
    db_columns = []
    header_to_col = {}
    for h in headers:
        if h in COLUMN_MAP and COLUMN_MAP[h] is not None:
            db_columns.append(COLUMN_MAP[h])
            header_to_col[h] = COLUMN_MAP[h]

    if not db_columns:
        raise ValueError(f"No matching columns found. Excel headers: {headers}")

    # If Description is present but papertype/gramature/width are missing,
    # add them — they'll be parsed from description at row level
    derived_cols = []
    for col in ['papertype', 'gramature', 'playbond', 'width']:
        if col not in db_columns:
            derived_cols.append(col)
            db_columns.append(col)
    has_description = 'description_raw' in db_columns

    # Build INSERT SQL — use INSERT ... ON CONFLICT (upsert) for PostgreSQL
    placeholders = ", ".join(["%s"] * len(db_columns))
    columns = ", ".join(['"' + c + '"' for c in db_columns])
    update_cols = ", ".join([f'"{c}" = EXCLUDED."{c}"' for c in db_columns])
    insert_sql = (
        f'INSERT INTO {table} ({columns}, import_batch_id) '
        f'VALUES ({placeholders}, %s) '
        f'ON CONFLICT (lot_id) DO UPDATE SET {update_cols}, import_batch_id = EXCLUDED.import_batch_id'
    )

    # Process rows — kumpulkan semua values dulu (ISI 2: batch insert)
    total = 0
    failed = 0
    errors = []  # (row_number, lot_id, description_raw, reason)

    # Pre-build col_index sekali, bukan per baris
    # Filter out None-mapped columns (sheet-only entries that have None value in COLUMN_MAP)
    col_index = {COLUMN_MAP[h]: i for i, h in enumerate(headers) if h in COLUMN_MAP and COLUMN_MAP[h] is not None}

    # Pre-build ordered header list untuk lookup langsung via index (hindari dict per baris)
    mapped_headers = [(i, h, COLUMN_MAP[h]) for i, h in enumerate(headers) if h in COLUMN_MAP and COLUMN_MAP[h] is not None]

    batch = []  # list of tuples, setiap tuple = values satu baris
    lot_ids_in_file = []  # kumpulkan lot_id untuk batch snapshot

    for row in ws.iter_rows(min_row=2, values_only=True):
        if all(cell is None for cell in row):
            continue

        total += 1
        row_number = total + 1  # +1 for header row
        lot_id = None

        try:
            values = []
            for idx, h, db_col in mapped_headers:
                val = row[idx] if idx < len(row) else None
                # Normalize datetime objects to strings
                # NOTE: datetime.datetime IS-A datetime.date — check datetime first!
                if isinstance(val, datetime.time):
                    val = val.strftime("%H:%M:%S")
                elif isinstance(val, datetime.datetime):
                    val = val.strftime("%Y-%m-%d %H:%M:%S")
                elif isinstance(val, datetime.date):
                    val = val.strftime("%Y-%m-%d")
                # Convert empty strings and dash placeholders to None
                elif val == "" or val == "-":
                    val = None
                # Numeric columns: coerce or null (bad cell like '1242/1250/275'
                # must skip one row, not roll back the whole batch)
                elif db_col in NUMERIC_COLS and val is not None:
                    try:
                        val = float(val)
                    except (TypeError, ValueError):
                        val = 0 if db_col == "weight" else None
                # Cap numeric overflow (Excel cell corruption)
                elif isinstance(val, (int, float)) and abs(val) >= 10**13:
                    val = None
                values.append(val)

            # Extract lot_id and description via pre-built index (no dict alloc)
            lot_id_idx = col_index.get("lot_id")
            lot_id = str(values[lot_id_idx]) if lot_id_idx is not None and values[lot_id_idx] is not None else None

            desc_idx = col_index.get("description_raw")
            description_raw = values[desc_idx] if desc_idx is not None else None

            # Parse description to fill missing fields — satu kali per baris
            parsed = None
            if description_raw:
                parsed = parse_description(description_raw)
                for field in ("papertype", "gramature", "playbond", "width"):
                    if field in col_index and not values[col_index[field]]:
                        values[col_index[field]] = parsed[field]

            # Append values for derived columns (not in Excel headers)
            if derived_cols:
                if parsed is None and description_raw:
                    parsed = parse_description(description_raw)
                for col in derived_cols:
                    values.append(parsed.get(col) if parsed else None)

            # Kumpulkan lot_id untuk batch snapshot (dilakukan setelah loop)
            if table == "roll_lots" and lot_id:
                lot_ids_in_file.append(lot_id)

            # Append job_id as import_batch_id
            values.append(job_id)
            batch.append(tuple(values))

        except Exception as exc:
            failed += 1
            description_fallback = row[col_index["description_raw"]] if "description_raw" in col_index and col_index["description_raw"] < len(row) else None
            errors.append((row_number, lot_id, description_fallback, str(exc)))

        # Update progress every IMPORT_BATCH_SIZE rows
        if total % IMPORT_BATCH_SIZE == 0:
            _update_progress(job_id, total, len(batch), failed)

    wb.close()

    # --- Snapshot history SEKALI untuk semua lot_id (bukan per baris) ---
    # Deduplicate: file mungkin punya baris duplikat — hindari double snapshot
    if table == "roll_lots" and lot_ids_in_file:
        _snapshot_existing_batch(list(dict.fromkeys(lot_ids_in_file)), job_id)

    # --- Satu transaksi: BEGIN -> executemany batch -> COMMIT -> delete stale ---
    # ISI 2: batch executemany, bukan row-by-row dengan koneksi baru
    # ISI 3: _delete_stale() HANYA setelah semua baris berhasil di-commit
    insert_ok = False
    conn = get_connection()
    try:
        cur = conn.cursor()
        for i in range(0, len(batch), IMPORT_BATCH_SIZE):
            chunk = batch[i:i + IMPORT_BATCH_SIZE]
            cur.executemany(insert_sql, chunk)
        conn.commit()  # <-- commit dulu, baru delete stale
        success = len(batch)
        insert_ok = True
    except Exception as exc:
        conn.rollback()
        success = 0
        failed = len(batch)
        errors.append((0, None, None, f"Batch insert failed, all rolled back: {exc}"))
        if errors:
            _log_errors(job_id, errors)
        _update_progress(job_id, total, 0, failed, completed=True)
        print(f"[import] job {job_id}: ROLLBACK — {exc}")
        return 0
    finally:
        conn.close()

    if not insert_ok:
        return 0

    # ISI 3: Full-sync delete HANYA setelah commit berhasil
    # Guard: jangan delete_stale kalau batch kosong — akan hapus semua row di tabel!
    if not batch:
        _update_progress(job_id, total, 0, 0, completed=True)
        print(f"[import] job {job_id}: empty file — skipping delete_stale to prevent data wipe")
        return 0
    _delete_stale(table, job_id)

    # Log errors to import_errors table
    if errors:
        _log_errors(job_id, errors)

    # Final update
    _update_progress(job_id, total, success, failed, completed=True)

    print(f"[import] job {job_id}: imported {success}/{total} rows "
          f"({failed} errors) from {filepath}")
    return success


def _delete_stale(table, job_id):
    """Full-sync: delete rows not present in the current import.

    Upsert sets import_batch_id = job_id for every row in the file (inserted or
    updated). Rows absent from the file keep their old import_batch_id, so
    `IS DISTINCT FROM %s` catches exactly the stale ones (NULL-safe).
    """
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute(
            f'DELETE FROM {table} WHERE import_batch_id IS DISTINCT FROM %s',
            (job_id,),
        )
        deleted = cur.rowcount
        conn.commit()
    finally:
        conn.close()
    print(f"[import] job {job_id}: deleted {deleted} stale rows from {table}")
    return deleted


def _snapshot_existing_batch(lot_ids, job_id):
    """Batch-copy existing roll_lot records to roll_lot_histories.

    One connection per call. Handles files with >32767 lots by chunking the
    SELECT. INSERT also chunked to keep param count within PostgreSQL limits.
    """
    if not lot_ids:
        return

    now = datetime.datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    conn = get_connection()
    try:
        cur = conn.cursor()

        # --- SELECT in chunks to stay under PostgreSQL param limit (32767) ---
        # ponytail: param limit hard; chunk at 30000 to leave headroom
        chunk_size = 30000
        all_rows = []
        for i in range(0, len(lot_ids), chunk_size):
            chunk = lot_ids[i:i + chunk_size]
            placeholders = ",".join(["%s"] * len(chunk))
            cur.execute(
                f"SELECT * FROM roll_lots WHERE lot_id IN ({placeholders})",
                chunk,
            )
            all_rows.extend(cur.fetchall())

        if not all_rows:
            return

        col_names = [desc[0] for desc in cur.description]

        # Build history rows — one tuple per existing lot
        history_data = []
        history_cols = None

        for row in all_rows:
            existing = dict(zip(col_names, row))
            h_cols = []
            h_vals = []
            for col in HISTORY_COLUMNS:
                if col in existing:
                    h_cols.append(col)
                    h_vals.append(existing[col])
            h_cols.extend(["archived_at", "created_at", "updated_at"])
            h_vals.extend([now, now, now])

            if history_cols is None:
                history_cols = h_cols  # same shape for every row
            history_data.append(tuple(h_vals))

        if not history_data or history_cols is None:
            return

        col_str = ", ".join([f'"{c}"' for c in history_cols])
        ph = ", ".join(["%s"] * len(history_cols))

        # --- INSERT in chunks to stay under PostgreSQL param limit ---
        insert_ok = True
        for i in range(0, len(history_data), chunk_size):
            chunk = history_data[i:i + chunk_size]
            try:
                cur.executemany(
                    f"INSERT INTO roll_lot_histories ({col_str}) VALUES ({ph})",
                    chunk,
                )
            except Exception as exc:
                insert_ok = False
                conn.rollback()
                print(f"[import] WARNING: snapshot history failed at chunk {i}: {exc}")
                break
        if insert_ok:
            conn.commit()
    finally:
        conn.close()
    return insert_ok


def _log_errors(job_id, errors):
    """Write per-row errors to the import_errors table."""
    now = datetime.datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    insert_sql = (
        "INSERT INTO import_errors "
        "(import_batch_id, row_number, lot_id, description_raw, reason, created_at, updated_at) "
        "VALUES (%s, %s, %s, %s, %s, %s, %s)"
    )
    data = [
        (job_id, row_num, lot_id, desc_raw, reason, now, now)
        for row_num, lot_id, desc_raw, reason in errors
    ]
    try:
        execute_many(insert_sql, data)
    except Exception as exc:
        # Don't let error-logging failures crash the import
        print(f"[import] WARNING: failed to log {len(errors)} errors: {exc}")


def _update_progress(job_id, total, success, failed, completed=False):
    """Update job progress in import_jobs table."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        if completed:
            cur.execute(
                "UPDATE import_jobs SET total_rows = %s, success_count = %s, "
                "failed_count = %s, status = 'completed' WHERE id = %s",
                (total, success, failed, job_id),
            )
        else:
            cur.execute(
                "UPDATE import_jobs SET total_rows = %s, success_count = %s, "
                "failed_count = %s WHERE id = %s",
                (total, success, failed, job_id),
            )
        conn.commit()
    finally:
        conn.close()
