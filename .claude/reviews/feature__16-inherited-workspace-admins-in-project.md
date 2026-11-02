# Self-review: feature/16-inherited-workspace-admins-in-project

Scope: `git diff develop...feature/16-inherited-workspace-admins-in-project` (commits `f6cbaa4` through `930bf30`).
Reviewed as a whole against CLAUDE.md §8 design pass, OWASP Top 10 (§5), architectural invariants (§7), and Feature 16 Tickets 01–07 acceptance criteria.

---

## 1. Acceptance Criteria Verification Matrix (AC-01 … AC-16)

All 16 acceptance criteria specified in RQ B9 are verified by passing automated tests across `@repo/contracts`, `@repo/api`, and `@repo/web`, or confirmed via visual inspection (AC-08):

| AC | Requirement Summary | Test File | Test Case (`it`) | Result |
| --- | --- | --- | --- | --- |
| **AC-01** | Project with 2 inherited Workspace Admins and 2 assigned members: API returns `total = 2`, `members` array of 4 items (`inherited` before `assigned`) for viewer with `workspace.members.view`. Title badge shows "2" | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-01 & AC-03: project with 2 inherited workspace admins and 2 assigned members returns total=2 and list of 4 members when viewer has workspace.members.view")`<br>`it("AC-01: returns only assigned members count in total while keeping inherited admins in list")` | **PASS** |
| **AC-02** | `GET /projects` returns `memberCount = 2`, and `members` array contains only assigned members without Workspace Admins in preview avatars (at any list limit) | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/src/projects/membersOf.service.spec.ts` | `it("AC-02: GET /projects returns memberCount=2 and members array without workspace admins in preview")`<br>`it("AC-02: membersOf excludes workspace admins with implicit project roles")` | **PASS** |
| **AC-03** | Viewer without `workspace.members.view` (e.g. member with `project.members.view` only): `GET /members` returns `total = 2` and `members` array with only 2 assigned members (inherited filtered out) | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-01 & AC-03: project with 2 inherited workspace admins and 2 assigned members returns total=2 and list of 4 members when viewer has workspace.members.view")`<br>`it("AC-03: viewer without workspace.members.view receives total=2 and members array with only assigned members")` | **PASS** |
| **AC-04** | Project with 2 inherited admins and >1000 assigned members: API truncates only assigned members at 1000, returns 1002 members, `total = 1005`, `truncated = true`. Emits event `membersListTruncated` with `source: 'assigned'` and `total: 1005`. Web displays "Showing the first 1,000 of 1,005 project members." | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/src/projects/members/projectMembers.service.spec.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx`<br>`apps/web/src/features/members/MembersListView.test.tsx` | `it("AC-04: truncation applies only to assigned members; inherited members are not counted toward total or bounded")`<br>`it("AC-04: list applies limit only to assigned members and logs membersListTruncated with source")`<br>`it("AC-04: payload with inherited and truncated assigned members renders total badge and project members truncation alert")`<br>`it("AC-04: uses custom truncatedMessage when provided")` | **PASS** |
| **AC-05** | Members table sorting: clicking each column header ("Full name", "Display name", "Email", "Role", "Joining date") in both asc and desc keeps inherited rows pinned at top; sort applies within each group | `apps/web/src/features/members/memberListView.test.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-05: pinned rows stay at the top across all sort keys and directions")`<br>`it("AC-05: clicking each column header in both directions keeps inherited members at top")` | **PASS** |
| **AC-06** | Search and filter apply to both inherited and assigned groups; searching "Project Admin" keeps inherited admins; total badge in header remains fixed to `state.total` | `apps/web/src/features/members/memberListView.test.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-06: search and filters apply to both pinned and unpinned members")`<br>`it("AC-06: filter and search apply to both groups, badge in title remains equal to total")` | **PASS** |
| **AC-07** | Inherited rows render with `.inheritedRow` class, `data-readonly="true"`, no combobox/button/action; Workspace Admin badge and Deactivated badge use `variant="default"`; badge is focusable with tooltip on hover/focus; holds for session user and deactivated admin | `apps/web/src/features/members/MembersTable.test.tsx`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-07: applies inheritedRow class and data-readonly on inherited row; uses default variant for badges")`<br>`it("AC-07: inherited row has .inheritedRow class, no button/combobox, tooltip on hover and focus; handles viewer as that admin and deactivated admin")` | **PASS** |
| **AC-08** | Contrast ratios & sticky column: verified light mode text `gray-7 #4B5563` on `gray-1 #F3F4F6` (6.9:1 ≥ 4.5:1), dark mode `dark-1 #B4C0D0` on `dark-5 #2A3646` (6.6:1 ≥ 4.5:1); `.inheritedRow > td` specificity (0,1,1) overrides `.stickyColumn` (0,1,0) | Visual audit & contrast verification (see Section 6) | Visual inspection / CSS analysis | **PASS** |
| **AC-09** | Project created by Workspace Admin with 0 project members: viewer with `workspace.members.view` sees title "Members 0", toolbar, inherited admin rows, and "No one has been added to this project yet." under table; no "No members yet" | `apps/web/src/features/members/MembersListView.test.tsx`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-09: renders noneAddedNote when total is 0 and members has rows, without showing emptyState")`<br>`it("AC-09: project created by Workspace Admin with 0 project members displays Members 0, toolbar, inherited rows, and none-added note")` | **PASS** |
| **AC-10** | When viewer lacks `workspace.members.view`, displays empty state "No members yet" (no D7 note). When role filter matches no members, displays "No members match your search" (no D7 note) | `apps/web/src/features/members/MembersListView.test.tsx`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-10 / INV-07: renders emptyState when members is empty and emptyState is passed")`<br>`it("AC-10: does not render noneAddedNote when search filters out all members")`<br>`it("AC-10: does not render noneAddedNote when role filter matches no members")`<br>`it("AC-10: displays No members yet when viewer has no workspace.members.view; and filtering role out hides none-added note")` | **PASS** |
| **AC-11** | Adding 1 member to project with 0 members: updates cache in-place without second `GET /members`, header updates to "Members 1", inherited rows remain at top, new member added below, D7 note disappears; `truncated` flag bounded by assigned rows | `apps/web/src/features/projects/api/projectQueries.test.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-11 (cache): addProjectMembersToCache keeps inherited rows at top, bounds assigned rows, and sets truncated per INV-03")`<br>`it("AC-11: adding 1 member to project with 0 members updates count to Members 1, keeps inherited row at top, removes none-added note, and makes no second GET to /members")` | **PASS** |
| **AC-12** | Workspace Admin creates project: returns `members = []`, `memberCount = 0`, project list shows dash "—" in Members column, no `project_members` row created. Member creates project: `memberCount = 1` | `apps/api/test/projects.e2e-spec.ts`<br>`apps/web/src/features/projects/ProjectsHome.test.tsx` | `it("AC-12: project created by workspace admin has members=[], memberCount=0; member created project has memberCount=1")`<br>`it("AC-12: displays dash when project has 0 members")` | **PASS** |
| **AC-13** | Without `pinned` option, `applyMemberListQuery` behaves identically to existing implementation; all workspace members tests pass unmodified | `apps/web/src/features/members/memberListView.test.ts`<br>`apps/web/src/features/workspaces/settings/members/memberListView.test.ts` | `it("AC-13: preserves exact behavior when pinned is not provided")`<br>All 11 tests in workspace `memberListView.test.ts` pass unmodified | **PASS** |
| **AC-14** | Glossary in `CONTEXT.md` updated at end of **Project member** section | `CONTEXT.md` | Line 60 updated per Ticket 06 and RQ B12 | **PASS** |
| **AC-15** | `implicitRoles` is required on `projectMembersQuery` and `membersOf`; `ProjectAccessPolicyStore` provides dynamic implicit roles | `apps/api/src/projects/membersOf.service.spec.ts`<br>`apps/api/src/projects/repositories/typeOrmProjects.repository.ts` | `it("AC-02: membersOf excludes workspace admins with implicit project roles")`<br>Typecheck & grep enforcement across `apps/api/src` | **PASS** |
| **AC-16** | `MembersTable` treats `inheritedRow === true` as read-only: does not call `roleControl` and renders no actions regardless of page props | `apps/web/src/features/members/MembersTable.test.tsx` | `it("AC-16: does not render roleControl on inherited row even if roleControl always returns a node")` | **PASS** |

---

## 2. Business Analysis Metrics (M-01 … M-03)

| Metric | Threshold | Measurement Procedure | Result | Status |
| --- | --- | --- | --- | --- |
| **M-01** | `= 0` | Active project member rows for roles with implicit project roles:<br>`SELECT count(*) FROM project_members pm JOIN workspace_members wm ON wm.workspace_id = pm.workspace_id AND wm.member_id = pm.member_id AND wm.is_active JOIN project_access_policies p ON p.workspace_role = wm.role WHERE pm.is_active AND p.implicit_project_role IS NOT NULL;` | `0` | **PASS** |
| **M-02** | `3/3` | Usability testing: observing 3 users identify inherited Workspace Admins vs assigned Project Admins without guidance | Documented: responsibility of product owner post-merge on staging/UAT | **RECORDED** |
| **M-03** | `100%` | `total` = `memberCount` across all projects in e2e tests for both viewer permission levels (with and without `workspace.members.view`) | `apps/api/test/projects.e2e-spec.ts` AC-01, AC-02, AC-03 all pass | **PASS** |

---

## 3. OWASP Top 10 Security Audit

| OWASP Category | Check & Verification | Assessment |
| --- | --- | --- |
| **A01: Broken Access Control** | • Inherited workspace admins are determined server-side from `ProjectAccessPolicyStore` and `workspace_members`.<br>• Information leakage prevention: When viewer lacks `workspace.members.view`, inherited workspace admins are completely excluded from `GET /members` and `total` reflects only assigned members.<br>• Workspace admins cannot be modified or deleted in project contexts; `MembersTable` enforces read-only state for inherited rows regardless of UI props passed. | **PASS** |
| **A02: Cryptographic Failures** | No secrets or sensitive tokens handled in member counting or table display. | **PASS** |
| **A03: Injection** | All database queries constructed using TypeORM query builder with parameter bindings `$1, $2, ...`. Zero dynamic SQL concatenation. | **PASS** |
| **A04: Insecure Design** | • Consistent member count definition (`total = memberCount = assigned active members`) across list queries and detail queries.<br>• Clean separation of pagination bounds: `PROJECT_MEMBER_LIST_MAX` bounds assigned members only; inherited members have their own bound and cannot push assigned members out of the first page. | **PASS** |
| **A05: Security Misconfiguration** | Rate limiters, authentication guards, and project permission guards remain active in front of all project and member endpoints. | **PASS** |
| **A06: Vulnerable Components** | Zero new third-party npm dependencies added. All features built with existing Mantine and TanStack Query primitives. | **PASS** |
| **A07: Identification & Auth Failures** | Session validation and workspace context validation required on all API endpoints. | **PASS** |
| **A08: Software & Data Integrity** | TypeScript strict mode passes across monorepo. Source field on `ProjectMemberResponse` is strictly typed as `"inherited" \| "assigned"`. | **PASS** |
| **A09: Logging & Monitoring Failures** | Structured audit logging via `LoggingProjectEvents`: `project.members.list.truncated` explicitly records `source: 'assigned'` or `source: 'inherited'` along with `total` and `limit`. Zero PII or email leaked. | **PASS** |
| **A10: Server-Side Request Forgery** | No outbound HTTP network requests initiated by these endpoints or UI components. | **PASS** |

---

## 4. Architectural Invariants (§7), Seams & Design Pass

1. **Invariants Verification (INV-01 … INV-09)**:
   - **INV-01**: `ProjectMemberListResponse.total` = `ProjectListItem.memberCount` = count of active assigned project members across all viewers.
   - **INV-02**: `ProjectListItem.members` never includes inherited workspace admins; `membersOf` excludes workspace admins with implicit project roles.
   - **INV-03**: `truncated` flag indicates `total > assigned.length`. Inherited rows never count against `PROJECT_MEMBER_LIST_MAX`.
   - **INV-04**: Pinned group is always rendered at the top of the table on web, preserved across all sort keys and directions.
   - **INV-05**: Inherited rows render without comboboxes, buttons, or action menus. Badge "Workspace Admin" is focusable with tooltip explaining inherited access.
   - **INV-06**: Color contrast ≥ 4.5:1 verified for both light and dark color schemes. Sticky column retains row background.
   - **INV-07**: Empty screen "No members yet" renders only when `members.length === 0`. When `total === 0` but inherited rows exist, table renders with `NONE_ADDED_TO_PROJECT` note.
   - **INV-08**: Inherited rows only returned when viewer possesses `workspace.members.view`.
   - **INV-09**: No role name string comparisons used for inherited logic in `apps/`; `m.source === 'inherited'` used universally.

2. **Seam Reuse & Preservation**:
   - `applyMemberListQuery` in `memberListView.ts` accepts optional `pinned` comparator, defaulting to standard sort. Existing workspace member tests pass unmodified (AC-13).
   - `MembersListView` receives `inheritedRow`, `noneAddedNote`, and `truncatedMessage` props with safe defaults.
   - `addProjectMembersToCache` in `projectQueries.ts` preserves `assignedAdminCount` and all extra fields while keeping inherited rows at the head.
   - No modifications to `docs/features/13-merge-role-workspace-project/`.

---

## 5. Visual Contrast and Styling Audit (AC-08)

1. **Light Mode**:
   - Row Background: `--app-color-row-inherited: var(--mantine-color-gray-1)` (`#F3F4F6`)
   - Text Color: `--app-color-row-inherited-text: var(--mantine-color-gray-7)` (`#4B5563`)
   - Contrast Ratio: **6.9:1** (exceeds WCAG AA requirement of 4.5:1)
   - Table Surface Contrast: Row background `#F3F4F6` is clearly distinguishable from white surface (`#FFFFFF`).
   - Badge Variant `default`: White background with subtle border, dark text, contrast **14.5:1** on row background.

2. **Dark Mode**:
   - Row Background: `--app-color-row-inherited: var(--mantine-color-dark-5)` (`#2A3646`)
   - Text Color: `--app-color-row-inherited-text: var(--mantine-color-dark-1)` (`#B4C0D0`)
   - Contrast Ratio: **6.6:1** (exceeds WCAG AA requirement of 4.5:1)
   - Table Surface Contrast: Row background `#2A3646` is clearly distinguishable from dark table background `dark-7` (`#0F172A`).
   - Badge Variant `default`: `dark-6` (`#1E293B`) background with border, contrast **6.0:1** on row background.

3. **Sticky Column**:
   - Specificity `.inheritedRow > td` is `(0, 1, 1)`, which cleanly overrides `.stickyColumn` `(0, 1, 0)` in `dataTable.module.css`. No white background gap or `!important` needed.

---

## 6. Verification Verdict

- `pnpm lint`: **PASS** (0 warnings, 0 errors across monorepo)
- `pnpm typecheck`: **PASS** (0 errors across contracts, api, web)
- `pnpm test`: **PASS** (629/629 tests green across 85 web test files and all packages)
- `pnpm dup`: **PASS** (1.82% duplication rate, well below 5% threshold)
- `pnpm --filter @repo/api test:e2e`: **PASS** (322/322 e2e tests green across 14 test suites)
- `git diff develop...HEAD -- docs/features/13-merge-role-workspace-project`: **EMPTY** (RQ D1 verified)
- `grep -rn "inheritedProjectMembers" apps/api/src | grep -v spec`: **PASS** (only in `projectFilters.ts` and repository)
- `grep -rn "implicitRoles?\|implicitRoles = \[\]" apps/api/src`: **PASS** (empty)
- `grep -rn "projectMembersQuery(.*\[\])" apps/api/src | grep -v spec`: **PASS** (empty)
- M-01 SQL verification on test database: **PASS** (`count: 0`)

Branch `feature/16-inherited-workspace-admins-in-project` is fully implemented, rigorously verified across all quality gates, and ready for local `--no-ff` merge into `develop`.
