# Render Free deployment

AGIS can be run on Render Free as a demonstration or temporary testing
deployment. The image contains one Apache/PHP web service. Apache serves the
compiled React application and forwards Laravel API, Sanctum, and health
requests to the same application origin:

```text
https://<service>.onrender.com/
https://<service>.onrender.com/api/...
```

Render Free has no persistent disk. `FILESYSTEM_DISK=local` stores evidence in
the container's ephemeral filesystem; uploaded evidence can disappear after a
restart, redeploy, or spin-down. The deployment does not create
`public/storage` links or public evidence URLs. Use a durable private object
store before treating the deployment as operational.

## Render Dashboard settings

Create one **Web Service** from the repository:

- Environment: `Docker`
- Root Directory: repository root (blank)
- Dockerfile Path: `/Dockerfile`
- Branch: the branch containing the deployment files
- Health Check Path: `/health`
- Instance: Free
- No disk and no background worker

Create one Render Free PostgreSQL database. Use its **internal** connection URL
as `DATABASE_URL` on the Web Service.

## Required environment variables

Set these in Render's Environment page. Generate `APP_KEY` with
`php artisan key:generate --show` locally, or another secure Laravel key
generator; never commit it.

```text
APP_NAME=AGIS
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated Laravel key>
APP_URL=https://<service>.onrender.com
VITE_API_BASE_URL=/api

DB_CONNECTION=pgsql
DATABASE_URL=<Render internal PostgreSQL URL>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
# Leave SESSION_DOMAIN unset for this same-origin deployment.
SANCTUM_STATEFUL_DOMAINS=<service>.onrender.com
CORS_ALLOWED_ORIGINS=https://<service>.onrender.com

RUN_PRODUCTION_SEEDERS=true
RUN_FULL_DEMO_SEEDERS=true
DEMO_ACCOUNTS_ENABLED=true
DEMO_DEFAULT_PASSWORD=<temporary strong demo password>
BOOTSTRAP_ADMIN_ENABLED=false
```

The first controlled demo deployment runs both the idempotent production
reference seeder and the dedicated `RenderDemoSeeder`. Set both seeder flags
and `DEMO_ACCOUNTS_ENABLED` to `false` after the dataset has been verified.
Never place a real administrator password in source control or in this guide.

## First administrator

The image includes a guarded `agis:bootstrap-admin` command. It does nothing
unless `BOOTSTRAP_ADMIN_ENABLED=true`. When enabled, provide these additional
variables temporarily:

```text
BOOTSTRAP_ADMIN_ENABLED=true
BOOTSTRAP_ADMIN_EMPLOYEE_ID=<unique employee id>
BOOTSTRAP_ADMIN_USERNAME=<unique username>
BOOTSTRAP_ADMIN_EMAIL=<administrator email>
BOOTSTRAP_ADMIN_FIRST_NAME=<first name>
BOOTSTRAP_ADMIN_LAST_NAME=<last name>
BOOTSTRAP_ADMIN_PASSWORD=<temporary strong password>
BOOTSTRAP_ADMIN_ROLE=agis_admin
BOOTSTRAP_ADMIN_OFFICE=AGIS-SYS
```

The command requires the approved production seeders to have created the
`agis_admin` role and `AGIS-SYS` office. It creates the account only when the
employee ID, username, and email are not already present, assigns only the
configured role, and never prints the password. Remove the flag and credential
variables immediately after the first successful bootstrap.

## Startup behavior

`docker/render-start.sh`:

1. creates writable Laravel cache, session, log, and bootstrap directories;
2. clears generated image-time caches;
3. runs `php artisan migrate --force` (never `migrate:fresh` or destructive SQL);
4. optionally runs only `Database\\Seeders\\ProductionSeeder` when
   `RUN_PRODUCTION_SEEDERS=true`;
5. optionally runs `Database\\Seeders\\RenderDemoSeeder` when
   `RUN_FULL_DEMO_SEEDERS=true`;
6. optionally runs the guarded administrator bootstrap;
7. caches configuration and views; and
8. starts Apache with `exec` on Render's `${PORT:-10000}`.

The production seeder contains only idempotent roles, permissions, offices,
controlled reference data, workflows, audit areas, and runtime configuration.
It does not create demo users, sample engagements, or sample recommendations.

### Full Render demonstration dataset

`RenderDemoSeeder` is a dedicated, repeatable runner. It does not call
`DatabaseSeeder`, does not truncate or force-delete retained data in full-demo
mode, and is safe to rerun after a restart. It creates the six configured demo
accounts (`admin`, `agisadmin`, `departmenthead`, `auditor`, `auditee`, and
`mayor`), Core office employees and heads, Audit Universe items and histories,
the SIAP plan with objectives, priorities, and workflow events, the 2025/2026
IAP risk periods with criteria, assessments, scores and events, the finalized
prioritization run, the 2026 annual plan with engagements, teams, schedules,
capacity, skills and unavailability, and seeded in-app notifications.

The current repository has no AEMS or CMS operational demo seeders. Therefore
the full Render demo does **not** fabricate AEMS engagements, working papers,
evidence, findings, auditee responses, reports, CMS recommendations, action
plans, or closure records. Those workflows remain available for authorized
users to create through the application. Seeders also create no physical
document/evidence fixture files; Render Free's local uploads remain ephemeral
and private.

## Local image check

With Docker installed, from the repository root:

```powershell
docker build -t agis-render-free .
docker run --rm -p 10000:10000 `
  -e APP_KEY=<local-test-key> `
  -e APP_ENV=local `
  -e APP_DEBUG=false `
  -e DB_CONNECTION=sqlite `
  -e DB_DATABASE=/var/www/html/backend/database/database.sqlite `
  -e SESSION_DRIVER=file `
  -e CACHE_STORE=file `
  -e QUEUE_CONNECTION=sync `
  -e FILESYSTEM_DISK=local `
  agis-render-free
```

The image must not use the Vite development server. Verify `/health`, `/`, a
nested React route, and `/api/...` against a database configured for the local
test. Do not claim a Docker result when Docker is unavailable.

## Scope

This deployment preparation does not change CMS workflows, statuses,
permissions, professional controls, or business migrations. CMS-9B remains the
latest implemented CMS phase. CMS-10A reopening, automation, reports, AIS, and
ARMIS remain outside this deployment task.
