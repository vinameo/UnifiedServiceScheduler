# Feature Review · `feature/00-accounts-and-sign-in`

Date: 2026-11-03  
Branch: `feature/00-accounts-and-sign-in`  
Spec: `docs/features/00-accounts-and-sign-in/`

---

## 1. Spec Completeness

- **All Acceptance Criteria (AC-00.1 to AC-00.12):** Fully implemented and covered by automated feature and unit tests.
  - AC-00.1: Customer sign-up creates user, customer, and session (`CustomerSignUpTest::test_AC_00_1_sign_up_creates_account_customer_and_session`).
  - AC-00.2: Email is case-insensitive and normalized (`CustomerSignUpTest::test_AC_00_2_email_is_case_insensitive`).
  - AC-00.3: Weak passwords rejected with 422 `WEAK_PASSWORD` (`CustomerSignUpTest::test_AC_00_3_weak_password_saves_nothing`).
  - AC-00.4: Invalid credentials return 401 with generic message (`SignInTest::test_AC_00_4_wrong_password_is_401`, `SignInPage.test.tsx`).
  - AC-00.5: 5 failed sign-in attempts lock account for 15 minutes with 429 (`SignInTest::test_AC_00_5_sixth_attempt_is_blocked_even_with_right_password`).
  - AC-00.6: Portal isolation rejects staff on customer portal and vice versa (`SignInTest::test_AC_00_6_wrong_portal_is_401`).
  - AC-00.7: Gate-based route authorization separates staff and customers (`GatesTest::test_AC_00_7_customer_cannot_use_staff_routes`).
  - AC-00.9: Sign-out destroys session, rejecting subsequent requests with 401 (`SignOutTest::test_AC_00_9_old_session_is_rejected_after_sign_out`).
  - AC-00.10: Lock automatically releases after 15 minutes (`SignInTest::test_AC_00_10_lock_ends_after_15_minutes`).
  - AC-00.11: Seeders are idempotent and skip accounts when password env is unset (`AccountSeederTest`).
  - AC-00.12: Unauthenticated route access redirects with preserved safe `returnTo` (`RequireRole.test.tsx`, `returnTo.test.ts`).
- **All Invariants (INV-00.1 to INV-00.10):**
  - INV-00.1: Postgres UNIQUE constraint and CHECK constraints enforce lower-case email and valid phone numbers (`AccountSchemaTest`).
  - INV-00.2: No password or email leaked in responses or log channels (`NoSecretsTest`).
  - INV-00.3: Sign-up is atomic across `users` and `customers` in a transaction (`CustomerSignUpTest::test_INV_00_3_sign_up_is_all_or_nothing`).
  - INV-00.4: Constant-time check on unknown email prevents enumeration (`SignInTest::test_INV_00_4_unknown_email_and_wrong_password_look_the_same`).
  - INV-00.5: Atomic rate limiter counter increment before bcrypt; parallel requests cannot bypass the lock (`ParallelSignInTest::test_INV_00_5_parallel_attempts_cannot_bypass_the_lock`).
  - INV-00.6: Customer role cannot be elevated via request body payload (`CustomerSignUpTest::test_INV_00_6_role_in_body_is_ignored`).
  - INV-00.7: Customer cannot be assigned to a dealership on sign-up (`CustomerSignUpTest`).
  - INV-00.8: Session id regenerates on sign-in and sign-out (`SignInTest::test_INV_00_8_sign_in_regenerates_the_session`, `SignOutTest`).
  - INV-00.9: No staff sign-up route exists (`RouteListTest::test_INV_00_9_no_staff_sign_up_route`).
  - INV-00.10: Zero role comparisons or role enum checks in controllers (`NoRoleChecksInControllersTest`).
- **All 11 Tickets:** Marked `done` with complete `.design.md` design notes tables.
- **Manual Acceptance Scenarios (M1 to M8):** Verified on live environment and results recorded in Table A2 of `6-test-case.md`.

---

## 2. SOLID Architecture

- **S (Single Responsibility):**
  - Controllers (`AuthController`, `MeController`) parse HTTP requests, invoke domain service interfaces, and format responses via JSON resources.
  - Domain Services (`SignInService`, `SignUpService`) encapsulate authentication business rules, rate limiting, and password complexity.
  - Domain Repositories (`UserRepository`, `CustomerRepository`) encapsulate database access and Eloquent operations.
- **O (Open/Closed):**
  - Roles managed via PHP 8.4 backed enum `Role` with domain helper methods.
  - Authorization handled via Gates (`customer`, `staff`) in `AppServiceProvider`, extensible without touching controllers.
- **L (Liskov Substitution):**
  - Concrete repositories and services fully honor their contracts in `UserRepositoryInterface`, `CustomerRepositoryInterface`, `SignInServiceInterface`, `SignUpServiceInterface`.
- **I (Interface Segregation):**
  - Domain interfaces expose only the exact methods consumed by their callers.
- **D (Dependency Inversion):**
  - Controllers depend exclusively on domain service interfaces.
  - Services depend on repository interfaces.
  - All bindings configured cleanly in `DomainServiceProvider`.

---

## 3. Security Review (OWASP Top 10)

- **A01 Broken Access Control:**
  - Route middleware `auth:sanctum` and Gates (`can:customer`, `can:staff`) protect all non-public endpoints.
  - UI routes guarded by `RequireRole`.
  - Open redirect attacks prevented via `safeReturnTo` sanitization.
- **A02 Cryptographic Failures:**
  - Bcrypt hashing used with 72-byte max boundary enforcement.
  - Passwords excluded from model serialization (`$hidden = ['password']`).
  - Verified by `NoSecretsTest` across responses and JSON logs.
- **A03 Injection:**
  - FormRequest validation strictness (`CustomerSignUpRequest`, `SignInRequest`).
  - Database queries use parameter bindings in Eloquent.
- **A04 Insecure Design:**
  - Email rate limiter: 5 failures / 15 minutes.
  - IP throttle: 30 requests / minute on all authentication routes.
  - Atomic counter operations prevent race condition bypasses.
- **A05 Security Misconfiguration:**
  - Standard RFC 9457 `application/problem+json` error responses.
  - Stack traces and internals suppressed (`INTERNAL_ERROR` 500).
- **A07 Identification and Authentication Failures:**
  - Constant-time dummy hash check on non-existent emails eliminates timing-based user enumeration.
  - Generic error messages ("Email or password is incorrect.") prevent field disclosure.
- **A08 Software and Data Integrity Failures:**
  - Multi-table operations encapsulated in `DB::transaction()`.
- **A09 Logging and Monitoring Failures:**
  - Authentication outcomes logged as structured JSON without sensitive personal data (emails or passwords).
- **A10 SSRF:**
  - No outbound requests driven by user-supplied URLs.

---

## 4. Findings and Resolutions

- **Finding 1:** Initial `JsonResource` status code for recently created models defaulted to 201 during `GET /me`.
  - *Fix:* Explicitly chained `->response()->setStatusCode(200)` in `MeController`.
- **Finding 2:** Bcrypt salt format in PHP 8.4 required valid standard salt structure for constant-time dummy hash.
  - *Fix:* Updated dummy hash to valid bcrypt constant `$2y$12$IZEIBAzXHcWMZk8oJKpqAe4z9/QU5UH0P.sNtXLKyHWG79Ov0.HIy`.
- **Finding 3:** Session store not attached to request when `sanctum.stateful` domain headers were absent in test environments.
  - *Fix:* In `AuthController::signOut`, resolved session store fallback via `$request->hasSession() ? $request->session() : session()` and cleared resolved guards via `Auth::forgetGuards()`.
- **Finding 4:** Vitest duplicate element role match for "Sign in" on `SignUpPage`.
  - *Fix:* Scoped test assertion to the specific alert container using `within(alert)`.

---

## 5. Verification Results

- **API Feature & Unit Tests:** 60 passed (256 assertions) across real PostgreSQL test database.
- **Laravel Pint:** 84 files checked, 0 style violations.
- **PHPStan Analysis:** Level 8, 71 files analysed, 0 errors.
- **Frontend Vitest:** 13 test files passed, 45 tests passed.
- **Frontend ESLint & TypeScript Check:** 0 lint errors, 0 type errors.

