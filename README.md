# Scout Management System

The Ifthithaah Scout Group portal: one Laravel 13 application and one database for scouts, groups, activities, attendance, fees, payments, the scout shop and certificates. It replaces the old Google Sheets + Apps Script workbooks.

- **Sign-in:** National ID + PIN. Anyone can register as a scout or parent; a leader or admin verifies them first.
- **Roles:** admin, leader, parent, student (a person may hold several). Extra permissions: Verify payments, Manage shop, Process delivery, Manage annual fees.
- **Modules:** Scout operations, Family, My record, Certificates, Fees and payments, Scout shop, Reports, Administration.

See [docs/USER_GUIDE.md](docs/USER_GUIDE.md) for how each role uses the portal.

## Stack

PHP 8.3+, Laravel 13, Livewire 4 (attendance registers, parent child lookup), Alpine.js, Tailwind CSS 3, Vite. MySQL 8 in production, SQLite locally and in tests. dompdf for certificate PDFs, PhpSpreadsheet for imports and exports. Google Drive/Slides and Telegram are optional and fail soft.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
npm install && npm run build
php artisan scout:install   # migrate, storage:link, demo data (local/testing only)
php artisan serve
```

The demo data includes sample sign-ins. In the local environment they are listed on the sign-in page, and **Use** fills in the form:

| Role | National ID | PIN |
| --- | --- | --- |
| Admin | `A000001` | `123456` |
| Leader | `A100001` | `123456` |
| Leader (treasurer: verify payments, shop, delivery, annual fees) | `A100002` | `123456` |
| Parent | `A100003` | `123456` |
| Scout | `A200001` | `123456` |

Set `SCOUT_ADMIN_PIN` (and `SCOUT_ADMIN_NATIONAL_ID`) in `.env` to use a different PIN or admin ID. Sample accounts are never created or shown outside local/testing.

Run the tests with `php artisan test`.

## Production deployment

1. Server: PHP 8.3 with `intl`, `mbstring`, `pdo_mysql`, `redis`, `gd`, `zip`; MySQL 8; Redis; Nginx or Apache serving `public/`.
2. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `SESSION_LIFETIME=480`, `SESSION_SECURE_COOKIE=true`, `MAIL_*`, the `SCOUT_*` values and optionally `GOOGLE_SERVICE_ACCOUNT_JSON`.
3. Install:
   ```bash
   composer install --no-dev -o
   npm ci && npm run build
   php artisan migrate --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
4. Cron: `* * * * * php artisan schedule:run` (runs `scout:cleanup` daily). Worker: `php artisan queue:work redis`.
5. **First admin:** `php artisan scout:create-admin A1234567 "Full Name" --email=you@example.org`. It prints a one-time PIN; sign in and change it under Profile. The demo seeder never runs in production.

### Website logo

Admins upload the logo under Administration → Settings (PNG or JPEG, up to 2 MB). It replaces the built-in logo in the header, on the sign-in page, as the browser icon and on certificates. It is stored on the public disk and served by the app at `/branding/logo`, so it works without `storage:link` and whatever `APP_URL` is set to.

### Optional integrations

- **Google Drive / Slides:** in Google Cloud Console enable the Drive and Slides APIs. Then either:
  - **Connect a Google account (recommended):** configure the OAuth consent screen (External, then *Publish app* so the connection does not expire after 7 days), create an *OAuth client ID* of type *Web application* with the redirect URI shown under Administration → Settings → Google Drive (`https://your-domain/settings/google/callback`; Google only accepts https on a public domain, or `http://localhost`), paste the Client ID and secret in Settings, save, and press **Connect Google account**. Files are stored in that account's Drive, so no sharing is needed.
  - **Or use a service account key:** upload the JSON key in Settings (or set `GOOGLE_SERVICE_ACCOUNT_JSON` on the server, which takes priority). Service accounts have no storage of their own, so use folders in a shared drive and share them with the service account's email as an editor.

  Then paste the photos and certificates folder links in Settings and press **Test Google Drive**. API keys cannot be used: they only reach public data. Without Google, photos go to `storage/app/public` and PDFs to `storage/app/certificates`.
- **Telegram:** create a bot with @BotFather and paste the token in Settings (it is stored encrypted and never shown again). Linking uses `getUpdates`, so the bot must **not** have a webhook set. Users connect from Profile.

## Commands

| Command | Purpose |
| --- | --- |
| `scout:install` | Migrate, link storage, seed demo data (local/testing only) |
| `scout:create-admin {national_id} {name}` | Create or promote an admin with a one-time PIN |
| `scout:import-legacy --file= [--sheet=] [--dry-run] [--force]` | Inspect and import a legacy workbook; dry run unless `--force` |
| `scout:export {type} [--path=] [--format=xlsx\|csv]` | Ledger export (students, users, groups, activities, attendance, rover-attendance, class-fees, annual-fees, payments, payment-proofs, shop-items, purchases, audit-logs) |
| `scout:recalculate-balances [--repair]` | Compare stored balances with approved payments; `--repair` fixes and audits |
| `scout:verify-integrity` | Duplicate, orphan, stock, section and role checks; non-zero exit on problems |
| `scout:cleanup` | Clear the settings cache, prune expired sessions and stale temp files |

## Legacy import layout

Sheets are imported in this order; headers are matched case- and punctuation-insensitively (`Student ID` = `student_id`): Students, Users, ParentLinks, Groups, GroupMembers, GroupLeaders, GroupAssistantLeaders, Activities, Attendance, RoverAttendance, ClassFeeConfig, ClassFees, Configuration, UserPermissions, AnnualFeeConfig, AnnualFees, Payments, ShopItems, Purchases. References accept legacy ids or National IDs. Dates accept `dd.MM.yyyy[ HH:mm]`, ISO, `d/m/Y` and Excel serials. The exact required columns per sheet are listed in `LegacyImportService::SHEETS`.

## Code map

| Area | Where |
| --- | --- |
| Visibility rules (the only place scope is computed) | `app/Services/LeaderScopeService.php` |
| Business rules | `app/Services/*Service.php` (controllers only validate, authorise and call these) |
| Policies | `app/Policies` |
| Status enums | `app/Enums` |
| Module catalogue and sidebar | `app/Support/ScoutModules.php`, `app/Support/Navigation.php` |
| Livewire registers | `app/Livewire` |
| Routes | `routes/web.php`, `routes/auth.php` |
| Tests | `tests/Feature`, `tests/Unit` |

## Decisions on the specification review (section 21)

1. Legacy users without a PIN get a random password and are imported **inactive**; an admin resets the PIN.
2. Unknown or missing legacy roles are reported as row errors; nobody silently becomes a leader.
3. Activity certificates are still issued automatically after the register is saved, but after the attendance transaction commits; failures are reported and can be retried with **Issue** on the Certificates page.
4. The unused `Rejected` fee status was removed.
5. Purchases can be cancelled (`POST /purchases/{id}/cancel`) by the buyer side or shop staff until money is received.
6. If stock runs out before an online payment is approved, approval is blocked with a message to reject and refund (or restock); the payment stays in the queue.
7. No unscoped dashboard service; Breeze's email password-reset flow was removed (PINs are reset by an admin).
8. Uploaded import files are deleted after confirmation; every export goes to its own temporary file.
9. Any leader may verify any pending scout or parent registration (kept as intended).
10. Activity scope is one query rule: all-scout activities, activities targeting a led group, or a section that a led group's members belong to.
11. Rover marks for unknown students fail the whole save.
12. Telegram linking uses `getUpdates` (documented above).
13. The demo seeder builds data with factories; no fixture file is needed.
14. Shop item audits link to the item.
