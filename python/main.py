#!/usr/bin/env python3
"""Roll-lot viewer background worker.

Polls import_jobs and export_jobs tables for pending work,
dispatches to import_excel / export_excel modules.
"""

import os
import sys
import json
import time
import traceback

# Ensure sibling modules are importable
sys.path.insert(0, os.path.dirname(__file__))

from db import execute_query, get_connection
from config import UPLOAD_DIR, EXPORT_DIR
from import_excel import import_roll_lots
from export_excel import export_roll_lots

POLL_INTERVAL = 5  # seconds between checks


def _set_job_status(table, job_id, status, error=None):
    """Update a job's status (and optional error message) atomically."""
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            if error is not None:
                cur.execute(
                    f"UPDATE {table} SET status = %s, error_message = %s WHERE id = %s",
                    (status, str(error), job_id),
                )
            else:
                cur.execute(
                    f"UPDATE {table} SET status = %s WHERE id = %s",
                    (status, job_id),
                )
        conn.commit()
    finally:
        conn.close()


def process_import_job(job):
    """Handle a single pending import job."""
    job_id = job["id"]
    filename = job["filename"]
    filepath = os.path.join(UPLOAD_DIR, filename)

    print(f"[worker] import job {job_id} — file: {filename}")
    _set_job_status("import_jobs", job_id, "processing")

    try:
        import_roll_lots(job_id, filepath)
        _set_job_status("import_jobs", job_id, "completed")
        print(f"[worker] import job {job_id} completed")
    except Exception as exc:
        tb = traceback.format_exc()
        _set_job_status("import_jobs", job_id, "failed", error=str(exc))
        print(f"[worker] import job {job_id} FAILED: {exc}\n{tb}")


def process_export_job(job):
    """Handle a single pending export job."""
    job_id = job["id"]

    # Parse filters — may be stored as JSON string or NULL
    raw_filters = job.get("filters")
    if isinstance(raw_filters, str):
        try:
            filters = __import__("json").loads(raw_filters)
        except (ValueError, Exception):
            filters = {}
    elif isinstance(raw_filters, dict):
        filters = raw_filters
    else:
        filters = {}

    # Filters may be stored nested: {"mode": "...", "resource": "...", "params": {...}}
    if isinstance(filters, dict) and "params" in filters:
        filters = filters["params"]

    mode = job.get("type", "roll")

    print(f"[worker] export job {job_id} — filters: {filters}, mode: {mode}")
    _set_job_status("export_jobs", job_id, "processing")

    try:
        filepath = export_roll_lots(job_id, filters=filters, mode=mode)
        # Update job with filename
        filename = os.path.basename(filepath)
        conn = get_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE export_jobs SET status = 'completed', filename = %s WHERE id = %s",
                    (filename, job_id),
                )
            conn.commit()
        finally:
            conn.close()
        print(f"[worker] export job {job_id} completed → {filepath}")
    except Exception as exc:
        tb = traceback.format_exc()
        _set_job_status("export_jobs", job_id, "failed", error=str(exc))
        print(f"[worker] export job {job_id} FAILED: {exc}\n{tb}")


def poll_once():
    """Run one polling cycle: check import then export jobs."""
    # --- Import jobs ---
    pending_imports = execute_query(
        "SELECT id, filename FROM import_jobs WHERE status = 'pending' LIMIT 1"
    )
    if pending_imports:
        process_import_job(pending_imports[0])

    # --- Export jobs ---
    pending_exports = execute_query(
        "SELECT id, filters, type FROM export_jobs WHERE status = 'pending' LIMIT 1"
    )
    if pending_exports:
        process_export_job(pending_exports[0])


def main():
    print("[worker] roll_lot_viewer worker started — polling every "
          f"{POLL_INTERVAL}s")
    try:
        while True:
            try:
                poll_once()
            except Exception:
                # Catch DB connection errors etc. so the loop keeps going
                traceback.print_exc()
            time.sleep(POLL_INTERVAL)
    except KeyboardInterrupt:
        print("\n[worker] shutting down")


if __name__ == "__main__":
    main()
