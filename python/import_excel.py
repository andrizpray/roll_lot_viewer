"""Import roll lots or paper sheets from an Excel file into the database."""
import os
import sys

sys.path.insert(0, os.path.dirname(__file__))

import openpyxl
from db import execute_query, execute_many, get_connection
from config import IMPORT_BATCH_SIZE, UPLOAD_DIR

# Column mapping: Excel header → DB column (roll mode)
ROLL_COLUMN_MAP = {
    "lot id": "lot_id",
    "lotid": "lot_id",
    "item id": "item_id",
    "itemid": "item_id",
    "weight": "weight",
    "paper type": "papertype",
    "papertype": "papertype",
    "gramature": "gramature",
    "play bond": "playbond",
    "playbond": "playbond",
    "width": "width",
    "rew id": "rew_id",
    "rewid": "rew_id",
    "grade": "grade",
    "comments": "comments",
    "diameter": "diameter",
    "thickness": "thickness",
    "description": "description_raw",
    "date": "source_tr_date",
    "time": "source_tr_time",
}

# Column mapping: Excel header → DB column (sheet mode)
SHEET_COLUMN_MAP = {
    "lotid": "lot_id",
    "lot id": "lot_id",
    "itemid": "item_id",
    "item id": "item_id",
    "weight": "weight",
    "description": "description_raw",
    "qty_pack": "content_pack",
    "qty pack": "content_pack",
    "trdate": "source_tr_date",
    "trdate": "source_tr_date",
    "trtime": "source_tr_time",
    "trtime": "source_tr_time",
}


def detect_type_from_headers(headers):
    """Detect file type from header row (positional approach for duplicates)."""
    normalized = [
        str(h).lower().strip() if isinstance(h, str) else ""
        for h in headers
    ]
    unique = list(dict.fromkeys(normalized))  # dedupe preserving order

    has_roll = (
        "papertype" in unique
        and "gramature" in unique
        and "rewid" in unique
    )
    if has_roll:
        return "roll"

    has_sheet = "qty_pack" in unique and (
        "description" in unique or "keterangan" in unique
    )
    if has_sheet:
        return "sheet"

    return "roll"  # fallback


def import_roll_lots(job_id, filepath):
    """Import roll lots or paper sheets from an Excel file."""
    if not os.path.isfile(filepath):
        raise FileNotFoundError(f"Import file not found: {filepath}")

    # Determine target table and file path from import_jobs
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute("SELECT type, storage_path FROM import_jobs WHERE id = ?", (job_id,))
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
            conn2.execute("UPDATE import_jobs SET type = ? WHERE id = ?", (job_type, job_id))
            conn2.commit()
        finally:
            conn2.close()

    table = "paper_sheets" if job_type == "sheet" else "roll_lots"
    column_map = SHEET_COLUMN_MAP if job_type == "sheet" else ROLL_COLUMN_MAP

    # Handle sheet mode with positional indexing (duplicate headers)
    if job_type == "sheet":
        return _import_sheet_rows(job_id, filepath, wb, ws, headers)

    # Roll mode: map headers to DB columns
    db_columns = []
    matched_headers = []
    for h in headers:
        key = str(h).lower().strip() if isinstance(h, str) else ""
        if key in column_map:
            db_columns.append(column_map[key])
            matched_headers.append(h)

    if not db_columns:
        raise ValueError(f"No matching columns found. Excel headers: {headers}")

    # Build INSERT SQL
    placeholders = ", ".join(["?"] * len(db_columns))
    columns = ", ".join([f'"{c}"' for c in db_columns])
    insert_sql = (
        f"INSERT OR REPLACE INTO {table} ({columns}, import_batch_id) "
        f"VALUES ({placeholders}, ?)"
    )

    # Process rows
    batch = []
    total = 0
    success = 0

    for row in ws.iter_rows(min_row=2, values_only=True):
        if all(cell is None for cell in row):
            continue

        data = dict(zip(headers, row))
        values = [data.get(h) for h in matched_headers]
        values.append(job_id)

        batch.append(values)
        total += 1

        if len(batch) >= IMPORT_BATCH_SIZE:
            execute_many(insert_sql, batch)
            success += len(batch)
            _update_progress(job_id, total, success)
            batch.clear()

    if batch:
        execute_many(insert_sql, batch)
        success += len(batch)

    wb.close()
    _update_progress(job_id, total, success, completed=True)
    print(f"[import] job {job_id}: imported {success}/{total} rows ({job_type})")
    return success


def _import_sheet_rows(job_id, filepath, wb, ws, headers):
    """Import paper_sheets using positional indexing (handles duplicate headers)."""
    import re
    import datetime as dt

    def parse_desc(desc):
        if not desc:
            return "", "", ""
        s = str(desc).strip()
        m = re.match(r"(?:\d+\s+)?([A-Za-z]+)(\d+\w*)\s+([0-9]+[xX×][0-9]+)", s)
        if m:
            return m.group(1), m.group(2), m.group(3)
        parts = s.split()
        if len(parts) >= 2:
            return "", parts[0], parts[1]
        return "", "", ""

    # Find column positions by normalized header name
    col_map = {}
    seen = {}
    for i, h in enumerate(headers):
        key = str(h).lower().strip() if isinstance(h, str) else str(h)
        if key in seen:
            continue  # skip duplicate
        seen[key] = i
        if key in ("lotid", "lot id"):
            col_map["lot_id"] = i
        elif key in ("itemid", "item id"):
            col_map["item_id"] = i
        elif key == "weight":
            col_map["weight"] = i
        elif key == "description":
            col_map["description"] = i
        elif key in ("qty_pack", "qty pack"):
            col_map["content_pack"] = i
        elif key == "trdate":
            col_map["source_tr_date"] = i
        elif key == "trtime":
            col_map["source_tr_time"] = i

    insert_sql = (
        'INSERT OR REPLACE INTO paper_sheets '
        '(lot_id, item_id, weight, papertype, gramature, dimension, '
        'content_pack, content_pallet, description_raw, source_tr_date, '
        'source_tr_time, import_batch_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )

    batch = []
    total = 0
    success = 0
    errors = 0

    for row in ws.iter_rows(min_row=2, values_only=True):
        if all(c is None for c in row):
            continue
        total += 1

        def _get(key, default=None):
            idx = col_map.get(key)
            if idx is None or idx >= len(row):
                return default
            return row[idx]

        lot_id = str(_get("lot_id", "") or "").strip()
        if not lot_id:
            errors += 1
            continue

        item_id = str(_get("item_id", "") or "").strip()
        weight = _get("weight", 0) or 0
        description = str(_get("description", "") or "").strip()
        qty_pack = _get("content_pack")

        papertype, gramature, dimension = parse_desc(description)

        # Parse qty_pack
        cp = None
        if qty_pack and str(qty_pack).strip() not in ("-", "", "None"):
            try:
                cp = int(float(str(qty_pack)))
            except (ValueError, TypeError):
                pass

        # Parse date/time
        tr_date = _get("source_tr_date")
        src_date = None
        if tr_date and isinstance(tr_date, dt.datetime):
            src_date = tr_date.strftime("%Y-%m-%d")

        tr_time = _get("source_tr_time")
        src_time = None
        if tr_time and isinstance(tr_time, dt.time):
            src_time = tr_time.strftime("%H:%M:%S")

        batch.append((
            lot_id, item_id, weight, papertype, gramature, dimension,
            cp, None, description, src_date, src_time, job_id,
        ))

        if len(batch) >= IMPORT_BATCH_SIZE:
            execute_many(insert_sql, batch)
            success += len(batch)
            _update_progress(job_id, total, success)
            batch.clear()

    if batch:
        execute_many(insert_sql, batch)
        success += len(batch)

    wb.close()
    _update_progress(job_id, total, success, completed=True)
    print(f"[import] job {job_id}: imported {success}/{total} sheet rows, {errors} errors")
    return success


def _update_progress(job_id, total, success, completed=False):
    """Update job progress in import_jobs table."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        if completed:
            cur.execute(
                "UPDATE import_jobs SET total_rows = ?, success_count = ?, "
                "failed_count = ?, status = 'completed' WHERE id = ?",
                (total, success, total - success, job_id),
            )
        else:
            cur.execute(
                "UPDATE import_jobs SET total_rows = ?, success_count = ? WHERE id = ?",
                (total, success, job_id),
            )
        conn.commit()
    finally:
        conn.close()
