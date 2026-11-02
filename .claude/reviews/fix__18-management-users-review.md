# Self-review: fix/18-management-users-review

Scope: `git diff develop...fix/18-management-users-review` (31daff3, c60997e, 5ec9268, edc8793, a3799d1). Fixes for the 10 findings of the feature 18 code review. Checked against the spec, now at version 2.2: RQ D37, INV-02, INV-18, INV-19, AC-39, AC-40; DB B5–B7; API B2.2, B3.2, B3.3, B5–B8; WEB B2.5–B2.7, B3, B5; BA ADR-09, FC-08. Also checked against SOLID, the OWASP Top 10, and the FC-01/FC-02 roadmap. The spec files are gitignored (`docs/features/.gitignore`), so the spec edits exist only locally.

## Findings fixed

| # | Finding | Fix | Proof |
| - | ------- | --- | ----- |
| 1 | Password change locked users → sessions; `touch`/`revoke` locked sessions → users, so 40P01 → 500 | One lock order everywhere: users before sessions (`touch`, `revoke`, sign-in, password change). Locked admin writes run in `runTransactionWithRetry` (3 attempts, `admin.users.lock.retry`) | e2e "DB B7: one lock order": holds the users lock, runs `touch` and sign-out, then revokes. It fails with `deadlock detected` on the old order (checked) and passes now |
| 2 | One stolen admin session could take over every other instance admin | Product owner chose this fix: re-authentication when the target is an instance admin. `actorPassword` is verified before the transaction and compared with the locked actor row. 403 `REAUTHENTICATION_REQUIRED` / `_FAILED`. 5 wrong passwords per 15 min per actor → 429 `TOO_MANY_REAUTH_ATTEMPTS`; only failures count (`peek` then `hit`). Web shows "Your password" | Service spec (required, wrong, right, changed between check and lock, limit, logs without the password); e2e en + vi + limit; `ChangePasswordModal.test.tsx`; adversarial case |
| 3 | `LISTED_USER` was restated in `list()` and on the locked row | `list()` uses `.where(LISTED_USER)`. Locked rows are decided by `matchesWhere(row, LISTED_USER)` / `ACTIVE_INSTANCE_ADMIN`, a new helper in `src/database` that throws on operators it can't evaluate | `matchesWhere.spec.ts`; locked-row checks in the service spec; list e2e unchanged and green |
| 4 | Rate-limit copy had no minutes; create limiter log had no `limiter=` label | `_one/_other` keys with `{{count}}`. Guards pass `count = retryAfterMinutes(...)`. Web passes minutes. Create guard logs `limiter=create` | guard specs, e2e messages, `adminUserErrors.test.ts` |
| 5 | Empty `catch(() => {})`, floating promises | Session refresh logs with `console.error`; invalidations use `void` (house style); no empty catch left in the feature | lint, review |
| 6 | Repository imported the HTTP DTO; search normalization copied with a hardcoded `5`; dead getters | `normalizeAdminUserSearch` / `adminUserSearchTerms` in contracts, used by the DTO and the repo; getters removed | `adminUsers.test.ts` (contracts) |
| 7 | Port leaked `EntityManager`; FC-01/02 would copy the transaction + lock + retry | `withLockedActorAndTarget(actorId, targetId, work)` with `LockedUserWrites`. The actor/target checks exist once, in `AdminUsersService.withAdminWriteLock`. Business rules moved from the repo to the service | Service spec runs on `FakeAdminUsersRepository` (test/fakes); FC-01 now needs only its write |
| 8 | The 403 refresh was copied 4 times and the 404 cleanup 3 times; the detail-load 403 never refreshed | `useAdminUserErrorRecovery` (expire, refresh, forget) used by UsersRoute, EditUserModal and the 3 forms | `EditUserModal.test.tsx` (403 on load re-reads the session) |
| 9 | "Field changed" was written 4 times | `changedEditFields()` feeds validation, `hasChanges` and the PATCH body; `emailChanged` reads the payload | `userFieldValidation.test.ts` invariant: Save enabled ⇔ body carries a field |
| 10 | `expectedUpdatedAt` without an offset was accepted (result depended on the server's time zone) | `ISO_DATE_TIME_WITH_OFFSET` regex plus `IsISO8601` | DTO spec; e2e: no offset → 400, `+07:00` → 200 |

### Found while fixing

- **403 session-refresh loop (web, pre-existing).** `useAuth()` rebuilds `updateUser`/`expireSession` on every session change, and the 403 effect was keyed on them. A 403 while the session still says instance admin (e.g. any non-`FORBIDDEN` 403 that the status branch maps to "forbidden") made it fetch `/api/auth/session` in a loop: 67 requests in 300 ms in the test. The `develop` code had the same deps. The fix reads auth through a ref, so the callbacks are stable, and the effect is keyed on the error only. Test: `UsersRoute.test.tsx`, "refreshes the session once".
- **Two 429s in one context (P12).** The write limiter and the re-auth limiter can both answer `PUT …/password`. The re-auth limit got its own code, `TOO_MANY_REAUTH_ATTEMPTS`, so the web picks the sentence by code.
- The `CreateUserForm` tests rendered without a `QueryClientProvider`. In the app the form always has one, and the shared hook now needs it, so the tests were given one.

## Review lists

- **SOLID:** The repository locks and writes; the service holds the rules (stale check, changed fields, password ≠ email, re-auth) and one check seam. `ActorReauthentication` is its own unit with its own limiter. The web has one recovery hook and one changed-fields rule. No god objects were added; `AdminUsersService` gained one dependency.
- **OWASP:** A01: INV-18 closes the admin-takeover path, and every decision is made on locked rows. A03: the list keeps bound parameters and object `where`. A04: the lock order is documented and enforced by a test, with retry on 40P01/40001. A07: re-auth checks the hash outside the transaction and re-checks it on the locked row; only failures are counted; a blocked actor is refused even with the right password, so the endpoint is not a password oracle; the password is never logged (asserted). A09: new log lines (`reauth_failed`, `rate_limited limiter=reauth`, `lock.retry`) carry ids only, and are bounded by the limiters.
- **Extensibility:** FC-01/FC-02 call `withAdminWriteLock` and write through `writes`. FC-08 (password recovery by email) must extend re-auth to `PATCH` email; it is recorded in BA and reuses `ActorReauthentication`. FC-03 is unaffected (the body is still an object).
- **Design pass:** all copy is in the catalog, en + vi. Codes: `REAUTHENTICATION_REQUIRED`, `REAUTHENTICATION_FAILED`, `TOO_MANY_REAUTH_ATTEMPTS`. Adversarial case added for the re-auth path.

## Not changed (recorded)

- **The limiters are in memory, per API process** (as all existing limiters are). With several API replicas, each replica allows 5 wrong passwords. Moving the limiters to Redis is a separate change.
- **Parallel wrong guesses** can all pass `peek` before any `hit`. A burst is still capped by the write limiter (60 per 10 min per actor).
- **Changing another admin's email** needs no re-auth yet: there is no password recovery by email, so it is not a takeover path today (RQ D37, BA FC-08).
- The 400 field-mapping loop is still repeated in the create and edit forms. It was not in the findings, and the two forms map different field sets.
- Files that already failed Prettier on `develop` were not reformatted, to keep the diff reviewable.

## Checks

- `pnpm lint`, `pnpm typecheck`, `pnpm dup`: pass.
- `pnpm test`: contracts 149, ui 5, api 433, web 787 pass. `pnpm test:hooks` pass.
- `pnpm --filter @repo/api test:e2e`: 15 files, 418 tests pass.
