# AGIS Operations Guide

## 1. Requirements

- Node.js and npm;
- PHP 8.2 or newer;
- Composer;
- PostgreSQL 14 or newer;
- PHP extensions required by Laravel plus `pdo_pgsql` and `pgsql`;
- Apache/XAMPP or another supported web server for production-like hosting.

## 2. Environment setup

Copy `backend/.env.example` to `backend/.env` and set environment-specific values.
Never commit `.env`.

Minimum PostgreSQL settings:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agis
DB_USERNAME=agis_app
DB_PASSWORD=replace-with-a-local-secret
```

Generate the Laravel application key once:

```powershell
cd backend
php artisan key:generate
```

`APP_KEY` protects encrypted configuration secrets. Back it up securely. Changing
it without a migration plan makes existing encrypted SMTP credentials unreadable.

## 3. Install dependencies

From the project root:

```powershell
npm.cmd install
composer install --working-dir=backend
```

## 4. Database setup

For a new local/demo database only:

```powershell
php backend/artisan migrate:fresh --seed
```

`migrate:fresh` destroys all tables. Never use it on retained or production data.

For normal upgrades:

```powershell
php backend/artisan migrate --force
```

The CMS-1 intake-hardening migration performs a read-only preflight before
adding the AEMS `cms_recommendation_id` foreign key. If it reports orphaned
recommendation-to-CMS IDs, stop the deployment. Do not null, rewrite, or discard
those values. Resolve each named pointer through an approved data-correction
migration, take a new backup, and rerun the normal migration. Existing `OPEN`
intake rows are deterministically changed to `TRANSFERRED`; recoverable source
attributes are backfilled without fabricating unavailable historical values,
and each intake receives one case and one initial event.

Run only safe idempotent reference seeders when required:

```powershell
cd backend
php artisan db:seed --class=MasterListSeeder --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=SystemConfigurationSeeder --force
```

Review seeders before running them in production. Demo/user/content seeders may
change prototype records.

## 5. File storage

Private documents and evidence remain under Laravel storage and are streamed
through protected endpoints.

Runtime logos use the `public` disk. Create the public storage link once:

```powershell
cd backend
php artisan storage:link
```

Back up:

- PostgreSQL;
- `backend/storage/app` private files;
- `backend/storage/app/public` managed public assets;
- production `.env` and `APP_KEY` through a secure secret-management process.

## 6. Local run

Use two terminals from the repository root:

```powershell
npm.cmd run api
```

```powershell
npm.cmd run dev
```

Vite proxies `/api` and `/sanctum` to Laravel for same-origin Sanctum behavior.

## 7. Demo accounts

Demo accounts are available only when:

```dotenv
DEMO_ACCOUNTS_ENABLED=true
DEMO_DEFAULT_PASSWORD=lala
```

Disable them outside a controlled local/demo environment:

```dotenv
DEMO_ACCOUNTS_ENABLED=false
```

Do not rely on demo passwords in any shared deployment.

## 8. Runtime administration

Platform/AGIS administrators manage supported values in **System Configuration**.
Changes are validated, cached values are cleared, and runtime settings are
reapplied without a process restart.

After direct database maintenance, clear caches:

```powershell
cd backend
php artisan optimize:clear
```

Prefer the UI/API over direct database changes because it preserves encryption,
validation, Activity Log, and Audit Trail behavior.

## 9. SMTP setup

Configure:

- outbound email enabled;
- transport (`smtp` for delivery or `log` for local verification);
- host and port;
- encryption (`tls`, `ssl`, or none);
- username and password;
- sender address and name.

Save first, then use **Send configuration test**. The password is encrypted with
`APP_KEY`, never returned to the browser, and preserved when the secret field is
left blank.

Users must also enable their email notification preference. In-app notifications
remain the authoritative delivery channel.

## 10. Verification

Required checks before handoff:

```powershell
npm.cmd run lint
npm.cmd run build

cd backend
php artisan test --testsuite=Feature
php artisan route:list
php artisan migrate:status
```

After CMS-1 deployment, also verify that intake, case, and initial-event counts
match:

```powershell
php artisan tinker --execute="dump([
    'intakes' => App\Models\CmsRecommendation::count(),
    'cases' => App\Models\CmsRecommendationCase::count(),
    'events' => App\Models\CmsRecommendationEvent::where('event_code', 'INTAKE_CREATED')->count(),
]);"
```

Each valid intake must have one case and one `INTAKE_CREATED` event. Formally
excluded AEMS recommendations are intentionally absent from all three counts.

Smoke-test:

1. `GET /api/health`;
2. login/logout;
3. one allowed and one forbidden role action;
4. a document upload/download;
5. notification deep link;
6. System Configuration public branding refresh;
7. an IAP detail page and report export.

## 11. Production checklist

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- strong unique database credentials;
- HTTPS and secure cookies;
- demo accounts disabled;
- production `APP_KEY` securely backed up;
- scheduled database/file backups;
- tested restore procedure;
- storage capacity and permissions checked;
- SMTP test passed;
- queue/scheduler configured when background jobs are enabled;
- web server routes unknown frontend paths to `index.html`;
- `/api` and `/sanctum` reach Laravel;
- writable Laravel cache/session/log/storage directories;
- migrations applied;
- lint, build, and feature tests passed;
- default demo passwords removed/reset.

## 12. Backup and restore

A valid backup set contains the database and the matching file-storage snapshot.
Restoring only one can leave document metadata without files or files without
records.

Recommended restore test:

1. provision an isolated database and storage directory;
2. restore PostgreSQL;
3. restore private/public storage;
4. use the same protected `APP_KEY`;
5. run `php artisan optimize:clear`;
6. verify login, a current document, a historical version, an IAP attachment,
   notifications, and reports;
7. document duration and failures.

## 13. Monitoring

Monitor:

- health endpoint and HTTP error rate;
- failed logins and account lockouts;
- database connections, query latency, locks, and disk;
- application logs and mail failures;
- private/public storage size;
- export volume;
- workflow deadlines and overdue notifications;
- backup completion and restore-test age;
- certificate and domain expiry.

## 14. Troubleshooting

### Login fails for every account

- confirm Laravel is running;
- confirm PostgreSQL connection values;
- run `php artisan optimize:clear`;
- inspect account active/archived/manual-lock state;
- verify `APP_KEY` and session domain/cookie settings.

### Frontend route works by click but fails on refresh

Configure the web server fallback to the built `index.html`. API and physical
asset paths must remain excluded from the SPA fallback.

### Uploaded logo does not display

- run `php artisan storage:link`;
- confirm `backend/public/storage` resolves to `storage/app/public`;
- check web-server access to the public link;
- reload runtime configuration.

### Document download returns 403

Confirm `documents.download`, the document confidentiality permission, account
status, and uploader/role policy.

### Workflow action says the record changed

Another session advanced the lock version. Refresh the record, re-evaluate the
latest state, and submit the action again.

### Email test fails

- enable outbound email;
- verify host/port/encryption;
- confirm firewall/network access;
- re-enter the SMTP secret if `APP_KEY` changed;
- use `log` transport to isolate application logic from network delivery.

## 15. Change-management rule

Every production change should include:

- reviewed migration impact and rollback limitations;
- safe backup;
- feature/permission tests;
- updated documentation;
- deployment steps;
- smoke-test result;
- named rollback/restore decision owner.
