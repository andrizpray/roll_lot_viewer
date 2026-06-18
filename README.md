# Roll Lot Viewer

Aplikasi internal untuk mengimpor data dari Excel `PeriodBalanceRoll.xlsx`, menampilkan dan memfilter data roll/lot dalam tabel modern.

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
# Via CLI
php artisan import:excel /path/to/PeriodBalanceRoll.xlsx

# Via Web UI
php artisan serve
# Buka http://localhost:8000 → Upload page
```

### 5. Start Server
```bash
php artisan serve --host=0.0.0.0 --port=8000
# Buka http://localhost:8000
```

## Fitur

### Import
- Upload Excel via Web UI (drag & drop) atau CLI (`import:excel`)
- Parsing otomatis kolom Description → Papertype, Gramature, Playbond, Width
- Snapshot history setiap kali re-import (data lama aman di `roll_lot_histories`)
- Error logging per baris yang gagal diparse

### Filter
- **Mode Batch:** Paste banyak LotID sekaligus (comma, newline, semicolon, tab, spasi)
- **Mode Advanced:** Filter per ItemID, Papertype, Grade, Gramature, Width, Date range
- Kedua mode saling eksklusif — hanya satu aktif

### Export
- Download hasil filter sebagai **XLSX** (Microsoft Excel)
- Kolom: LotID | ItemID | Weight | PaperType | Gramature | Plybond | Width | RewID | Grade | Comment | Diameter
- Tombol download muncul di area hasil (batch & advanced mode)
- Via Web UI atau API endpoint

### UX
- Loading skeleton shimmer (8 baris)
- Empty state dengan CTA ke halaman upload
- Modal detail row (klik ikon mata)
- Notifikasi LotID tidak ditemukan
- Badge warna untuk Grade (1=hijau, 2=kuning, 3=merah, WIPB=ungu)
- Font Fira Sans + Fira Code, primary color `#1E40AF`

## API Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| POST | `/api/imports` | Upload Excel file |
| GET | `/api/imports` | List import history (paginated) |
| GET | `/api/imports/{id}` | Import batch detail + errors |
| GET | `/api/imports/{id}/status` | Polling status (untuk progress) |
| GET | `/api/roll-lots?mode=batch&lot_ids=...` | Batch search LotID |
| GET | `/api/roll-lots?mode=advanced&papertype=...` | Advanced filter |
| GET | `/api/roll-lots/{id}` | Single roll lot detail |
| GET | `/api/export?mode=batch&lot_ids=...` | XLSX export |

## Artisan Commands

```bash
php artisan import:excel /path/to/file.xlsx   # Import file (async via queue)
php artisan import:status                      # Lihat history import
php artisan import:status 31                   # Detail batch + errors
```

## Struktur Database

| Tabel | Fungsi |
|-------|--------|
| `roll_lots` | Data aktif (ditampilkan) |
| `roll_lot_histories` | Snapshot sebelum replace (+ `archived_at`) |
| `import_batches` | Log setiap import (status, counters) |
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
# Jika pakai async import (tanpa --sync)
php artisan queue:work --daemon
```

## Testing
```bash
php artisan test
# 23 tests, 88 assertions — unit (DescriptionParser) + integration (API)
```
