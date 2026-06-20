# Roll Lot Viewer

Aplikasi internal untuk mengimpor, menampilkan, dan memfilter data mutasi kertas produksi.

**Dua tipe data:**
- **Mutasi Roll (PM1/PM2):** Data roll/lot harian dari `PeriodBalanceRoll.xlsx`
- **Mutasi Stock Sheet:** Data stock sheet harian (format kolom berbeda, auto-detect)

> **Live:** https://roll-lot.driz.web.id/

## Tech Stack
- **Backend:** Laravel 13 (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API) + PrimeVue + Vite
- **Database:** MySQL 8.0 / SQLite (dev)
- **Queue:** Database (Laravel default)

## Quick Start

### 1. Clone & Install
```bash
git clone git@github.com:andrizpray/roll_lot_viewer.git
cd roll-lot-viewer
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Database Setup
```bash
# MySQL (production)
mysql -u root -p -e "
  CREATE DATABASE roll_lot_viewer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'roll_lot'@'localhost' IDENTIFIED BY 'roll_lot';
  GRANT ALL PRIVILEGES ON roll_lot_viewer.* TO 'roll_lot'@'localhost';
  FLUSH PRIVILEGES;
"

# Atau SQLite (development)
# Biarkan DB_CONNECTION=sqlite di .env
```

### 3. Migrate & Build
```bash
php artisan migrate
npm install && npm run build
```

### 4. Import Data
```bash
# Via CLI (Roll)
php artisan import:excel /path/to/PeriodBalanceRoll.xlsx

# Via Web UI
php artisan serve
# Buka http://localhost:8000 → Upload page → drag & drop file
# Tipe (Roll / Sheet) terdeteksi otomatis dari header kolom
```

### 5. Start Server
```bash
php artisan serve --host=0.0.0.0 --port=8000
# Buka http://localhost:8000
```

## Fitur

### Import
- Upload Excel via Web UI (drag & drop) atau CLI (`import:excel`)
- **Auto-detect tipe file** — deteksi dari header kolom (Roll atau Sheet), user tidak perlu pilih manual
- **Mutasi Roll** — Parsing kolom Description → Papertype, Gramature, Playbond, Width
- **Mutasi Stock Sheet** — Parsing Description → Gramature & Dimension (Content Pack & Content Pallet)
- Snapshot history setiap kali re-import roll (data lama aman di `roll_lot_histories`)
- Sheet data bersifat **append** (tidak replace), sesuai laporan harian
- Error logging per baris yang gagal diparse

### Tampilan Data

**Data Roll** (`/`)
- Tabel: LotID, ItemID, Weight, RewID, Papertype, Gramature, Width, Grade, Diameter
- Mode Batch: Paste banyak LotID sekaligus (comma, newline, semicolon)
- Mode Advanced: Filter per ItemID, Grade, Papertype, Gramature, Width, Date range, LotID

**Data Sheet** (`/sheets`)
- Tabel: LotID, ItemID, Weight, Papertype, Gramature, Dimension, Content Pack, Content Pallet
- Mode Batch: Paste banyak LotID
- Mode Advanced: Filter per ItemID, Papertype, Gramature, Dimension, Date range, LotID

### Export
- Download hasil filter sebagai **XLSX** (Microsoft Excel)
- **Roll export:** LotID, ItemID, Weight, PaperType, Gramature, Plybond, Width, RewID, Grade, Comment, Diameter
- **Sheet export:** LotID, ItemID, Weight, PaperType, Gramature, Dimension, Content Pack, Content Pallet
- Export mengikuti filter saat ini (batch atau advanced)
- Via Web UI atau API endpoint (`?resource=roll|sheet`)

### UX
- Loading skeleton shimmer (8 baris)
- Empty state dengan CTA ke halaman upload
- Modal detail per row (klik ikon mata)
- Notifikasi LotID tidak ditemukan
- Badge tipe (Roll biru / Sheet ungu) di history import
- Font Fira Sans, primary color `#1E40AF`

## API Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/imports` | Upload Excel file (auto-detect type) |
| GET | `/api/imports` | List import history (paginated) |
| GET | `/api/imports/{id}` | Import batch detail + errors |
| GET | `/api/imports/{id}/status` | Polling status |
| GET | `/api/roll-lots?mode=batch&lot_ids=...` | Batch search LotID (Roll) |
| GET | `/api/roll-lots?mode=advanced&papertype=...` | Advanced filter (Roll) |
| GET | `/api/roll-lots/{id}` | Single roll lot detail |
| GET | `/api/sheets?mode=batch&lot_ids=...` | Batch search LotID (Sheet) |
| GET | `/api/sheets?mode=advanced&dimension=...` | Advanced filter (Sheet) |
| GET | `/api/sheets/{id}` | Single sheet detail |
| GET | `/api/export?resource=roll&mode=...` | XLSX export (roll) |
| GET | `/api/export?resource=sheet&mode=...` | XLSX export (sheet) |

## Artisan Commands

```bash
php artisan import:excel /path/to/file.xlsx   # Import file (async via queue, auto-detect type)
php artisan import:status                      # Lihat history import
php artisan import:status 31                   # Detail batch + errors
```

## Struktur Database

| Tabel | Fungsi |
|-------|--------|
| `roll_lots` | Data mutasi roll aktif (ditampilkan, replace tiap import) |
| `roll_lot_histories` | Snapshot roll sebelum replace (+ `archived_at`) |
| `paper_sheets` | Data mutasi stock sheet (append tiap import) |
| `import_batches` | Log setiap import (status, counters, type) |
| `import_errors` | Baris gagal import beserta reason |

## Deployment

### Requirements
- PHP 8.3+
- MySQL 8.0 / MariaDB 10.5+
- Node.js 18+ (untuk build asset)
- Composer 2.x
- Nginx + PHP-FPM (production)

### Steps
1. Clone repo ke server
2. `composer install --no-dev --optimize-autoloader`
3. Setup MySQL database & user
4. Copy `.env.example` → `.env`, isi konfigurasi
5. `php artisan key:generate`
6. `php artisan migrate --force`
7. `npm install && npm run build`
8. Set document root ke `public/`
9. Setup Nginx rewrite ke `index.php`
10. Jalankan queue worker untuk async import

### Nginx Config
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/roll-lot-viewer/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### SSL (Let's Encrypt)
```bash
sudo certbot --nginx -d your-domain.com
```

### Queue Worker
```bash
php artisan queue:work --daemon
```

## Testing
```bash
php artisan test
# 23 tests, 88 assertions — unit (DescriptionParser) + integration (API)
```
