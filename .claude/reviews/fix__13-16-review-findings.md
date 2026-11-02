# Self-review: fix/13-16-review-findings

Scope: `git diff develop...fix/13-16-review-findings` (commits `d322302` through `a0b7535`).
Fixes the 10 findings of the `/code-review` of features 13–16 (merge role, change workspace role, change project role, inherited workspace admins). Reviewed as a whole against CLAUDE.md §5 (SOLID, OWASP Top 10, Permissions, Database), the §8 design pass, §7 pitfalls, and the cited spec sections.

## 1. Findings → fixes

| # | Finding | Fix | Commit | Tests |
| --- | --- | --- | --- | --- |
| 1 | Creator role from the guard's stale workspace role; `creatorRole` optional with silent `'admin'` fallback | Create transaction locks the creator's `workspace_members` row `FOR SHARE` and calls the required `creatorRoleFor(role)` from the service; returns `creator_gone` (→ 404) or the locked role, which the read-back resolver call uses | `d322302` | `projects.service.spec.ts` (locked role wins over guard role; creator gone → 404), e2e `projects.e2e-spec.ts` unchanged and green |
| 2 | `assignedProjectAdmins` defaulted `implicitRoles` from role names | Parameter is required; preview passes `workspaceRolesWithImplicitProjectAccess(policies)`; other call sites already did | `5199808` | `projectFilters.spec.ts` |
| 3 | `changeRole` locked a soft-deleted workspace | Lock `where: { id, deletedAt: IsNull() }`; new `workspace_gone` outcome → 404 "Workspace not found" (same as `addMembers`) | `5199808` | `workspaceMembers.service.spec.ts` |
| 4 | Preview made business/HTTP decisions in the repository and duplicated INV-11 | `checkWorkspaceRoleChange` (contracts) runs steps 3–6 for both routes; repository returns typed outcomes; `roleChangeRejected()` in the service is the one HTTP mapping | `5199808` | contracts `workspaceRoleChange.test.ts` (order of checks), service spec (same answer from preview and change) |
| 5 | `role.forbidden` warnings unbounded | `ROLE_FORBIDDEN_ALERT_LIMITER` / `PROJECT_ROLE_FORBIDDEN_ALERT_LIMITER` (1 line / 10 min / actor) gate the logging adapters | `71f3e48` | `loggingWorkspaceEvents.spec.ts`, new `loggingProjectEvents.spec.ts` |
| 6 | Web chose the 403 sentence by `err.message` | API answers `ROLE_NOT_ALLOWED` (403) for "above the actor's role"; web branches on `code` | `2eddc30` | `useChangeProjectRole.test.ts`, service spec, e2e AC-07 |
| 7 | Empty `catch` in `mutate` | Non-API errors are logged; API errors were already reported by `onError` | `2eddc30` | — (behaviour unchanged for API errors) |
| 8 | Duplicated `ProjectRef`, unused `rolesQueryOptions`, deprecated `isProjectRoleClamped` | Import from contracts; hook uses `rolesQueryOptions`; helper and its test removed | `a0b7535` | existing tests |
| 9 | `listInherited` two round trips | `count(*) OVER ()` in the same query | `a0b7535` | e2e AC-04 (truncation) green |
| 10 | Losing-admin list compared a count to 1 and ignored the applied role | Count *other* assigned admins (`excludeMemberId`) = 0; list only when `mayRemoveAssignedProjectAdmin(plan)`; dialog hides the warning when `projectRoleAppliedBy(preview, selected)` is a Project Admin role | `5199808` | e2e `31b` (locked target + active co-admin → not listed), modal test (selecting Project Admin hides warning) |

## 2. Branch review

- **SOLID / one seam:** INV-11 lives once (contracts); HTTP mapping lives once (service). Creator-role policy stays in the service, the lock in the repository (same pattern as `addMembers`' `canAdd`).
- **Read once, decide on that row:** creator role and change checks are decided from rows locked in the same transaction.
- **Locks:** create takes `FOR SHARE` on the creator's membership; role change takes `FOR NO KEY UPDATE` on workspace then members. `FOR SHARE` conflicts with `FOR NO KEY UPDATE`, so the two serialize; the project/history inserts only take `FOR KEY SHARE` on `workspaces`, which does not conflict with `FOR NO KEY UPDATE` → no new deadlock cycle.
- **OWASP:** no new endpoints. A01: create re-checks membership inside the transaction. A09: attacker-triggerable warnings are now bounded. The new `ROLE_NOT_ALLOWED` code reveals nothing beyond the existing message.
- **Permissions (§5):** no role-name comparison added; implicit roles come from `project_access_policies`. `isProjectAdminRole` is only used for the "assigned Project Admin" display concept, as its doc allows.
- **Data access:** no hand-written SQL; property paths and `Not()` operator only.
- **Adversarial inputs:** no new write endpoints, so no new `adversarialInputs` case.
- **Duplicates:** `pnpm dup` passes.

## 3. Spec updates (docs/features is git-ignored; edited locally)

- 13 API B4.2: creator role decided from the locked membership, `creator_gone` → 404.
- 14 API B4.1 / B7, DB B4 / B5.2, Web B4: shared `checkWorkspaceRoleChange`, `workspace_gone`, losing-admin rule, limiter.
- 15 API B6 / B7 (log limiter), Web B2.4: `ROLE_NOT_ALLOWED`.
- 16 DB / RQ DB-03: `count(*) OVER ()` for the inherited total.

## 4. Checks

`pnpm lint`, `pnpm typecheck`, `pnpm test` (all 5 workspaces), `pnpm --filter @repo/api test:e2e` (14 files, 323 tests) and `pnpm dup` pass.

Recorded, not fixed: Prettier `--check` warns on 12 touched files, but those files were already not Prettier-clean on `develop`; reformatting them would bury the fixes in whitespace changes. A repo-wide `pnpm format` belongs in its own `chore/` branch.
