# Self-review: chore/spec-sketches-as-hypotheses

Scope: `git diff develop...chore/spec-sketches-as-hypotheses` (`1376289`, `c38e539`, `ae1321b`). Workflow-only change: hooks, skills, agents, CLAUDE.md. No app code.

## Goal

Root cause 1 of the 13–16 review: six of ten findings came from part B sketches copied as written, although specs already labelled them *minh hoạ*. 26 commits recorded zero deviations. Fix structurally: specs state invariants, the design pass leaves checkable evidence, and the instructions stop telling agents to follow part B literally.

## Findings and fixes

| Area | Check | Result |
| --- | --- | --- |
| Single source of truth | Design notes table defined once (`implement-ticket` step 4); CLAUDE.md, `write-feature-spec`, `deep-coder` and the hook message point to it; hook column names match the skill header exactly | OK |
| Conflicting authority | Removed "Part B is followed literally" (write-feature-spec) and "part B wins … stop and ask" (B0 template). Remaining: the user-level `spec-review` skill says agents follow part B literally. That is a reviewer's risk statement, shared across projects, left unchanged on purpose | Recorded |
| Hook behaviour | Tested in a scratch repo: no notes → deny; placeholder cells (`—`, `N/A`, `none`, `ok` verdict) → deny with per-cell reasons; complete notes → allow; `review(...)`/`docs(...)` (inline and heredoc), `--amend`, non-feature branches, branches without `tickets/` → allow; Antigravity payload → deny in its format | OK |
| Bug found in review | Heredoc messages (`-m "$(cat <<'EOF' …)"`) were parsed as inline and enforced on `docs(...)`; fixed by reading the heredoc first, re-tested | Fixed (`c38e539`) |
| Error handling | Hook git failures are written to stderr and the call is allowed (git reports its own error); no empty catch | OK |
| Duplication | Hook payload parsing and deny answer extracted to `hookIo.mjs` before the second hook (`1376289`); merge gate re-tested with both payloads | OK |
| Limits (deferred) | Freshness is by file mtime, so touching an old notes file passes the freshness check (its cells are still validated). Accepted: the gate makes skipping visible rather than impossible; the feature review checks each ticket has its own `.design.md` | Deferred, reason given |
| Formatting | New hook and `implement-ticket` are Prettier-clean; `CLAUDE.md` and `write-feature-spec` were not clean on `develop` and stay as they were | Recorded |

## Not in this branch

Spec 17 has no spec files yet; it will be the first spec written under the new part B rules and P8–P12.
