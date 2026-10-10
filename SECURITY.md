# Security notes

This document records how secrets, tokens and credentials are handled, and the
checks that back each claim.

## 1. Tokens — no JWT, no client-side storage

- Authentication is a **server-side PHP session** (`bkabd_session` cookie;
  HttpOnly, SameSite=Lax, `Secure` when `SESSION_SECURE=true`). There is **no
  JWT** anywhere in the codebase.
- The `token` field in the `POST /api/login` response is a random string kept in
  the server session (contract compatibility with the original Sanctum app). The
  front-end ignores it and relies on the session cookie.
- Nothing is written to `localStorage` / `sessionStorage` / `document.cookie`.
  Verify:

  ```bash
  rg -n "localStorage|sessionStorage|document\.cookie" public resources
  # (no matches)
  ```

## 2. API secrets stay server-side

- The SMS gateway key is read from `.env` by `App\Core\SmsService` and sent only
  from the server to the gateway. It is never included in any response, view or
  JavaScript asset.
- A placeholder/empty key (`SMS_API_KEY=CHANGE_ME`) **refuses to send**. Keep
  `SMS_DRY_RUN=1` until a real key is set.
- `.env` is gitignored; only `.env.example` (placeholders) is committed.

## 3. Credentials

- No default password ships in the repository. Fresh-install seeds
  (`database/demo_seed.sql`) contain no usable password.
- Rotate any account:

  ```bash
  php tools/set_password.php <email> [new-password] [--no-2fa]
  ```

  With no password it generates a strong random one and prints it once.
- The sample-data logins (`tools/set_sample_login.php`) generate a strong random
  password and store it in `.env` as `TEST_ADMIN_PASSWORD` (gitignored); the
  `tools/test_*.php` suite reads it via `tools/_test_credentials.php`.
- Known weak passwords (`admin123`, `center123`, `password`, …) are **refused at
  login** outside local dev. Disable with `BLOCK_DEFAULT_PASSWORDS=false` only if
  you really must. In production, deploy the update and rotate first:

  ```bash
  git pull
  php tools/set_password.php admin@your-domain
  ```

## 4. Other production settings

- `.env`: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE=true` (HTTPS).
- `storage/` (and `cache/`, `logs/`, `uploads/`) writable by PHP; the rate-limit
  counters live in `storage/cache/ratelimit/` and fail open if not writable.
- Passwords are stored with `password_hash(PASSWORD_DEFAULT)` and upgraded in
  place when the algorithm/cost changes.
- Two-factor authentication (TOTP) is available per account on `/passchange`.
