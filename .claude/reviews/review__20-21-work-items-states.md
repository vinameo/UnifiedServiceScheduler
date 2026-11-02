# Review: features 20 (new work item) and 21 (work item states)

Branch: `review/20-21-work-items-states` (from `develop` at 6a1d89d)
Input: `/code-review` of features 20 and 21 (spec, coding, security, SOLID, extensibility), 10 findings.

## Findings and outcomes

| # | Finding | Outcome | Commit |
| - | ------- | ------- | ------ |
| 1 | X4 used DB column names and its own "live project" subquery; filter copied in 3 places | Fixed. `stateFilters.liveWorkItemsIn(manager, scope)` + `liveProjectIdsOf()` are the only copies; X4 updates `id IN (liveWorkItemsIn …)` with property paths. Spec DB B2.6 note added (docs are gitignored, also in commit msg). | c60ea27 |
| 2 | Edit row stayed open after confirming a group change; confirmed-request field errors dropped | Fixed. Update resolves when the modal settles: confirm → row closes; cancel / failure → row stays open with the field error. Tests added (WorkspaceStatesPage). | 105f4dc |
| 3 | Create work item chose sentence + Save lock by HTTP status | Fixed. Picks by `code` (`FORBIDDEN`, `PROJECT_NOT_FOUND`, `TOO_MANY_ATTEMPTS`, `VALIDATION_ERROR`). Test: 403 `ORIGIN_NOT_ALLOWED` does not lock Save. | 692a019 |
| 4 | Stale `workItemCount` in cached state lists | Fixed. Work item create bumps counts via `setQueryData` on project + workspace lists; project state delete also invalidates the workspace list. Unused `workItemKeys` removed. Delete keeps invalidation (server decides the moved count; the API does not return it). | afa3548 |
| 5 | Any 23505 mapped to `name_taken` (duplicated twice) | Fixed. `isUniqueViolation` against the two name indexes, mapped outside the aborted transaction; other violations rethrow (500 + log). | c60ea27 |
| 6 | `stateFilters.ts` dead; filters re-inlined 10+ times; default clause of `findNameClash` missed `deletedAt` | Fixed. Catalog and repository use `ownStates` / `visibleStates` / `projectStatesOf`; `ownProjectStates` now also filters `workspaceId`. | c60ea27 |
| 7 | Empty `catch {}` in `StateRowForm`; class-level `''` validation error invisible | Fixed. Handlers never reject; the form's catch logs (`state.form.submit_failed`). `parseStateError` routes non-`name` validation fields to the page alert. Unit tests added. | 105f4dc |
| 8 | `listForProject` ≈ `listForWorkspace`; repeated branch mapping in `lockAndAuthorize` | Fixed. One private `list(scope)`; `ownStatesMax(scope)`; `lockAndAuthorize` uses `requiredPermission` and maps lock kinds directly. | c60ea27 |
| 9 | Guard deps `@Optional()`; `WriteLimit` in `projects/` used by `workspaces/` | Fixed. Moved to `common/writeLimit.ts`; `Reflector`/`ModuleRef` required; missing limiter provider throws an error naming the bucket (test added). | f4870f2 |
| 10 | State write retries silent | Fixed. `StateEvents.writeRetried` → `state.write.retry` (operation, workspaceId, projectId, attempt). | c60ea27 |

## Checks

- `pnpm lint` — pass
- `pnpm typecheck` — pass
- `pnpm test` — pass (api 63 files, web 128 files, ui, contracts)
- `pnpm --filter @repo/api test:e2e` — 24 files, 542 tests pass (covers AC-24/26/32 move paths, AC-07 counts, AC-18 name clash, concurrency, logging)

## Notes / not changed

- Spec docs under `docs/features/` are gitignored; the B2.6 deviation note was written locally and recorded in commit c60ea27.
- Error-param `max` in `StatesService.create` still reads the two constants directly (two codes per level); `ownStatesMax` is used for decisions.
