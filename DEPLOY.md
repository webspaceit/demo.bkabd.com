# Deploying to Shared Hosting (FTP only — no SSH, no Composer, no Node)

The app is a plain PHP 8 OOP front controller. `vendor/` is committed, Tailwind CSS is
prebuilt at `public/assets/css/app.css`, and all URLs are rewritten by `public/.htaccess`.
Uploading files + importing the database is all it takes.

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
- Show hidden/dot files in your FTP client (`.env`, `.htaccess` are dotfiles!).
- Upload the whole project **except** the contents of `public/` to `/oop-exam/`.
- Upload the **contents** of `oop-exam/public/` into `public_html/`
  (so you get `public_html/index.php`, `public_html/.htaccess`, `public_html/assets/…`).

### 4. Point `public_html/index.php` at the app folder
Open `public_html/index.php`, change line 15:

```php
// from
require __DIR__ . '/../bootstrap/app.php';
// to (adjust folder name if you chose another)
require __DIR__ . '/../oop-exam/bootstrap/app.php';
```

### 5. Configure `/oop-exam/.env`
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
DB_HOST=localhost
DB_DATABASE=youruser_oop_exam
DB_USERNAME=youruser_dbuser
DB_PASSWORD=********
SMS_API_KEY=real_key_here
SMS_DRY_RUN=0        # keep 1 while testing — logs SMS instead of sending
```
Then set `APP_DEBUG=false` (errors go to `storage/logs/error.log`, never to the browser).

### 6. Permissions
`oop-exam/storage/` and subfolders (`cache/`, `logs/`, `uploads/`) must be writable:
chmod **755** (775 if the host requires it). The app writes logs and cache there.

### 7. Verify
- Visit `https://yourdomain.com/login` — login page renders.
- Log in (`demo_seed.sql` ships no default password — run
  `php tools/set_password.php admin@bkabd.com` first), click through the sidebar.
- A broken page? Check `oop-exam/storage/logs/error.log`.

## Host requirements
- PHP **8.1+** with `pdo_mysql`, `mbstring`, `curl`, `fileinfo`, `gd` (all standard/enabled by default)
- MySQL 5.7+ / 8.x
- Apache with `mod_rewrite` + `AllowOverride All` for the site (standard on cPanel hosting)

## Updating the app later
Re-upload changed files over FTP (overwrite), then delete everything inside
`oop-exam/storage/cache/` if pages look stale.

## After schema changes
`database/upgrade_notes.sql` contains new/changed indexes — run it (or re-import) on
hosts that already have an older copy of the DB.
