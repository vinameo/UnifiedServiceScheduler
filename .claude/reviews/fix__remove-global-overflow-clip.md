# Self-review: fix/remove-global-overflow-clip

Diff reviewed: `git diff develop...HEAD` (3 files, packages/ui only).

## Change
- Root cause: a VisuallyHidden caption (`position: absolute`) inside a wide table escaped the `Table.ScrollContainer`
  overflow because the container was not positioned; it widened the page to 396–400px on 393px phones (members pages),
  so mobile Chrome widened the layout viewport and full-screen modals were cut off.
- Fix: theme sets `position: relative` on every `TableScrollContainer` (one seam: covers MembersTable, ProjectsTable,
  RoleTables, PermissionMatrixTable).
- Removed the global `html, body { max-width: 100vw; overflow-x: clip }` band-aid from 4cc5154.

## Verification
- Playwright (Chrome, iPhone emulation, stubbed API) sweep at 320px and 393px over home, roles & permissions,
  workspace general/members (+ Add member modal with 2 rows), project, project general/members (+ modal):
  all 18 cases scrollWidth == viewport width, no element past the right edge.
- New test `packages/ui/src/theme.test.tsx` fails without the fix, passes with it.
- `pnpm lint`, `pnpm typecheck`, `pnpm test` pass. No API change, so no e2e run.

## Findings (recorded, not fixed here)
- Other 4cc5154 additions (`overflow-x: hidden` on SettingsLayoutFrame, extra `max-width/min-width` in
  SettingsSectionFrame, dataTable) are redundant but harmless; left as is to keep this fix minimal.
- Add-members modal shows "+ + Add more" (icon plus a "+" in the label): separate copy bug.
- Prettier warnings in packages/ui (theme.ts, index.ts, …) predate this branch.
