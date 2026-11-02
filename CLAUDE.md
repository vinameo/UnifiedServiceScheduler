<!--
  CLAUDE.md | AGENTS.md — project instructions for Claude Code (and a quick reference for humans).
  Claude loads this file automatically at the start of every session in this repo.
  Keep it accurate: when tooling, scripts, or conventions change, update this file in the same PR.
  Items marked (planned) describe target conventions that are not wired up yet.
-->
# CLAUDE.md · Unified Service Scheduler

Guidance for Claude Code when working in this repository. Read this file fully before you change code.

## What this project is

A service appointment scheduler for car dealerships (Keyloop technical assessment, Scenario A).
Each appointment needs three things at the same time: a **technician** with the right skill, a **service bay** of the right type, and the **customer's vehicle**.

Two front-end areas share one API:

- **Staff web** (desktop, 1440 px): service advisor, workshop foreman, admin.
- **Customer app** (mobile-first web, 390 px): sign up, book, hold, view, cancel, reschedule.

Source documents (read the relevant part before you build a feature):

- Feature requirements F00 to F12 (FR / BR / AC IDs): https://claude.ai/artifact/D8tfQSFoHuuV6PdTLW4XmX
- System Design Document: https://claude.ai/artifact/VZY3k9hbBM1ub4FyB12FCR (PDF copy in `docs/`)
- Screen designs (Mantine): https://claude.ai/artifact/DaoHdgKWcCRKumWNS7Tvx8 (PNG and HTML copies in `docs/designs/`)

## Tech stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel (PHP 8.3+), API only, served by **PHP-FPM** |
| Auth | Laravel Sanctum, SPA cookie mode (same domain behind Nginx) |
| Frontend | React + TypeScript + Vite, **Mantine** UI kit, TanStack Query, React Router |
| Database | **PostgreSQL 16+** with the `btree_gist` extension |
| Web server | **Nginx**: serves the React build, sends `/api`, `/sanctum`, `/health` to PHP-FPM |
| Local run | Docker Compose |

Exact versions are pinned in `backend/composer.json`, `frontend/package.json` and `docker-compose.yml`. Do not upgrade a major version without asking.

## Repository layout

```
backend/                     Laravel app (API only, no Blade views)
  app/Domain/Booking/        appointments, status rules, version checks (F02, F04, F07, F09, F10)
  app/Domain/Allocation/     candidate search and ranking (F03). Plain PHP, no HTTP, no Eloquent
  app/Domain/Workshop/       check-in, overruns, reassign (F12)
  app/Domain/MasterData/     dealerships, service types, bays, technicians, shifts (F01)
  app/Domain/Notifications/  outbox writer and relay (F11)
  app/Domain/Auth/           sign-up, sign-in, roles (F00)
  app/Http/Controllers/Api/V1/  thin controllers
  app/Http/Requests/         FormRequest validation
  app/Http/Resources/        JSON output (camelCase)
  app/Http/Middleware/       Idempotency, IfMatch, CorrelationId
  app/Support/Problem/       RFC 9457 problem+json rendering
  database/migrations/       schema, including raw SQL for EXCLUDE and tstzrange
  database/seeders/          demo data and seed staff accounts
  tests/Unit/                pure logic (Allocation ranking, status rules)
  tests/Feature/             HTTP + real PostgreSQL
frontend/
  src/customer/              customer app screens (mobile-first)
  src/staff/                 staff web screens (desktop)
  src/shared/                API client, auth, error handling, Mantine theme
docker/nginx/default.conf
docker/php/Dockerfile        php-fpm with pdo_pgsql, opcache
docker-compose.yml           nginx, php, scheduler, postgres, node (build)
docs/                        SDD PDF, design PNGs
```

## Commands

```bash
docker compose up -d --build                         # start nginx, php-fpm, scheduler, postgres
docker compose exec php php artisan migrate --seed   # schema + demo data + seed accounts
docker compose exec php php artisan test             # all backend tests (real PostgreSQL)
docker compose exec php ./vendor/bin/pint            # format PHP
docker compose exec php ./vendor/bin/phpstan analyse # static analysis (Larastan)

cd frontend
npm ci
npm run dev        # Vite dev server, proxies /api and /sanctum to nginx
npm run build      # output to frontend/dist, served by nginx
npm run test       # Vitest + React Testing Library
npm run lint       # ESLint + TypeScript check
```

Run the backend tests and the frontend lint before you say a task is done.

## The core rules (never break these)

The whole design rests on two protections. Read SDD sections 5 to 10 before you touch booking code.

### 1. New bookings: PostgreSQL EXCLUDE constraint

- `resource_allocations` has one row per resource per booking: `TECHNICIAN`, `BAY`, `VEHICLE`.
- The database refuses overlaps. This is the real guarantee; application checks are only for nice messages.
- Laravel's schema builder cannot express this, so the migration uses raw SQL:

```php
DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
DB::statement('ALTER TABLE resource_allocations ADD COLUMN period tstzrange NOT NULL');
DB::statement(<<<'SQL'
    ALTER TABLE resource_allocations
    ADD CONSTRAINT resource_allocations_no_overlap
    EXCLUDE USING gist (resource_type WITH =, resource_id WITH =, period WITH &&)
    WHERE (active)
SQL);
```

- Ranges are half-open: build them as `tstzrange(:start, :end, '[)')`.
- Insert the appointment and its 3 allocation rows in **one** `DB::transaction()`.
- Catch `Illuminate\Database\QueryException` where `$e->getCode() === '23P01'`. Read the conflicting `resource_type` from the error detail:
  - `TECHNICIAN` or `BAY`: roll back and try the next candidate pair (max 3 tries), then `409 NO_AVAILABILITY` with alternatives.
  - `VEHICLE`: `409 VEHICLE_ALREADY_BOOKED` at once.
- Any status that frees a slot (`COMPLETED`, `CANCELLED`, `NO_SHOW`, `EXPIRED`) must set its allocations to `active = false` in the same transaction.
- Never remove or weaken the constraint, and never replace it with a `SELECT` check.

### 2. Changes to an existing appointment: optimistic locking

- `appointments.version` starts at 1 and goes up by 1 on **every** write, including background jobs.
- The API returns it as `ETag: "3"`. Every change needs `If-Match: "3"`.
- Use one repository method for all appointment updates. Do not call Eloquent `save()` or `update()` on an appointment directly:

```php
$affected = DB::table('appointments')
    ->where('id', $id)
    ->where('version', $expectedVersion)
    ->update([...$changes, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);

if ($affected === 0) {
    throw new AppointmentModified($id); // rendered as 412 APPOINTMENT_MODIFIED with the latest data
}
```

- Missing `If-Match` on a change: `428 PRECONDITION_REQUIRED`. Never guess the version.
- Never retry a 412 on the server. The user, or the job's own logic, reads again and decides.
- Order inside a change that moves the time (reschedule, extend, reassign):
  1. version update (this also locks the row),
  2. set old allocations `active = false`,
  3. insert new allocations (EXCLUDE checks them),
  4. insert the outbox row,
  5. commit.
- A retry after `23P01` reruns the whole transaction with the **same** expected version.

### 3. Duplicate requests: Idempotency-Key

- Every `POST` that writes needs an `Idempotency-Key` (UUID). Missing: `400 IDEMPOTENCY_KEY_REQUIRED`.
- The middleware checks the key **before** `If-Match`. Same key + same body hash: return the saved response. Same key + different body: `422 IDEMPOTENCY_KEY_MISMATCH`. Same key still running: `409 REQUEST_IN_PROGRESS`.
- Keys are kept for 24 hours (`IDEMPOTENCY_TTL_HOURS`).

### 4. Outside systems: transactional outbox

- Never call SMS, email or DMS inside a request.
- Write an `outbox_messages` row in the same transaction as the business change.
- `php artisan outbox:relay` sends them (scheduled every 10 seconds, `withoutOverlapping()`), with up to 5 retries, then `FAILED`. Delivery is at least once.

### 5. Holds and the scheduler

- A customer hold is `HELD` with `expires_at = now + HOLD_MINUTES` (default 5). Its allocations are active.
- `php artisan holds:expire` runs every 30 seconds (`everyThirtySeconds()`) and uses the version check plus `status = 'HELD'`.
- The booking flow also frees expired holds that block the wanted time. The EXCLUDE `WHERE` cannot use `now()`.
- The `scheduler` container runs `php artisan schedule:work`.

### 6. Time

- App time zone is `UTC`. Columns are `timestamptz`. The API uses ISO 8601 with an offset.
- Opening hours and shifts are in the dealership's local time zone (`dealerships.timezone`, IANA name). Convert with Carbon; never use the server's or the browser's local zone for business times.

### 7. Auth and access (F00)

- Sanctum SPA mode: the React app calls `GET /sanctum/csrf-cookie` before sign-in; Axios uses `withCredentials` and sends the XSRF token.
- `SESSION_DRIVER=database` so every PHP-FPM worker shares sessions.
- Roles (PHP backed enum): `CUSTOMER`, `SERVICE_ADVISOR`, `WORKSHOP_FOREMAN`, `ADMIN`. Use Policies and Gates, not `if` checks in controllers.
- Customers sign up with email + password. Staff accounts come only from seeders; there is no staff sign-up route. Seed passwords come from env (`SEED_*_PASSWORD`), never from code.
- The customer id always comes from `auth()->user()`, never from the request body.
- Staff queries are always scoped to the user's `dealership_id`. Another customer's appointment returns 404, not 403.
- Passwords use Laravel's `Hash` (bcrypt or Argon2). Sign-in is rate limited (5 tries per 15 minutes per email). The error message never says whether the email or the password was wrong.

## Backend conventions

- Controllers are thin: FormRequest in, Domain action, API Resource out.
- Business logic lives in `app/Domain`. `Allocation` must stay free of HTTP and Eloquent so it is unit-testable.
- Database names are snake_case and plural (`appointments`, `resource_allocations`, `idempotency_keys`, `outbox_messages`). JSON is camelCase.
- Status values are PHP backed enums. Allowed transitions live in one place (`AppointmentStatus::canMoveTo()`).
- Errors are RFC 9457 `application/problem+json` with a stable `code` (list in the requirements doc, "Shared rules"). Map exceptions in `bootstrap/app.php`.
- Every response carries `X-Correlation-Id`. Logs are JSON, include `correlationId`, `dealershipId`, `userId`, `appointmentId`, and never contain passwords, tokens, phone numbers or emails.
- Transactions use the default READ COMMITTED level. Do not switch to SERIALIZABLE.
- Never edit a migration that has already run; add a new one.

## Frontend conventions

- Use Mantine components and the shared theme. No hard-coded hex colours in components.
- Match the design PNGs in `docs/designs/` for layout and copy. Copy is English.
- Server state uses TanStack Query. Keep the `ETag` with the cached appointment and send it as `If-Match` on every change.
- Create one Idempotency-Key per user action (`crypto.randomUUID()`) and reuse it when the same action is retried.
- Handle errors by `code`:
  - `409 NO_AVAILABILITY`: show the alternatives from the response.
  - `412 APPOINTMENT_MODIFIED`: reload, show what changed, let the user try again.
  - `410 HOLD_EXPIRED`: go back to "Choose date and time".
  - `401`: go to the right Sign-in screen.
- Show business times in the dealership's time zone (`Intl.DateTimeFormat` with `timeZone`).
- Customer screens are built for 390 px first; staff screens for 1440 px.

## Nginx and PHP-FPM

`docker/nginx/default.conf` follows this shape:

```nginx
server {
    listen 80;
    root /var/www/frontend/dist;
    index index.html;

    location ~ ^/(api|sanctum|health)/ {
        root /var/www/backend/public;
        try_files $uri /index.php?$query_string;
    }

    location = /index.php {
        root /var/www/backend/public;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTP_X_REQUEST_ID $request_id;
        fastcgi_pass php:9000;
    }

    location / {
        try_files $uri /index.html;   # React SPA fallback
    }
}
```

- `/metrics` must not be reachable from outside the Docker network.
- PHP-FPM workers are short-lived and many: never keep locks, holds or caches in PHP memory or static properties.
- Enable opcache in the production image; keep `display_errors=Off`.

## Testing rules

- Backend tests run on **real PostgreSQL** (`DB_CONNECTION=pgsql` in `phpunit.xml`). Never use SQLite for booking tests: it has no EXCLUDE constraint.
- Name tests after the requirement they prove, for example `test_AC_04_1_only_one_of_twenty_parallel_bookings_wins`.
- Concurrency tests (AC-04.1, AC-04.2, AC-04.6, AC-10.3) need real parallel work. Start N separate PHP processes with `Process::pool()` (or parallel HTTP requests to nginx) that all wait on the same start time. These tests must **not** use `RefreshDatabase`, because its wrapping transaction hides data from other processes; use `DatabaseTruncation` instead.
- After each booking test, assert that no two active allocations overlap (one SQL query must return 0 rows).
- Never skip, delete or weaken a test to make the build green.

## Environment variables

`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_DRIVER=database`, `SANCTUM_STATEFUL_DOMAINS`, `HOLD_MINUTES=5`, `IDEMPOTENCY_TTL_HOURS=24`, `SEED_ADMIN_PASSWORD`, `SEED_ADVISOR_PASSWORD`, `SEED_FOREMAN_PASSWORD`. Never commit `.env`.

## How to work in this repo

- Before a feature, read its section in the requirements doc and list the FR and AC IDs you will cover.
- Build in this order: F00, F01, F03, F04, F02, F05, then F06, F07, F08, then F09 to F12.
- Keep each change small and focused on one feature. Add or update tests in the same change.
- Prefer built-in Laravel and Mantine features. Ask before adding a new Composer or npm package.
- If a requirement is unclear or seems to conflict with the SDD, stop and ask instead of guessing.
