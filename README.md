# Roll Lot Viewer

Aplikasi internal untuk mengimpor, menampilkan, dan memfilter data mutasi kertas produksi.

**Dua tipe data:**
- **Mutasi Roll (PM1/PM2):** Data roll/lot harian dari `PeriodBalanceRoll.xlsx`
- **Mutasi Stock Sheet:** Data stock sheet harian (format kolom berbeda, auto-detect)

> **Live:** https://lot-view.drizdev.space/

## Tech Stack
- **Backend:** Laravel 11 (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API) + Vite
- **Database:** SQLite
- **Worker:** Python 3 (background import/export via systemd)
- **Server:** Nginx + PHP-FPM (Docker)
- **Proxy:** 9Router (AI proxy)

## Architecture

```
Browser → Nginx (Docker) → PHP-FPM (Docker) → SQLite
                         → Python Worker (systemd) → SQLite
```

- **SQLite** — zero-config, no container needed, file-based
- **Python worker** — polls `import_jobs` / `export_jobs` tables, processes async
- **No queue dependency** — worker replaces Laravel queue for import/export

## Quick Start

### 1. Clone & Install
```bash
git clone git@github.com:andrizpray/roll_lot_viewer.git
cd roll_lot_viewer
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Database Setup (SQLite)
```bash
# Create SQLite database
touch database/database.sqlite

# Update .env
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/sd-projects/roll_lot_viewer/database/database.sqlite

# Migrate
php artisan migrate
```

### 3. Build Frontend
```bash
npm install && npm run build
```

### 4. Import Data
```bash
# Via Web UI
php artisan serve
# Buka http://localhost:8000 → Upload page → drag & drop file

# Tipe (Roll / Sheet) terdeteksi otomatis dari header kolom
```

### 5. Start Services
```bash
# Laravel
php artisan serve --host=0.0.0.0 --port=8000

# Python Worker (systemd)
sudo systemctl start roll-lot-worker
```

## Fitur

### Import
- Upload Excel via Web UI (drag & drop)
- **Auto-detect tipe file** — deteksi dari header kolom (Roll atau Sheet)
- **Mutasi Roll** — Parsing kolom Description → Papertype, Gramature, Playbond, Width
- **Mutasi Stock Sheet** — Parsing Description → Gramature & Dimension
- Snapshot history setiap kali re-import roll (data lama aman di `roll_lot_histories`)
- Sheet data bersifat **append** (tidak replace)
- Error logging per baris yang gagal diparse

### Tampilan Data

**Data Roll** (`/`)
- Tabel: LotID, ItemID, Weight, RewID, Papertype, Gramature, Width, Grade, Diameter
- **Mode Batch:** Paste banyak LotID sekaligus (comma, newline, semicolon)
- **Mode Advanced:** Filter per ItemID, Grade (multi-select), Papertype, Gramature, Width, Date range, LotID

**Data Sheet** (`/sheets`)
- Tabel: LotID, ItemID, Weight, Papertype, Gramature, Dimension, Content Pack, Content Pallet
- Mode Batch & Advanced filter

### Export
- Download hasil filter sebagai **XLSX** (Microsoft Excel)
- **Roll export:** LotID, ItemID, Weight, PaperType, Gramature, Plybond, Width, RewID, Grade, Comment, Diameter
- **Sheet export:** LotID, ItemID, Weight, PaperType, Gramature, Dimension, Content Pack, Content Pallet
- Export async via Python worker

### UX
- Loading skeleton shimmer
- Empty state dengan CTA ke halaman upload
- Modal detail per row (klik ikon mata 👁)
- Multi-select grade filter dengan tags
- Notifikasi LotID tidak ditemukan

## API Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/imports` | Upload Excel file (auto-detect type) |
| GET | `/api/imports` | List import history |
| GET | `/api/imports/{id}` | Import batch detail + errors |
| GET | `/api/roll-lots?mode=batch&lot_ids=...` | Batch search (Roll) |
| GET | `/api/roll-lots?mode=advanced&grade=1,2` | Advanced filter multi-grade (Roll) |
| GET | `/api/roll-lots/distinct-values` | Filter dropdown values |
| GET | `/api/roll-lots/{id}` | Single roll lot detail |
| GET | `/api/sheets?mode=batch&lot_ids=...` | Batch search (Sheet) |
| GET | `/api/sheets?mode=advanced&...` | Advanced filter (Sheet) |
| GET | `/api/sheets/distinct-values` | Filter dropdown values |
| GET | `/api/sheets/{id}` | Single sheet detail |
| GET | `/api/export?resource=roll` | Create export job (Roll) |
| GET | `/api/export?resource=sheet` | Create export job (Sheet) |
| GET | `/api/export/{id}/status` | Poll export status |
| GET | `/api/export/{id}/download` | Download exported XLSX |
| GET | `/api/dashboard` | Dashboard summary stats |

## Struktur Database

| Tabel | Fungsi |
|-------|--------|
| `roll_lots` | Data mutasi roll aktif |
| `roll_lot_histories` | Snapshot roll sebelum replace |
| `paper_sheets` | Data mutasi stock sheet |
| `import_batches` | Log setiap import (status, counters, type) |
| `import_jobs` | Async import jobs (processed by Python worker) |
| `export_jobs` | Async export jobs (processed by Python worker) |
| `import_errors` | Baris gagal import beserta reason |

## Deployment

### Docker Stack
- **lemp-nginx** — Nginx Alpine
- **lemp-php** — PHP-FPM with OPcache
- **lemp-9router** — AI proxy

### Systemd Services
- `roll-lot-worker.service` — Python background worker

### Setup
```bash
# Clone repo
git clone git@github.com:andrizpray/roll_lot_viewer.git

# Install PHP deps
cd roll_lot_viewer && composer install --no-dev

# Build frontend
npm install && npm run build

# Setup SQLite
touch database/database.sqlite
php artisan migrate

# Start worker
sudo systemctl enable --now roll-lot-worker
```

### Post-Deploy (Auto)
```bash
# OPcache + Laravel cache + benchmark
/mnt/sdcard-data/dev-web/setup.sh
```

## Python Worker

Background worker yang menggantikan Laravel queue:

```bash
# Location
/root/projects/roll_lot_viewer/python/main.py

# Service
sudo systemctl status roll-lot-worker

# Logs
journalctl -u roll-lot-worker -f
```

Worker poll setiap 5 detik:
- `import_jobs` (status=pending) → proses Excel import
- `export_jobs` (status=pending) → generate XLSX export

## Performance

Dengan optimasi:
- **PHP OPcache** (128MB, JIT 64MB)
- **Laravel cache** (config, route, view, event)
- **SQLite WAL mode** + busy_timeout
- **Nginx gzip** + static asset cache (30d)

Response time: **~90ms** per endpoint (sebelumnya ~800ms)
