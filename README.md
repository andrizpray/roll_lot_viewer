# Roll Lot Viewer

Aplikasi internal untuk mengimpor, menampilkan, dan memfilter data mutasi kertas produksi.

**Dua tipe data:**
- **Mutasi Roll (PM1/PM2):** Data roll/lot harian dari `PeriodBalanceRoll.xlsx`
- **Mutasi Stock Sheet:** Data stock sheet harian (format kolom berbeda, auto-detect)

## Tech Stack
- **Backend:** Laravel 13 (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API) + Vite + PrimeVue
- **Database:** SQLite (WAL mode)
- **Worker:** Python 3 + openpyxl (background import/export via systemd)
- **Server:** Nginx reverse proxy + artisan serve (port 8080)
- **Tunnel:** Cloudflare Tunnel (public access)

## Architecture

```
Browser (Vue SPA)
   │  HTTP/JSON + X-API-Key header
   ▼
Laravel API ──► SQLite ◄── Python Worker (polling setiap 5 detik)
   │                         │
   ├─ Upload file            ├─ import_jobs → roll_lots/paper_sheets
   ├─ Create job record      ├─ export_jobs → file .xlsx
   ├─ Query & filter data    ├─ snapshot history + error logging
   └─ Health check (/health) └─ Heartbeat file monitoring
```

## Workflow

### Import Flow

1. User upload Excel via Web UI (drag & drop)
2. Laravel `POST /api/imports` → simpan file ke `storage/app/uploads/`, detect tipe, insert `import_jobs` (status=pending)
3. Python worker poll `import_jobs` → parse Excel → snapshot existing data ke `roll_lot_histories` → `INSERT OR REPLACE` ke target table
4. Error per baris dicatat ke `import_errors`
5. Frontend poll status sampai completed

### Export Flow

1. User klik "Download Data" dengan filter aktif
2. Laravel `GET /api/export` → insert `export_jobs` (status=pending)
3. Python worker poll → query data → generate XLSX → update status=completed
4. Frontend download file via `/api/export/{id}/download`

## Quick Start

### 1. Clone & Install
```bash
git clone git@github.com:andrizpray/roll_lot_viewer.git
cd roll_lot_viewer
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Konfigurasi `.env`
```bash
# Wajib di production:
API_KEY=your-random-secret-key-here

# Database sudah default SQLite (tidak perlu diubah)
```

### 3. Database Setup
```bash
touch database/database.sqlite
php artisan migrate
```

### 4. Build Frontend
```bash
npm install && npm run build
```

### 5. Start Services
```bash
# Laravel (development)
php artisan serve --host=0.0.0.0 --port=8000

# Python Worker
cd python && python3 main.py

# atau via systemd:
sudo systemctl start roll-lot-worker
```

## Autentikasi API

Semua endpoint API dilindungi oleh API key. Kirim key via:
- Header: `X-API-Key: ***`
- Query param: `?api_key=<key>`

Di environment `local`/`testing` tanpa `API_KEY` dikonfigurasi, auth dilewati otomatis.

## Fitur

### Import
- Upload Excel via Web UI (drag & drop)
- **Auto-detect tipe file** — deteksi dari header kolom (Roll atau Sheet)
- **Roll:** kolom Excel sudah terpisah (Paper Type, Gramature, Width, dll)
- **Sheet:** format kolom berbeda, auto-detect
- **Snapshot history** — setiap re-import roll, data lama di-copy ke `roll_lot_histories`
- **Error logging** — baris gagal dicatat ke `import_errors` dengan row number dan alasan

### Tampilan Data

**Data Roll** (`/`)
- Tabel: LotID, ItemID, Weight, RewID, Papertype, Gramature, Width, Grade, Diameter
- **Mode Batch:** Paste banyak LotID sekaligus (comma, newline, semicolon)
- **Mode Advanced:** Filter per ItemID, Grade (multi-select), Papertype, Gramature, Width, Date range

**Data Sheet** (`/sheets`)
- Tabel: LotID, ItemID, Weight, Papertype, Gramature, Dimension, Content Pack, Content Pallet
- Mode Batch & Advanced filter

**Dashboard** (`/dashboard`)
- Summary statistics: total lots, total weight, breakdown per grade
- Charts: weight distribution, daily trend
- Color-coded weight ranges

### Export
- Download hasil filter sebagai **XLSX**
- Support multi-grade filter (comma-separated)
- Export async via Python worker

### UX
- **Dark theme** dengan emerald accent
- **Responsive sidebar** navigation
- Loading skeleton shimmer
- Modal detail per row (ikon mata)
- Multi-select grade filter dengan tags
- Notifikasi LotID tidak ditemukan

## API Endpoints

Semua endpoint memerlukan `X-API-Key` header (kecuali di local env).

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/health` | Health check endpoint |
| GET | `/api/dashboard` | Dashboard summary |
| POST | `/api/imports` | Upload Excel file |
| GET | `/api/imports` | List import jobs |
| GET | `/api/imports/{id}` | Import job detail |
| GET | `/api/imports/{id}/status` | Poll import status |
| GET | `/api/roll-lots?mode=batch&lot_ids=...` | Batch search (Roll) |
| GET | `/api/roll-lots?mode=advanced&grade=1,2` | Advanced filter (Roll) |
| GET | `/api/roll-lots/distinct-values` | Filter dropdown values |
| GET | `/api/roll-lots/{id}` | Single roll lot detail |
| GET | `/api/sheets?mode=batch&lot_ids=...` | Batch search (Sheet) |
| GET | `/api/sheets?mode=advanced&...` | Advanced filter (Sheet) |
| GET | `/api/sheets/distinct-values` | Filter dropdown values |
| GET | `/api/sheets/{id}` | Single sheet detail |
| GET | `/api/export?resource=roll` | Create export job |
| GET | `/api/export/{id}/status` | Poll export status |
| GET | `/api/export/{id}/download` | Download XLSX |

## Database

| Tabel | Fungsi |
|-------|--------|
| `roll_lots` | Data mutasi roll aktif |
| `roll_lot_histories` | Snapshot roll sebelum re-import |
| `paper_sheets` | Data mutasi stock sheet |
| `import_jobs` | Async import jobs (diproses Python worker) |
| `import_errors` | Baris gagal import (per row) |
| `export_jobs` | Async export jobs (diproses Python worker) |

## Artisan Commands

```bash
# Cek status import job
php artisan import:status         # 10 terbaru
php artisan import:status {id}    # Detail + errors
```

## Python Worker

Background worker yang menggantikan Laravel queue:

```bash
# Start manual
cd python && python3 main.py

# Via systemd
sudo systemctl start roll-lot-worker
sudo systemctl status roll-lot-worker

# Logs
journalctl -u roll-lot-worker -f
```

Worker poll setiap 5 detik:
- `import_jobs` (status=pending) → parse Excel → snapshot → insert → log errors
- `export_jobs` (status=pending) → query → generate XLSX

Config: `python/config.py`

## UI Components

- **AppNavbar** — Top navigation bar dengan branding
- **AppSidebar** — Side navigation dengan menu items
- **DefaultLayout** — Layout wrapper dengan navbar + sidebar
- **DashboardPage** — Dashboard dengan charts dan statistics
- **HomePage** — Roll lots data table dengan filter
- **SheetPage** — Paper sheets data table dengan filter
- **UploadPage** — File upload dengan drag & drop

## Performance

- **SQLite WAL mode** + busy_timeout (5s)
- **PHP OPcache** + JIT
- **Laravel cache** (config, route, view)
- **Nginx gzip** + static asset cache
- **Batch import** dengan progress tracking

## Systemd Services

```bash
# Services yang berjalan
sudo systemctl status roll-lot-viewer   # Laravel (artisan serve)
sudo systemctl status roll-lot-worker   # Python worker
sudo systemctl status cloudflared       # Cloudflare tunnel
sudo systemctl status nginx             # Reverse proxy
```

## Live Demo

Aplikasi berjalan di: https://lot-viewer.driz.web.id

## License

Internal use only.
