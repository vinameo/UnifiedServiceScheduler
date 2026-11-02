# Review: review/12-add-member-to-project

Branch: `review/12-add-member-to-project` (from `develop` 24a617b). Fixes the 10 findings of the `/code-review` run on feature 12 (add member to project). The diff (`git diff develop...HEAD`) was reviewed as a whole against CLAUDE.md §5 (OWASP), §7 and the §8 design pass.

## Findings and what was done

| # | Finding | Fix | Commit |
| --- | --- | --- | --- |
| 1 | An uppercase `userId` missed the lowercase id maps (false `not_in_workspace`, and the duplicate check could be bypassed) | `@Transform(toCanonicalUserId)` on both add DTOs (project and workspace). E2E: uppercase id → 201 with the canonical id; same id in two cases → 400 duplicate | 5e7a9da |
| 2 | A rejected `reload()` after 403/404 escaped `onSubmit` and the frame, causing an unhandled rejection | `submitAddMembers` logs refresh and after-add failures and still returns `done`. The frame catches a rejected `onSubmit` and shows an alert. `onClick={() => void handleSubmit()}` | 16d4e12 |
| 3 | The repository decided project access itself, a second source next to `ProjectAccessResolver`, and also held the `workspace_admin` rule | New `ProjectAccessResolver.forMembership(manager, membership)` runs the same `resolveProjectAccess` + role matrix the guard uses. The `workspace_admin → already_member → role_not_allowed` rule moved to the service (`rowRejection`) and shares `candidateStatus()` with the candidate search | 32ed545 |
| 4 | After a 403, `addableRoles` stayed stale | `useAddProjectMembers.onError` invalidates `projectKeys.members` on 403/404. Covered by a test | c15a1dc |
| 5 | The repository threw an HTTP error with hardcoded copy and a field key the web cannot map | Both member repositories log `*.members.add.role_unknown` and return `{ status: 'role_unknown' }`. Services map it to 400 `MEMBER_MESSAGES.checkFields` with `members.<i>.role = MEMBER_MESSAGES.chooseRole` (`unknownRoleFields`) | 32ed545 |
| 6 | The project and workspace modals had duplicate submit/error mapping that had already drifted | Shared `features/members/submitAddMembers.ts` with 9 unit tests. Both modals call it. The workspace modal no longer shows `err.message` | 16d4e12 |
| 7 | The actor was built 3× in the service and a 4th way in the repository | `toProjectActor` / `canSeeMemberEmails` (`projectMemberActor.ts`). The service's `viewOf()` and the repository both use them | 32ed545 |
| 8 | The member select was written twice, and `total` leaked into rows | `selectProjectMemberColumns` + `toMemberDbRow`, shared by `list` and the add read-back | 32ed545 |
| 9 | The cache append ignored the list cap and `truncated` | The list is cut at `PROJECT_MEMBER_LIST_MAX` and `truncated = total > members.length`. Covered by a test | c15a1dc |
| 10 | The workspace candidate hint was hardcoded | `MEMBER_MESSAGES.candidateQueryTooShort` in `useMemberCandidates` and in the shared combobox fallback | e747d6f |

## Self-review notes

- **OWASP:** no new endpoints. Authorization is unchanged: the guard runs first, then the in-transaction re-check, which now uses the same resolver. The new log lines carry only ids and `requestId`, with no PII. Input is narrowed further (canonical UUIDs).
- **Behaviour changes in the workspace modal (from sharing the mapper):** a 404 now closes the modal and reloads the workspace instead of showing "Something went wrong". 403 and 400/409 alerts use `MEMBER_MESSAGES` instead of the server text. After a successful add, `onSuccess()` now runs before `reloadWorkspace()`, so a failed reload no longer skips it.
- **Cost:** the add transaction now loads the project role matrix (one small query) plus the actor's workspace permissions. Before, it made one combined query. That is one extra round trip inside the transaction, accepted in exchange for a single access rule.
- **Spec deviations** are recorded in `docs/features/12-add-member-to-project/{3-database,4-api,5-web}-spec.md`. That directory is gitignored, so the notes exist only locally.
- `compareIds` still lowercases. That is redundant now that ids are canonical, but harmless; left as is.
- Prettier: files that were already unformatted on `develop` were not reformatted, to keep the diff focused. Only files this branch touched and newly put out of format were formatted.

## Checks

- `pnpm lint`, `pnpm typecheck`, `pnpm test`: pass.
- `pnpm --filter @repo/api test:e2e`: pass (14 files, 262 tests).
- New tests: 2 failed against `develop` sources before the fix (modal invalidation and the rejected `onSubmit`), then passed.
