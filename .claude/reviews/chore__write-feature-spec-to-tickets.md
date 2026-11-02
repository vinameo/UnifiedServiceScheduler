# Review: chore/write-feature-spec-to-tickets

- Diff: one line in `.claude/skills/write-feature-spec/SKILL.md` (step 4). No code, no tests affected.
- Change: step 4 explains that `mattpocock-skills:to-tickets` sets `disable-model-invocation`, so the agent reads its SKILL.md and follows it in place, waits for the user's approval of the breakdown, and writes tickets under the spec directory's `tickets/`.
- Findings: none.
- Checks: `pnpm lint` and `pnpm typecheck` pass; api, contracts and ui tests pass. `@repo/web` has 6–9 failing tests (ProjectSettings.vi, WorkspaceSettings.vi, ProjectSettings, AddMembersModal, AddProjectMembersModal, ProjectMembersSettingsPage, GeneralSettingsPage). The diff touches no code, so they fail on `develop` b82a969 too.
- Merge approved by the user despite the pre-existing web failures (2026-10-15); a fix branch follows.
