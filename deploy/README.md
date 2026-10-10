# BKABD Exam System — Production Deployment (4 cores / 8 GB RAM / 140 GB SSD)

Plain PHP 8 OOP app, **no web-framework vhost refactor needed** — the web root is
`public/` and URL paths are root-relative, so it deploys at the domain root.

> The built-in dev server (`php -S`) is **single-threaded** and is never used in
> production. Result-day traffic (~10k concurrent users) is served by
> **nginx + PHP-FPM**, with OPcache and a warmed MySQL buffer pool.

---

## 1. Stack (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-cli \
    php8.2-mysql php8.2-gd php8.2-zip php8.2-xml php8.2-mbstring \
    php8.2-curl unzip composer certbot python3-certbot-nginx
```

Required PHP extensions come from `composer.json`: `ext-gd`, `ext-pdo_mysql`
(plus phpspreadsheet's zip/xml/mbstring).

## 2. Pull from GitHub

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/webspaceit/exam.bkabd.com.git oop-exam
cd oop-exam
sudo chown -R www-data:www-data .
sudo -u www-data git checkout v1.0-pre-result
```

Only code ships (the repo is gitignored) — no `.env`, `vendor/`, or probe scripts.

Applying later updates, or rolling back:

```bash
cd /var/www/oop-exam
sudo -u www-data git pull                    # or: git checkout <old-tag> to roll back
sudo -u www-data composer install --no-dev --classmap-authoritative
sudo systemctl restart php8.2-fpm            # new code is live (opcache revalidates in ≤60s anyway)
```

## 3. Composer (dompdf + phpspreadsheet live in vendor/)

```bash
cd /var/www/oop-exam
sudo -u www-data composer install --no-dev --classmap-authoritative
```

## 4. Environment — edit `.env`

```ini
APP_NAME=BKABD
APP_ENV=production
APP_DEBUG=false          # IMPORTANT: hides errors, turns on the prod handler
APP_URL=https://example.com
APP_TIMEZONE=Asia/Dhaka

DB_HOST=localhost        # or 127.0.0.1
DB_PORT=3306
DB_DATABASE=oop_exam
DB_USERNAME=oop_exam
DB_PASSWORD=<strong password>
DB_CHARSET=utf8mb4

SESSION_LIFETIME=120
CACHE_DRIVER=file        # keep 'file' — it is shared across all FPM workers;
                         # 'apcu' is per-worker and would re-warm per process.
CACHE_TTL=60

SMS_API_KEY=<real key>   # when going live
SMS_DRY_RUN=0
```

## 5. Database

```sql
CREATE DATABASE oop_exam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'oop_exam'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON oop_exam.* TO 'oop_exam'@'localhost';
FLUSH PRIVILEGES;
```

```bash
mysql -u oop_exam -p oop_exam < database/schema.sql
mysql -u oop_exam -p oop_exam < database/seed_data.sql   # + demo_seed.sql if you want samples
# Check the indexes exist (result-day hot path):
mysql -u oop_exam -p oop_exam -e "SHOW INDEX FROM students"
#  -> registration UNIQUE, unique_roll_session_class, idx_session_center_class
```

## 6. Permissions

```bash
cd /var/www/oop-exam
sudo chown -R www-data:www-data storage public/uploads
sudo chmod -R 775 storage public/uploads
sudo mkdir -p /var/lib/php/sessions && sudo chown www-data:www-data /var/lib/php/sessions
```

## 7. Configs (already in this repo under `deploy/`)

| File | Destination |
|---|---|
| `deploy/nginx/oop-exam.conf` | `/etc/nginx/sites-available/oop-exam` → symlink into `sites-enabled` |
| `deploy/php-fpm/oop-exam.conf` | `/etc/php/8.2/fpm/pool.d/oop-exam.conf` |
| `deploy/php/opcache-php.ini` | `/etc/php/8.2/fpm/conf.d/99-oop-exam.ini` |
| `deploy/mysql/oop-exam.cnf` | `/etc/mysql/mysql.conf.d/oop-exam.cnf` |

Then:

```bash
sudo nginx -t
sudo systemctl restart nginx php8.2-fpm mysql
```

## 8. Smoke test + HTTPS

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/            # 302 -> /login
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/scholarship-result   # 200
sudo certbot --nginx -d example.com        # wire up HTTPS once DNS points here
```

On the dev machine, the regression suites still work after the URL base change;
`tools/bench_result.php` accepts the base URL as argv:

```bash
php tools/bench_result.php http://your-vps-ip
```

(`tools/check_sidebar.php` / `tools/test_reports.php` are dev-local suites — to
run them against the VPS, point their base constant at it first.)

## 9. Load test (before result day!)

```bash
# The form page is the highest-traffic page (every visitor loads it).
# GET /scholarship-result, 500 concurrent, 50k requests:
hey -n 50000 -c 500 http://your-vps-ip/scholarship-result
# Target: 300+ rps sustained, p95 < 200 ms. (10k users/1 min ≈ 167 rps avg.)
```

The actual **`POST /result/search`** is captcha-gated, so load tools can't hammer
it in bulk — that's the design. Its per-search cost is measured by
`tools/bench_result.php` (DB lookup ~0.2 ms, cold path ~1.6 ms, full request
~35 ms on the dev server).

## 10. Result-day checklist

- [ ] `APP_DEBUG=false`, nginx/php-fpm/mysql restarted after configs
- [ ] `storage/cache` warm: `curl -s http://your-vps-ip/scholarship-result > /dev/null` once
- [ ] Slow query log on (`deploy/mysql/oop-exam.cnf`), watch `/var/log/mysql/oop-exam-slow.log`
- [ ] `watch -n 1 "free -m | head -2; ps -C php-fpm --no-headers | wc -l"` — free RAM + live FPM worker count
- [ ] Backups for the admin-side writes (result day is reads, but mark entries keep coming)

## Capacity notes (4C / 8 GB / 140 GB)

- **PHP-FPM:** 50 workers (config) ≈ 2 GB peak — enough for ~160+ req/s sustained,
  comfortably above the ~167 rps average that 10k users/minute implies.
- **MySQL:** 2 GB buffer pool — the whole dataset (~116k students) fits in RAM,
  so lookups stay at the measured ~0.2 ms even during a spike.
- **Bottleneck to watch:** PHP process throughput, not the DB. If `pm.max_children`
  saturates (FPM "max_children reached" in logs), raise it to 70 — RAM permits —
  and check for queue backup with `hey` again.