# Review: fix/web-tests-after-vi-copy

## Cause

- `d468994 fix(language): fix text Vietnamese language` changed catalog copy ("Chung" → "Tổng quan", "+ Add more" → "Add more"). It was committed at 06:09, after the reviewed fix branch had been merged (06:04), and merged at 06:10 as `git merge origin/fix/17-multiple-languages-review` without running the tests. The review report `fix__17-multiple-languages-review.md` predates that commit.
- The merge gate did not stop it: `.claude/hooks/requireBranchReview.mjs` guards branch names matching `^(feature|fix|refactor|chore|review)/`, and `origin/fix/...` does not match, so a remote-tracking merge skips the report check.
- Separately, three tests failed only under the root run's parallel load (1 s async timeout); the feature 17 review had recorded this as passing alone.

## Changes

| Commit | Change |
| --- | --- |
| 0608381 | Four tests look for the new copy |
| 0cfce81 | `configure({ asyncUtilTimeout: 5000 })` in the web test setup |

## Findings

- Open (not fixed here, needs the owner's decision): the merge hook should also guard `origin/<guarded>/…` refs.

## Checks

`pnpm lint`, `pnpm typecheck` pass; `pnpm test --force` twice: contracts 144, ui 5, api 366, web 693, all pass. No API change, so no e2e run.
