# Review: fix/state-picker-dropdown-width

Branch: `fix/state-picker-dropdown-width` (from `develop` at 8f112c7), commit 0158e2b.
Input: user report — the State select in the new work item modal is misaligned (screenshot).

## Cause

`StatePicker`'s Mantine `Combobox` used the default dropdown width (`target`), so the dropdown was as narrow as the "Backlog" chip: "In Progress" wrapped, "Cancelled" and the check icon overflowed onto the Write/Preview tabs.

## Change

- `Combobox` `width={240}` (`DROPDOWN_WIDTH`), `position="bottom-start"`.
- Option name `Text truncate="end"` with `title`; `.optionLabel { min-width: 0 }` so truncation works inside the flex row.
- `.options { max-height: 280px; overflow-y: auto }` — a project sees up to 5 + 50 + 50 states.
- Test: the dropdown has its own width and long names carry a title.

## Findings

| # | Finding | Outcome |
| - | ------- | ------- |
| 1 | Width is a component constant, not from the spec | Accepted: web spec 21 sets no picker width; one picker, no second user yet |
| 2 | jsdom cannot measure layout | Test asserts the applied width style; visual check left to the user |

No API, permission or copy changes.

## Checks

- `pnpm lint` — pass
- `pnpm typecheck` — pass
- `pnpm test` — pass (api 63, web 128, contracts 20, ui 3 files)
- `prettier --check` on the changed files — clean
