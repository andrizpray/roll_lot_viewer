#!/usr/bin/env python3
"""Roll-lot viewer background worker.

Polls import_jobs and export_jobs tables for pending work,
dispatches to import_excel / export_excel modules.
Includes heartbeat for health monitoring and retry logic for failed jobs.
"""

import os
import sys
import time
import traceback

# Ensure sibling modules are importable
sys.path.insert(0, os.path.dirname(__file__))

from db import execute_query, get_connection
from config import (
    UPLOAD_DIR, EXPORT_DIR, HEARTBEAT_FILE,
    MAX_RETRY_ATTEMPTS, RETRY_BACKOFF_BASE,
)
from import_excel import import_roll_lots
from export_excel import export_roll_lots

POLL_INTERVAL = 5  # seconds between checks


def _write_heartbeat():
    """Write current timestamp to heartbeat file for health monitoring."""
    try:
        with open(HEARTBEAT_FILE, "w") as f:
            f.write(str(int(time.time())))
    except OSError:
        pass  # Non-critical — don't crash if we can't write heartbeat


def _set_job_status(table, job_id, status, error=None):
    """Update a job's status (and optional error message) atomically."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        if error is not None:
            cur.execute(
                f"UPDATE {table} SET status = %s, error_message = %s WHERE id = %s",
                (status, str(error)[:1000], job_id),
            )
        else:
            cur.execute(
                f"UPDATE {table} SET status = %s WHERE id = %s",
                (status, job_id),
            )
        conn.commit()
    finally:
        conn.close()


def _increment_attempts(table, job_id):
    """Increment the attempts counter for a job. Returns new attempt count."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute(
            f"UPDATE {table} SET attempts = COALESCE(attempts, 0) + 1 WHERE id = %s",
            (job_id,),
        )
        conn.commit()
        cur.execute(f"SELECT attempts FROM {table} WHERE id = %s", (job_id,))
        row = cur.fetchone()
        return row[0] if row else 1
    finally:
        conn.close()


def _should_retry(table, job_id):
    """Check if a failed job should be retried based on attempts count."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute(
            f"SELECT COALESCE(attempts, 0) FROM {table} WHERE id = %s",
            (job_id,),
        )
        row = cur.fetchone()
        attempts = row[0] if row else 0
        return attempts < MAX_RETRY_ATTEMPTS
    finally:
        conn.close()


def process_import_job(job):
    """Handle a single pending import job."""
    job_id = job["id"]
    filename = job["filename"]
    filepath = os.path.join(UPLOAD_DIR, filename)

    attempt = _increment_attempts("import_jobs", job_id)
    print(f"[worker] import job {job_id} — file: {filename} (attempt {attempt})")
    _set_job_status("import_jobs", job_id, "processing")

    try:
        import_roll_lots(job_id, filepath)
        _set_job_status("import_jobs", job_id, "completed")
        print(f"[worker] import job {job_id} completed")
    except Exception as exc:
        tb = traceback.format_exc()
        if _should_retry("import_jobs", job_id):
            # Reset to pending for retry on next cycle (with backoff)
            backoff = RETRY_BACKOFF_BASE * attempt
            _set_job_status("import_jobs", job_id, "pending", error=f"Retry {attempt}: {exc}")
            print(f"[worker] import job {job_id} FAILED (will retry in ~{backoff}s): {exc}")
            time.sleep(backoff)
        else:
            _set_job_status("import_jobs", job_id, "failed", error=str(exc))
            print(f"[worker] import job {job_id} FAILED permanently: {exc}\n{tb}")


def process_export_job(job):
    """Handle a single pending export job."""
    job_id = job["id"]

    # Parse filters — may be stored as JSON string or NULL
    raw_filters = job.get("filters")
    if isinstance(raw_filters, str):
        try:
            import json
            filters = json.loads(raw_filters)
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

    attempt = _increment_attempts("export_jobs", job_id)
    print(f"[worker] export job {job_id} — filters: {filters}, mode: {mode} (attempt {attempt})")
    _set_job_status("export_jobs", job_id, "processing")

    try:
        filepath = export_roll_lots(job_id, filters=filters, mode=mode)
        # Update job with filename
        filename = os.path.basename(filepath)
        conn = get_connection()
        try:
            cur = conn.cursor()
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
        if _should_retry("export_jobs", job_id):
            backoff = RETRY_BACKOFF_BASE * attempt
            _set_job_status("export_jobs", job_id, "pending", error=f"Retry {attempt}: {exc}")
            print(f"[worker] export job {job_id} FAILED (will retry in ~{backoff}s): {exc}")
            time.sleep(backoff)
        else:
            _set_job_status("export_jobs", job_id, "failed", error=str(exc))
            print(f"[worker] export job {job_id} FAILED permanently: {exc}\n{tb}")


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
          f"{POLL_INTERVAL}s (max retries: {MAX_RETRY_ATTEMPTS})")
    try:
        while True:
            try:
                _write_heartbeat()
                poll_once()
            except Exception:
                # Catch DB connection errors etc. so the loop keeps going
                traceback.print_exc()
            time.sleep(POLL_INTERVAL)
    except KeyboardInterrupt:
        print("\n[worker] shutting down")


if __name__ == "__main__":
    main()
