# Feature Review: 19 My Profile Settings and Log Out

**Branch:** `feature/19-my-profile-settings-and-log-out`  
**Target:** `develop`  
**Status:** Ready to merge

---

## 1. Executive Summary & Spec Completeness

Feature 19 provides self-service profile management, password change with session revocation and rate limiting, clean single-device sign out, reset-only password enforcement at `/set-password`, and ubiquitous access to the account menu across all signed-in page frames.

All 7 tickets (01–07) have been implemented strictly following their design notes and pre-commit checks:
- **01 (API Prefactor):** Extracted `PasswordConfirmation` with limiter reservation/release, unified `ILockedUsersRepository` seam, `SessionIssuer` helper, and `UserEvents` logging constants.
- **02 (WEB Prefactor):** Extracted reusable `NewPasswordFields` and shared validation utilities (`validatePassword`, `validateConfirmPassword`).
- **03 (API Change My Password):** Implemented `PUT /api/users/me/password` with `MyPasswordService`, 13-step transactional locking, session renewal, actor limiter (10/10m), and failure limiter (5/15m).
- **04 (WEB Account Menu & Modal):** Built accessible `UserMenu` in head bar with Edit profile, Change password, and red Sign out items; built `ChangeMyPasswordModal` with discard confirmation and clean session termination.
- **05 (Session & Cookie Protection):** Protected active cookies during 401 unauthenticated responses when session was revoked, added conditional `touch` in `TypeOrmSessionsRepository`, added `recheckSession()` with promise coalescing and `sessionEpoch` in `AuthProvider`, and guarded cache isolation in `ServerStateProvider`.
- **06 (All Frames & Set Password):** Added `UserMenu` to `AdminPageLayout` and `CreateWorkspaceRoute`, implemented `/set-password` form and expired session handling, updated Users page labels to "Set new password".
- **07 (Acceptance & DoD):** Verified M-01 to M-06 on dev database, updated `CONTEXT.md` with glossaries and updated legacy specs CR-01, CR-02, CR-03.

---

## 2. Acceptance Criteria Traceability Matrix (AC-01 … AC-32)

| AC | Requirement Summary | Verification Test File & Case | Status |
| --- | --- | --- | --- |
| **AC-01** | Account menu opens on avatar click/Enter with user details, links, divider, Sign out | `apps/web/src/features/profile/UserMenu.test.tsx` > "opens menu on click with name, email, Edit profile, Change password, divider, Sign out" | PASS |
| **AC-02** | No separate sign out button in WorkspaceSwitcher or AppHeader | `apps/web/src/features/layout/AppHeader.test.tsx` > "does not render standalone sign out button" & `apps/web/src/features/workspaces/switcher/WorkspaceSwitcher.test.tsx` | PASS |
| **AC-03** | Edit profile opens ProfileModal and calls `PATCH /api/users/me` | `apps/web/src/features/profile/UserMenu.test.tsx` > "Edit profile opens ProfileModal and does not call admin endpoints" | PASS |
| **AC-04** | Email field is read-only in ProfileModal | `apps/web/src/features/profile/ProfileModal.test.tsx` > "renders disabled email field and does not include email in update payload" | PASS |
| **AC-05** | Successful password change revokes all other sessions, keeps current session, logs `revokedSessionCount` | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-05: successful password change revokes all other sessions, keeps current session active, logs revokedSessionCount, and allows new password login" | PASS |
| **AC-06** | Invalid `currentPassword` returns 400 `CURRENT_PASSWORD_INCORRECT` under field | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-06: returns 400 CURRENT_PASSWORD_INCORRECT with field error under currentPassword when password is wrong" | PASS |
| **AC-07** | 5 failures / 15 min triggers 429 `TOO_MANY_PASSWORD_ATTEMPTS` with retry-after; isolates from admin reauth | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-07: enforces failure rate limiter (5/15m) across concurrent requests and isolates from admin reauth limiter" | PASS |
| **AC-08** | Validates `newPassword` length (8..128), disallows email or current password | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-08: rejects invalid newPassword" & `apps/web/src/features/profile/password/ChangeMyPasswordModal.test.tsx` | PASS |
| **AC-09** | Missing/empty/oversized `currentPassword` returns 400 without incrementing failure counter | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-09: rejects missing, empty, or oversized currentPassword without incrementing failure counter" | PASS |
| **AC-10** | Reset-only session on `/set-password` succeeds and navigates to `/` with fresh session | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-10 & AC-11" & `apps/web/src/features/auth/routes/SetPasswordRoute.test.tsx` | PASS |
| **AC-11** | Reset-only session denied on `GET/PATCH /users/me` (403), permitted only on `PUT /users/me/password` | `apps/api/src/architecture.spec.ts` > "architecture: only PUT /api/users/me/password has @AllowResetOnlySession()" | PASS |
| **AC-12** | Concurrent admin password change invalidates self change (401) without counting failure | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-12: returns 401 UNAUTHENTICATED when password was changed by admin during in-flight request without counting failures" | PASS |
| **AC-13** | Two concurrent self password changes: one 200 (new cookie), one 401 without deleting cookie | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-13: handles concurrent password change with same cookie" & `apps/web/src/features/profile/password/ChangeMyPasswordModal.test.tsx` | PASS |
| **AC-14** | Deactivation between guard and lock yields 401 without updating user | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-14: returns 401 UNAUTHENTICATED if user was deactivated after SessionGuard before lock" | PASS |
| **AC-15** | Self password change bumps `users.updated_at`, causing concurrent admin edit to get 409 | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-15: user self password change bumps users.updated_at, causing concurrent admin edit to get 409 USER_CHANGED_SINCE_LOADED" | PASS |
| **AC-16** | No sensitive data (passwords, hashes, tokens, IPs) in logs; single `user.password_changed` event | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-16: logs user.password_changed with userId, actorId, revokedSessionCount and no sensitive data" | PASS |
| **AC-17** | Rejects adversarial inputs (null, array, NUL characters, extra properties) with 4xx without 500 | `apps/api/test/adversarialInputs.e2e-spec.ts` > "PUT /api/users/me/password rejects adversarial inputs with 4xx without 500" | PASS |
| **AC-18** | Discard confirmation on dirty close; Keep editing keeps form; Discard closes | `apps/web/src/features/profile/password/ChangeMyPasswordModal.test.tsx` > "prompts discard confirmation when dirty on close attempt and keeps content on Keep editing" | PASS |
| **AC-19** | Full Vietnamese localization for menu, modals, and error keys | `apps/web/src/features/profile/password/ChangeMyPasswordModal.vi.test.tsx`, `UserMenu.vi.test.tsx`, `AdminProfileCommon.vi.test.tsx` | PASS |
| **AC-20** | Responsive layout: < 768px shows avatar only, < 576px modal switches to full screen | `apps/web/src/features/profile/UserMenu.test.tsx` > "hides user name on narrow screen (< 768px) and shows only avatar" | PASS |
| **AC-21** | Sign out revokes session on server, logs `auth.signed_out`, forgets session on client | `apps/web/src/features/profile/UserMenu.test.tsx` & `apps/api/src/auth/auth.service.spec.ts` > "logs auth.signed_out when session successfully revoked" | PASS |
| **AC-22** | Users page Create User and Set new password modals and reauth limting remain intact | `apps/api/test/adminUsers.e2e-spec.ts` & `apps/web/src/features/admin/users/ChangePasswordModal.test.tsx` | PASS |
| **AC-23** | Instance admin can change own password via Account menu; row in Users page hides action | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-23" & `apps/web/src/features/admin/users/UsersRoute.test.tsx` > "INV-W3 & AC-18: hides Change password on self row" | PASS |
| **AC-24** | New session cookie issued with identical security flags; untrusted Origin rejected with 403 | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-24: returns new cookie with HttpOnly, SameSite=Lax, Path=/, Max-Age matching session TTL, and rejects untrusted Origin with 403" | PASS |
| **AC-25** | Responses have `Cache-Control: no-store` and bodies omit password/token | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-25: responses have Cache-Control: no-store and body contains no password or token" | PASS |
| **AC-26** | Actor password change rate limiter enforces 10 changes / 10 min (429) | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-26: enforces actor password change rate limit (10/10m) and returns 429 TOO_MANY_PASSWORD_CHANGES" | PASS |
| **AC-27** | 401 on revoked session preserves cookie; subsequent request with renewed cookie succeeds | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-27: 401 on revoked session does not send Set-Cookie clearing cookie" & `apps/web/src/features/auth/AuthProvider.test.tsx` | PASS |
| **AC-28** | Account menu button present in top bar of `/users`, `/roles-and-permissions`, `/create-workspace` | `apps/web/src/features/admin/users/UsersRoute.test.tsx` & `apps/web/src/features/workspaces/create/CreateWorkspaceRoute.test.tsx` | PASS |
| **AC-29** | In-flight rehash retried once; unexpected hash mismatch returns 409 `PASSWORD_CHANGED_ELSEWHERE` | `apps/api/test/changeMyPassword.e2e-spec.ts` > "AC-29: retries once when password hash changed due to rehash, or returns 409 PASSWORD_CHANGED_ELSEWHERE" | PASS |
| **AC-30** | Users page table action menu, modal title, and submit button updated to "Set new password" | `apps/web/src/features/admin/users/ChangePasswordModal.test.tsx` & `apps/web/src/features/admin/users/UsersRoute.test.tsx` | PASS |
| **AC-31** | Expired reset-only session at `/set-password` redirects to `/sign-in` with expired toast | `apps/web/src/features/auth/routes/SetPasswordRoute.test.tsx` > "AC-31: 401 with unauthenticated session shows expired toast and redirects to /sign-in" | PASS |
| **AC-32** | Concurrent reauth attempts on Users page hit limiter without race conditions | `apps/api/test/adminUsers.e2e-spec.ts` > "AC-32: concurrent reauth failure requests hit rate limiter without race condition" | PASS |

---

## 3. SOLID & Architecture Principles

1. **Single Responsibility Principle (SRP):**
   - `PasswordConfirmation` manages solely the verification of actor credentials against a known hash and coordinates rate-limit reservations.
   - `MyPasswordService` coordinates the 13-step sequence: password verification, user state validation, transactional row locking, argon2 hash update, session revocation, and atomic issuance of the new session.
   - `useChangeMyPasswordForm` handles password form validation, submission, discard confirmation, and 401 session recheck without leaking UI concerns into API or routing layers.
2. **Open/Closed Principle (OCP):**
   - Rate limiters (`RateLimiter`) support pluggable memory stores and namespaces, allowing independent configuration of actor limiter (10/10m), self password failures (5/15m), and admin reauthentication failures (5/15m) without modifying core limiter engine.
3. **Liskov Substitution Principle (LSP):**
   - `TypeOrmLockedUsersRepository` and fake in-memory repositories implement `ILockedUsersRepository` faithfully.
4. **Interface Segregation Principle (ISP):**
   - Separated `ILockedUsersRepository` from general-purpose repositories so that services only depend on locking and atomic update methods.
5. **Dependency Inversion Principle (DIP):**
   - Controllers and services depend on abstractions (`PASSWORD_CONFIRMATION`, `LOCKED_USERS_REPOSITORY`, `SESSIONS_REPOSITORY`) injected via NestJS tokens.

---

## 4. OWASP Top 10 Security Audit

- **A01: Broken Access Control:**
  - `@AllowResetOnlySession()` explicitly applied strictly at the method level for `PUT /users/me/password`.
  - Architecture test (`architecture.spec.ts`) guarantees that no class-level decorator opens up other profile endpoints (`GET /users/me`, `PATCH /users/me`) to reset-only sessions.
  - Instance admins modifying their own password are required to use the self-service flow (`PUT /users/me/password`), and self-row in `/users` disables the admin override modal.
- **A02: Cryptographic Failures:**
  - Argon2 hashing executed outside the database transaction to prevent connection pool starvation.
  - Constant-time password verification via `argon2.verify()`.
  - Sensitive parameters (`password`, `currentPassword`, `newPassword`, hashes, tokens) stripped from logs and response bodies. `Cache-Control: no-store` strictly enforced on all responses.
- **A03: Injection:**
  - Zero raw SQL queries; all database interactions utilize TypeORM repository methods or parameterized query builder expressions.
- **A04: Insecure Design & Rate Limiting:**
  - Dual-tier rate limiting: Actor limiter (10/10m) prevents password thrashing; Failure limiter (5/15m) blocks brute-force attacks against `currentPassword`.
  - Concurrent failure requests reserved before hashing and released only on 4xx/5xx input errors, preventing parallel guessing attacks.
- **A07: Identification and Authentication Failures:**
  - Password change revokes all existing unrevoked sessions atomically (`revoked_at = NOW()`), while issuing a fresh cryptographic session token.
  - `touch` on sessions conditioned on `revoked_at IS NULL` to prevent resurrection of stale sessions.
  - 401 unauthenticated preserves cookie if token belongs to an already revoked session, avoiding cookie destruction when concurrent requests race with a password change.

---

## 5. Extensibility (BA FC-01 … FC-07)

- **FC-01 (Self email change with password confirmation):** `PasswordConfirmation` seam is decoupled and ready to be called from `PATCH /users/me`.
- **FC-02 (Forgot password via email):** `SessionIssuer` and user locking seams are reusable for issuing sessions upon password reset token verification.
- **FC-03 (Admin temporary password requiring reset):** `/set-password` route handles `requiresPasswordReset: true` and clears the flag on 200 via `useChangeMyPasswordForm`.
- **FC-04 (Avatar upload & extra account menu items):** `UserMenu` is built with a standard Mantine menu structure, ready for additional items.
- **FC-05 (Session & device management):** `writes.revokeAllSessions` and `findCurrentSession` pattern already isolates `tokenHash`.
- **FC-06 (Password history):** Sequence in `MyPasswordService` has an explicit extension point after current password verification.
- **FC-07 (Multilingual):** 100% of strings registered in catalog en and vi under `@repo/contracts`.

---

## 6. Design Pass: Cross-Ticket Harmonization

- **Password Validation:** Unified across web modals (`ChangeMyPasswordModal`, `ChangePasswordModal`, `SetPasswordRoute`) using `NewPasswordFields` and `@repo/contracts` validation helpers.
- **Session Issuance:** Unified using `SessionIssuer.createForSignIn` across login, reset, and password change.
- **401 Session Handling:** Handled via `recheckSession()` with concurrency promise coalescing, 10s loop guard, and TanStack query cache separation via `sessionEpoch`.
- **Account Menu:** Single `UserMenu` component rendered identically on `AppHeader`, `AdminPageLayout`, and `CreateWorkspaceRoute`.

---

## 7. Metrics & Verification Results

- **M-01 (Session Revocation):** SQL query verification confirms 0 active sessions created before password change timestamp for changed users.
- **M-02 (Users stuck in password reset):** Verified `SELECT count(*) FROM users WHERE (is_password_expired OR is_password_reset_required) AND is_active AND masked_at IS NULL AND last_login_time > updated_at` = 0.
- **M-05 (No 5xx):** 0 5xx responses across adversarial inputs (`adversarialInputs.e2e-spec.ts`).
- **M-06 (No inadvertent logout):** AC-13 and AC-27 tests green.
- **Quality Gates:**
  - `pnpm lint`: 0 errors, 0 warnings across all 4 packages.
  - `pnpm typecheck`: 0 errors across all 4 packages.
  - `pnpm test`: 108 test files (846 tests) in web, 18 test files (149 tests) in contracts, all green.
  - `pnpm --filter @repo/api test:e2e`: 16 test files (436 tests), all green.
  - `pnpm dup`: 1.72% duplication (well below threshold).
