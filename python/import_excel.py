"""Import roll lots or paper sheets from an Excel file into the database."""

import os
import sys

sys.path.insert(0, os.path.dirname(__file__))

import openpyxl
from db import execute_query, execute_many, get_connection
from config import IMPORT_BATCH_SIZE


# Column mapping: Excel header → DB column
COLUMN_MAP = {
    "Lot ID": "lot_id",
    "Item ID": "item_id",
    "Weight": "weight",
    "Paper Type": "papertype",
    "Gramature": "gramature",
    "Play Bond": "playbond",
    "Width": "width",
    "Rew ID": "rew_id",
    "Grade": "grade",
    "Comments": "comments",
    "Diameter": "diameter",
    "Thickness": "thickness",
    "Description": "description_raw",
    "Date": "source_tr_date",
    "Time": "source_tr_time",
}


def import_roll_lots(job_id, filepath):
    """Import roll lots or paper sheets from an Excel file.

    Args:
        job_id: The import_jobs.id to update.
        filepath: Absolute path to the .xlsx file.

    Raises:
        FileNotFoundError: If the file doesn't exist.
        ValueError: If the file has no valid data rows.
    """
    if not os.path.isfile(filepath):
        raise FileNotFoundError(f"Import file not found: {filepath}")

    # Determine target table from import_jobs.type
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT type FROM import_jobs WHERE id = %s", (job_id,))
            result = cur.fetchone()
            job_type = result["type"] if result else "roll"
    finally:
        conn.close()

    table = "paper_sheets" if job_type == "sheet" else "roll_lots"

    wb = openpyxl.load_workbook(filepath, read_only=True, data_only=True)
    ws = wb.active
    if ws is None:
        raise ValueError("Workbook has no active sheet")

    # Read headers from first row
    headers = [cell.value for cell in next(ws.iter_rows(min_row=1, max_row=1))]

    # Map headers to DB columns
    db_columns = []
    for h in headers:
        if h in COLUMN_MAP:
            db_columns.append(COLUMN_MAP[h])

    if not db_columns:
        raise ValueError(f"No matching columns found. Excel headers: {headers}")

    # Build INSERT SQL
    placeholders = ", ".join(["%s"] * len(db_columns))
    columns = ", ".join([f"`{c}`" for c in db_columns])
    insert_sql = (
        f"INSERT INTO {table} ({columns}, import_batch_id) "
        f"VALUES ({placeholders}, %s) "
        f"ON DUPLICATE KEY UPDATE weight=VALUES(weight)"
    )

    # Process rows
    batch = []
    total = 0
    success = 0

    for row in ws.iter_rows(min_row=2, values_only=True):
        if all(cell is None for cell in row):
            continue

        # Map row values to DB columns
        data = dict(zip(headers, row))
        values = [data.get(h) for h in headers if h in COLUMN_MAP]
        values.append(job_id)  # import_batch_id

        batch.append(values)
        total += 1

        if len(batch) >= IMPORT_BATCH_SIZE:
            execute_many(insert_sql, batch)
            success += len(batch)
            # Update progress
            _update_progress(job_id, total, success)
            batch.clear()

    # Insert remaining
    if batch:
        execute_many(insert_sql, batch)
        success += len(batch)

    wb.close()

    # Final update
    _update_progress(job_id, total, success, completed=True)

    print(f"[import] job {job_id}: imported {success}/{total} rows from {filepath}")
    return success


def _update_progress(job_id, total, success, completed=False):
    """Update job progress in import_jobs table."""
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            if completed:
                cur.execute(
                    "UPDATE import_jobs SET total_rows = %s, success_count = %s, "
                    "failed_count = %s, status = 'completed' WHERE id = %s",
                    (total, success, total - success, job_id),
                )
            else:
                cur.execute(
                    "UPDATE import_jobs SET total_rows = %s, success_count = %s WHERE id = %s",
                    (total, success, job_id),
                )
        conn.commit()
    finally:
        conn.close()
