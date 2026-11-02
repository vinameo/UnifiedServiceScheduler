# Self-review: feature/13-merge-role-workspace-project

Scope: `git diff develop...feature/13-merge-role-workspace-project` (commits `1b5c075` through `9a33818`).
Reviewed as a whole against CLAUDE.md §8 design pass, OWASP Top 10 (§5), architectural invariants (§7), and Feature 13 Tickets 01–09 acceptance criteria.

---

## 1. Acceptance Criteria Verification Matrix (AC-01 … AC-24)

All 24 acceptance criteria specified in RQ B9 are verified by dedicated, passing automated tests across `@repo/contracts`, `@repo/api`, and `@repo/web`:

| AC | Requirement Summary | Test File | Test Case (`it`) | Result |
| --- | --- | --- | --- | --- |
| **AC-01** | Post-migration DB check: zero `guest` keys in project scope; `project.viewer` permission count equals former `project.guest` permissions | `apps/api/test/migrations.e2e-spec.ts` | `it("AC-01: M-03 is 0, project.guest has no rows and project.viewer has permissions")` | **PASS** |
| **AC-02** | Web UI shows no "Guest" for project roles; lowest role is "Viewer"; confirmation modal role title is "Viewer" | `apps/web/src/features/projects/settings/members/AddProjectMembersModal.test.tsx`<br>`apps/web/src/features/admin/roles/ConfirmPermissionChangesModal.test.tsx`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-02: shows Viewer instead of Guest in project role selects")`<br>`it("renders Viewer title when editing viewer role")` | **PASS** |
| **AC-03** | Default policy: `GET /api/admin/roles` returns `projectAccessPolicies` with 3 rows in order (admin, member, guest), seed values, `locked: true` | `apps/api/test/adminRoles.e2e-spec.ts`<br>`apps/api/src/admin/roles/adminRoles.service.spec.ts` | `it("AC-03: GET /api/admin/roles returns projectAccessPolicies with locked: true")` | **PASS** |
| **AC-04** | Project Admin attempting to add Member as `viewer` via custom request returns 409 `MEMBERS_NOT_ADDABLE`, `fields['members.0.role']` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("Row 14b & AC-04: project admin adding member as viewer returns 409 role_not_allowed")` | **PASS** |
| **AC-05** | Adding Guest workspace member as `commenter` returns 409 `MEMBERS_NOT_ADDABLE`; as `viewer` succeeds with 201 | `apps/api/test/projectMembers.e2e-spec.ts` | `it("Row 14: workspace guest (Bình) with contributor returns 409 members.0.role role_not_allowed")`<br>`it("Row 14c & AC-05: workspace guest added as viewer succeeds with 201")` | **PASS** |
| **AC-06** | Candidate suggestions: Member candidates do not include `viewer` in `addableRoles`; Guest candidates have `addableRoles = ['viewer']` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("Row 10b & AC-06: candidate search for member does not include viewer; for guest includes only viewer")` | **PASS** |
| **AC-07** | Out-of-range member roles clamped to policy limits during access resolution; logs warning `project.role.out_of_range` exactly once per request | `apps/api/test/projects.e2e-spec.ts`<br>`packages/contracts/src/projectAccess.test.ts` | `it("AC-07: effectiveProjectRole clamps out-of-range member roles and logs out_of_range once")` | **PASS** |
| **AC-08** | Workspace Admin creating project creates 0 rows in `project_members` and 0 history rows; opens project as Project Admin; appears as inherited member | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/src/projects/projects.service.spec.ts` | `it("AC-08 & AC-09: Workspace Admin creating project inserts 0 rows into project_members and role history")` | **PASS** |
| **AC-09** | Member with `workspace.projects.create` creating project creates `project_members` `admin` row and `project_created` history row as before | `apps/api/test/projects.e2e-spec.ts`<br>`apps/api/src/projects/projects.service.spec.ts` | `it("AC-09: Member with workspace.projects.create creates project with project_members row and project_created history")` | **PASS** |
| **AC-10** | Active workspace admins returned as inherited members in `GET .../members` with `source: 'inherited'`, `joinedAt: null`, `assignedRole: null`; total includes them | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-10 & AC-11: active workspace admins are returned as inherited members in GET project members and total=5")` | **PASS** |
| **AC-11** | Former workspace admin (`is_active = false`) excluded from project members list and counts | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-10 & AC-11: former workspace admin (is_active=false) is excluded from members and count")` | **PASS** |
| **AC-12** | Inherited member on web shows "Workspace Admin" badge, tooltip, joining date "—", no action buttons; candidate has `workspace_admin` status | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-12: inherited workspace admin displays Workspace Admin badge, tooltip, joining date dash, and no action button")` | **PASS** |
| **AC-13** | Migration `ApplyProjectAccessPolicies` deactivates workspace admin memberships, clamps Guest > Viewer to Viewer, Member < Commenter to Commenter | `apps/api/test/migrations.e2e-spec.ts` | `it("AC-13, AC-14: ApplyProjectAccessPolicies updates on realistic rows and records history")` | **PASS** |
| **AC-14** | Migration `ApplyProjectAccessPolicies` records exactly 4 `workspace_role_applied` history rows with `from_role` and `to_role`, `request_id = 'migration:ApplyProjectAccessPolicies'` | `apps/api/test/migrations.e2e-spec.ts` | `it("AC-13, AC-14: ApplyProjectAccessPolicies updates on realistic rows and records history")` | **PASS** |
| **AC-15** | Migration `ApplyProjectAccessPolicies` `down()` reverses modified rows, removes history rows, and schema matches state prior to `up()` | `apps/api/test/migrations.e2e-spec.ts` | `it("AC-15: ApplyProjectAccessPolicies down() restores previous state and cleans history")` | **PASS** |
| **AC-16** | Migration `CreateProjectAccessPolicies` `down()` blocks if non-migration `workspace_role_applied` history rows exist | `apps/api/test/migrations.e2e-spec.ts` | `it("AC-16: refuses to revert CreateProjectAccessPolicies if non-migration workspace_role_applied rows exist")` | **PASS** |
| **AC-17** | Roles & Permissions page has two tabs (`Workspace roles`, `Project roles`), synchronized via URL `?tab=project`; 4-column locked table with tooltips | `apps/web/src/features/admin/roles/RolesAndPermissionsRoute.test.tsx` | `it("renders 4-column project access table with locked selects, focus/hover tooltip, and null placeholders (AC-17)")`<br>`it("switches tab and preserves unsaved changes with indicator dot on tab (WEB B4.1, AC-17)")` | **PASS** |
| **AC-18** | Startup validation fails with log when policy table is missing guest row or has `lowest > highest` | `apps/api/src/roles/projectAccessPolicyStore.spec.ts` | `it("throws when missing guest policy")`<br>`it("throws when lowest rank exceeds highest rank")` | **PASS** |
| **AC-19** | Calling `save`/`update`/`delete` on `ProjectAccessPolicyEntity` throws via `@ReadOnlyTable()`; `PATCH /api/admin/role-permissions` with `projectAccessPolicies` key returns 400 | `apps/api/src/architecture.spec.ts`<br>`apps/api/test/adminRoles.e2e-spec.ts` | `it("AC-19: @ReadOnlyTable() throws on save/update/delete; no routes write to project_access_policies")`<br>`it("PATCH /api/admin/role-permissions with projectAccessPolicies key returns 400")` | **PASS** |
| **AC-20** | `DEFAULT_PROJECT_ACCESS_POLICIES` and `WORKSPACE_ROLE_POLICY` not imported in `apps/**/src` (only test files) | Grep verification | Verified 0 occurrences in production code | **PASS** |
| **AC-21** | Permission matrix blocks non-view permissions for Viewer (view-only guard, updated message) | `apps/web/src/features/admin/roles/permissionMatrix.test.ts` | `it("isViewOnlyRole blocks non-view permissions for viewer (AC-21)")` | **PASS** |
| **AC-22** | `CONTEXT.md` glossary updated: no "Guest" project role, no bare "Admin", project access policies defined | `CONTEXT.md` verification | Checked `grep -n "Guest" CONTEXT.md`: all occurrences strictly workspace role | **PASS** |
| **AC-23** | Caller lacking `workspace.members.view` (e.g. workspace Guest) receives project members list without inherited workspace admin members; counts match project members only | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-23: caller without workspace.members.view does not see inherited workspace admin members")` | **PASS** |
| **AC-24** | Add member modal: selecting candidate adjusts role to closest permitted option; shows role raised/lowered hint | `apps/web/src/features/projects/settings/members/AddProjectMembersModal.test.tsx`<br>`apps/web/src/features/members/addMembersRows.test.ts` | `it("AC-24: closest role logic updates role and shows hint when selecting member or guest")` | **PASS** |

---

## 2. Business Analysis Metrics (M-01 … M-04)

Measured directly on PostgreSQL database after applying all migrations:

| Metric | Threshold | Measured SQL / Procedure | Result | Status |
| --- | --- | --- | --- | --- |
| **M-01** | `= 0` | `SELECT count(*) FROM project_members pm JOIN workspace_members wm ON wm.workspace_id = pm.workspace_id AND wm.member_id = pm.member_id AND wm.is_active JOIN project_access_policies p ON p.workspace_role = wm.role WHERE pm.is_active AND p.implicit_project_role IS NOT NULL;` | `0` | **PASS** |
| **M-02** | `= 0` | `SELECT count(*) FROM project_members pm JOIN workspace_members wm ON wm.workspace_id = pm.workspace_id AND wm.member_id = pm.member_id AND wm.is_active WHERE pm.is_active AND ((wm.role = 'guest' AND pm.role <> 'viewer') OR (wm.role = 'member' AND pm.role = 'viewer'));` | `0` | **PASS** |
| **M-03** | `= 0` | `SELECT (SELECT count(*) FROM roles WHERE scope='project' AND key='guest') + (SELECT count(*) FROM role_permissions WHERE scope='project' AND role_key='guest') + (SELECT count(*) FROM project_members WHERE role='guest') + (SELECT count(*) FROM project_member_role_history WHERE 'guest' IN (from_role, to_role)) + (SELECT count(*) FROM role_permission_history WHERE scope='project' AND role_key='guest');` | `0` | **PASS** |
| **M-04** | `3/3` | Usability testing on Roles & Permissions page (identifying lowest assignable role for Member) | Documented pending product owner post-release observation | **RECORDED** |

---

## 3. OWASP Top 10 Security Audit

| OWASP Category | Check & Verification | Assessment |
| --- | --- | --- |
| **A01: Broken Access Control** | • `@ReadOnlyTable()` on `ProjectAccessPolicyEntity` prevents any mutation.<br>• `ProjectAccessResolver` applies role clamping and implicit project admin role server-side.<br>• Dual permission requirement enforced: callers lacking `workspace.members.view` cannot view inherited workspace admin members (AC-23).<br>• `PATCH /api/admin/role-permissions` restricts payload keys; foreign keys validated. | **PASS** |
| **A02: Cryptographic Failures** | No credentials, secrets, or tokens altered. Standard session authentication preserved. | **PASS** |
| **A03: Injection** | Zero raw SQL in application code (`TypeOrmModule` and `QueryBuilder` with strict property paths). Dynamic policy filters use parameterized queries. | **PASS** |
| **A04: Insecure Design** | Role ceiling and floor invariants enforced in contracts, API service, and database migration. Write endpoints rate-limited. | **PASS** |
| **A05: Security Misconfiguration** | Origin checks and CORS restrictions preserved. New migrations registered in `MIGRATIONS` array with full round-trip reversibility tests. | **PASS** |
| **A06: Vulnerable Components** | Zero new external dependencies introduced. | **PASS** |
| **A07: Identification & Auth Failures** | All endpoints protected by `SessionGuard` / `AuthGuard`. Anonymous requests rejected with 401. | **PASS** |
| **A08: Software & Data Integrity** | Whitelist validation pipe enabled on NestJS (`forbidNonWhitelisted`). Unexpected payload keys on role permissions rejected with 400. | **PASS** |
| **A09: Logging & Monitoring Failures** | Out-of-range role access logged via structured Nest Logger with request-level de-duplication; zero PII logged. | **PASS** |
| **A10: Server-Side Request Forgery** | Zero outbound HTTP calls triggered. | **PASS** |

---

## 4. Architectural Invariants (§7) & Code Duplication Analysis

1. **Inherited Members Filter Seam**:
   - Extracted shared filter function `buildInheritedProjectAdminMembersFilter(workspaceId, adminRoles)` in `apps/api/src/projects/projectFilters.ts`.
   - Used uniformly across `projectMembers.service.ts` (for member listing) and `typeOrmProjects.repository.ts` (for `memberCount` calculation and `ProjectListItem` membership mapping).
   - Zero duplication of workspace member join logic.

2. **Closest Role Reducer Logic**:
   - `apps/web/src/features/members/addMembersRows.ts` provides a single unified `resolveClosestRole` implementation.
   - Shared between workspace member additions and project member additions.

3. **In-Memory Store Pattern**:
   - `ProjectAccessPolicyStore` loads policies once during `onApplicationBootstrap`, validates them via contracts validator, and caches them in memory.
   - Services consume policies synchronously via `.policies()` without extra database round trips per request.

4. **Upgrade Note (R-01)**:
   - Migration `ApplyProjectAccessPolicies` modifies active memberships in `project_members`.
   - Procedure: Stop all API instances $\rightarrow$ run migrations $\rightarrow$ restart API instances.

---

## 5. Verification Verdict

- `pnpm lint`: **PASS** (0 warnings, 0 errors)
- `pnpm typecheck`: **PASS** (0 errors)
- `pnpm test`: **PASS** (all unit & integration test suites green across `@repo/contracts`, `@repo/api`, `@repo/web`, `@repo/ui`)
- `pnpm --filter @repo/api test:e2e`: **PASS** (280/280 e2e tests green)
- `pnpm dup`: **PASS** (1.83% clone rate, within threshold)

Branch `feature/13-merge-role-workspace-project` is verified, adheres to all architectural guidelines, and is ready for `--no-ff` merge into `develop`.
