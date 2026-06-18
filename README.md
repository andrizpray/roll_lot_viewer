# Roll Lot Viewer

Aplikasi internal untuk mengimpor data dari Excel `PeriodBalanceRoll.xlsx`, menampilkan dan memfilter data roll/lot dalam tabel modern.

## Tech Stack
- **Backend:** Laravel 13 (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API) + PrimeVue
- **Database:** MySQL 8.0 / MariaDB / SQLite
- **Queue:** Database (Laravel default)

## Quick Start

### 1. Clone & Install
```bash
git clone <repo-url>
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
  CREATE USER 'roll_lot'@'localhost' IDENTIFIED BY 'roll_lot_secret_2026';
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
php artisan import:excel /path/to/PeriodBalanceRoll.xlsx --sync

# Via Web UI
php artisan serve
# Buka http://localhost:8000 → Upload page
```

### 5. Start Server
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## Fitur

### Import
- Upload Excel via Web UI (drag & drop) atau CLI
- Parsing otomatis kolom Description → Papertype, Gramature, Playbond, Width
- Snapshot history setiap kali re-import (data lama aman)
- Error logging per baris

### Filter
- **Mode Batch:** Paste banyak LotID sekaligus (comma/newline/semicolon)
- **Mode Advanced:** Filter ItemID, Papertype, Grade, Gramature, Width, Date range

### Export
- CSV export sesuai filter aktif
- Via Web UI atau API endpoint

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
| GET | `/api/export?mode=batch&lot_ids=...` | CSV export |

## Artisan Commands

```bash
php artisan import:excel /path/to/file.xlsx --sync   # Import file
php artisan import:status                             # Lihat history
php artisan import:status 31                          # Detail batch + errors
```

## Struktur Database

| Tabel | Fungsi |
|-------|--------|
| `roll_lots` | Data aktif (ditampilkan) |
| `roll_lot_histories` | Snapshot sebelum replace (+ archived_at) |
| `import_batches` | Log setiap import (status, counters) |
| `import_errors` | Baris gagal import beserta reason |

## Deployment

### Requirements
- PHP 8.3+
- MySQL 8.0 / MariaDB 10.5+
- Node.js 18+ (untuk build asset)
- Composer 2.x

### Steps
1. Clone repo ke server
2. `composer install --no-dev --optimize-autoloader`
3. Setup MySQL database & user
4. Copy `.env.example` → `.env`, isi konfigurasi
5. `php artisan key:generate`
6. `php artisan migrate --force`
7. `npm install && npm run build`
8. Set document root ke `public/`
9. Setup Nginx/Apache rewrite ke `index.php`
10. Setup cron untuk queue worker (jika pakai async import)

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

### Queue Worker
```bash
# Jika pakai async import (tanpa --sync)
php artisan queue:work --daemon
```
