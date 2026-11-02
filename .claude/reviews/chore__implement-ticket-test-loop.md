# Self-review: chore/implement-ticket-test-loop

Scope: `git diff develop...chore/implement-ticket-test-loop` (755624b, bdb0979). Tooling and docs only (`.claude/skills`, `.claude/agents`, `CLAUDE.md`); no app code.

## Findings

None open.

## Checked

- **Steps 6–7 stay general.** They state goals and defaults (narrow runs while building, build `@repo/contracts` before narrow runs elsewhere, expected values read from the source, test only what this ticket builds, `vitest related` before the full checks). There is no fixed test order, no hardcoded fixture paths, and no rule taken from one feature. `vitest related --run` was run on web and works; the API vitest config has no coverage thresholds, so narrow runs do not fail on coverage.
- **Step 7 consistency.** A failure unrelated to the branch is noted in the report, and the "Done" line allows it.
- **Feature review order.** The section reads the full feature, tests the feature, reviews, fixes, tests the whole app (API e2e included), then reports and merges. The four review areas are unchanged.
- **References.** `deep-coder` points at section 4 (the removed "Then" list is gone). `implement-feature` step 5 and `CLAUDE.md` Self-review follow the new order. The step numbers 1–10 are unchanged, so the existing references to steps 4, 7, 8, 9 and 10 still hold.
- **Prettier** is clean on the touched skill and agent files.

## Not verified

The new flow has not run on a real feature yet.

## Checks

`pnpm lint`, `pnpm typecheck` and `pnpm test` pass (run in the branch worktree). There are no API changes, so `test:e2e` was not run.
