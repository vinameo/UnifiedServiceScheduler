# F00 · Accounts and sign-in · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Account and sign-in rules** (duplicate email, weak password, 5-failure lock, wrong portal) are tested through HTTP on real PostgreSQL, because they rely on the unique index, CHECK constraints and the shared cache.
- **Password and normalisation rules** get unit tests (fast, many cases).
- **Screens** get component tests (Vitest + Testing Library) with a fake API.
- **One manual run** through Nginx proves cookies, CSRF and redirects work for real.

## A2. Manual scenarios

| # | Steps | Expected |
| --- | --- | --- |
| M1 | Open `http://localhost:8088/` while signed out | Redirect to `/sign-in`. |
| M2 | "Create an account" → email `qa1@example.test`, password `secret12`, confirm, name, phone `0903 111 222` → "Create account" | Booking page; still signed in after F5. |
| M3 | Sign out, sign up again with `QA1@example.test` | Alert "An account with this email already exists. Sign in instead." |
| M4 | Sign in with a wrong password 5 times, then the right one | Tries 1–5: "Email or password is incorrect."; try 6: "Too many attempts. Try again in 15 minutes." |
| M5 | Open `/staff/appointments` while signed out | Redirect to `/staff/sign-in?returnTo=%2Fstaff%2Fappointments`. |
| M6 | Sign in as `minhanh@saigonauto.test` | Header: "Saigon Auto · District 7", "Minh Anh", "Service advisor"; no Monitoring in the menu. |
| M7 | Use the advisor account on `/sign-in` (customer app) | "Email or password is incorrect." |
| M8 | Sign in as admin | Menu has Master data, Notifications, Monitoring. |

## A3. Test accounts and data

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@saigonauto.test` | `SEED_ADMIN_PASSWORD` |
| Service advisor | `minhanh@saigonauto.test` | `SEED_ADVISOR_PASSWORD` |
| Workshop foreman | `quangtin@saigonauto.test` | `SEED_FOREMAN_PASSWORD` |
| Customer | `hung@example.test`, `khanh@example.test` | `SEED_CUSTOMER_PASSWORD` |

Automated tests use factories, not the seed accounts.

## A4. Not tested

- Sending email/SMS (not part of F00).
- HTTPS (production Nginx config, outside automated tests).
- Personal tokens (deferred, D-00.7).

## A5. Exit criteria

Every TC below is green; M1–M8 pass by hand, with the result recorded in table A2 (add a "Result, date, tester" column).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-00.1 | TC-00-01 |
| AC-00.2 | TC-00-02 |
| AC-00.3 | TC-00-04 |
| AC-00.4 | TC-00-06, TC-00-21 |
| AC-00.5 | TC-00-09, TC-00-29 |
| AC-00.6 | TC-00-08 |
| AC-00.7 | TC-00-13 (and TC-05-10 in F05) |
| AC-00.8 | TC-05-04 (F05) |
| AC-00.9 | TC-00-12 |
| AC-00.10 | TC-00-10 |
| AC-00.11 | TC-00-15, TC-00-16 |
| AC-00.12 | TC-00-23, TC-00-24 |
| INV-00.1 | TC-00-02, TC-00-03, TC-00-14 |
| INV-00.2 | TC-00-17 |
| INV-00.3 | TC-00-04, TC-00-31 |
| INV-00.4 | TC-00-07, TC-00-08 |
| INV-00.5 | TC-00-09, TC-00-10, TC-00-11, TC-00-32 |
| R-00.1 (shared cache) | TC-00-32, TC-00-33 |
| D-00.4, D-00.13 | TC-00-20 |
| WEB §B3 (one source of rules) | TC-00-34 |
| INV-00.6 | TC-00-05, TC-00-14 |
| INV-00.7 | TC-00-05 |
| INV-00.8 | TC-00-12, TC-00-18 |
| INV-00.9 | TC-00-26 |
| INV-00.10 | TC-00-27 |
| DB-00.1–00.3 | TC-00-14 |
| API §B4 (adversarial input) | TC-00-20, TC-00-30 |
| API §B5 (IP limit) | TC-00-19 |
| WEB-00.1–00.7 | TC-00-21…TC-00-25, TC-00-29 |
| F00 copy (README §5) | TC-00-21, TC-00-22, TC-00-29 |

## B2. Test cases

"Given" uses `User::factory()` and `Dealership::factory()` by default. "Where" is a path under `api/tests/` or `web/src/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-00-01 | AC-00.1 | Integration | No user `hung@mail.com` | Valid `POST /auth/customer/sign-up`, then `GET /me` in the same session | 201, `Me` body with role `CUSTOMER`; `GET /me` 200; 1 `customers` row with the right `user_id` | Not signed in after sign-up, or no `customers` row | `Feature/Auth/CustomerSignUpTest::test_AC_00_1_sign_up_creates_account_customer_and_session` |
| TC-00-02 | AC-00.2, INV-00.1 | Integration | `hung@mail.com` exists | Sign up with `HUNG@mail.com` | 409 `EMAIL_ALREADY_USED`; still 1 user | Email not lower-cased | `…CustomerSignUpTest::test_AC_00_2_email_is_case_insensitive` |
| TC-00-03 | INV-00.1 | Integration | Repository replaced by one that always says "email is free", user already exists | Sign up with that email | 409 `EMAIL_ALREADY_USED` (not 500); still 1 user | `23505` not mapped | `…CustomerSignUpTest::test_INV_00_1_unique_violation_maps_to_409` |
| TC-00-04 | AC-00.3, INV-00.3 | Integration | Valid form except password `abc123` | Sign up | 422 `WEAK_PASSWORD`; `users`, `customers` counts unchanged | Strength checked after insert | `…CustomerSignUpTest::test_AC_00_3_weak_password_saves_nothing` |
| TC-00-05 | INV-00.6, INV-00.7 | Integration | — | Sign up with `"role":"ADMIN","dealershipId":"<id>"` | 201; user is `CUSTOMER`, `dealership_id` NULL | `role` mass-assigned | `…CustomerSignUpTest::test_INV_00_6_role_in_body_is_ignored` |
| TC-00-06 | AC-00.4 | Integration | Customer with password `secret12` | Customer sign-in with `wrong123` | 401 `INVALID_CREDENTIALS`, `title` = "Email or password is incorrect." | Different message | `Feature/Auth/SignInTest::test_AC_00_4_wrong_password_is_401` |
| TC-00-07 | INV-00.4 | Integration | — | Sign in with an unknown email; sign in with a real email and wrong password | Bodies identical except `correlationId`; both call `Hash::check` (spy) | Early return when no user | `…SignInTest::test_INV_00_4_unknown_email_and_wrong_password_look_the_same` |
| TC-00-08 | AC-00.6, INV-00.4 | Integration | Advisor and customer | Advisor on the customer portal; customer on the staff portal | Both 401 `INVALID_CREDENTIALS` | Portal not checked | `…SignInTest::test_AC_00_6_wrong_portal_is_401` (data provider) |
| TC-00-09 | AC-00.5, INV-00.5 | Integration | Customer | 5 failures, then the right password | Try 6: 429 `TOO_MANY_ATTEMPTS` with `Retry-After` ≤ 900 | Password checked before the counter | `…SignInTest::test_AC_00_5_sixth_attempt_is_blocked_even_with_right_password` |
| TC-00-10 | AC-00.10, INV-00.5 | Integration | As TC-00-09 | `travel(16)->minutes()`, correct sign-in | 200 | Permanent lock | `…SignInTest::test_AC_00_10_lock_ends_after_15_minutes` |
| TC-00-11 | INV-00.5 | Integration | Customer | 4 failures, 1 success, 4 failures | Last try 401 (not 429) | Success does not clear the counter | `…SignInTest::test_INV_00_5_success_clears_the_counter` |
| TC-00-12 | AC-00.9, INV-00.8 | Integration | Signed-in customer | Sign out, then `GET /me` with the old cookie | 204; then 401 `UNAUTHENTICATED` | No `invalidate()` | `Feature/Auth/SignOutTest::test_AC_00_9_old_session_is_rejected_after_sign_out` |
| TC-00-13 | AC-00.7, INV-00.10 | Integration | The test registers temporary routes `GET /api/v1/_test/staff-only` (`can:staff`) and `…/customer-only` (`can:customer`) | Customer calls staff-only; advisor calls customer-only; no session calls both | 403 `FORBIDDEN`, 403, 401 | Wrong Gate | `Feature/Auth/GatesTest::test_AC_00_7_customer_cannot_use_staff_routes` |
| TC-00-14 | INV-00.1, INV-00.6, DB-00.2, DB-00.3 | Integration | — | Direct inserts: advisor without dealership; customer with dealership; email `A@b.c`; phone `abc` | Each insert throws `QueryException` `23514` | Missing CHECK | `Feature/Auth/AccountSchemaTest::test_DB_00_2_checks_reject_invalid_rows` |
| TC-00-15 | AC-00.11 | Integration | All 4 password variables set | `db:seed` twice | 1 dealership, 3 staff, 2 customers; second run leaves the `password` hash unchanged | No `updateOrCreate` | `Feature/Auth/AccountSeederTest::test_AC_00_11_seeder_is_safe_to_run_twice` |
| TC-00-16 | AC-00.11, D-00.8 | Integration | `SEED_ADMIN_PASSWORD` empty | `db:seed` | No admin; the other accounts exist | Default password in code | `…AccountSeederTest::test_AC_00_11_empty_password_env_skips_the_account` |
| TC-00-17 | INV-00.2 | Integration | Test log channel writes to an array | Sign-up, failed sign-in, `GET /me` | No response has `password`; no log line contains an email or password | Request body logged | `Feature/Auth/NoSecretsTest::test_INV_00_2_no_password_or_email_in_responses_or_logs` |
| TC-00-18 | INV-00.8 | Integration | Session id before sign-in | Sign in | Session id after differs from before | No `regenerate()` | `…SignInTest::test_INV_00_8_sign_in_regenerates_the_session` |
| TC-00-19 | API §B5 | Integration | — | 31 sign-ins (different emails) from one IP within 1 minute | Try 31: 429 | Missing `throttle:auth-ip` | `…SignInTest::test_ip_limit_blocks_the_31st_request` |
| TC-00-20 | API §B4 | Integration | — | Each row of the adversarial input table | Status and `code` as in the table | A validation rule missing | `Feature/Auth/AuthAdversarialInputTest::test_adversarial_input` (data provider named per row) |
| TC-00-21 | AC-00.4, WEB-00.5 | Web component | Fake API returns 401 `INVALID_CREDENTIALS` | Submit Sign-in | `role=alert` shows "Email or password is incorrect."; password empty, email kept | Server `detail` shown | `web/src/customer/auth/SignInPage.test.tsx` |
| TC-00-22 | WEB §B3 | Web component | Fake API returns 400 (`errors.phone`), 422 `WEAK_PASSWORD`, 409 in turn | Submit Sign-up | Error on Phone; error on Password; Alert with a "Sign in" link | `errors` not mapped | `web/src/customer/auth/SignUpPage.test.tsx` |
| TC-00-23 | AC-00.12, WEB-00.2 | Web component | `GET /me` → 401 | Render `/my-bookings` | At `/sign-in?returnTo=%2Fmy-bookings` | Guard sends to the wrong area | `web/src/shared/auth/RequireRole.test.tsx` |
| TC-00-24 | AC-00.12, WEB-00.4 | Web component | `GET /me` → 401 | Render `/staff/appointments`; then sign in with `returnTo=//evil.com` | At `/staff/sign-in?returnTo=…`; after sign-in lands on the role home, never `//evil.com` | Open redirect | `…RequireRole.test.tsx`, `web/src/shared/auth/returnTo.test.ts` |
| TC-00-25 | WEB §B3 menu | Web component | `me` is an advisor, then an admin | Render `StaffLayout` | Advisor: no Monitoring; admin: Master data, Notifications, Monitoring | Menu not filtered by role | `web/src/staff/StaffLayout.test.tsx` |
| TC-00-26 | INV-00.9 | Integration | — | Read `Route::getRoutes()` | No route matches `auth/staff/` other than `sign-in` | A staff sign-up route exists | `Feature/Auth/RouteListTest::test_INV_00_9_no_staff_sign_up_route` |
| TC-00-27 | INV-00.10 | Unit | — | Scan `app/Http/Controllers/**/*.php` | No `->role ===` and no `Role::` comparison | A controller checks roles with `if` | `Unit/Architecture/NoRoleChecksInControllersTest` |
| TC-00-28 | All | Manual | Stack running via `docker compose up` | M1–M8 (Part A) | As table A2 | — | Record in table A2 |
| TC-00-29 | AC-00.5, WEB §B3 | Web component | Fake API returns 429, `Retry-After: 840` | Submit Sign-in | "Too many attempts. Try again in 14 minutes." | `Retry-After` ignored | `…SignInPage.test.tsx` |
| TC-00-30 | API §B4 | Integration | Stateful request without the XSRF header | `POST /auth/customer/sign-in` | 419 `CSRF_TOKEN_MISMATCH` | CSRF off | `…AuthAdversarialInputTest::test_missing_xsrf_is_419` |
| TC-00-31 | INV-00.3 | Integration | The `customers` insert is forced to fail (fake repository breaks the phone CHECK) | Sign up | 500; no `users` row left | The two inserts are not in one transaction | `…CustomerSignUpTest::test_INV_00_3_sign_up_is_all_or_nothing` |
| TC-00-32 | INV-00.5 | Concurrency | Customer; `CACHE_STORE=database` passed to the child processes | 10 processes sign in with a wrong password at the same moment (`Parallel`) | At most 5 responses are 401, the rest 429; at most 5 `Hash::check` calls (counted in the request log lines) | Counter read before the check and increased after | `Feature/Auth/ParallelSignInTest::test_INV_00_5_parallel_attempts_cannot_bypass_the_lock` |
| TC-00-33 | R-00.1 | Unit | — | Read `config('cache.default')` with the `.env.example` values | `database` | Cache in worker memory or a per-process store | `Unit/Config/SharedCacheStoreTest` |
| TC-00-34 | WEB §B3 | Web unit | — | `rules.ts` against each sign-up row of API §B4 | Same accept/reject as the API table | Client and server rules drift | `web/src/shared/auth/rules.test.ts` |

## B3. Fixtures

- `UserFactory` states `customer()`, `advisor(Dealership)`, `foreman(Dealership)`, `admin(Dealership)`; `customer()` also creates the `customers` row.
- `DealershipFactory` defaults to `Asia/Ho_Chi_Minh`.
- Web: `web/src/test/apiMock.ts` — a hand-written mock adapter for the axios `api` (no new package), reused by every later component test.

## B4. Regression

Must stay green unchanged: `Feature/Platform/*` (Health, CorrelationId, ProblemDetails, DomainBindings), `Unit/Support/ProblemTest`, `web/src/router.test.tsx` (updated for new routes, same meaning), `web/src/shared/api/problem.test.ts`.
