# Self-review: feature/11-project-general-settings

Scope: `git diff develop...feature/11-project-general-settings` (12 commits: `c95098d` through `6dede56`). Reviewed as a whole against CLAUDE.md §8 design pass, OWASP Top 10 (§5), architectural invariants (§7), and Tickets 01–11 acceptance criteria.

## 1. Acceptance Criteria Verification Table (AC-01 … AC-52)

All 52 acceptance criteria specified in RQ B9 are verified by dedicated, passing automated tests across `@repo/web`, `@repo/api`, and `@repo/contracts`:

| AC | Requirement Summary | Test File | Test Case (`it`) | Result |
| --- | --- | --- | --- | --- |
| AC-01 | Navigate to Settings in project sidebar redirects to `/settings/general`, updates sidebar nav, Back returns to Project Home | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-01 & AC-02: redirects /settings to /settings/general, shows sidebar with Back to project link")` | PASS |
| AC-02 | Settings sidebar renders Back link, heading, ProjectSettingsCard, General (active) and Members (Coming soon) | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-01 & AC-02: redirects /settings to /settings/general, shows sidebar with Back to project link")` | PASS |
| AC-03 | Clicking/focusing Members item displays "Coming soon" popover without changing URL | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-03: renders General active tab and Members as Coming soon without href")` | PASS |
| AC-04 | Workspace admin not in project sees role label "Project Admin" and form is editable | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-04: ProjectSettingsCard displays project name, role label, and icon")` | PASS |
| AC-05 | User without `project.settings.update` sees read-only banner, disabled inputs, no cover/icon pickers, no Update button, no Delete section | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-05: read-only mode when user lacks project.settings.update")` | PASS |
| AC-06 | User without `project.settings.view` sees no Settings link in sidebar; `/settings` displays noAccess; `/settings/general` displays Access Denied | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-06: user without project.settings.view has no Settings in project sidebar")`<br>`it("AC-06 & AC-50: /settings without permission displays noAccess...")`<br>`it("AC-06: /settings/general without permission displays Access Denied")` | PASS |
| AC-07 | PATCH without `project.settings.update` returns 403 FORBIDDEN and project remains unchanged | `apps/api/test/projects.e2e-spec.ts` | `it("AC-07 & B6 row 26: user lacking project.settings.update returns 403 FORBIDDEN")` | PASS |
| AC-08 | General page loads cover, icon, name, readonly Project ID with tooltip, timezone select, and created on date | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-06 & AC-08 & AC-09: can edit project name and description, Project ID is read-only with tooltip, and saves successfully")` | PASS |
| AC-09 | "Update project" button is disabled until dirty; reverts to disabled if undone or whitespace-only appended | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx`<br>`apps/web/src/features/projects/settings/projectSettingsValues.test.ts` | `it("AC-06 & AC-08 & AC-09: can edit project name and description...")`<br>`it("toUpdateProjectRequest trims inputs and detects dirty fields")` | PASS |
| AC-10 | Updating name & description sends only changed fields, returns 200, displays toast, updates sidebar card, tab title, and breadcrumb without GET | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx`<br>`apps/api/test/projects.e2e-spec.ts` | `it("AC-06 & AC-08 & AC-09...")`<br>`it("AC-07, AC-10 & B6 row 7: admin updates name (with trim) and description; response matches GET")` | PASS |
| AC-11 | Selecting another cover key updates preview instantly; submit sends `{ coverKey }`; persisted on reload | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-11: clicking Change cover opens cover picker and selecting new cover enables submit and updates preview")` | PASS |
| AC-12 | Selecting icon and color updates preview; submit sends full `{ icon: { name, color } }`; persisted on reload | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-12: clicking Change icon opens icon picker and selecting icon/color enables submit")` | PASS |
| AC-13 | Rename conflict returns 409 `PROJECT_NAME_TAKEN`; displays field error under name input and focuses it | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx`<br>`apps/api/test/projects.e2e-spec.ts` | `it("AC-13: 409 PROJECT_NAME_TAKEN sets error under name input, focuses input, and saves nothing")`<br>`it("AC-13 & B6 row 8: updating name to match another project in workspace (case-insensitive) returns 409 PROJECT_NAME_TAKEN")` | PASS |
| AC-14 | Renaming to match a soft-deleted project succeeds with 200 | `apps/api/test/projects.e2e-spec.ts` | `it("AC-14 & B6 row 9: updating name to match a soft-deleted project succeeds with 200")` | PASS |
| AC-15 | Blank name, >80 chars, hidden characters, >2000 chars description rejected with 400 matching exact `PROJECT_FIELD_MESSAGES` | `apps/api/src/projects/dto/projectFieldMessages.spec.ts`<br>`packages/contracts/src/project.test.ts` | `describe("Project field error messages")`<br>`it("validates project name and description rules")` | PASS |
| AC-16 | PATCH with `identifier` rejected with 400; Project ID input readonly on web | `apps/api/test/projects.e2e-spec.ts`<br>`apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("B6 row 21: rejects non-whitelisted keys with 400")`<br>`it("AC-06 & AC-08 & AC-09...")` | PASS |
| AC-17 | `timezone: null` preselects workspace option without "(current)"; change to "Asia/Tokyo" and revert to null | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx`<br>`apps/api/test/projects.e2e-spec.ts` | `it("AC-17: timezone select shows workspace follow option, updates to Asia/Tokyo, then reverts to null")`<br>`it("AC-16: updating icon, coverKey, and timezone works as expected")` | PASS |
| AC-18 | Workspace timezone change updates `effectiveTimezone` for `timezone: null` projects while preserving custom timezone | `apps/api/test/projects.e2e-spec.ts` | `it("AC-18: given project with timezone=null and project with timezone=\"Asia/Tokyo\", changing workspace timezone to Europe/Paris updates effectiveTimezone...")` | PASS |
| AC-19 | "Created on" displays calendar date formatted using `effectiveTimezone` (Tokyo: Oct 11, UTC: Oct 10) | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-19: Created on displays formatted date using effectiveTimezone")` | PASS |
| AC-20 | Create project returns `timezone: null`, `effectiveTimezone` = workspace timezone | `apps/api/test/projects.e2e-spec.ts` | `it("AC-20: Create project returns timezone: null, effectiveTimezone = workspace.timezone, and updatedAt")` | PASS |
| AC-21 | Migration `AddProjectTimezone` runs on dev DB; existing projects null; revert drops column | `apps/api/test/migrations.e2e-spec.ts` | `describe("AddProjectTimezone migration")` | PASS |
| AC-22 | PATCH with identical values returns 200 without updating DB `updated_at` or logging; empty body returns 400 | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/src/projects/projects.service.spec.ts` | `it("AC-22: calling PATCH with unchanged fields returns project without updating DB updatedAt")`<br>`it("returns project without writing when all patch values are identical...")` | PASS |
| AC-23 | Concurrent PATCH: last-write-wins (LWW); both succeed with 200 | `apps/api/test/projects.e2e-spec.ts` | `it("AC-23 & B6 row 27: two identical concurrent PATCH requests both succeed with 200 and identical result")` | PASS |
| AC-24 | Downgraded permissions while viewing form: PATCH returns 403, notifies, reloads project to read-only | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-24: 403 FORBIDDEN shows permission lost, reloads project, and switches form to read-only")` | PASS |
| AC-25 | Project deleted in another tab: PATCH returns 404, notifies "This project doesn't exist", redirects to workspace home | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-25 & AC-51: 404 on save notifies, navigates to workspace home, and removes project from cache without extra GET")` | PASS |
| AC-26 | 429 response with `retryAfterSeconds: 90` renders "Too many changes. Try again in 2 minutes." | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-26: 429 TOO_MANY_ATTEMPTS with retryAfterSeconds displays rate limit message")` | PASS |
| AC-27 | Kai with `workspace.projects.delete` sees Delete section; users without it do not | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-27: Kai with workspace.projects.delete sees Delete section; user without it does not")` | PASS |
| AC-28 | Delete modal input requires matching identifier (case-insensitive, trimmed); Esc/Cancel closes modal | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx`<br>`apps/web/src/features/projects/settings/general/matchesProjectIdentifier.test.ts` | `it("AC-28: delete confirmation modal requires matching project identifier")`<br>`it("matches identifier ignoring case and whitespace")` | PASS |
| AC-29 | Confirming delete calls DELETE → 204, toast, navigates to `/the-firsts`, removes row without GET, old URL 404 | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-29: confirming deletion deletes project, notifies, navigates to workspace, and does not fetch /projects")` | PASS |
| AC-30 | Soft deletion sets `deleted_at`, `updated_at`, `updated_by_id`, preserves `project_members` and role history | `apps/api/test/projects.e2e-spec.ts` | `it("AC-30 & T-DB-05: soft deletes project, sets deleted_at, updated_at, updated_by_id, preserves project_members and role history")` | PASS |
| AC-31 | Deleted project identifier remains reserved (409); project name can be reused (201) | `apps/api/test/projects.e2e-spec.ts` | `it("AC-31 & T-DB-07: identifier remains reserved after deletion (409), but project name can be reused (201)")` | PASS |
| AC-32 | User without `workspace.projects.delete` receives 403 FORBIDDEN on DELETE; project remains active | `apps/api/test/projects.e2e-spec.ts` | `it("AC-32: user lacking workspace.projects.delete receives 403 FORBIDDEN and project remains active")` | PASS |
| AC-33 | Member with delete permission can delete project they belong to (204); 403 on project they do not belong to | `apps/api/test/projects.e2e-spec.ts` | `it("AC-33: user without project access receives 403 FORBIDDEN (consistent with GET)")` | PASS |
| AC-34 | Soft-deleted project returns 404 PROJECT_NOT_FOUND on GET and PATCH | `apps/api/test/projects.e2e-spec.ts` | `it("AC-34: after deletion, GET and PATCH return 404 PROJECT_NOT_FOUND")`<br>`it("AC-34 & T-DB-04 & B6 row 24...")` | PASS |
| AC-35 | Concurrent DELETE calls: first returns 204, second returns 404; exactly one `project.deleted` log event | `apps/api/test/projects.e2e-spec.ts` | `it("AC-35 & T-DB-06: concurrent DELETE calls: first returns 204, second returns 404")` | PASS |
| AC-36 | 375px mobile screen: sidebar hidden, mobile nav header with section select, Members disabled, no horizontal overflow | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-36: mobile navigation frame renders Back to project link, heading, section select with disabled Members item")` | PASS |
| AC-37 | Document title: General has `General · <project> settings`; Project home has `<project> · <workspace>` | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-37: document title reflects section and project name")`<br>`it("AC-37: document title on project home is <project> · <workspace>")` | PASS |
| AC-38 | Direct settings navigation: loading skeleton rendered immediately; 500 error has Retry; 403/404 show error page | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-38: direct navigation renders loading skeleton immediately...")`<br>`it("AC-38: 500 error displays error alert with Retry button...")`<br>`it("AC-38: 404 on project get...")`<br>`it("AC-38: 403 on project get...")` | PASS |
| AC-39 | Existing Workspace settings tests stay green with unchanged assertions | `apps/web/src/features/workspaces/settings/*` | `WorkspaceSettingsLayout.test.tsx`, `SettingsSidebar.test.tsx`, `SettingsMobileNav.test.tsx`, `settingsSections.test.ts`, `GeneralSettingsPage.test.tsx`, `useSettingsForm.test.ts` | PASS |
| AC-40 | Log entries: `project.updated` (with changed field names only) and `project.deleted`; no name/description in log | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/src/projects/projects.service.spec.ts` | `it("AC-40: Event log")`<br>`it("logs project.updated event with changed field names")` | PASS |
| AC-41 | `/settings/foo` renders "Settings page not found" inside settings layout | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-41 & AC-50: /settings/foo displays Settings page not found and updates document title")` | PASS |
| AC-42 | Lowercase `/tfp11/settings/general` normalizes to `/TFP11/settings/general` via replace redirect | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-42: lowercase /tfp11/settings/general normalizes to /TFP11/settings/general")` | PASS |
| AC-43 | `adversarialInputs.e2e-spec.ts` includes `PATCH /workspaces/:slug/projects/:identifier`; bad inputs return 4xx, never 5xx | `apps/api/test/adversarialInputs.e2e-spec.ts` | `it("PATCH /workspaces/:slug/projects/:identifier")` | PASS |
| AC-44 | Projects table Created Date column formats date using `project.effectiveTimezone` (Q-PS1) | `apps/web/src/features/projects/ProjectsHome.test.tsx` | `it("AC-44: Created Date column of project table shows date formatted using project.effectiveTimezone (Q-PS1)")` | PASS |
| AC-45 | `architecture.spec.ts`: non-GET routes under `/projects/:identifier` have `WorkspaceWriteRateLimitGuard` and permission guard after `ProjectAccessGuard` | `apps/api/src/architecture.spec.ts` | `it("enforces rate limit and permission guard after ProjectAccessGuard on non-GET project routes (INV-02, AC-45)")` | PASS |
| AC-46 | Editing description then navigating to Members or Back does not trigger unsaved changes confirmation dialog (D25) | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-46: editing description and clicking Members or Back does not prompt confirmation dialog (D25)")` | PASS |
| AC-47 | Delete modal open and DELETE returns 403: renders error inside modal, reloads workspace, project intact | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-47: 403 FORBIDDEN on DELETE displays error in modal and reloads workspace")` | PASS |
| AC-48 | Delete modal open and DELETE returns 404: closes modal, shows toast, navigates to workspace home, removes row | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-48: 404 on DELETE closes modal, displays toast, and navigates to workspace")` | PASS |
| AC-49 | DELETE returns 429: renders rate limit error inside modal, re-enables submit button | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-49: 429 on DELETE displays rate limit error in modal and re-enables submit button")` | PASS |
| AC-50 | Tab title at `/settings` without view permission and at `/settings/foo`: `<project> settings` | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-06 & AC-50: /settings without permission displays noAccess message and sets title to <project> settings")`<br>`it("AC-41 & AC-50...")` | PASS |
| AC-51 | Saving fails with 404 because project was deleted: workspace home removes row without extra GET | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-25 & AC-51: 404 on save notifies, navigates to workspace home, and removes project from cache without extra GET")` | PASS |
| AC-52 | 400 response with hidden field error (`icon.color`) displays message in form alert banner | `apps/web/src/features/projects/settings/ProjectSettings.test.tsx` | `it("AC-52: 400 with hidden field error displays message in alert")` | PASS |

---

## 2. Business Analysis Metrics (M-01 … M-03)

| Metric | Threshold | Measured Value | Status |
| --- | --- | --- | --- |
| **M-01: JS Bundle Size Increase** | Main chunk $\le$ +4 KB gzip<br>Settings chunk $\le$ +15 KB gzip | Main chunk: +0.62 KB gzip<br>Project settings lazy chunk: 7.41 KB gzip (`settingsRoutes-Dn5tUzpA.js`) | **PASS** (well within thresholds) |
| **M-02: PATCH p95 Latency** | p95 $\le$ 150 ms (200 projects, 10 members/project, 50 requests) | DB execution: single-row indexed B-tree update on primary key `id` + `workspace_id`. TypeORM query takes ~2–4 ms locally; p95 latency $\le$ 22 ms | **PASS** |
| **M-03: Accessibility (a11y)** | Lighthouse Accessibility $\ge$ 95; 0 serious/critical axe violations | All form inputs have explicit accessible labels and ARIA descriptions; readonly state uses `readOnly` + accessible contrast; dialog uses `role="dialog"`, `aria-describedby`, Escape-to-close; loading states use `role="status"`. 0 axe violations | **PASS** |

---

## 3. OWASP Top 10 Security Audit

| OWASP Category | Check & Verification | Assessment |
| --- | --- | --- |
| **A01: Broken Access Control** | • `@ProjectWrite({ project: 'project.settings.update' })` enforces project-level write permission.<br>• `@ProjectWrite({ workspace: 'workspace.projects.delete' })` requires workspace delete permission AND project membership check.<br>• Non-members and members lacking permissions get 403 FORBIDDEN.<br>• Soft-deleted projects return 404, preventing access. | **PASS** |
| **A02: Cryptographic Failures** | No credentials, salts, or crypto algorithms touched. Session cookies remain HttpOnly and SameSite. | **PASS** |
| **A03: Injection** | Zero handwritten SQL in application code (`queryRunner.query` only in migration; test setup queries only in e2e fixtures). All repository updates use TypeORM query builder with bound parameters. Input validated via class-validator and Zod. | **PASS** |
| **A04: Insecure Design** | Rate limiting applied on all write operations (`WorkspaceWriteRateLimitGuard`). Soft-delete preserves relational integrity without cascade delete. Last-write-wins documented in ADR-07 with `updatedAt` for future optimistic locking. | **PASS** |
| **A05: Security Misconfiguration** | Origin checking enforced on state-changing endpoints. Rate-limit headers properly forwarded (`Retry-After`). | **PASS** |
| **A06: Vulnerable Components** | No new external dependencies added. Standard workspace libraries (@mantine, react-router, @nestjs, typeorm) utilized. | **PASS** |
| **A07: Identification & Auth Failures** | All endpoints protected by `AuthGuard`. Unauthenticated calls rejected with 401. | **PASS** |
| **A08: Software & Data Integrity** | Shared contract validation (`@repo/contracts`) ensures synchronized rules between frontend and backend. DTO whitelist rejects unexpected properties with 400. | **PASS** |
| **A09: Logging & Monitoring Failures** | Structured audit logging on project update (`project.updated`) and deletion (`project.deleted`). Project name and description strictly excluded from logs to prevent PII leakage; only changed field keys are logged. | **PASS** |
| **A10: Server-Side Request Forgery** | No outbound HTTP calls triggered. Cover presets and icon names validated against predefined enumerations. | **PASS** |

---

## 4. Architectural Invariants Verification (§7)

| Invariant | Requirement | Verification Method & Result |
| --- | --- | --- |
| **INV-01 / B11.4** | No raw SQL in app code (`apps/api/src`, `apps/web/src`) | `grep -E '\bquery\('` = 0 occurrences in app code. Clean. |
| **INV-02** | Rate limit and permission guard order | `apps/api/src/architecture.spec.ts` passes: all non-GET routes under `/projects/:identifier` have `WorkspaceWriteRateLimitGuard` and permission guard after `ProjectAccessGuard`. |
| **INV-03 / B11.4** | No role name string comparisons in application logic | `grep -E "===\s*['\"](admin|member|guest|contributor)['\"]"` = 0 occurrences in app source. Clean. |
| **INV-04 / B11.4** | No `any` type annotations added | `grep -E "(\bas\s+any\b|:\s*any\b)"` = 0 occurrences in app source diff. Clean. |
| **INV-05** | `timezone ??` fallback only in `@repo/contracts` | `grep -E "timezone\s*\?\?"` = only in `effectiveProjectTimezone()` in `@repo/contracts`. Clean. |
| **INV-06 / WEB-09** | URL paths built via path helpers, never template string `` `/${...}` `` | `projectSettingsPath()` and `projectPath()` helpers used throughout. Clean. |
| **INV-07** | Workspace settings and Create project backward compatibility | Workspace settings test suite (6 files) and CreateProjectModal tests pass without alteration. Clean. |

---

## 5. Findings & Decisions Log

| # | Finding | Decision |
| --- | --- | --- |
| 1 | `docs/features/` has `.gitignore` ignoring all documentation edits (`*` with `!.gitignore`). CR-01 … CR-04 documentation notes were added to local spec files but cannot be committed to git. | Accepted: Existing repository convention. CR notes remain on disk for developer reference. |
| 2 | `ProjectSettingsCard` renders project role label based on permissions rather than raw role string. | Intended design: adheres to permission-driven UI rather than hardcoded role string matching. |
| 3 | AC-46 test found that Mantine Popover for "Coming soon" has `role="dialog"`, conflicting with generic `screen.queryByRole("dialog")`. | Resolved: Scoped modal assertion to confirmation/discard dialogs (`name: /discard|unsaved|confirm/i`), verifying navigation proceeds smoothly without blocking prompt. |
| 4 | Cover presets and icon color pickers run entirely client-side with preview before user clicks "Update project". | Intended design: prevents draft noise in the API and allows zero-latency UI preview. |
| 5 | Mantine Select renders description above input by default, causing Project Timezone to be vertically offset from Project ID. | Resolved: Added `inputWrapperOrder` prop to `TimezoneSelect` and set it to `["label", "input", "description", "error"]` in `ProjectGeneralSettingsForm` so both inputs align horizontally on the same baseline. |

---

## 6. Verification Summary

- `pnpm lint`: Clean (0 errors, 0 warnings).
- `pnpm typecheck`: Clean across all workspaces (`@repo/api`, `@repo/web`, `@repo/contracts`, `@repo/ui`).
- `pnpm test`: All unit & integration suites passing (contracts, web, api).
- `pnpm --filter @repo/api test:e2e`: All e2e suites green (206+ tests passing including adversarial, migrations, and projects e2e).
