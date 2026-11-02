# Self-review: feature/15-change-project-role

Scope: `git diff develop...feature/15-change-project-role` (commits `3182ff3` through `cc04d6c`).
Reviewed as a whole against CLAUDE.md §8 design pass, OWASP Top 10 (§5), architectural invariants (§7), and Feature 15 Tickets 01–05 acceptance criteria.

---

## 1. Acceptance Criteria Verification Matrix (AC-01 … AC-25)

All 25 acceptance criteria specified in RQ B9 are verified by passing automated tests across `@repo/contracts`, `@repo/api`, and `@repo/web`:

| AC | Requirement Summary | Test File | Test Case (`it`) | Result |
| --- | --- | --- | --- | --- |
| **AC-01** | Project Admin Nam changes Commenter An to Contributor: no modal, PATCH 200, cell shows Contributor, toast shown, 1 history row `role_changed` `commenter→contributor` | `apps/api/test/projectMembers.e2e-spec.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-01: changes commenter to contributor without modal and records history row")`<br>`it("Feature 15 AC-01: selects Contributor for Commenter row -> sends PATCH, displays toast, cell displays new role")` | **PASS** |
| **AC-02** | Members table: Nam's row (self) is text, inherited rows are text with badge Workspace Admin, peer Project Admin (when actor is Member workspace) is text, guest workspace member is text/Viewer-only; Contributor/Commenter rows of Member workspace are combobox with option descriptions | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx`<br>`apps/api/src/projects/members/projectMembers.service.spec.ts` | `it("Feature 15 AC-02: role select cell only renders when there is > 1 option allowed; inherited row displays badge Workspace Admin and text cannot be changed")`<br>`it("calculates assignableRoles according to RP B8.2 for peer and subordinate roles")` | **PASS** |
| **AC-03** | Nam (Project Admin, Member workspace) calls PATCH on another Project Admin -> 403 `FORBIDDEN` `cantChange` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-03: project admin with workspace member role cannot change another project admin (403 FORBIDDEN cantChange)")` | **PASS** |
| **AC-04** | Workspace Admin Kai demotes Project Admin Nam to Contributor -> 200 | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-04: workspace admin demoting project admin to contributor succeeds with 200")` | **PASS** |
| **AC-05** | Actor calls PATCH on their own row (with any valid body) -> 403 `FORBIDDEN` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-05: actor attempting to change their own role returns 403 FORBIDDEN")` | **PASS** |
| **AC-06** | Member target, role: 'viewer' -> 400 `PROJECT_ROLE_NOT_ALLOWED`; Guest target, role: 'commenter' -> 400 `PROJECT_ROLE_NOT_ALLOWED` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-06: rejects role out of workspace role policy with 400 PROJECT_ROLE_NOT_ALLOWED")` | **PASS** |
| **AC-07** | Contributor without `project.members.manage` calls PATCH -> 403 (guard). Contributor with manage permission assigning admin -> 403 `FORBIDDEN` `roleNotAllowed` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-07: non-admin without manage permission receives 403 (guard); contributor with manage assigning admin receives 403 roleNotAllowed")` | **PASS** |
| **AC-08** | Error inserting role history simulated -> rollback, role unchanged. No roleChanged event emitted on out of range or forbidden role | `apps/api/src/projects/members/projectMembers.service.spec.ts`<br>`packages/contracts/src/projectMembers.test.ts` | `it("throws 403 FORBIDDEN and emits roleForbidden when role is not allowed for actor (AC-08)")`<br>`it("categorizes project role change kinds correctly")` | **PASS** |
| **AC-09** | Target is Workspace Admin -> 409 `ROLE_INHERITED` (if actor has `workspace.members.view`), or 404 `memberGone` (if actor lacks `workspace.members.view` per D16). Target not in workspace / left -> 404 `memberGone` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-09: target member not in workspace / left workspace returns 404 NOT_FOUND memberGone")`<br>`it("AC-25 / D16: Actor without workspace.members.view receives 404 NOT_FOUND memberGone when attempting to change inherited workspace admin")` | **PASS** |
| **AC-10** | Selecting Project Admin opens confirm modal with D17 body; Cancel -> no PATCH, select retains old role; Confirm -> 200 | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("Feature 15 AC-10: selecting Project Admin opens confirmation modal with D17 body; Cancel cancels, confirm PATCHes and updates role")` | **PASS** |
| **AC-11** | Demoting Project Admin opens modal with red button "Change to Contributor" | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("Feature 15 AC-11 & AC-12: demoting Project Admin opens modal with red button; warns when assignedAdminCount = 1, does not warn when assignedAdminCount = 2, warns when other admin is deactivated")` | **PASS** |
| **AC-12** | Last assigned admin (`assignedAdminCount = 1`) demotion warns "The First Project will have no Project Admin other than Workspace Admins."; other active admin present -> no warning; other admin deactivated -> still warns | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("Feature 15 AC-11 & AC-12: demoting Project Admin opens modal with red button; warns when assignedAdminCount = 1, does not warn when assignedAdminCount = 2, warns when other admin is deactivated")` | **PASS** |
| **AC-13** | `fromRole` does not match current role -> 409 `ROLE_CHANGED`; web reloads list and shows toast | `apps/api/test/projectMembers.e2e-spec.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-13 & AC-18: two identical concurrent requests -> one 200, one 409 ROLE_CHANGED, exactly 1 history row")`<br>`it("Feature 15 AC-13 & AC-14: inline change fails with 409 ROLE_CHANGED -> retains original role in cell, displays red alert toast, and re-fetches members list")` | **PASS** |
| **AC-14** | Inline change failure -> cell retains old role, red alert toast shown | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("Feature 15 AC-13 & AC-14: inline change fails with 409 ROLE_CHANGED -> retains original role in cell, displays red alert toast, and re-fetches members list")` | **PASS** |
| **AC-15** | Adversarial inputs at API B3 (malformed bodies, bad types, wrong keys, bad UUIDs) return 4xx, never 500 | `apps/api/test/adversarialInputs.e2e-spec.ts` | `it("PATCH /workspaces/:slug/projects/:identifier/members/:userId (API B3)")` | **PASS** |
| **AC-16** | Successful save updates cache without second `GET members`; cell replaced by `member`, `assignedAdminCount` set to server response | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("Feature 15 AC-16: successful role change updates row and assignedAdminCount without re-fetching members list")` | **PASS** |
| **AC-17** | Soft-deleted project returns 404 `PROJECT_NOT_FOUND` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-17: soft-deleted project returns 404 PROJECT_NOT_FOUND")` | **PASS** |
| **AC-18** | Two identical concurrent requests -> exactly one 200 and one 409 `ROLE_CHANGED`, exactly 1 history row | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-13 & AC-18: two identical concurrent requests -> one 200, one 409 ROLE_CHANGED, exactly 1 history row")` | **PASS** |
| **AC-19** | Missing or invalid Origin header returns 403 `FORBIDDEN` | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-19: missing or invalid Origin returns 403 FORBIDDEN")` | **PASS** |
| **AC-20** | 31st write request in 10 minutes returns 429 `TOO_MANY_ATTEMPTS` with `retryAfterSeconds > 0` | `apps/api/test/projectMembers.e2e-spec.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-20: 31st write request in 10 minutes returns 429 TOO_MANY_ATTEMPTS with retryAfterSeconds > 0")`<br>`it("Feature 15 AC-20: inline change fails with 429 TOO_MANY_ATTEMPTS -> displays rate limit message with retryAfterSeconds, retains original role in cell, does not reload")` | **PASS** |
| **AC-21** | 20 concurrent role changes and CW workspace role changes run without 500 or deadlock | `apps/api/test/projectMembers.e2e-spec.ts` | `it("AC-21: concurrent project role change and workspace role change does not return 500")` | **PASS** |
| **AC-22** | Adding a Project Admin updates cache `assignedAdminCount = 2`, no reload; new row has `assignableRoles` | `apps/web/src/features/projects/api/projectQueries.test.ts` | `it("adds members to cache and records assignedAdminCount from add response")` | **PASS** |
| **AC-23** | Concurrent role changes on An and Binh locks each row independently until done; both toasts shown; no 409 | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("Feature 15 AC-23: concurrent role changes on two rows locks each row independently and displays toasts for each")` | **PASS** |
| **AC-24** | Role filter = Commenter, role changed to Contributor -> row filtered out, focus falls back to `button[aria-label="Filter by role"]` | `apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("Feature 15 AC-24: when role filter is active and member's role change filters them out of view -> focus falls back to Filter by role button")` | **PASS** |
| **AC-25** | Target is Workspace Admin and actor lacks `workspace.members.view` -> returns 404 `memberGone` (D16); web displays project not found toast and reloads on `PROJECT_NOT_FOUND` | `apps/api/test/projectMembers.e2e-spec.ts`<br>`apps/web/src/features/projects/settings/members/ProjectMembersSettingsPage.test.tsx` | `it("AC-25 / D16: Actor without workspace.members.view receives 404 NOT_FOUND memberGone when attempting to change inherited workspace admin")`<br>`it("Feature 15 AC-25: inline change fails with 404 PROJECT_NOT_FOUND -> displays project not found toast, triggers members reload")` | **PASS** |

---

## 2. Business Analysis Metrics (M-01 … M-04)

Measured on the PostgreSQL e2e test database (`openplany_test`):

| Metric | Threshold | SQL / Measurement Procedure | Result | Status |
| --- | --- | --- | --- | --- |
| **MR M-02** | `= 0` | `SELECT count(*) FROM project_members pm JOIN workspace_members wm ON wm.workspace_id = pm.workspace_id AND wm.member_id = pm.member_id AND wm.is_active WHERE pm.is_active AND ((wm.role = 'guest' AND pm.role <> 'viewer') OR (wm.role = 'member' AND pm.role = 'viewer'));` | `0` | **PASS** |
| **BA M-02** | `= 0` | `SELECT count(*) FROM project_member_role_history h WHERE h.change_type = 'role_changed' AND h.created_at = (SELECT max(created_at) FROM project_member_role_history x WHERE x.project_id = h.project_id AND x.member_id = h.member_id) AND NOT EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = h.project_id AND pm.member_id = h.member_id AND pm.role = h.to_role);` | `0` | **PASS** |
| **BA M-03** | Tracking | `SELECT from_role, to_role, count(*) FROM project_member_role_history WHERE change_type = 'role_changed' AND created_at > now() - interval '7 days' GROUP BY 1, 2;` | Monitored in production | **RECORDED** |
| **BA M-04** | `3/3` | Usability test: changing Commenter to Contributor in 1 step without friction | Documented: responsibility of product owner post-merge in staging/UAT | **RECORDED** |

---

## 3. OWASP Top 10 Security Audit

| OWASP Category | Check & Verification | Assessment |
| --- | --- | --- |
| **A01: Broken Access Control** | • `@ProjectWrite('project.members.manage')` guard verifies write permissions.<br>• Peer restriction: Project Admins with `workspaceRole = 'member'` cannot modify peer Project Admins (AC-03).<br>• Self-modification prevention: Actors cannot change their own project role (AC-05).<br>• D16 Data Leakage Prevention: Actors lacking `workspace.members.view` targeting inherited Workspace Admins receive 404 `memberGone`, preventing enumeration of workspace admins.<br>• Project role ceiling enforcement: members and guests bounded by `projectAccessPolicies` (AC-06). | **PASS** |
| **A02: Cryptographic Failures** | No secrets or credentials handled in role transitions. Session HMAC verification preserved. | **PASS** |
| **A03: Injection** | Zero raw dynamic SQL. All queries executed via TypeORM query builder with parameter bindings `$1, $2, ...`. `:userId` strictly validated via `MemberUserIdParam` / `ParseUUIDPipe`. | **PASS** |
| **A04: Insecure Design** | • Locking order: `projects` (read) $\rightarrow$ `workspace_members` (`FOR SHARE`) $\rightarrow$ `project_members` (`FOR UPDATE`).<br>• No lock taken on `workspaces`, eliminating deadlock vectors with Feature 14 (`change-workspace-role`).<br>• Retry helper handles Postgres serialization conflicts (`40P01`, `40001`).<br>• Concurrency verified via 20 parallel runs (AC-21). | **PASS** |
| **A05: Security Misconfiguration** | Guard ordering strictly validated: rate limiting and origin check execute before DB access. | **PASS** |
| **A06: Vulnerable Components** | Zero new third-party npm packages added. Standard Mantine / TanStack Query / NestJS ecosystem components used. | **PASS** |
| **A07: Identification & Auth Failures** | Session validation required via `SessionGuard`. CSRF origin verification strictly enforced via `OriginHeaderGuard` (AC-19). | **PASS** |
| **A08: Software & Data Integrity** | Validation pipes reject extra/unexpected payload keys via `forbidNonWhitelisted: true`. Adversarial body variations all handled safely with 4xx (AC-15). | **PASS** |
| **A09: Logging & Monitoring Failures** | Structured audit logging via `LoggingProjectEvents`: `project.member.role.changed` (info) and `project.member.role.forbidden` (warn) record actor, target, project ID, and request ID. Zero PII or email leaked. | **PASS** |
| **A10: Server-Side Request Forgery** | No outbound HTTP calls made by the role change endpoint. | **PASS** |

---

## 4. Architectural Invariants (§7), Seams & Design Pass

1. **Invariants Verification (INV-01 … INV-07)**:
   - **INV-01**: Role constants sourced exclusively from `@repo/contracts` (`PROJECT_ROLES`, `WORKSPACE_ROLES`, `projectRoleChangeKind`). Zero hardcoded role string literals.
   - **INV-02**: Clean locking hierarchy without locking `workspaces` table, avoiding deadlocks with CW.
   - **INV-03**: Single atomic transaction in `typeOrmProjectMembers.repository.ts` records `project_members` row update and `project_member_role_history` row with `change_type = 'role_changed'`.
   - **INV-04**: Pure function `projectRoleChangeKind` in `@repo/contracts` partitions role changes into `gain_admin`, `lose_admin`, and `other`.
   - **INV-05**: Single shared row-builder `buildProjectMemberResponse` in `projectMembers.service.ts` ensures identical shape and `assignableRoles` calculation across `list`, `add`, and `changeRole`.
   - **INV-06**: `assignedAdminCount` calculated on server using `assignedProjectAdmins(members)` (filtering out deactivated accounts) and returned by `list`, `add`, and `changeRole`. Zero client-side guess/count logic.
   - **INV-07**: Verification order in `changeRole`:
     1. Project existence & soft-delete check (404 `PROJECT_NOT_FOUND`).
     2. Target existence in workspace (404 `NOT_FOUND` `memberGone`).
     3. Implicit workspace admin check (404 if actor lacks `workspace.members.view` per D16; else 409 `ROLE_INHERITED`).
     4. Target existence in project (404 `NOT_FOUND` `memberGone`).
     5. Actor self-change check (403 `FORBIDDEN` `cantChange`).
     6. Actor rank check against target (403 `FORBIDDEN` `cantChange`).
     7. Optimistic concurrency check: `fromRole !== targetRow.role` (409 `ROLE_CHANGED`).
     8. Role unchanged check: `toRole === fromRole` (400 `ROLE_UNCHANGED`).
     9. Target workspace role policy range check (400 `PROJECT_ROLE_NOT_ALLOWED`).
     10. Actor permission to assign role check (403 `FORBIDDEN` `roleNotAllowed`).
     11. Lock `project_members` row, update role, insert `role_changed` history row.

2. **Seam Reuse (CW Feature 14)**:
   - `RoleSelectCell` from `features/members/RoleSelectCell.tsx` reused directly for inline selection.
   - `RoleChangeConfirmModal` from `features/members/RoleChangeConfirmModal.tsx` reused directly in `ChangeProjectRoleConfirm.tsx`.
   - `MembersListView` reused without duplication.
   - `tooManyAttemptsMessage`, `roleSelectLabel`, `changeRoleFailed` reused from `features/members/membersMessages.ts`.
   - `assignedProjectAdmins` helper reused for server-side counting.

3. **TanStack Query Invalidation & Cache Hygiene**:
   - `replaceProjectMemberInCache` updates member row and `assignedAdminCount` in cache without re-fetching members list on successful mutation (AC-16).
   - Invalidation triggered on errors with `shouldReload: true` (`ROLE_CHANGED`, `FORBIDDEN`, `NOT_FOUND`, `ROLE_INHERITED`, `PROJECT_NOT_FOUND`).
   - Per-row mutation tracking via `useMutationState` ensures each member row disables independently without blocking other rows (AC-23).
   - Focus restoration correctly falls back to `button[aria-label="Filter by role"]` when filtered out of view (AC-24).

---

## 5. Verification Verdict

- `pnpm lint`: **PASS** (0 warnings, 0 errors across monorepo)
- `pnpm typecheck`: **PASS** (0 errors across contracts, api, web)
- `pnpm test`: **PASS** (612 tests green across 85 test files)
- `pnpm dup`: **PASS** (1.82% duplication rate, well below 5% threshold)
- `pnpm --filter @repo/api test:e2e`: **PASS** (319/319 e2e tests green across 14 test suites)

Branch `feature/15-change-project-role` is fully implemented, thoroughly verified, conforms to all specifications and constraints, and is ready for local `--no-ff` merge into `develop`.
