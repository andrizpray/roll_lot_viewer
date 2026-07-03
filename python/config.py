"""Database and path configuration for roll_lot_viewer worker."""

# --- Database (SQLite) ---
DB_PATH = "/root/projects/roll_lot_viewer/database/database.sqlite"

# --- Paths ---
UPLOAD_DIR = "/root/projects/roll_lot_viewer/storage/app/private/imports"
EXPORT_DIR = "/root/projects/roll_lot_viewer/storage/app/public"

# --- Limits ---
MAX_EXPORT_ROWS = 10_000
IMPORT_BATCH_SIZE = 1_000
