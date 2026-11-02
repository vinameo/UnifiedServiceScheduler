# F00 · Accounts and sign-in · Requirement spec (RQ)

Branch: `feature/00-accounts-and-sign-in` · Priority: Must · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** Every other feature needs to know who is calling: a customer may only see their own appointments, and staff may only see their own dealership. Without accounts there is no such boundary.

**The problem.** The app has no sign-in today. Anyone can call the API, and no request carries a dealership.

**What it does.**
- A customer creates an account with email and password, plus full name and phone number, and is signed in at once.
- Customers sign in to the customer app; staff (service advisor, workshop foreman, admin) sign in to the staff web with accounts from seed data.
- Sign-out makes the old session unusable.
- Every API except sign-up, sign-in and health needs a valid session; the wrong role is refused.

**Who uses it, where.** Customers: customer app, Sign-up and Sign-in screens (mobile, 390 px). Staff: staff web, Staff sign-in screen (desktop, 1440 px).

**Inputs.** Sign-up: email, password, confirm password, full name, phone number. Sign-in: email, password.

**Outcome.** A cookie session (Sanctum SPA). After sign-up a customer lands on "Choose a service"; after sign-in staff land on their role's home page.

**What it does not do.** Forgot password, email verification, Google/Apple sign-in, two-step sign-in, a screen to create or manage staff accounts, tokens for external clients (D-00.7).

## A2. Usage steps

1. A customer opens the app → "Create an account" → fills 5 fields → "Create account" → lands on "Choose a service".
2. Next day the customer → "Sign in" → email + password → back to booking (or the page they tried to open).
3. A staff member opens `/staff` → is sent to "Staff sign-in" → signs in → lands on their role's home page.
4. Everyone has "Sign out" (customer app: on My bookings; staff web: in the header).

## A3. Screens and deviations from the drafts

These three screens are **not in the design canvas yet** (requirements F00). They use the same style as the drafts: Mantine, primary blue.8, Inter, one centred Card. Details in WEB §A.

The staff web header follows the drafts: dealership name "Saigon Auto · District 7", initials avatar + name + role, plus a "Sign out" button (not in the drafts).

## A4. Key rules in plain words

- One email = one account. `Hung@Mail.com` and `hung@mail.com` are the same.
- Password: at least 8 characters, with a letter and a number. Only the hash is stored.
- A wrong email and a wrong password get the same message: "Email or password is incorrect."
- 5 failed sign-ins in 15 minutes for one email → that email is blocked for 15 minutes, even with the right password.
- A staff account on the customer app (and the other way round) gets the same message as above.
- A session ends after 2 hours without activity.
- Customers see only their own data; staff see only their own dealership.

## A5. Out of scope

See A1 "What it does not do". Also: changing email, changing password, editing the customer profile.

## A6. Success criteria

- A customer can sign up and book in one session (measured in F02).
- No log line contains an email, password, token or phone number (BA M-00.2).
- The sign-in failure rate is visible on the monitoring screen (`sign_in_failures_total`, F08).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-00.1 | The `dealerships` table is created in F00 (not F01) because staff accounts must belong to a dealership. F01 adds opening hours and the rest. |
| D-00.2 | `users` gets `role` and `dealership_id`. Staff must have a dealership; customers must not (CHECK constraint). |
| D-00.3 | A `customers` table, 1–1 with `users` (`user_id` unique). Email lives only on `users`. Every customer has an account. |
| D-00.4 | Password: at least 8 characters and at most **72 bytes** (UTF-8; bcrypt only uses the first 72 bytes), ≥ 1 letter, ≥ 1 digit. Weak → `422 WEAK_PASSWORD`; missing or > 72 bytes → `400 VALIDATION_ERROR`. |
| D-00.5 | Phone: digits, spaces, `+`, `-`, `.`, `(`, `)` allowed; stored normalised (digits only, leading `+` kept); 9–15 digits. Not unique. |
| D-00.6 | Sign-in lock per email: failed attempts counted in the shared cache (`CACHE_STORE=database`), keyed by the SHA-256 of the normalised email. Plus a limit of 30 requests/minute per IP on sign-in and sign-up. |
| D-00.7 | Only Sanctum SPA cookie sessions. Personal access tokens for external clients (second half of FR-00.5) are deferred: the `personal_access_tokens` table exists, no endpoint issues tokens yet. |
| D-00.8 | Seed passwords come from `SEED_ADMIN_PASSWORD`, `SEED_ADVISOR_PASSWORD`, `SEED_FOREMAN_PASSWORD` and the new `SEED_CUSTOMER_PASSWORD`. An empty variable → the seeder skips that account and prints a warning; there is no default password in code. |
| D-00.9 | Seed 3 staff (Minh Anh: advisor, Quang Tín: foreman, one admin) for "Saigon Auto · District 7", and 2 customers with accounts (Nguyễn Văn Hùng, Lâm Khánh). Vehicles are seeded in F01. |
| D-00.10 | A sign-in on the wrong portal (staff on the customer app, customer on the staff web) counts as a failure and returns the same `401 INVALID_CREDENTIALS`. |
| D-00.11 | Home after sign-in: customer → `/book`; advisor → `/staff` (New booking); foreman → `/staff/workshop`; admin → `/staff/master-data`. A page that does not exist yet falls back to `/staff`. A valid `returnTo` wins. |
| D-00.12 | Sign-up/sign-in/sign-out need no `Idempotency-Key` (README D-G.8). |
| D-00.13 | Emails are ASCII only (`email:rfc` plus `^[\x21-\x7E]+$`); non-ASCII → 400. Lower-casing is then the same in PHP and PostgreSQL, so `CHECK (email = lower(email))` can never fail on valid input. |
| D-00.14 | Sign-up answers 409 `EMAIL_ALREADY_USED` for a known email, so sign-up reveals which emails have an account (sign-in does not). Accepted for usability (BA ADR-00.5); limited by the per-IP limit and watched by M-00.5. |

## A8. Open questions (with defaults)

| ID | Question | Default if nobody answers |
| --- | --- | --- |
| Q-00.a | Do we need personal tokens for external clients in this version? | No (D-00.7). |
| Q-00.b | Does a customer pick a dealership at sign-up? | No. The customer picks one when booking (F02). |
| Q-00.c | What does "Signed in as" show on My bookings (the draft shows a masked phone)? | The account email, because customers sign in with email. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state (checked at `0e39f86` + working tree, 2026-11-02)

| Exists | File |
| --- | --- |
| Tables `users` (bigint id, `name`, unique `email`, `password`), `sessions`, `personal_access_tokens` | `api/database/migrations/0001_01_01_000000_create_users_table.php`, `2026_11_02_085405_create_personal_access_tokens_table.php` |
| `User` model (`password` cast `hashed`, `password` hidden) | `api/app/Models/User.php` |
| `UserRepository::findByEmail`, `UserService` + interfaces, bindings | `api/app/Domain/Auth/**`, `api/app/Providers/DomainServiceProvider.php` |
| Sanctum `statefulApi()`; error renderer: `AuthenticationException` → 401 `UNAUTHENTICATED`, `AuthorizationException` → 403 `FORBIDDEN`, `ThrottleRequestsException` → 429 `TOO_MANY_ATTEMPTS`, `ValidationException` → 400 | `api/bootstrap/app.php`, `api/app/Support/Problem/ProblemRenderer.php` |
| `ProblemException` (base for business errors) | `api/app/Support/Problem/ProblemException.php` |
| Empty `api/v1` routes | `api/routes/api.php` |
| Web: axios `api` (withCredentials, XSRF), `ensureCsrfCookie()`, `toProblem()`, router `/` and `/staff`, `StaffLayout` | `web/src/shared/api/client.ts`, `problem.ts`, `web/src/router.tsx`, `web/src/staff/StaffLayout.tsx` |
| Env: `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `SEED_*_PASSWORD` (no CUSTOMER yet) | `api/.env.example` |

Missing: roles, dealerships, customers, auth routes, Gates, sign-in screens.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-00.1 | Migrations: `dealerships`, change `users`, create `customers` (DB §B). |
| S-00.2 | `Role` enum, Gates `customer`, `staff`; the place later features add abilities. |
| S-00.3 | 5 endpoints: sign-up, customer sign-in, staff sign-in, sign-out, `GET /me` (API §B). |
| S-00.4 | Sign-in limits per email and per IP. |
| S-00.5 | Seeders for the dealership, staff and customers (safe to rerun). |
| S-00.6 | Web: 3 sign-in/sign-up screens, `useMe`, `RequireRole`, global 401 handling, staff header, `errorCopy.ts`. |
| S-00.7 | Add `SEED_CUSTOMER_PASSWORD` to `.env.example`. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-00.1 | Emails are stored trimmed and lower case. No two users share an email. | Normalised in the FormRequest before validation (ASCII only, D-00.13); the DB has `CHECK (email = lower(email))` and the existing unique index. Two sign-ups with the same email at once: one loses on the unique index (`23505`) → mapped to `409 EMAIL_ALREADY_USED`, never 500. |
| INV-00.2 | Passwords are stored only as a hash (`Hash`, bcrypt), never in a response, log, exception message or `api_events`. | Existing `hashed` cast; the request log never writes the body of auth routes. |
| INV-00.3 | Sign-up creates `users` + `customers` in **one** transaction; on any failure no row is left. | `DB::transaction` around both inserts. |
| INV-00.4 | The sign-in error is identical for: unknown email, wrong password, wrong portal. Response time does not reveal whether the email exists: with no user, `Hash::check` still runs against a dummy hash. | In `SignInService`. |
| INV-00.5 | After 5 failures in 15 minutes for one email, every attempt (even correct) gets `429 TOO_MANY_ATTEMPTS` with `Retry-After` until the 15 minutes pass. A correct sign-in (while not locked) clears the counter. | Every attempt first **increments** the counter atomically (`RateLimiter::hit`, cache shared by all PHP-FPM workers, never PHP memory), then checks it: a value above 5 → 429 without checking the password. A correct sign-in then clears it. So parallel attempts can never run more than 5 password checks per window. |
| INV-00.6 | `role` is one of 4 values; staff have a `dealership_id`, customers do not. | CHECK constraints in the DB; sign-up always sets `role = CUSTOMER` and ignores `role`/`dealershipId` in the body. |
| INV-00.7 | The customer id always comes from `auth()->user()->customer`, never from the body or query. | Seam `CurrentCustomer` (B6). Applies to every later feature. |
| INV-00.8 | Sign-in regenerates the session id (no session fixation). Sign-out invalidates the session and regenerates the CSRF token; `GET /me` with the old cookie → 401. | `session()->regenerate()` / `invalidate()` + `regenerateToken()`. |
| INV-00.9 | There is no staff sign-up route. | Route list; a test checks there is no path under `auth/staff/` other than `sign-in`. |
| INV-00.10 | Role permissions go through Gates/Policies; controllers never compare roles directly. | Review + a simple architecture test (grep for `->role ===` in `app/Http/Controllers` must be empty). |

Edge cases of the write decisions:

| Decision | Edge case | TC |
| --- | --- | --- |
| Account creation (INV-00.1, 00.3) | Different letter case; two sign-ups with one email at once; the `customers` insert fails | TC-00-02, TC-00-03, TC-00-31 |
| Sign-in (INV-00.4, 00.5) | Unknown email; wrong portal; right password while locked; lock ends after 15 minutes; success clears the counter; 10 attempts in parallel | TC-00-06…TC-00-11, TC-00-32 |
| Input (D-00.4, D-00.13) | Non-ASCII email; password of 30 characters but > 72 bytes | TC-00-20 |
| Permissions (INV-00.7, 00.10) | Body carries `customerId`/`role`; customer calls a staff route | TC-00-05, TC-00-13 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/database/migrations/2026_11_03_000001_create_dealerships_table.php` | New. DB-00.1. |
| `api/database/migrations/2026_11_03_000002_add_role_and_dealership_to_users.php` | New. DB-00.2. |
| `api/database/migrations/2026_11_03_000003_create_customers_table.php` | New. DB-00.3. |
| `api/app/Domain/Auth/Role.php` | New. Enum `CUSTOMER`, `SERVICE_ADVISOR`, `WORKSHOP_FOREMAN`, `ADMIN`; `isStaff()`, `label()`. |
| `api/app/Models/User.php`, `Customer.php`, `Dealership.php` | Change/new. Cast `role` → `Role`; relations `customer`, `dealership`; lower-case email mutator. |
| `api/app/Domain/Auth/Services/SignUpService.php`, `SignInService.php` (+ interfaces, bindings) | New. |
| `api/app/Domain/Auth/Exceptions/*.php` | `InvalidCredentials` (401), `EmailAlreadyUsed` (409), `WeakPassword` (422), `TooManyAttempts` (429, `Retry-After` header) — all extend `ProblemException`. |
| `api/app/Domain/Auth/CurrentCustomer.php` | New. Seam that returns the signed-in user's customer; 403 when the user is not a customer. |
| `api/app/Providers/AppServiceProvider.php` (`boot()`, README D-G.14) | Gates `customer`, `staff`. The only place abilities are defined; later features add theirs here. |
| `api/app/Http/Requests/Auth/*.php` | `CustomerSignUpRequest`, `SignInRequest`. |
| `api/app/Http/Controllers/Api/V1/AuthController.php`, `MeController.php` | New, thin. |
| `api/app/Http/Resources/MeResource.php` | New. API §B2. |
| `api/routes/api.php` | Auth routes, `GET /me` (`auth:sanctum`), rate limiter `auth-ip`. |
| `api/database/seeders/AccountSeeder.php`, `DatabaseSeeder.php` | Seed dealership, staff, customers (D-00.8, D-00.9) with `updateOrCreate`. |
| `api/database/factories/*` | `UserFactory` states `customer()`, `advisor($d)`, `foreman($d)`, `admin($d)`; `DealershipFactory`, `CustomerFactory`. |
| `api/.env.example`, `docker-compose.yml` | Add `SEED_CUSTOMER_PASSWORD`. |
| `web/src/**` | WEB §B. |

## B5. Contracts

API details in [4-api-spec.md](4-api-spec.md). Data in [3-database-spec.md](3-database-spec.md). Screens in [5-web-spec.md](5-web-spec.md).

Service shapes (_illustrative_, serve INV-00.3, 00.4, 00.5):

```php
interface SignUpServiceInterface {
    public function signUp(string $email, string $password, string $fullName, string $phone): User; // throws EmailAlreadyUsed, WeakPassword
}
interface SignInServiceInterface {
    public function signIn(string $email, string $password, Portal $portal): User; // Portal::CUSTOMER|STAFF; throws InvalidCredentials, TooManyAttempts
}
```

## B6. Seams

| Seam | Used for | Later features |
| --- | --- | --- |
| `Role` enum + Gates in one provider | Role permissions (README §4) | Every feature adds abilities here |
| `CurrentCustomer` | INV-00.7 | F02, F05, F07, F09, F10 |
| `UserFactory` states | Tests | Every feature |
| Web `useMe()`, `RequireRole`, `onUnauthenticated` interceptor | Route guards, 401 redirects | Every screen |
| Web `errorCopy.ts` | Copy by `code` (README §5) | Every screen |

FCs from BA: FC-00.1 (staff account screen later) → `AccountSeeder` shares a `CreateStaffAccount` action; FC-00.2 (tokens for external clients) → deferred, Sanctum already supports it, `Portal` enum can grow.

## B7. Algorithms

**Sign-up**
1. Normalise email (trim, lower) and phone (D-00.5). Validate format → 400.
2. Check password strength → 422 `WEAK_PASSWORD`.
3. Email already exists → 409 `EMAIL_ALREADY_USED`.
4. Transaction: insert `users` (role CUSTOMER, name = fullName), insert `customers`. A unique violation here → 409 `EMAIL_ALREADY_USED`.
5. Sign in (guard `web`), `session()->regenerate()`. Return 201 `MeResource`.

**Sign-in (portal `CUSTOMER` or `STAFF`)**
1. Validate format → 400.
2. Rate-limit key `sha256(email)`: `hit` (atomic increment, 15-minute window) **first**; the new value > 5 → 429 + `Retry-After`, no password check.
3. Find the user by email. None → `Hash::check` against a dummy hash, 401 (the attempt is already counted).
4. Wrong password, or the role does not match the portal (CUSTOMER ↔ customer portal, staff ↔ staff portal) → 401 (already counted).
5. Correct → clear the counter, `Auth::login`, `session()->regenerate()`, 200 `MeResource`.

**Sign-out**: `Auth::guard('web')->logout()`, `session()->invalidate()`, `session()->regenerateToken()`, 204.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| Missing field, bad email, confirm password differs, password > 72, bad phone | 400 | `VALIDATION_ERROR` |
| Weak password | 422 | `WEAK_PASSWORD` |
| Email already used | 409 | `EMAIL_ALREADY_USED` |
| Wrong email/password/portal | 401 | `INVALID_CREDENTIALS` |
| Locked by email, or over 30/minute by IP | 429 | `TOO_MANY_ATTEMPTS` |
| No session / expired / signed out | 401 | `UNAUTHENTICATED` |
| Wrong role for the route | 403 | `FORBIDDEN` |
| Missing/wrong CSRF token | 419 | `CSRF_TOKEN_MISMATCH` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-00.1 | No account for `hung@mail.com` | Sign up with valid data | 201, the customer is signed in (`GET /me` 200), a `customers` row exists | TC-00-01 |
| AC-00.2 | An account for `hung@mail.com` exists | Sign up again with `HUNG@mail.com` | 409 `EMAIL_ALREADY_USED` | TC-00-02 |
| AC-00.3 | Sign-up form | Password is `abc123` | 422 `WEAK_PASSWORD`, nothing saved | TC-00-04 |
| AC-00.4 | A customer account | Sign in with the wrong password | 401 `INVALID_CREDENTIALS`, message "Email or password is incorrect" | TC-00-06, TC-00-21 |
| AC-00.5 | 5 failed sign-ins in the last 15 minutes | Sign in again, even with the right password | 429 `TOO_MANY_ATTEMPTS` | TC-00-09 |
| AC-00.6 | A seeded service advisor account | Sign in on the customer app | 401 `INVALID_CREDENTIALS` | TC-00-08 |
| AC-00.7 | A signed-in customer | Call a staff-only route | 403 `FORBIDDEN` | TC-00-13 (the real route `GET /dealerships/{id}/appointments` is proved again in TC-05-10) |
| AC-00.8 | Customer A is signed in | Open customer B's appointment by id | 404 `APPOINTMENT_NOT_FOUND` | Proved in TC-05-04 (the endpoint comes with F05) |
| AC-00.9 | A signed-in user | Sign out, then call `GET /me` with the old session | 401 `UNAUTHENTICATED` | TC-00-12 |
| AC-00.10 (new) | 15 minutes after a lock | Sign in correctly | 200 | TC-00-10 |
| AC-00.11 (new) | The seeder runs twice | Count users | No duplicates; an empty password variable → that account is not created | TC-00-15, TC-00-16 |
| AC-00.12 (new) | A signed-out customer opens `/my-bookings`; signed-out staff open `/staff/appointments` | Page loads | Redirect to the right sign-in screen with `returnTo` | TC-00-23, TC-00-24 |

## B10. Implementation order

1. Ticket 01 (prefactor): `Role` enum, empty Gates in `AppServiceProvider::boot()`, factories; nothing users can see.
2. Migrations + models + seeder.
3. Tracer bullet: staff sign-in + `GET /me` + Staff sign-in screen + header.
4. Sign-up + customer sign-in + customer screens.
5. Rate limits, sign-out, 401 interceptor.
6. Acceptance.

## B11. Definition of Done

- Every TC in [6-test-case.md](6-test-case.md) is green or run by hand with the result recorded.
- README §8 is green.
- `php artisan migrate:fresh --seed` runs twice in a row without errors.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-00.1 | The rate limit lives in worker memory → each worker counts alone | Cache store `database` (shared). Tests use `CACHE_STORE=array` in one process, so TC-00-33 checks the configured store and TC-00-32 runs real parallel processes on the `database` store |
| R-00.2 | Sanctum stateful does not recognise a request whose domain is missing from `SANCTUM_STATEFUL_DOMAINS` → confusing 401 | Documented in the dev README; the Vite proxy uses `localhost:5173`, already in the env |
| R-00.3 | The default log writes exceptions that contain the email (for example a unique `QueryException`) | Do not `report()` mapped `23505` errors; F08 adds a log filter |
