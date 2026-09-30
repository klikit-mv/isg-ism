# Scout Management System

The Ifthithaah Scout Group portal: one web application and one database for scouts, groups, activities, attendance, fees, payments, the scout shop, events and certificates. It replaces the old Google Sheets + Apps Script workbooks.

- **Sign-in:** National ID + PIN. Anyone can register as a scout or parent; a leader or admin verifies them first.
- **Roles:** admin, leader, parent, student (a person may hold several). Extra permissions: Verify payments, Manage shop, Process delivery, Manage annual fees.
- **Modules:** Scout operations, Family, My record, Certificates, Fees and payments, Scout shop, Events, Reports, Administration.
- **Public pages:** the home page lists upcoming events and each event has a public details page; registering needs an account. Anyone can verify a certificate number.

See [docs/USER_GUIDE.md](docs/USER_GUIDE.md) for how each role uses the portal.

## Stack

Next.js 15 (App Router, Server Components and Server Actions), React 19, TypeScript, Tailwind CSS 3, MySQL 8 (Drizzle ORM), Node.js 18.18 to 21. `pdfkit` makes certificate PDFs, `exceljs` reads and writes Excel, `nodemailer` sends email, and the Telegram Bot API is called directly.

It is one Node.js app plus a MySQL database, which is exactly what Hostinger's **Node.js Apps** hosting runs.

## Deploy on Hostinger (Node.js Apps)

1. **Code on GitHub.** Hostinger imports from a public GitHub repository. Merge the work branch into `main` (or pick the branch when importing).
2. **Database.** In hPanel go to *Databases → MySQL Databases*, create a database and user, and write down the database name, user name and password. The host is `localhost`.
3. **Create the app.** *Websites → Add website → Node.js Apps → Import Git Repository*. Choose the repository and branch. Framework: **Next.js**. **Node.js version: 20** (never above 21). Build command `npm run build`, start command `npm start`.
4. **Environment variables** (in the app's settings, before the first deploy):

   | Variable | Value |
   | --- | --- |
   | `NODE_ENV` | `production` |
   | `APP_URL` | `https://your-domain` |
   | `APP_KEY` | run `openssl rand -base64 32` and write `base64:` before the result. Keep it secret and never change it later: it protects the stored Telegram token. |
   | `DB_HOST`, `DB_PORT` | `localhost`, `3306` |
   | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from step 2 |
   | `STORAGE_DIR` | an absolute folder **outside** the app folder, for example `/home/uXXXXXXXX/scout-storage`. Photos, payment receipts and certificates live here and must survive redeploys. Create the folder in the File Manager. |
   | `SCOUT_ADMIN_NATIONAL_ID`, `SCOUT_ADMIN_NAME`, `SCOUT_ADMIN_PIN` | your first administrator (see below) |
   | `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | optional, for email alerts. `MAIL_ENCRYPTION=ssl` for port 465. |
   | `SCOUT_TIMEZONE`, `SCOUT_CURRENCY`, `SCOUT_NAME`, … | optional, see `.env.example` |

5. **Deploy.** Every time the app starts it applies the database migrations, creates the first administrator when the database has none, and adds one default certificate template per type. Nothing else needs running: there is no cron job and no terminal step.
6. **First sign-in.** Sign in with the National ID and PIN you set, change the PIN under *Profile*, then **delete `SCOUT_ADMIN_PIN`** from the environment. Add the logo, bank details and Telegram bot under *Administration → Settings*, and create the other users.
7. **Domain and SSL.** Point the domain at the app in hPanel and switch on the free SSL. Sign-in cookies are https-only in production; while testing on a plain `http` address set `SESSION_SECURE_COOKIE=false` and remove it afterwards.

**Updating:** push to the branch and press *Redeploy*; migrations run by themselves. **Backups:** export the MySQL database from phpMyAdmin and copy `STORAGE_DIR`.

**Trouble?** *Database setup failed* in the logs means the `DB_*` values are wrong. A blank page after deploy usually means the Node version is 22 or higher, or `npm run build` failed.

## Local setup

Needs Node.js 18.18 to 21 (`.nvmrc` selects 20) and a MySQL 8 database.

```bash
cp .env.example .env.local        # fill in the DB_* values and APP_KEY
npm install
npm run db:migrate                # create the tables
npm run db:seed                   # demo data (never runs in production)
npm run dev                       # http://localhost:3000
```

The demo data includes sample sign-ins, listed on the sign-in page outside production:

| Role | National ID | PIN |
| --- | --- | --- |
| Admin | `A000001` | `123456` |
| Leader | `A100001` | `123456` |
| Leader (treasurer: verify payments, shop, delivery, annual fees) | `A100002` | `123456` |
| Parent | `A100003` | `123456` |
| Scout | `A200001` | `123456` |

Run the tests with `npm test` (they use a separate MySQL database, `scout_next_test`, and empty it: set `TEST_DB_*` to point elsewhere). `npm run typecheck` checks the types and `npm run build` makes the production build.

## Commands

| Command | Purpose |
| --- | --- |
| `npm run db:migrate` | Apply migrations by hand (the app also does this every time it starts) |
| `npm run db:seed` | Demo data (not in production) |
| `npm run scout:create-admin -- A1234567 "Full Name" --email=you@example.org` | Create or promote an admin with a one-time PIN (needs a terminal) |
| `npm run scout:import-legacy -- --file=workbook.xlsx [--sheet=Name] [--force]` | Inspect and import a legacy workbook; a dry run unless `--force` |
| `npm run scout:export -- <type> [--path=file] [--format=xlsx\|csv]` | Ledger export (students, users, groups, activities, attendance, rover-attendance, class-fees, annual-fees, payments, payment-proofs, shop-items, purchases, audit-logs) |

Every Excel file the portal produces (reports, ledger exports, the scout import template and the workbook import template) has dropdown lists for coded columns such as section, gender and status.

## Optional integrations

- **Telegram:** create a bot with @BotFather and paste the token under *Administration → Settings* (stored encrypted, never shown again). The bot must **not** have a webhook: linking reads the `/start` message with `getUpdates`. People connect from *Profile*.
- **Email:** set the `MAIL_*` variables. People turn email alerts on under *Profile*.

## Legacy workbook import layout

Sheets are imported in this order; headers are matched case- and punctuation-insensitively (`Student ID` = `student_id`): Students, Users, ParentLinks, Groups, GroupMembers, GroupLeaders, GroupAssistantLeaders, Activities, Attendance, RoverAttendance, ClassFeeConfig, ClassFees, Configuration, UserPermissions, AnnualFeeConfig, AnnualFees, Payments, ShopItems, Purchases. References accept legacy ids or National IDs. Dates accept `dd.MM.yyyy[ HH:mm]`, ISO, `d/m/Y` and Excel dates. *Administration → Import* offers a template with every sheet, and Inspect, Dry run and Import for real.

## Code map

| Area | Where |
| --- | --- |
| Pages and route groups per module | `src/app` |
| Server actions (forms and buttons) | `src/app/actions` |
| Visibility rules (the only place scope is computed) | `src/server/scope.ts` |
| Business rules | `src/server/*.ts` (pages and actions only validate, authorise and call these) |
| Module catalogue and sidebar | `src/lib/modules.ts` |
| Status enums, money and date helpers | `src/lib/enums.ts`, `money.ts`, `dates.ts` |
| Database schema and migrations | `src/db/schema.ts`, `drizzle/` |
| Excel reading and writing with dropdowns | `src/server/xlsx.ts` |
| Certificate PDFs | `src/server/certificate-pdf.ts` |
| Tests | `tests/` |

## Decisions carried over from the specification review

1. Legacy users without a PIN get a random password and are imported **inactive**; an admin resets the PIN.
2. Unknown or missing legacy roles are reported as row errors; nobody silently becomes a leader.
3. Activity certificates are issued automatically after the register is saved, but after the attendance is committed; failures are reported and can be retried with **Issue** on the Certificates page.
4. Purchases can be cancelled by the buyer side or shop staff until money is received.
5. If stock runs out before an online payment is approved, approval is blocked with a message to reject and refund (or restock); the payment stays in the queue.
6. PINs are reset by an admin; there is no email password reset.
7. Any leader may verify any pending scout or parent registration.
8. Activity scope is one query rule: all-scout activities, activities targeting a led group, or a section that a led group's members belong to.
9. Rover marks for unknown students fail the whole save.
10. Telegram linking uses `getUpdates` (documented above).

## Differences from the Laravel version

The portal was rebuilt on Next.js and MySQL so it can run on Hostinger. Table and column names are unchanged, so data can be copied across (use the legacy import or a SQL export); start the new app on a fresh, empty database. Not carried over: Google Drive and Google Slides (files are stored under `STORAGE_DIR`, and certificates use one built-in design per type), the ZIP download of many certificates (download them one by one), and the Laravel-only Artisan commands `scout:recalculate-balances`, `scout:verify-integrity` and `scout:cleanup` (balances are always derived from approved payments, and expired sign-ins are removed at every start).
