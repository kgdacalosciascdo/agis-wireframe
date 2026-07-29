# AGIS — Audit Governance Information System

AGIS uses a React 19 + Vite frontend styled with Tailwind CSS, Lucide React icons, Recharts dashboard visualizations, React Router for page URLs, and a Laravel 12 API backed by PostgreSQL. Authentication uses Laravel Sanctum's first-party SPA cookie sessions; passwords and permissions are verified by the backend and are no longer stored in browser storage.

## Requirements

- Node.js and npm
- PHP 8.2 or newer with `pdo_pgsql` and `pgsql`
- Composer
- PostgreSQL 14 or newer

The current local machine has PostgreSQL 18 installed at `C:\Program Files\PostgreSQL\18`.

## PostgreSQL setup

Create a dedicated application role and database from pgAdmin or `psql` while signed in as a PostgreSQL administrator:

```sql
CREATE ROLE agis_app WITH LOGIN PASSWORD 'replace-with-a-strong-local-password';
CREATE DATABASE agis OWNER agis_app ENCODING 'UTF8';
```

Then connect to the new `agis` database and remove public schema-creation rights:

```sql
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
```

Copy `backend/.env.example` to `backend/.env` if the file does not exist, then set only the local secret:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agis
DB_USERNAME=agis_app
DB_PASSWORD=your-local-password
```

Never commit `backend/.env`. For the initial empty database, run:

```powershell
php backend/artisan migrate:fresh --seed
```

`migrate:fresh` deletes existing tables and is only appropriate for initial/demo setup. Use `php backend/artisan migrate` for normal upgrades.

## Run locally

Use two terminals from the repository root:

```powershell
npm run api
```

```powershell
npm run dev
```

Vite proxies `/api` and `/sanctum` to `http://127.0.0.1:8000`, keeping Sanctum authentication same-origin in development.

## Frontend routes and permissions

Public authentication uses `/login`; authenticated users start at `/dashboard`. Each module has its own URL, including `/office-registry`, `/internal-audit-planning`, `/audit-engagement-management`, `/audit-findings-recommendations`, and the remaining registry and administration pages defined in `src/config/navigation.js`.

The sidebar and dashboard cards only show routes allowed by the authenticated user's permission list. Typing a disallowed URL directly redirects to `/unauthorized`. This frontend guard improves navigation, but every future module API must also use Laravel's `auth:sanctum` and `permission` middleware.

The production build includes `public/.htaccess`, which sends unknown Apache paths back to `index.html` so routed pages continue to work after a browser refresh.

## Demo accounts

Demo accounts are seeded with hashed passwords. Their clickable credentials are returned by `/api/demo-accounts` only when `DEMO_ACCOUNTS_ENABLED=true`. Disable that setting outside local/demo environments.

| Role                    | Employee ID         | Default local password |
| ----------------------- | ------------------- | ---------------------- |
| Platform Administrator  | `AGIS-PLATFORM-001` | `lala`                 |
| AGIS Administrator      | `AGIS-ADMIN-001`    | `lala`                 |
| CIAS Management         | `CIAS-HEAD-001`     | `lala`                 |
| AGIS User               | `CIAS-AUD-001`      | `lala`                 |
| Auditee Representative | `AUDITEE-001`       | `lala`                 |
| Read Only User          | `MAYOR-001`         | `lala`                 |

Change these through `backend/.env` before seeding any shared environment.

## Verification

```powershell
npm run lint
npm run build
npm run test:api
```

The API health endpoint is `GET /api/health`. Authentication endpoints are `POST /api/login`, `GET /api/me`, and `POST /api/logout`.

AGIS Core includes working Office, Audit Area, Audit Focus, User, Access Role,
Permission, and Master List registries; System Configuration; Activity Log; and
self-service Profile/Password pages. Demo seeding creates the 42 referenced city
offices plus the AGIS system office, an office head and employee account per city
office, realistic audit areas/focuses, and shared workflow reference lists.

## Documentation

- `docs/README.md` — documentation index
- `docs/SYSTEM_FLOW.md` — complete end-to-end system flow
- `docs/CORE_WORKFLOW_DESIGN.md` — as-built AGIS Core workflow
- `docs/IAP_WORKFLOW_DESIGN.md` — as-built IAP workflow
- `docs/API_AND_DATA_REFERENCE.md` — API and entity reference
- `docs/OPERATIONS_GUIDE.md` — setup, deployment, backup, and troubleshooting
- `docs/DEVELOPMENT_STANDARDS.md` — required security, privacy, quality, and operations rules

## Architecture

- `src/` — React Router interface styled with Tailwind CSS
- `src/config/navigation.js` — route, navigation, and permission map
- `lucide-react` — tree-shakable interface icons; navigation stores icon components directly
- `src/services/api.js` — same-origin Sanctum/API client
- `backend/app/` — Laravel controllers, requests, middleware, resources, and models
- `backend/database/migrations/` — PostgreSQL-compatible schema
- `backend/database/seeders/` — roles, permissions, offices, and demo accounts
- `docs/DEVELOPMENT_STANDARDS.md` — required security, privacy, quality, and operations rules

Build Core Registries first, then IAP, AEM, AFR, CMS, ARMS, and AIS. Every module endpoint must enforce permissions on the server; hiding a React button is never authorization.
