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
| Backend | Laravel 13 (PHP 8.4), API only, served by **PHP-FPM** |
| Auth | Laravel Sanctum, SPA cookie mode (same domain behind Nginx) |
| Frontend | React 19 + TypeScript 6.0 + Vite, **Mantine 9** UI kit, TanStack Query, React Router |
| Database | **PostgreSQL 17** with the `btree_gist` extension |
| Web server | **Nginx**: serves the React build, sends `/api`, `/sanctum`, `/health` to PHP-FPM |
| Run | Docker Compose: `docker-compose.yml` (local), `docker-compose.prod.yml` (deploy) |

Exact versions are pinned in `api/composer.json`, `web/package.json` and the Dockerfiles. Do not upgrade a major version without asking.
TypeScript stays on 6.0.x: `typescript-eslint` does not support TypeScript 7 yet.

## Repository layout

```
api/                         Laravel app (API only, no Blade views)
  app/Models/                Eloquent models (used only by repositories)
  app/Domain/<Module>/Repositories/  XxxRepositoryInterface + XxxRepository (Eloquent)
  app/Domain/<Module>/Services/      XxxServiceInterface + XxxService (business rules)
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
  app/Providers/DomainServiceProvider.php  binds every Repository/Service interface to its class
  app/Support/Problem/       RFC 9457 problem+json rendering (Problem, ProblemException, ProblemRenderer)
  routes/api.php             feature routes, all under /api/v1
  routes/health.php          /health/live, /health/ready (no session)
  database/migrations/       schema, including raw SQL for EXCLUDE and tstzrange
  database/seeders/          demo data and seed staff accounts
  tests/Unit/                pure logic (Allocation ranking, status rules)
  tests/Feature/             HTTP + real PostgreSQL
web/                         React app (Vite)
  src/customer/              customer app screens (mobile-first)
  src/staff/                 staff web screens (desktop)
  src/shared/                API client, auth, error handling, Mantine theme
docker/nginx/snippets/app.conf   shared nginx locations (SPA, /api, /sanctum, /health, /metrics blocked)
docker/nginx/default.conf    local, HTTP
docker/nginx/production.conf HTTPS + redirect from HTTP
docker/nginx/Dockerfile      production web image (React build + nginx)
docker/php/Dockerfile        php-fpm 8.4 with pdo_pgsql, opcache; targets development / production
docker/php/entrypoint.sh     local bootstrap (.env, composer install, key) and RUN_MIGRATIONS / RUN_SEEDERS
docker/postgres/init/        creates the scheduler_testing database on first start
docker-compose.yml           local: nginx, php, scheduler, postgres, node (build)
docker-compose.prod.yml      deploy: images with code baked in, one-shot migrate service
docs/                        SDD PDF, design PNGs
```

## Commands

```bash
docker compose up -d --build                         # start everything; migrates and seeds; http://localhost:8088
docker compose exec php php artisan migrate --seed   # run again by hand after adding a migration
docker compose exec php php artisan test             # all backend tests (real PostgreSQL)
docker compose exec php ./vendor/bin/pint            # format PHP
docker compose exec php ./vendor/bin/phpstan analyse # static analysis (Larastan)

cd web
npm ci
npm run dev        # Vite dev server on :5173, proxies /api, /sanctum, /health to nginx (:8088)
npm run build      # output to web/dist, served by nginx
npm run test       # Vitest + React Testing Library
npm run lint       # ESLint + TypeScript check
```

Run the backend tests and the frontend lint before you say a task is done.

Deploy (code baked into images, HTTPS only):

```bash
cp .env.production.example .env.production   # fill in APP_KEY, DB_PASSWORD, domains, seed passwords
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
```

`TLS_CERT_DIR` must hold `fullchain.pem` and `privkey.pem`. The `migrate` service runs migrations (and seeders when `RUN_SEEDERS=true`) before `php` and `scheduler` start.

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

- Layers: **Eloquent Model → Repository → Service → Controller**. Each Repository and Service is an interface plus a class in the same folder (`UserRepositoryInterface` + `UserRepository`, `UserServiceInterface` + `UserService`), bound in `DomainServiceProvider::$bindings`.
  - Repository: the only layer that uses Eloquent or `DB`. Returns models or plain values. No business rules.
  - Service: business rules and transactions. Depends on repository interfaces, never on Eloquent query calls.
  - Controller: thin. FormRequest in, service interface call, API Resource out. Never calls a repository.
  - Type-hint interfaces in constructors, never the concrete class.
- `Allocation` services must stay free of HTTP and Eloquent so they are unit-testable; their repositories load the data.
- The single appointment update method (core rule 2) lives in `AppointmentRepository`.
- Business errors throw a `ProblemException` subclass with a stable code; `ProblemRenderer` turns every exception into problem+json. Validation errors are `400 VALIDATION_ERROR`.
- Database names are snake_case and plural (`appointments`, `resource_allocations`, `idempotency_keys`, `outbox_messages`). JSON is camelCase.
- Status values are PHP backed enums. Allowed transitions live in one place (`AppointmentStatus::canMoveTo()`).
- Errors are RFC 9457 `application/problem+json` with a stable `code` (list in the requirements doc, "Shared rules"). Map exceptions in `bootstrap/app.php`.
- Every response carries `X-Correlation-Id`. Logs are JSON, include `correlationId`, `dealershipId`, `userId`, `appointmentId`, and never contain passwords, tokens, phone numbers or emails.
- Transactions use the default READ COMMITTED level. Do not switch to SERIALIZABLE.
- Never edit a migration that has already run; add a new one.
- Seeders run on every `docker compose up`, so they must be idempotent (`updateOrCreate` / `firstOrCreate`).

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

`docker/nginx/snippets/app.conf` holds the locations; `default.conf` (HTTP) and `production.conf` (HTTPS) include it. Its shape:

```nginx
root /var/www/web/dist;

location ^~ /metrics { return 404; }             # never public (FR-08.5)

location ~ ^/(api|sanctum|health)(/|$) {
    root /var/www/api/public;
    try_files $uri /index.php?$query_string;
}

location = /index.php {
    root /var/www/api/public;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param HTTP_X_REQUEST_ID $request_id;  # becomes the correlation id
    fastcgi_pass php:9000;
}

location / {
    try_files $uri /index.html;                   # React SPA fallback
}
```

- Locations use `expires`, not `add_header`: an `add_header` inside a location drops the security headers set above it.
- `/metrics` must not be reachable from outside the Docker network.
- PHP-FPM workers are short-lived and many: never keep locks, holds or caches in PHP memory or static properties.
- Enable opcache in the production image; keep `display_errors=Off`.

## Testing rules

- Backend tests run on **real PostgreSQL** (`DB_CONNECTION=pgsql` in `phpunit.xml`). Never use SQLite for booking tests: it has no EXCLUDE constraint.
- Name tests after the requirement they prove, for example `test_AC_04_1_only_one_of_twenty_parallel_bookings_wins`. `pint.json` turns off `php_unit_method_casing` so Pint keeps these names.
- Concurrency tests (AC-04.1, AC-04.2, AC-04.6, AC-10.3) need real parallel work. Start N separate PHP processes with `Process::pool()` (or parallel HTTP requests to nginx) that all wait on the same start time. These tests must **not** use `RefreshDatabase`, because its wrapping transaction hides data from other processes; use `DatabaseTruncation` instead.
- After each booking test, assert that no two active allocations overlap (one SQL query must return 0 rows).
- Never skip, delete or weaken a test to make the build green.

## Environment variables

`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_DRIVER=database`, `SANCTUM_STATEFUL_DOMAINS`, `HOLD_MINUTES=5`, `IDEMPOTENCY_TTL_HOURS=24`, `SEED_ADMIN_PASSWORD`, `SEED_ADVISOR_PASSWORD`, `SEED_FOREMAN_PASSWORD`, `OTEL_SDK_DISABLED=true`. Never commit `.env`.

## How to work in this repo

- Before a feature, read its section in the requirements doc and list the FR and AC IDs you will cover.
- Build in this order: F00, F01, F03, F04, F02, F05, then F06, F07, F08, then F09 to F12.
- Keep each change small and focused on one feature. Add or update tests in the same change.
- Prefer built-in Laravel and Mantine features. Ask before adding a new Composer or npm package.
- If a requirement is unclear or seems to conflict with the SDD, stop and ask instead of guessing.

## Branch workflow for agents

`develop` is the integration branch; `production` is the release branch. Branch names:

```
feature/<docs-dir>          # one per feature spec: feature/04-booking-integrity
fix/<short-description>     # fix/hold-countdown-drift
review/<short-description>  # review fixes outside a feature branch
chore/ | refactor/ | docs/ | test/
```

`<docs-dir>` is the feature's folder name under `docs/features/`: coding `docs/features/04-booking-integrity/` happens on `feature/04-booking-integrity`. Work not tied to one feature folder (cross-cutting fixes, review follow-ups) uses `fix/`, `refactor/` or `chore/` with the same flow.

1. **Before writing any code**, check the tree is clean (`git status --porcelain` empty); if it is not, stop and ask the user. Run `git fetch`, then check whether the branch exists locally or on the remote (`git branch -a --list '*feature/<docs-dir>'`).
   - Exists → `git switch feature/<docs-dir>` and continue there.
   - Missing → branch from an up-to-date `develop`: `git switch develop && git pull --ff-only`, then `git switch -c feature/<docs-dir>`.
2. Features follow the build order above (`docs/features/README.md` §1): start a feature only after the previous one is merged into `develop`.
3. Tickets of a feature (`docs/features/<docs-dir>/tickets/`) are a chain: implement them one at a time on the feature branch, in the order of `tickets/README.md`, each after the one before it is committed. Use the `implement-ticket` skill. Commit one Conventional Commit per ticket ("Commits" below).
4. **When the work is done**, the self-review ("Design pass and self-review") is clean and the checks in "Commits" pass: `git switch develop && git merge --no-ff feature/<docs-dir>`, so the feature shows up as one merge commit. Keep the feature branch.
5. On merge conflicts, or when any check fails, stop and report. Merge only with green checks, keep shared history as it is (no force, no rebase), and leave conflict resolution to the user.
6. Pushing, and anything on `production`, happen only when the user asks.

## Design pass and self-review

Green tests prove the spec's happy paths; they do not prove the design. Run the design pass before coding each ticket and the self-review before merging (step 4 above).

**Every sketch in a spec is a hypothesis.** Part B's code, queries, algorithms and file lists were written without running code. The requirement is the ACs, INVs, permissions and copy. Test every sketch against the code and data at HEAD in the ticket's design notes (`implement-ticket` step 4; the `requireTicketDesignNotes` hook refuses a ticket commit without them), build the sound version, and record the deviation in the commit message and in the spec.

**Design pass** (per ticket, before writing code):

1. **One decision, one seam.** A rule that a second endpoint or page will need (access, visibility, time rules, shared copy) gets a reusable seam the first time it is written, sized for the features the spec's `FC-` constraints already name. The seam registry is `docs/features/README.md` §6:
   - API: a dealership is loaded and checked by the `{dealership}` binding, an appointment by the `{appointment}` binding over `findVisibleTo`, never inside a service. Abilities are Gates or Policies.
   - Web: times through `formatInZone`, writes through `useIdempotencyKey` and `withIfMatch()`, role checks through `RequireRole`.
2. **Read once, decide on that row.** Decide existence and permission from one read. A value a write depends on (status, version, active flag) is read inside the write's transaction from a locked row or checked by `updateVersioned()`, never taken from an earlier read.
3. **Bounded.** Every list in a response has a limit and the real count (D-G.12). Every lookup whose 404 reveals existence is rate limited, and every log line an outsider can trigger is bounded.
4. **Adversarial inputs.** For each endpoint, list and test: missing field, wrong type, array or `null` instead of object, empty string, control characters and NUL, over-long values, a time without offset or off the 15-minute step, an id from another dealership or customer, two concurrent identical writes. Each one answers 4xx, never 500.
5. **Migrations meet real data.** Before adding a unique index or constraint, query for the rows that would violate it and stop with a message naming them. Every FK column gets a full index; partial indexes do not serve FK lookups.
6. **Extract before you copy.** When new code would repeat a block (about 10 lines of logic, a JSX block) from existing code, extract it into a shared service, component, hook or helper in a prefactor commit, then use it in both places.
7. **Copy from one place.** User-facing text comes from the spec, through the area's `copy.ts` or `web/src/shared/api/errorCopy.ts`. The web picks a sentence for an API error by its `code`, never by `detail` or `title`.

**Self-review** (done when every finding is fixed or recorded with a reason in the report):

- Before each ticket's commit, review its diff against the ticket and the spec sections it cites (`implement-ticket` step 8).
- After the tracer-bullet ticket, run `/code-review` on the branch: later tickets copy whatever the tracer establishes.
- After the last ticket, review `git diff develop...HEAD` as a whole branch with "After the last ticket: feature review" in `implement-ticket`: spec completeness, SOLID, the OWASP Top 10, extensibility against the `FC-` constraints, and the design pass above. Tickets built in separate contexts duplicate each other: look for two implementations of the same idea.
- Write the report to `.claude/reviews/<branch with "/" as "__">.md` after the last commit. The `requireBranchReview` hook refuses `git merge` of a `feature/`, `fix/`, `refactor/`, `chore/` or `review/` branch (also as a remote ref such as `origin/fix/…`) whose report is missing or older than its tip.

## Spec review: project checks

The `spec-review` skill (user-level) reads this section. Specs live in `docs/features/<nn>-<slug>/`, written in English; shared conventions are in `docs/features/README.md`. The five components map to `1-requirement-spec.md` (R), `2-business-analytics-spec.md` (B), `3-database-spec.md` (D), `4-api-spec.md` (A) and `5-web-spec.md` (W); `6-test-case.md` holds the test cases. Part B of each file is what agents implement. Add these checks to the skill's list:

- **P1 Access seams.** Every route has `auth:sanctum` and a `can:` ability or Policy per the permission matrix (README §4). Dealership routes use the `{dealership}` binding; appointment routes use the `{appointment}` binding over `findVisibleTo`. Another dealership's or customer's data answers 404, not 403.
- **P2 Roles.** Roles are the `Role` enum; each ability is one Gate registered in `AppServiceProvider::boot()`. No `$user->role === …` comparisons in controllers or services.
- **P3 Data access.** Eloquent and `DB` only in repositories; raw SQL only in migrations and repositories, with bindings. Appointments change only through `updateVersioned()`; each new migration states its pre-check and its exact `down()`.
- **P4 Booking protections.** Every write names which core rule protects it (EXCLUDE constraint, version check, `Idempotency-Key`, outbox) and the order of its steps inside one transaction (core rule 2). A write that frees a slot sets its allocations inactive in the same transaction.
- **P5 Adversarial inputs.** Each new writing endpoint has an adversarial input table in its API spec and a `TC-` row for it.
- **P6 Web building blocks.** Mantine and the shared theme; server state in TanStack Query with the `ETag` kept beside the appointment; `useIdempotencyKey`, `formatInZone`, `RequireRole`, `SlotGrid` reused, not rebuilt.
- **P7 Glossary.** Terms follow `CONTEXT.md`.
- **P8 Hypotheses.** Try to break every sketch and algorithm in part B: name a concrete scenario where it answers wrong (a change committed between the read and the write, an inactive technician, an expired hold, a cancelled booking, the same write twice, a time across midnight or a DST change). Each scenario is a finding whose fix is an invariant or edge case plus a `TC-` row. A sketch with no linked `INV-`, or a lock, query or `where` clause written only as code, is a finding.
- **P9 Decide on locked rows.** A value a write depends on (status, version, active flag, a count) is read inside the write's transaction from a locked or version-checked row, never from what an earlier read returned.
- **P10 Live rows.** Every read says how inactive rows are excluded or answered: inactive technicians, bays and service types, inactive allocations, closed or expired appointments.
- **P11 One rule, one section.** A rule (a check order, a time limit, a filter) is stated once; other sections and files point to it.
- **P12 One code per message.** Each sentence the web shows for an error has its own `code` in the API error table (README §5). The web maps codes, never messages, and a decision that tells two errors apart gets two codes.

## Feature workflow skills and other agents

**Skills** (`.claude/skills/`):

- `write-feature-spec`: grill, write the six spec files, gate them with the `spec-reviewer` subagent (which runs `spec-review` with the checks above), then split into tickets.
- `implement-ticket`: one ticket, plus the final feature review and merge.
- `implement-feature` (`/implement-feature <docs path>`): every ticket in order through the `deep-coder` subagent, then the branch review and merge.
- `web-frontend`: how the frontend conventions above land in `web/src` (Mantine 9, React Router, TanStack Query, tests); preloaded in `deep-coder`.

**Other coding agents (Codex, Antigravity).** `AGENTS.md` is a symlink to this file, and `.agents/skills` and `.agents/agents` are symlinks to `.claude/skills` and `.claude/agents`, so every agent reads the same rules, skills and subagents. In an agent's frontmatter, restrict tools with `disallowedTools`, never `tools:`: Antigravity drops an agent whose `tools:` names Claude Code tools. Antigravity gets the hooks from `.agents/hooks.json`, which runs the same `.claude/hooks/requireBranchReview.mjs` (merge gate) and `requireTicketDesignNotes.mjs` (ticket commit gate). When a skill names a Claude Code feature your agent lacks:

- A subagent (`deep-coder`, `spec-reviewer`) your agent cannot start: read `.claude/agents/<name>.md` and do its job yourself, one ticket at a time.
- `/code-review` or `/security-review`: review `git diff develop...HEAD` yourself with the feature-review checklist in `implement-ticket`.
- `$ARGUMENTS`: the path or number the user gave with the request.
- An agent without hooks (Codex): write the ticket's design notes before each ticket commit and the review report before `git merge` anyway.

Hook tests: `node --test .claude/hooks/hooks.test.mjs`.

## Commits

Conventional Commits:

```
<type>(<scope>): <summary in imperative mood>

feat(api): add appointment cancel endpoint
fix(web): keep the vehicle after a hold expires
review(api): lock the technician row before allocation
```

Types: `feat`, `fix`, `review`, `refactor`, `test`, `docs`, `chore`, `style`, `perf`, `build`, `ci`. Scopes: `api`, `web`, `docker`, `docs`, `repo`. One commit per ticket; its body names the ticket file and every `deviate` row of its design notes. The `requireTicketDesignNotes` hook lets `review`, `docs`, `chore`, `style`, `ci` and `build` commits through without design notes.

**Before every commit**, all of these pass (`docs/features/README.md` §8):

```bash
docker compose exec php php artisan test
docker compose exec php ./vendor/bin/pint --test
docker compose exec php ./vendor/bin/phpstan analyse
cd web && npm run lint && npm run test
```

Run `docker compose exec php ./vendor/bin/pint` when Pint reports changes.

**Review checklist** (each merge into `develop`):

- [ ] Small, focused change tied to one feature or ticket; tests in the same change
- [ ] Checks above green; no test skipped, deleted or weakened
- [ ] Core rules hold: EXCLUDE constraint untouched, every appointment write through `updateVersioned()`, outside systems only through the outbox
- [ ] No secrets, passwords, tokens, emails or phone numbers in code or logs
- [ ] New env vars added to `.env.example` and the "Environment variables" list above
- [ ] DB changes ship as a new migration; seeders stay idempotent
- [ ] This file updated if conventions, commands or tooling changed

## Common pitfalls

- Business time comparisons (in the past, hold expired, 2 hours before start) use the app clock, `now()` from Carbon, passed into SQL as a parameter; SQL `now()` is only for `created_at`/`updated_at` (D-G.6).
- An `Idempotency-Key` belongs to one attempt: reuse it only to resend after no response, a 5xx or 409 `REQUEST_IN_PROGRESS`; any 2xx or 4xx answer is final for that key (D-G.15).
- `Carbon::setTestNow` does not reach the child processes of a concurrency test: those tests use real future times.
- Laravel's `RefreshDatabase` hides rows from other processes; concurrency tests use `DatabaseTruncation`.
- TypeScript stays `strict`: narrow `unknown` instead of using `any`, and every `catch` (PHP or TypeScript) rethrows, maps to a `ProblemException`, or logs with context.
