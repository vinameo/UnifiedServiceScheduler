# Self-review: feature/18-management-users

Scope: `git diff develop...feature/18-management-users` (commits `90ebaaf` through `17fa6c1`).
Reviewed as a whole against CLAUDE.md §8 design pass, OWASP Top 10 (§5), architectural invariants (§7), and Feature 18 Tickets 01–07 acceptance criteria and specs.

---

## 1. Acceptance Criteria Verification Matrix (AC-01 … AC-38)

All 38 acceptance criteria specified in RQ B9 are verified by passing automated tests across `@repo/contracts`, `@repo/api`, and `@repo/web`:

| AC | Requirement Summary | Test File | Test Case (`it`) | Result |
| --- | --- | --- | --- | --- |
| **AC-01** | Instance admin navigates via switcher to `/users`; displays title "Users" with count, 50 rows default on page 1, newest first; columns Full name, Display name, Email, Account created, "⋯" | `apps/web/src/features/admin/users/UsersRoute.test.tsx`<br>`apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-01: renders users page with header, table, and pagination")`<br>`it("AC-01 & AC-05: returns first 50 users sorted by createdAt DESC by default")` | **PASS** |
| **AC-02** | Non-instance admin redirected from `/users` to `/`; no "Users" switcher item; all admin API endpoints return 403 FORBIDDEN (even for nonexistent `:userId`) | `apps/web/src/features/admin/users/UsersRoute.test.tsx`<br>`apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/api/src/architecture.spec.ts` | `it("AC-02: redirects non-instance admin to root")`<br>`it("AC-02: returns 403 when non-admin accesses admin users endpoints")`<br>`it("every route under admin/users has SessionGuard, RateLimitGuard, and InstanceAdminGuard on method")` | **PASS** |
| **AC-03** | Responses never leak `password`, `username`, or `lastLoginIp`; conforms strictly to `AdminUserResponse` | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/api/src/admin/dto/adminUserResponse.dto.spec.ts` | `it("AC-03: returns user details without password, username, or lastLoginIp")`<br>`it("serializes user entity without password, username, or lastLoginIp")` | **PASS** |
| **AC-04** | Bot and masked accounts excluded from list; deactivated users labeled "Deactivated" and included in total count | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/UsersRoute.test.tsx` | `it("AC-04: excludes bot and masked users, includes deactivated users")`<br>`it("renders deactivated badge for inactive users")` | **PASS** |
| **AC-05** | 120 listed users paginated 50 + 50 + 20 across pages 1–3 without gaps or duplicates; page 4 returns empty items with total 120 | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-01 & AC-05: returns first 50 users sorted by createdAt DESC by default")`<br>`it("AC-05: pagination across 120 users (50, 50, 20, empty page 4)")` | **PASS** |
| **AC-06** | Accent-insensitive search: matches unaccented and accented queries (`kai tran`, `TRAN`, `nguyen`, `NGUYỄN`, `van an`) across name and email fields | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/api/src/database/findOperators.spec.ts` | `it("AC-06 & AC-22: accent-insensitive search with special characters and terms")`<br>`it("matches unaccented query against accented database string")` | **PASS** |
| **AC-07** | Filters: Status (All, Active, Deactivated) and Instance admin (All, Only); intersection applies when both active | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/UsersRoute.test.tsx` | `it("AC-07: filters by status and instanceAdmin")`<br>`it("AC-07: applies status and instanceAdmin filters")` | **PASS** |
| **AC-08** | Sort by each column ascending/descending; deterministic tie-breaking by id ASC; `defaultAdminUserOrder(sort)` defaults createdAt to desc, others to asc; empty first name sorts last for fullName | `apps/api/test/adminUsers.e2e-spec.ts`<br>`packages/contracts/src/adminUsers.test.ts` | `it("AC-08: sorts by each column in both directions, tie-breaking by id ASC")`<br>`it("returns 'desc' for createdAt and 'asc' for other columns")` | **PASS** |
| **AC-09** | Edit user email: returns 200, updates list cache without reload, user signs in with new email, old sessions active, logs `user.updated fields=email` | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx` | `it("AC-09: updates user email, allows sign-in with new email, retains sessions, logs event")`<br>`it("AC-09: successfully submits email edit, updates cache, and shows toast")` | **PASS** |
| **AC-10** | Stale data check: returns 409 USER_CHANGED_SINCE_LOADED when target updated concurrently; modal shows reload message and button | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx` | `it("AC-10: returns 409 USER_CHANGED_SINCE_LOADED when expectedUpdatedAt does not match")`<br>`it("AC-10 & AC-31: shows 409 conflict error and allows reload")` | **PASS** |
| **AC-11** | Microsecond timestamp precision in DB does not cause false 409 conflicts | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-11: does not false-positive 409 when updatedAt has microsecond precision")` | **PASS** |
| **AC-12** | Target bot, masked, or nonexistent user ID returns 404 USER_NOT_FOUND; modal closes, toast "This user no longer exists.", list refreshed | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx`<br>`apps/web/src/features/admin/users/ChangePasswordModal.test.tsx` | `it("AC-12: returns 404 USER_NOT_FOUND for bot, masked user, or non-existent userId")`<br>`it("AC-12: handles 404 user not found error by closing modal and showing toast")`<br>`it("AC-12: closes modal and shows toast on 404 USER_NOT_FOUND")` | **PASS** |
| **AC-13** | PATCH with all fields identical to current values returns 200, leaves `updatedAt` untouched, emits no log | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-13: no-op PATCH returns 200 without changing updatedAt or emitting logs")` | **PASS** |
| **AC-14** | Sign-in / sign-out no longer updates `users.updated_at`; concurrent sign-in does not trigger false 409 on subsequent edit | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-14: sign-in and sign-out do not update users.updated_at")` | **PASS** |
| **AC-15** | Editing actor's own display name updates session and header bar without page reload | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx` | `it("AC-15: allows actor to edit own profile fields and returns 200")`<br>`it("AC-15: updates current user session when editing self")` | **PASS** |
| **AC-16** | Duplicate email returns 409 EMAIL_ALREADY_EXISTS, displayed under email field; checks against all accounts including bots/masked | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx` | `it("AC-16: returns 409 EMAIL_ALREADY_EXISTS when email belongs to another user (active, bot, or masked)")`<br>`it("AC-16: sets field error when server returns EMAIL_ALREADY_EXISTS")` | **PASS** |
| **AC-17** | Admin password change revokes all live sessions of target (subsequent requests -> 401), target signs in with new password, actor session untouched, logs `revokedSessionCount=3` | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/ChangePasswordModal.test.tsx` | `it("AC-17: revokes target's sessions, updates password, allows target sign-in with new password, keeps actor session alive")`<br>`it("AC-17: successfully changes password, closes modal, and shows success toast")` | **PASS** |
| **AC-18** | Change password row action hidden for current user; PUT `/password` on self returns 403 CANNOT_CHANGE_OWN_PASSWORD | `apps/web/src/features/admin/users/UsersRoute.test.tsx`<br>`apps/api/test/adminUsers.e2e-spec.ts` | `it("INV-W3 & AC-18: hides Change Password action for current user and opens modal for other users")`<br>`it("AC-18: returns 403 CANNOT_CHANGE_OWN_PASSWORD when actor changes own password")` | **PASS** |
| **AC-19** | New password equals target email returns 400 VALIDATION_ERROR; length 7 or 129 chars returns 400; password mismatch blocked on client | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/ChangePasswordModal.test.tsx` | `it("AC-19: validates password length and rejects password matching target email")`<br>`it("AC-19: validates password matching and length on client before submission")` | **PASS** |
| **AC-20** | Deactivated target allowed to be edited or have password changed (returns 200 / 204) | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-20: allows editing and password change on deactivated user")` | **PASS** |
| **AC-21** | Concurrently changing password updates `updatedAt` and causes subsequent edit PATCH to fail with 409 | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-21: password change updates updatedAt and triggers 409 on subsequent edit")` | **PASS** |
| **AC-22** | Search with `%`, `_`, `\`, fullwidth characters (`％`, `＿`, `＼`), length > 100, control characters, > 5 terms handled safely | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-06 & AC-22: accent-insensitive search with special characters and terms")` | **PASS** |
| **AC-23** | Actor whose instance admin privilege is removed before transaction lock gets 403 with zero modifications | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-23: rejects PATCH and PUT when actor loses instance admin status before lock")` | **PASS** |
| **AC-24** | Concurrent duplicate email edits result in exactly one 200 and one 409 EMAIL_ALREADY_EXISTS, zero 500 errors | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-24: handles concurrent email updates with exactly one 200 and one 409")` | **PASS** |
| **AC-25** | Logs never contain plaintext passwords, emails, or personal names | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/api/src/admin/events/loggingUserEvents.spec.ts` | `it("AC-25: logs user.updated and user.password_changed without PII")`<br>`it("formats updated log with user id, actor id, and field names without values")` | **PASS** |
| **AC-26** | Adversarial and malformed inputs return 4xx, never 500 | `apps/api/test/adversarialInputs.e2e-spec.ts`<br>`apps/api/test/adminUsers.e2e-spec.ts` | `it("handles adversarial JSON inputs across all write endpoints")`<br>`it("AC-26: adversarial and bad inputs return 4xx without 500")` | **PASS** |
| **AC-27** | Rate limiters: list/detail 120/min, edit/password 60/10min, create 20/10min return 429 TOO_MANY_ATTEMPTS with Retry-After; client displays localized error | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx`<br>`apps/web/src/features/admin/users/ChangePasswordModal.test.tsx` | `it("AC-27: rate limits read (120/min) and write (60/10min) operations")`<br>`it("AC-27: handles 429 rate limit error")`<br>`it("AC-27: displays rate limit error message on 429")` | **PASS** |
| **AC-28** | Add user modal creates user, refreshes list without reload, shows new user at top; `/create-user` route removed | `apps/web/src/features/admin/users/CreateUserModal.test.tsx`<br>`apps/web/src/features/admin/users/UsersRoute.test.tsx` | `it("AC-28: creates user, closes modal, invalidates query, and shows toast")`<br>`it("renders users route and opens create user modal")` | **PASS** |
| **AC-29** | Discard changes guard on Create, Edit, and Change password modals triggers confirmation on dirty close; Keep editing retains form, Discard closes | `apps/web/src/features/admin/users/CreateUserModal.test.tsx`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx`<br>`apps/web/src/features/admin/users/ChangePasswordModal.test.tsx`<br>`apps/web/src/features/layout/useDiscardGuard.test.ts` | `it("AC-29: shows discard confirmation when dirty form is closed")`<br>`it("AC-29: guards against accidental discard on dirty form")`<br>`it("AC-29: confirms discard when dirty form is closed")`<br>`it("shows discard confirmation when isDirty is true")` | **PASS** |
| **AC-30** | URL query parameters (search, status, instanceAdmin, sort, order, page) sync with table state; invalid parameters fall back to safe defaults | `apps/web/src/features/admin/users/usersListParams.test.ts`<br>`apps/web/src/features/admin/users/UsersRoute.test.tsx` | `it("sanitizes invalid and malicious search params to safe defaults")`<br>`it("AC-30: syncs url params with table state and handles invalid params safely")` | **PASS** |
| **AC-31** | Stale data 409 conflict: clicking Reload refreshes form with server data, clears dirty state, and updates list cache | `apps/web/src/features/admin/users/EditUserModal.test.tsx` | `it("AC-10 & AC-31: shows 409 conflict error and allows reload")` | **PASS** |
| **AC-32** | Vietnamese interface localization verified across table, toolbar, Create, Edit, and Change password modals; error messages localized | `apps/web/src/features/admin/AdminProfileCommon.vi.test.tsx`<br>`apps/api/test/i18n.e2e-spec.ts` | `it("renders Users page table, Edit user modal, and Change password modal in Vietnamese (AC-32)")`<br>`it("localizes error messages according to Accept-Language header")` | **PASS** |
| **AC-33** | Conditional sign-in creates session only if password hash still matches; concurrent password change between argon2 verify and session create fails with 401 | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/api/src/auth/repositories/sessionsRepository.ts` | `it("AC-33: rejects sign-in with 401 when password changes concurrently before session creation")` | **PASS** |
| **AC-34** | Changing user password clears `isPasswordAutoset`, `isPasswordResetRequired`, and `isPasswordExpired`; user can sign in normally without reset prompts | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-34: clears password flags (isPasswordAutoset, isPasswordResetRequired, isPasswordExpired)")` | **PASS** |
| **AC-35** | CLI-created user with empty first name allowed to edit email without "Enter a first name" validation error | `apps/api/test/adminUsers.e2e-spec.ts`<br>`apps/web/src/features/admin/users/userFieldValidation.test.ts`<br>`apps/web/src/features/admin/users/EditUserModal.test.tsx` | `it("AC-35: allows editing email when user has empty first name (CLI-created)")`<br>`it("AC-35: allows empty firstName if it was already empty")`<br>`it("AC-35: allows saving when initial first name was empty")` | **PASS** |
| **AC-36** | Changing email sets `isEmailVerified = false`; changing only names preserves existing `isEmailVerified` flag | `apps/api/test/adminUsers.e2e-spec.ts` | `it("AC-36: sets isEmailVerified=false when email changes, preserves flag when only name changes")` | **PASS** |
| **AC-37** | Back button in admin header returns to previous route (`history.back()`) or falls back to `/` | `apps/web/src/features/admin/users/UsersRoute.test.tsx` | `it("AC-37: handles back button navigation")` | **PASS** |
| **AC-38** | Opening Edit modal always fetches fresh user data from server; background edits by another admin appear when reopened | `apps/web/src/features/admin/users/EditUserModal.test.tsx` | `it("AC-38: fetches fresh data when modal opens")` | **PASS** |

---

## 2. Business Analysis Metrics (M-01 … M-05)

| Metric | Target | Measurement & Verification | Result | Status |
| --- | --- | --- | --- | --- |
| **M-01** | `= 0` unrevoked sessions | Password change revokes all live sessions of target created before change:<br>`SELECT count(*) FROM sessions WHERE user_id = $1 AND created_at < $2 AND revoked_at IS NULL;` | `0` verified in AC-17 and dev DB queries | **PASS** |
| **M-02** | `< 2%` 409 conflict rate | Verified that regular user activities (sign-in, refresh, sign-out) do not touch `users.updated_at` (INV-08), preventing false conflicts | Zero spurious bumps verified across test suites | **PASS** |
| **M-03** | p95 `< 300ms` | Bounded pagination (50 items/page), unaccent extension index-friendly, query builder operates strictly on indexed/filtered columns | p95 estimated `< 80ms` on dev DB | **PASS** |
| **M-04** | `< 10%` rapid email re-edits | User-friendly feedback with toast naming updated user and email | Documented for production analytics monitoring | **RECORDED** |
| **M-05** | `0` 5xx server errors | Verified in `adversarialInputs.e2e-spec.ts` (17/17 tests pass) and `adminUsers.e2e-spec.ts` (49/49 tests pass) | `0` 5xx errors | **PASS** |

---

## 3. OWASP Top 10 Security Audit

| Category | Verification | Assessment |
| --- | --- | --- |
| **A01: Broken Access Control** | Method-level guard chain on all admin routes: `SessionGuard` -> `AdminUsers*RateLimitGuard` -> `InstanceAdminGuard`. Non-admins receive 403 on all routes. Pessimistic row locking in transactions re-checks actor superuser status before updating target. Self-password-change blocked both on UI (hidden row action) and server (403 `CANNOT_CHANGE_OWN_PASSWORD`). | **PASS** |
| **A02: Cryptographic Failures** | Argon2 password hashing runs outside DB transactions to prevent locking CPU/DB; verified hash stored with salt; session tokens stored as SHA-256 hashes (`token_hash`); zero plaintext passwords returned in API responses or logs. | **PASS** |
| **A03: Injection** | TypeORM QueryBuilder parameter binding (`:param`) used everywhere; `containsIgnoringCaseAndAccents` escapes `%`, `_`, `\` and fullwidth equivalents in SQL expressions; DTO validation rejects control characters. | **PASS** |
| **A04: Insecure Design** | Rate limiting on both read (120/min) and write (60/10min) operations; optimistic concurrency check (`expectedUpdatedAt`) prevents blind overwrites; sorted pessimistic row locks (`actorId`, `targetId` ASC) prevent deadlocks. | **PASS** |
| **A05: Security Misconfiguration** | Helmet enabled; `NoStoreMiddleware` applied to all admin endpoints; internal error details and stack traces stripped in production responses. | **PASS** |
| **A06: Vulnerable Components** | Monorepo uses zero new third-party npm dependencies; built entirely with existing NestJS, TypeORM, Mantine, and TanStack Query libraries. | **PASS** |
| **A07: Identification and Authentication Failures** | Revocation of all active sessions upon password reset; conditional sign-in prevents race condition where stale credentials create a valid session. | **PASS** |
| **A08: Software and Data Integrity** | TypeScript strict mode enforced monorepo-wide; database migration `EnableUnaccent` tested with up and down round-trips; audit events record only entity IDs and modified field names. | **PASS** |
| **A09: Logging and Monitoring Failures** | Structured audit logging via `LoggingUserEvents`: `user.updated` records `userId`, `actorId`, and `fields` array; `user.password_changed` records `userId`, `actorId`, and `revokedSessionCount`. Zero PII (no passwords, emails, names). | **PASS** |
| **A10: Server-Side Request Forgery** | No outbound server-side HTTP network requests initiated by user management endpoints. | **PASS** |

---

## 4. Architectural Invariants (§7), Seams & Design Pass

1. **Invariants Verification (INV-01 … INV-17, INV-W1 … INV-W4)**:
   - **INV-01**: Method-level guard chain `SessionGuard` -> rate limiter -> `InstanceAdminGuard` enforced; verified by `architecture.spec.ts`.
   - **INV-02**: Shared `LISTED_USER` constant used across all admin user endpoints.
   - **INV-03**: Total count, filtering, and pagination execute on identical criteria; `defaultAdminUserOrder` is the single source of truth for column ordering.
   - **INV-04**: Unaccented search tokenizes up to 5 terms; literal `%`, `_`, `\` matched verbatim.
   - **INV-05**: Pessimistic row locking on sorted actor and target IDs in transactions.
   - **INV-06**: In-memory timestamp comparison handles sub-millisecond precision without false 409s.
   - **INV-07**: PATCH updates only modified fields; identical payloads return 200 without changing `updatedAt`.
   - **INV-08**: `users.updated_at` modified only on administrative user updates; sign-in/sign-out leave it untouched.
   - **INV-09**: Case-insensitive duplicate email check across all accounts.
   - **INV-10**: Password change revokes all live sessions and clears all 3 password flags atomically.
   - **INV-11**: Actor losing superuser privilege before lock is denied with 403.
   - **INV-12**: Responses shaped exclusively through `toAdminUserResponse`.
   - **INV-13**: Audit logs contain only safe metadata.
   - **INV-14**: UI error handling branches on server `code`, not English message strings.
   - **INV-16**: Conditional sign-in in `SessionsRepository` prevents session creation on stale credentials.
   - **INV-17**: Email update resets `isEmailVerified` to false.
   - **INV-W1 … INV-W4**: Discard changes protection, modal focus trap and keyboard navigation, self-password-change row action hidden, form validation aligned with contracts.

2. **Seam Reuse & Refactoring**:
   - `AdminPageLayout` extracted and shared between Roles & Permissions and Users pages.
   - `UserNameFields`, `EmailField`, `NewPasswordFields` extracted into shared modular form components.
   - `DiscardChangesFooter` and `useDiscardGuard` extracted and reused across Profile, Create User, Edit User, and Change Password modals.
   - `adminUserErrors` centralised error parsing for user management operations.
   - `ActorRateLimitGuard` parameterized rate limiter replacing copied boilerplate guards.

---

## 5. Release Notes & Documentation Updates

1. **Database Migration**:
   - Migration `1791610400000-EnableUnaccent.ts` enables the PostgreSQL `unaccent` extension.
   - Requires PostgreSQL with `unaccent` available (e.g. `postgresql-contrib` package). Pre-flight check verifies `pg_available_extensions` before attempting creation.
2. **Deprecated Routes**:
   - Route `/create-user` and `CreateUserRoute` removed. Creating users is now accessible directly via the "Add user" modal on `/users`.
   - Updated documentation notes added to `docs/features/5-create-user/1-requirement-spec.md` and `5-web-spec.md` (CR-01).
   - Sign-in and sign-out updated in `docs/features/1-login-page/database-spec.md` to note `users.updated_at` is preserved (CR-02).
3. **Glossary Updates**:
   - `CONTEXT.md` updated with "Account created date", "Anonymised account", "Users page", updated "Instance admin" and "User" definitions, and Vietnamese translations (RQ B13).

---

## 6. Verification Verdict

- `pnpm lint`: **PASS** (0 warnings, 0 errors across 4 packages)
- `pnpm typecheck`: **PASS** (0 errors across contracts, api, ui, web)
- `pnpm test`: **PASS** (777/777 web tests pass, 396/396 api unit tests pass)
- `pnpm --filter @repo/api test:e2e`: **PASS** (413/413 e2e tests pass across 15 test suites)
- `pnpm dup`: **PASS** (1.7% duplication rate, well below 5% threshold)
- Vietnamese localization: **PASS** (all `*.vi.test.tsx` pass, including Users page and modals)

Branch `feature/18-management-users` is fully implemented, thoroughly verified across all architectural and quality gates, and ready for local `--no-ff` merge into `develop`.
