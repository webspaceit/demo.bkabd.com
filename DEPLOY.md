# Deploying to Shared Hosting (FTP only — no SSH, no Composer, no Node)

The app is a plain PHP 8 OOP front controller. Tailwind CSS is prebuilt at
`public/assets/css/app.css`, and all URLs are rewritten by `public/.htaccess`.
The host needs no Composer or Node, but the upload must include a locally prepared
`vendor/` directory because dependencies are not stored in Git.

## Folder layout on the host

```
/home/USER/
├── oop-exam/            ← everything EXCEPT the contents of public/  (outside web root)
│   ├── .env             ← DB + SMS config (never web-accessible)
│   ├── app/  bootstrap/  routes/  resources/  vendor/  database/  storage/
└── public_html/         ← web root (cPanel default)
    ├── index.php        ← from oop-exam/public/ (one path line edited, see step 4)
    ├── .htaccess        ← from oop-exam/public/
    └── assets/          ← from oop-exam/public/assets/ (css, js, img)
```

Keeping app code outside `public_html` means visitors can never download source,
`.env` secrets, or `database/*.sql` dumps.

## Steps

### 0. Prepare the upload from a trusted local copy
- In the project folder, run `composer install --no-dev --optimize-autoloader` on your computer. The `vendor/` directory is ignored by Git and is **not** in GitHub ZIPs, but the application needs `vendor/autoload.php`.
- Confirm `vendor/autoload.php` exists before uploading. The current local checkout has it.
- Never upload your local `.env`, `.git/`, or `tmp/`. In particular, the local `.env` may contain database and exam-sync credentials. Create a new `.env` on the hosting account using `.env.example` as a template.
- Keep the app files outside the public web root. Only the contents of `public/` belong in `public_html/`.

### 1. Create the database (cPanel → MySQL Databases)
- Create DB and DB user, add user to DB with **ALL PRIVILEGES**.
- cPanel prefixes names (e.g. `youruser_oop_exam`) — note the exact names.

### 2. Import SQL (phpMyAdmin → Import, run **in order**)
1. `database/schema.sql` — tables + result-day indexes
2. `database/seed_data.sql` — permissions, roles, geo, settings, sessions
3. `database/demo_seed.sql` — *(optional)* demo users/admin + sample students.
   Skip for a clean production install, then create your own admin.
4. `database/migrations/2026_10_23_student_duplicate_guard.sql` — required
   duplicate-registration guard; run this after the student data is imported.
5. `database/migrations/2026_10_23_student_duplicate_guard_mother_name.sql` —
   refreshes the guard to include mother's name; also run this after student data is imported.

### 3. Upload files (FTP)
- Show hidden files in your FTP client so you can upload `public/.htaccess`.
- Upload the project files **except** `.env`, `.git/`, `tmp/`, and the contents of `public/` to `/oop-exam/`. Include `vendor/` and `storage/`.
- Upload the **contents** of `oop-exam/public/` into `public_html/`
  (so you get `public_html/index.php`, `public_html/.htaccess`, `public_html/assets/…`).
- Do not upload the local `public/uploads/` contents; these are local development files. Keep the uploaded `public/uploads/.htaccess` hardening file.

### 4. Point `public_html/index.php` at the app folder
Open `public_html/index.php`, change the bootstrap require:

```php
// from
require __DIR__ . '/../bootstrap/app.php';
// to (adjust folder name if you chose another)
require __DIR__ . '/../oop-exam/bootstrap/app.php';
```

### 5. Create and configure `/oop-exam/.env` on the host
Create this file on the server from `.env.example`; **do not copy the local `.env`**.
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Dhaka
DB_HOST=localhost
DB_DATABASE=youruser_oop_exam
DB_USERNAME=youruser_dbuser
DB_PASSWORD=********
SESSION_LIFETIME=1440
SESSION_SECURE=true
SMS_API_KEY=real_key_here
SMS_DRY_RUN=0        # keep 1 while testing — logs SMS instead of sending
```
Keep `DB_SYNC_ENABLED=false` unless you deliberately configure and verify separate source/target credentials on the host. Keep `APP_DEBUG=false` (errors go to `storage/logs/error.log`, never to the browser).

### 6. Permissions
`oop-exam/storage/` and subfolders (`cache/`, `logs/`, `uploads/`), plus `public_html/uploads/`, must be writable:
chmod **755** (775 if the host requires it). The app writes logs and cache there.

### 7. Verify
- Visit `https://yourdomain.com/login` — login page renders.
- Log in (`demo_seed.sql` ships no default password — run
  `php tools/set_password.php admin@bkabd.com` first), click through the sidebar.
- A broken page? Check `oop-exam/storage/logs/error.log`.

## Host requirements
- PHP **8.1+** with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`; enable `curl` as well for Database Sync photo downloads
- MySQL 5.7+ / 8.x
- Apache with `mod_rewrite` + `AllowOverride All` for the site (standard on cPanel hosting)

## Updating the app later
Re-upload changed files over FTP (overwrite), then delete everything inside
`oop-exam/storage/cache/` if pages look stale.

## After schema changes
`database/upgrade_notes.sql` contains new/changed indexes — run it (or re-import) on
hosts that already have an older copy of the DB.
