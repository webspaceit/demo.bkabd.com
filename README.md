# BKABD Exam System — Plain PHP Clone

Scholarship exam management system: a **plain PHP 8 OOP + fetch(Ajax) + Tailwind CSS**
clone of the original Laravel 10 / Vue 2 SPA (`E:\wamp64\www\exam`), keeping the **same
MySQL schema, features, and ~100 `/api/*` URL contracts**.

Built for **shared hosting with no SSH or Composer on the host**. CSS is prebuilt. The
`vendor/` directory is intentionally not tracked in Git; prepare it locally with Composer
and upload it along with the application files (see [DEPLOY.md](DEPLOY.md)).

## Features

- Auth + session management (admin override via `admin_session`), Spatie-style RBAC (90 permissions, 15 roles)
- Center-user scoping (type-4 users see only their own center)
- Geo tree: Divisions → Districts → Upazilas → Unions (region-gated by `manage-area`)
- Centers, Institutes, Students (registration-number algorithm, duplicate checks)
- Exam sessions, mark distribution, mark entry, grade assign
- Admit cards, seat plan, student/institute certificates
- Reports hub (student list, center/institute details, class-wise attend sheets, …) — 26 report
  endpoints; sheets render as printable HTML (Print / Save as PDF) and Excel exports as .xlsx
  (phpspreadsheet)
- Notices, routines, instructions, settings, role-place
- SMS dashboard (mram gateway; key from `.env`, dry-run mode, mask/numeric sender quirk preserved)
- **Public membership self-registration** (`/membership-institute-registration` →
  `POST /api/ins-store`, geo cascade, logo/principal upload, duplicate guard) and the
  printable membership card (`GET /api/institute-registration/{id}`)
- **Public result portal** (`/scholarship-result`) hardened for result-day traffic:
  server-session captcha, per-IP throttle (429), `session_write_close()` on public pages,
  cached dropdowns, `result=1 AND result_time <= NOW()` gate (original only checked the time)

## Architecture

```
public/            front controller (.htaccess → index.php), static assets
bootstrap/app.php   autoload (.env via plain parser), error handling, timezone
app/Core/           framework: Router, Request, Response, Database(PDO),
                    Query (Laravel-shape paginate), Model, Validator, Auth,
                    Session (close() for high traffic), Cache (file/APCu),
                    View, SmsService, HttpException
app/Controllers/    HTTP controllers
app/Services/       business logic (students, reports, result portal, captcha, institutes…)
resources/views/    PHP templates + layouts.dashboard (permission-filtered sidebar)
routes/             ONE file per module, loaded sorted by routes.php
database/           schema.sql, seed_data.sql, demo_seed.sql, upgrade_notes.sql, import.bat
storage/            cache/, logs/, uploads/  (must be writable)
public/assets/css/app.css   prebuilt Tailwind (see rebuild command below)
public/assets/js/app.js     BK helper library (fetch, CSRF, toast, errors, lists)
tools/              smoke_test.php + per-module test scripts
CONVENTIONS.md      framework API + response-shape contract — read before editing
```

## Response contracts (must not change)

- Laravel paginator shape: `{current_page, data, last_page, total, …}`
- Original JSON keys preserved: `studentList`, `roleList`, `userList`, `noticeList`,
  `sessionList`, `status: 'success'|'errors'`, `{success, data, message}`, …
- Unauthenticated API → 401; forbidden → 403; validation → 422 `{message, errors}`
  (redirect-back with errors for plain form posts); missing CSRF → 419

## Local development

```bat
REM dev server (port 8090)
E:\wamp64\bin\php\php8.2.29\php.exe -S 127.0.0.1:8090 -t public public/index.php

REM full spine test (12 assertions)
E:\wamp64\bin\php\php8.2.29\php.exe tools\smoke_test.php

REM per-module tests
E:\wamp64\bin\php\php8.2.29\php.exe tools\test_pages.php
E:\wamp64\bin\php\php8.2.29\php.exe tools\test_institutes.php
E:\wamp64\bin\php\php8.2.29\php.exe tools\test_centers.php
E:\wamp64\bin\php\php8.2.29\php.exe tools\test_contract.php

REM contract guards (run after touching routes/controllers)
E:\wamp64\bin\php\php8.2.29\php.exe tools\lint.php            REM php -l every file
E:\wamp64\bin\php\php8.2.29\php.exe tools\check_routes.php    REM duplicate route registrations
E:\wamp64\bin\php\php8.2.29\php.exe tools\check_coverage.php  REM original /api/* path coverage

REM import/reset DB
database\import.bat

REM rebuild Tailwind AFTER adding new view classes
npx tailwindcss@3.4.17 -c tailwind.config.js -i resources/css/app.css -o public/assets/css/app.css --minify
```

- DB: `oop_exam` on localhost, root, empty password (config in `.env`).
  It holds the original sample data imported from `testdform`
  (115,765 students, 2,940 institutes, 226 centers, 229 users).
- Logins (no password ships in the repo — it is generated locally):
  - `mehedi@gmail.com` — role "Admin Pro", full access
  - `admin@gmail.com` — role "Admin"
  Run `php tools/set_sample_login.php` to set a strong password on these
  accounts; it writes it to `.env` (`TEST_ADMIN_PASSWORD`) for `tools/test_*.php`.
  Rotate any account any time with `php tools/set_password.php <email>`.
- A fresh install instead of the sample data uses
  `database/schema.sql` → `seed_data.sql` → `demo_seed.sql`. The demo seed ships
  **no usable password** — set one with `php tools/set_password.php admin@bkabd.com`.
  After importing student data, run `database/migrations/2026_10_23_student_duplicate_guard.sql`.
  Then run `database/migrations/2026_10_23_student_duplicate_guard_mother_name.sql` to include mother's name in duplicate checks.

## Security fixes over the original

1. SMS API key lives only in `.env` (original hardcoded it in helpers).
2. State-changing admin endpoints require auth (original left `changestatus` and
   `uploadFile` unauthenticated — both are now protected). The only public
   state-changing route is the membership self-registration `POST /api/ins-store`,
   which is deliberately public (it is the public form) and is CSRF-checked and
   duplicate-guarded.
3. Destructive actions use DELETE/POST — no GET deletes.
4. CSRF enforced on every POST/PUT/PATCH/DELETE (419 without token).
5. Prepared statements everywhere; RBAC query uses bound params (literal-string bug fixed).
6. No `dd()` / dead code; errors logged to `storage/logs/error.log`, generic message to users.
7. Result portal captcha is server-session-backed (original's hidden field was forgeable).
8. Session-cookie collisions are handled. Both apps use `APP_NAME=BKABD`, so both
   set a `bkabd_session` cookie; cookies are keyed by host (not port), so the
   original's encrypted value can reach this app. `Session::start()` now discards
   a foreign/malformed id (and enables `session.use_strict_mode`) instead of
   letting `session_start()` emit a warning and fail.
9. No default credentials. Fresh-install seeds ship **no usable password**; rotate
   any account with `php tools/set_password.php <email>`, and known default
   passwords are refused outside local dev (`BLOCK_DEFAULT_PASSWORDS`).
10. No JWT and no client-side tokens. Auth is a server-side session cookie
    (`bkabd_session`); the `token` in the login response is a random value kept
    in the session and is never written to `localStorage`/`sessionStorage`.
11. API secrets stay server-side. The SMS key is read from `.env` by `SmsService`
    and never sent to the browser; a placeholder/empty key refuses to send.

## Deployment

See **[DEPLOY.md](DEPLOY.md)** — FTP upload + phpMyAdmin import + `.env` edit.
No Composer, Node, or SSH needed on the host.
