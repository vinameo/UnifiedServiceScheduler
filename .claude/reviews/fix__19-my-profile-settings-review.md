# Self-review: fix/19-my-profile-settings-review

Scope: `git diff develop...fix/19-my-profile-settings-review` (e2b1f29, ca09375, 8792dd5, 1c4fea1). Fixes for the 10 findings of the `/code-review` of feature 19 (merge `b3ebd43`). Checked against the spec at version 2.1: RQ INV-03, INV-11, INV-12, INV-16, R-04; API B4.1, B4.3; DB B4; WEB B2.5. Also checked against SOLID, the OWASP Top 10 and BA FC-01…FC-07. The spec files are gitignored (`docs/features/.gitignore`), so the spec edits (WEB B2.5 "one decision per recheck", API B4.1 point 0 "one wiring") exist only locally.

## Findings fixed

| # | Finding | Fix | Proof |
| - | ------- | --- | ----- |
| 1 | Concurrent `expireSession()` calls each ran their own outcome handler; the second saw the first's `lastKeptAt` and signed the user out as a "loop" (INV-16) | `expireSession` joins an in-flight decision (`expirePromiseRef`) instead of adding a handler: one recheck, one decision | `AuthProvider.test.tsx` "makes one decision when several components call it at once": fails on the old code (checked), passes now |
| 2 | A recheck answered after `replaceSession`/`signIn`/`signOut` counted as `'kept'`: remount, cache dropped, 10 s loop window opened (and after sign-out, a remount while anonymous) | Internal outcome `superseded`. `recheckSession()` still answers `'kept'` to its callers; `expireSession()` ignores it (no epoch bump, no loop window) | Two new tests ("replaceSession wins", "signOut during the recheck"); both fail on the old code |
| 3 | Password-change columns spelled out twice (`updateUser` and the response row); 401 throw-and-log copied four times | One `passwordChange: UserChanges` used by both; `refused(reason)` helper for API B5 #7, #9, #10 | `myPassword.service.spec.ts`, `changeMyPassword.e2e-spec.ts` green unchanged |
| 4 | `RequestContext` declared twice, `clientIp()` copied into `ProfileController`, user-agent cut in two places | `auth/requestContext.ts`: `RequestContext`, `clientIp`, `requestContext`, `truncateUserAgent`, `MAX_USER_AGENT_LENGTH`; sign-in, `SessionIssuer` and `PUT /users/me/password` use it | `auth.service.spec.ts` (2000-char UA → 512), `sessionIssuer.spec.ts` green |
| 5 | `PasswordConfirmation` built with `new` inside `ActorReauthentication` for the Users page, but through a DI factory for Self | `passwordConfirmationProvider(token, options)` is the only wiring; it creates the failure limiter itself. `ActorReauthentication` removed; `ADMIN_REAUTH_CONFIRMATION_OPTIONS` beside `MY_PASSWORD_CONFIRMATION_OPTIONS`. Log lines, codes and copy of the Users page unchanged | `adminUsers.service.spec.ts` (re-auth, limit, parallel guesses), `adminUsers.e2e-spec.ts` green unchanged |
| 6 | `max(1, ceil(seconds / 60))` in four web files | `retryAfterMinutes()` in `lib/apiClient.ts`, used by settings, admin users, create workspace, Change password | `settingsMessages.test.ts`, `myPasswordErrors.test.ts`, `adminUserErrors.test.ts` green |
| 7 | Pass-through re-export files left after the moves | Removed `admin/events/userEvents.ts`, `loggingUserEvents.ts` (and the duplicate spec), the re-export block in `adminUsersRepository.ts`, the unused `SignOutIcon` re-export in `workspaces/icons.tsx`; importers point at `users/events` and `auth/repositories/lockedUsers` | typecheck, lint |
| 8 | Inline style objects (CLAUDE.md §4) | Hidden username field in Mantine `VisuallyHidden`; avatar `flex-shrink` in `UserMenu.module.css` | `ChangeMyPasswordModal.test.tsx`, `UserMenu.test.tsx` green |
| 9 | `touch()` wrote `users.last_active` before learning the session was revoked | Locks the users row (no write) first, keeping the users → sessions order; writes `last_active` only when the session update matched | AC-27 e2e now asserts `last_active` unchanged |
| 10 | `UsersModule` exported its limiters, guard and service | No exports. The failure limiters no longer have tokens at all (created inside the provider) | `architecture.spec.ts`, e2e green |

## Review lists

- **SOLID:** one way to build a password confirmation (open for a third use such as FC-01 email change: pass a token and options), no wrapper class; `MyPasswordService` keeps one responsibility with its record and refusal helper; request context is a single module both controllers depend on.
- **OWASP:** A07: lockout counters are unreachable from other modules (INV-03); parallel-guess reservation unchanged. A09: log lines unchanged (`refused reason=`, `reauth_failed`, `rate_limited`). A04: `touch` keeps the lock order (MU INV-19); AC-27 and the DB B7 lock-order e2e stay green.
- **Extensibility:** FC-01 (email change needs the current password) and FC-02 reuse `passwordConfirmationProvider`; FC-05 (device list) unaffected.
- **Design pass:** no copy changes; no new codes.

## Checks

`pnpm typecheck` (5/5), `pnpm lint` (5/5), `pnpm test` (contracts 149, ui 5, api 463, web 108 files) and `pnpm --filter @repo/api test:e2e` (16 files, 436 tests) green. `pnpm dup`: 46 → 45 clones, under the threshold.

## Not changed (recorded)

- An accidental `pnpm format` during this work reformatted the whole repo. Tracked files outside this fix were restored from `HEAD`, and the formatting noise was removed from the fixed files with a three-way merge. The gitignored `docs/features/**/*.md` files cannot be restored from git: Prettier re-aligned their Markdown (tables, emphasis, lists) without changing their text.
- Limiters are still in memory per API process (BA BR-03).
