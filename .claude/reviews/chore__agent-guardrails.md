# Self-review: chore/agent-guardrails

Scope: `git diff develop...chore/agent-guardrails` (10 commits). Reviewed as a whole against the CLAUDE.md §8 design pass, the §5 OWASP list and §7.

## Findings

| # | Finding | Decision |
| --- | --- | --- |
| 1 | `requireBranchReview.mjs` has a `catch` that continues when `git log` fails for an unknown branch. | Kept: git itself reports the unknown branch on merge. The comment states the reason. A hook crash does not block (`onFailure` default). |
| 2 | `useProject` shows the list row (initialData) before the server re-checks access. A user who lost access can briefly see data they had already seen in the list. | Accepted: nothing new is revealed. `staleTime: 0` forces the re-check, and 403 then switches to the Forbidden page (route test added). |
| 3 | `useProjects` now reloads the workspace on 403/404 in parallel with showing the error, instead of awaiting it first. | Accepted: the existing ProjectsHome tests stay green, and the end state is unchanged. |
| 4 | The CI Prettier step checks every changed `.ts`/`.tsx`/`.css` file, and 13 files touched lately were already unformatted on develop. | Intended ratchet: touched files end up formatted. CLAUDE.md already asks for `pnpm format` when Prettier reports changes. |
| 5 | `ProjectLayout.test.tsx` was reformatted as a whole while one test was added (noise in the diff). | Accepted, same reason as 4. |
| 6 | The migrations e2e suite creates and drops `<db>_migref`, which needs CREATEDB. | OK: the local docker user and the CI service user are superusers. Documented in the test. |
| 7 | Hand-rolled fetch hooks remain for workspaces and members. | Deferred: CLAUDE.md says to migrate them to TanStack Query when touched. |
| 8 | Four per-user rate-limit guards are near-duplicates (jscpd baseline). | Deferred: the threshold now ratchets at 2.0%, and extracting a shared guard is a separate refactor. |
| 9 | The `spec-reviewer` subagent and the project skills load only in a new session. | Expected platform behaviour, verified by an error when spawning it in this session. The trial ran through a general-purpose agent following the same skill. |

## Verified

- `pnpm lint`, `pnpm typecheck`, `pnpm test` (API 221, web 380, contracts 69, ui 3), `pnpm dup` (1.95% < 2.0%), API e2e 183/183, all green.
- Fixing keep-alive made e2e reliable: 5/5 full runs green, against about 1 in 3 failing before.
- Mutation checks: removing `@ProjectRead()` turns the architecture spec red. A `down()` that does not restore the partial index turns the migrations spec red. A stricter jscpd threshold exits 1. The merge hook denied a real `git merge` in this session.
