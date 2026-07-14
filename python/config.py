"""Database and path configuration for roll_lot_viewer worker."""

import os

# --- Database (PostgreSQL) ---
# Connection config is now in db.py
# DB_PATH no longer used (migrated from SQLite to PostgreSQL)

# --- Paths ---
UPLOAD_DIR = "/home/andriz/roll_lot_viewer/storage/app/uploads"
EXPORT_DIR = "/home/andriz/roll_lot_viewer/storage/app/public"
HEARTBEAT_FILE = "/home/andriz/roll_lot_viewer/storage/app/worker_heartbeat"

# --- Limits ---
MAX_EXPORT_ROWS = 10_000
IMPORT_BATCH_SIZE = 1_000

# --- Retry ---
MAX_RETRY_ATTEMPTS = 3
RETRY_BACKOFF_BASE = 10  # seconds: 10, 20, 30
