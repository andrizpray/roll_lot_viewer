"""Database and path configuration for roll_lot_viewer worker."""

# --- Database ---
DB_HOST = "127.0.0.1"
DB_PORT = 3306
DB_USER = "roll_lot"
DB_PASSWORD = "rolllot2026"
DB_NAME = "roll_lot_viewer"

# --- Paths ---
UPLOAD_DIR = "/var/www/sd-projects/roll_lot_viewer/storage/app/uploads"
EXPORT_DIR = "/root/projects/roll_lot_viewer/storage/app/public"

# --- Limits ---
MAX_EXPORT_ROWS = 10_000
IMPORT_BATCH_SIZE = 1_000
