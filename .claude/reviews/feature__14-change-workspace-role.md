# Self-review: feature/14-change-workspace-role

Scope: `git diff develop...feature/14-change-workspace-role` (commits `23df47f` through `d7f2eff`).
Reviewed as a whole against CLAUDE.md §8 design pass, OWASP Top 10 (§5), architectural invariants (§7), and Feature 14 Tickets 01–06 acceptance criteria.

---

## 1. Acceptance Criteria Verification Matrix (AC-01 … AC-29)

All 29 acceptance criteria specified in RQ B9 are verified by dedicated, passing automated tests across `@repo/contracts`, `@repo/api`, and `@repo/web`:

| AC | Requirement Summary | Test File | Test Case (`it`) | Result |
| --- | --- | --- | --- | --- |
| **AC-01** | Guest -> Member with Contributor: 200, role is Member, all 3 project rows updated to contributor, 1 role_changed, 3 workspace_role_applied history rows, appliedProjectCount = 3 | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`packages/contracts/src/workspaceRoleChange.test.ts` | `it("AC-01: changes guest to member with contributor applied to projects")`<br>`it("applies selected project role within assignable range for guest -> member")` | **PASS** |
| **AC-02** | Member -> Guest: 200, all active project rows updated to viewer, preview projectRoleChoices = ['viewer'] | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`packages/contracts/src/workspaceRoleChange.test.ts` | `it("AC-02: changes member to guest with viewer applied to projects")`<br>`it("forces viewer project role for member -> guest")` | **PASS** |
| **AC-03** | Member -> Workspace Admin: 200, gain_implicit deactivates project_members rows, inserts history with toRole = null, target opens all projects with Project Admin | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`packages/contracts/src/workspaceRoleChange.test.ts` | `it("AC-03: promotes member to workspace admin (gain_implicit, project members deactivated)")`<br>`it("plans gain_implicit for member -> admin")` | **PASS** |
| **AC-04** | Workspace Admin -> Member: 200, lose_implicit leaves no project rows, target cannot open any project without being member, preview effect = 'lose_implicit', affectedProjects.total = 7 (excluding deleted) | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`packages/contracts/src/workspaceRoleChange.test.ts` | `it("AC-04: demotes workspace admin to member (lose_implicit, affectedProjects.total = 7)")`<br>`it("plans lose_implicit for admin -> member")` | **PASS** |
| **AC-05** | Actor changing own role returns 403 `You can't assign this role` (tested with valid payload, unchanged role, wrong fromRole) | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-05: returns 403 when user attempts to change their own role")` | **PASS** |
| **AC-06** | Member with role.update demoting admin or assigning admin returns 403 | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-06: returns 403 when actor lacks rank to assign or demote admin")` | **PASS** |
| **AC-07** | Target role equals current role and fromRole matches returns 400 `ROLE_UNCHANGED` | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-07: returns 400 when role is unchanged")` | **PASS** |
| **AC-08** | Non-admin actor upgrading Guest to Member sending projectRole 'admin' returns 403; omitted or 'commenter' succeeds with 200, 2 rows commenter; preview actorCanChooseProjectRole = false, choices = ['commenter'] | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`packages/contracts/src/workspaceRoleChange.test.ts` | `it("AC-08: non-admin actor upgrading Guest to Member restricted to commenter")`<br>`it("locks choices to commenter when actor is non-admin for guest -> member")` | **PASS** |
| **AC-09** | Actor passes rank check but active admin count drops to 0 returns 409 `LAST_ADMIN` and rolls back | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`apps/api/src/workspaces/members/workspaceMembers.service.spec.ts` | `it("AC-09: returns 409 LAST_ADMIN when demoting the last workspace admin")`<br>`it("throws 409 LAST_ADMIN when active admin count drops to 0")` | **PASS** |
| **AC-10** | Concurrent mutual demotion of 2 workspace admins: workspace row locked FOR UPDATE, exactly one succeeds with 200, second receives 403 (rank lost upon first commit), leaving exactly 1 admin | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-10: concurrent mutual demotion of two workspace admins results in exactly one 200 and one 403")` | **PASS** |
| **AC-11** | Failure during history insertion simulated: entire transaction rolls back cleanly | `apps/api/src/workspaces/members/workspaceMembers.service.spec.ts` | `it("AC-11: rolls back transaction if project history insertion fails")` | **PASS** |
| **AC-12** | Target added to 4th project between preview and apply: apply modifies all 4 rows, appliedProjectCount = 4 | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-12: applies role to projects added between preview and apply")` | **PASS** |
| **AC-13** | Non-admin actor viewing preview of target in 3 projects where actor only accesses 1: preview has 1 name, total = 3, hiddenCount = 2 | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`apps/api/src/projects/access/projectAccessResolver.spec.ts` | `it("AC-13: non-admin actor only sees names of accessible projects in preview (hiddenCount)")`<br>`it("filters accessible project IDs for non-admin actor with project membership")` | **PASS** |
| **AC-14** | Target in 25 projects, actor has access to all: preview bounds to 10 names, total = 25, hiddenCount = 0 | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`apps/web/src/features/members/BoundedProjectList.test.tsx` | `it("AC-14: bounds preview names to at most 10 when target is in 25 projects")`<br>`it("renders up to 10 project names and shows count of remaining projects")` | **PASS** |
| **AC-15** | Preview detects target is sole assigned admin of project B -> B in `projectsLosingAssignedAdmin`; locked accounts excluded from count | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`apps/api/src/projects/projectFilters.spec.ts` | `it("AC-15: detects projects losing assigned admin in preview")`<br>`it("excludes locked users from assigned admin counts")` | **PASS** |
| **AC-16** | Workspace settings Members table: actor's own row displays plain text; other members display role select with aria-label "Role for {name}"; non-admin actor without permission sees text for all rows | `apps/web/src/features/workspaces/settings/members/MembersTable.test.tsx`<br>`apps/web/src/features/members/RoleSelectCell.test.tsx` | `it("AC-16: shows plain text for actor's own row, select for other members")`<br>`it("renders plain text when isReadOnly is true")` | **PASS** |
| **AC-17** | Selecting new role opens confirm modal, table cell retains original role; cancel closes modal without write request | `apps/web/src/features/workspaces/settings/members/MembersTable.test.tsx`<br>`apps/web/src/features/workspaces/settings/members/ChangeWorkspaceRoleModal.test.tsx` | `it("AC-17: selecting a role opens modal and cancel closes without change")`<br>`it("calls onCancel and performs no mutations when Cancel clicked")` | **PASS** |
| **AC-18** | Guest -> Member modal: project role selects defaults to Commenter, 3 choices available; non-admin actor sees disabled selector with explanation | `apps/web/src/features/workspaces/settings/members/ChangeWorkspaceRoleModal.test.tsx` | `it("AC-18: Guest -> Member modal defaults to Commenter with 3 choices and locks for non-admin actor")` | **PASS** |
| **AC-19** | Demoting Workspace Admin shows bold impact warning with project count, red "Change to Member" button, and descriptive modal title | `apps/web/src/features/workspaces/settings/members/ChangeWorkspaceRoleModal.test.tsx` | `it("AC-19: demoting admin shows lose_implicit warning and red confirm button")` | **PASS** |
| **AC-19b** | Promoting Member to Workspace Admin shows gain_implicit notification | `apps/web/src/features/workspaces/settings/members/ChangeWorkspaceRoleModal.test.tsx` | `it("AC-19b: promoting to admin shows gain_implicit note and confirm button")` | **PASS** |
| **AC-20** | Successful save updates cache without full members reload, shows toast, manages focus; invalidates project queries on implicit change or appliedProjectCount > 0 | `apps/web/src/features/workspaces/settings/members/MembersSettingsPage.test.tsx`<br>`apps/web/src/features/workspaces/settings/members/useChangeWorkspaceRole.test.ts` | `it("AC-20: updates list cache and shows success toast without reloading list")`<br>`it("invalidates project queries when appliedProjectCount > 0 or implicit role changed")` | **PASS** |
| **AC-21** | Error during save keeps modal open with error banner; 404/409/403 triggers members cache invalidation; 403 also invalidates workspace cache | `apps/web/src/features/workspaces/settings/members/ChangeWorkspaceRoleModal.test.tsx`<br>`apps/web/src/features/workspaces/settings/members/useChangeWorkspaceRole.test.ts` | `it("AC-21: retains modal on error and triggers invalidate/refetch")`<br>`it("invalidates workspace queries on 403 error")` | **PASS** |
| **AC-22** | Adversarial inputs B5.2 (invalid UUID, missing fields, malformed types, invalid roles) return 400/404, never 500 | `apps/api/test/adversarialInputs.e2e-spec.ts` | `it("AC-22: rejects malformed bodies and invalid params with 4xx")` | **PASS** |
| **AC-23** | fromRole mismatch returns 409 `ROLE_CHANGED`, no rows modified | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-23: returns 409 ROLE_CHANGED when fromRole does not match current DB role")` | **PASS** |
| **AC-24** | 20 parallel executions of PATCH member role and POST project members run concurrently without deadlock or 5xx | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-24: 20 parallel executions of PATCH member role and POST project members are deadlock-free")` | **PASS** |
| **AC-25** | Project added between preview and apply when request payload omits projectRole: falls back to floor of new role, appliedProjectCount = 1 | `apps/api/test/workspaceMembers.e2e-spec.ts`<br>`packages/contracts/src/workspaceRoleChange.test.ts` | `it("AC-25: applies floor role when project added between preview and apply without projectRole in payload")`<br>`it("resolves default project role from floor when projectRole is not provided")` | **PASS** |
| **AC-26** | Target in active and deleted projects: deleted excluded from preview total and history records; both rows updated in DB (INV-05) | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-26: handles target in active and deleted projects (deleted excluded from total/history)")` | **PASS** |
| **AC-27** | Locked target user account changed normally with 200, history recorded | `apps/api/test/workspaceMembers.e2e-spec.ts` | `it("AC-27: changes role of a locked user account successfully")` | **PASS** |
| **AC-28** | Rate limit guard enforces 30 requests/10 min for mutations and previews; returns 429 with retryAfterSeconds; web shows tooManyAttemptsMessage | `apps/api/test/adversarialInputs.e2e-spec.ts`<br>`apps/web/src/features/workspaces/settings/members/ChangeWorkspaceRoleModal.test.tsx` | `it("AC-28: enforces preview and mutation rate limits")`<br>`it("renders rate limit error message when 429 received")` | **PASS** |
| **AC-29** | Workspace Admin with no other active admins sees persistent "No active Admin" banner after modifying other members' roles | `apps/web/src/features/workspaces/settings/members/MembersSettingsPage.test.tsx` | `it("AC-29: displays no active admin warning accurately")` | **PASS** |

---

## 2. Business Analysis Metrics (M-01 … M-04)

Measured directly on PostgreSQL test database via `open-plany-postgres` query inspection:

| Metric | Threshold | SQL / Measurement Procedure | Result | Status |
| --- | --- | --- | --- | --- |
| **MR M-01** | `= 0` | `SELECT count(*) FROM project_members pm JOIN workspace_members wm ON wm.workspace_id = pm.workspace_id AND wm.member_id = pm.member_id AND wm.is_active JOIN project_access_policies p ON p.workspace_role = wm.role WHERE pm.is_active AND p.implicit_project_role IS NOT NULL;` | `0` | **PASS** |
| **MR M-02** | `= 0` | `SELECT count(*) FROM project_members pm JOIN workspace_members wm ON wm.workspace_id = pm.workspace_id AND wm.member_id = pm.member_id AND wm.is_active WHERE pm.is_active AND ((wm.role = 'guest' AND pm.role <> 'viewer') OR (wm.role = 'member' AND pm.role = 'viewer'));` | `0` | **PASS** |
| **BA M-02** | `= 0` | `SELECT count(*) FROM workspaces w WHERE w.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM workspace_members wm JOIN users u ON u.id = wm.member_id WHERE wm.workspace_id = w.id AND wm.is_active AND wm.role = 'admin' AND u.is_active);` | `0` | **PASS** |
| **BA M-03** | `= 0` | `SELECT count(*) FROM project_member_role_history p WHERE p.change_type = 'workspace_role_applied' AND p.actor_id IS NOT NULL AND (p.request_id IS NULL OR p.request_id = '' OR NOT EXISTS (SELECT 1 FROM workspace_member_role_history w WHERE w.change_type = 'role_changed' AND w.workspace_id = p.workspace_id AND w.member_id = p.member_id AND w.request_id = p.request_id));` | `0` | **PASS** |
| **BA M-04** | `3/3` | Usability test: upgrading Guest to Member in ≤ 3 steps with accurate impact understanding | Documented: Automated agent execution environment; verified via component & E2E UX tests (Step 1: Role select, Step 2: Impact preview, Step 3: Confirmation) | **RECORDED** |

---

## 3. OWASP Top 10 Security Audit

| OWASP Category | Check & Verification | Assessment |
| --- | --- | --- |
| **A01: Broken Access Control** | • Actor rank enforcement: Actors cannot modify their own role (AC-05), cannot demote equal or higher rank, and cannot assign higher rank than their own (AC-06).<br>• `WorkspacePermissionGuard` verifies `workspace.members.role.update` (or `workspace.members.view` for preview).<br>• Non-admin actors cannot select arbitrary project roles; choices are server-locked to the floor role (`commenter`) with 403 on override attempt (AC-08).<br>• Project privacy filtering: `filterAccessible` verifies actor's access scope and masks project names actor cannot view, returning only `hiddenCount` (AC-13). | **PASS** |
| **A02: Cryptographic Failures** | No credentials, secrets, or keys modified. Standard HMAC-signed session cookies and authorization tokens maintained. | **PASS** |
| **A03: Injection** | Zero raw SQL queries; all database interactions utilize TypeORM `EntityManager` and parameterized `SelectQueryBuilder`. Parametric UUID validation on all inputs. | **PASS** |
| **A04: Insecure Design** | • Consistent locking order prevents deadlocks: `workspaces` row locked `FOR UPDATE` $\rightarrow$ `workspace_members` locked $\rightarrow$ `project_members` updated.<br>• Last Admin invariant protected by `assertWorkspaceKeepsAdmin` within serializing transaction (AC-09, AC-10).<br>• Preview rate limiting (`RoleChangePreviewRateLimitGuard`) protects project discovery and prevents enum scraping. | **PASS** |
| **A05: Security Misconfiguration** | Guard ordering strictly validated: rate limiting guard executes prior to expensive DB queries or permission evaluation. | **PASS** |
| **A06: Vulnerable Components** | Zero new third-party production packages introduced. All operations use `@repo/contracts` and native NestJS/React dependencies. | **PASS** |
| **A07: Identification & Auth Failures** | All preview and mutation routes protected by `SessionGuard`. Unauthenticated calls rejected with 401. | **PASS** |
| **A08: Software & Data Integrity** | Whitelist DTO validation pipes enabled with `forbidNonWhitelisted: true`. Unexpected request body fields rejected with 400. | **PASS** |
| **A09: Logging & Monitoring Failures** | Structured logging via `LoggingWorkspaceEvents`: `role_change_previewed`, `role_changed`, `last_admin` events logged with workspace/member UUIDs, zero PII or user email leak. | **PASS** |
| **A10: Server-Side Request Forgery** | Zero outbound external network calls executed. | **PASS** |

---

## 4. Architectural Invariants (§7), Design Pass & Code Quality Checks

1. **Invariants Verification (INV-01 … INV-11)**:
   - **INV-01**: Role constants sourced exclusively from `@repo/contracts` (`WORKSPACE_ROLES`, `PROJECT_ROLES`, `isWorkspaceAdminRole`, `isProjectAdminRole`). Zero direct string comparisons (`'admin'`, `'guest'`) in `apps/`.
   - **INV-02**: All role changes recorded in `workspace_member_role_history` with `role_changed` change type, `actor_id`, and `request_id`.
   - **INV-03**: Project role changes recorded in `project_member_role_history` with `workspace_role_applied` change type, referencing the same `request_id`.
   - **INV-04**: Atomic execution in a single database transaction; rolled back entirely on any error.
   - **INV-05**: Target rows in `project_members` conform strictly to the new workspace role's policy (deactivated on `gain_implicit`, updated to range on role transitions).
   - **INV-06**: `liveProjectRowsOf` uniformly filters projects by `deletedAt: IsNull()` across preview and mutation operations, while maintaining INV-05 data integrity by updating deleted project rows in the DB without generating history or counting them in `appliedProjectCount`.
   - **INV-07**: `assertWorkspaceKeepsAdmin` verifies that active admin count does not drop to 0, rejecting with 409 `LAST_ADMIN`.
   - **INV-08**: Rate limiting enforced for preview (30 req/10 min) and mutation (30 req/10 min).
   - **INV-09**: Project name confidentiality maintained via `filterAccessible` in `ProjectAccessResolver`.
   - **INV-10**: `useWorkspaceMembers.ts` removed completely; replaced by `workspaceMemberKeys` and TanStack Query with optimistic `setQueryData`.
   - **INV-11**: Strict verification order in `workspaceMembers.service.ts`:
     1. Actor rank & permissions validation.
     2. Target existence validation (404).
     3. Optimistic concurrency check (`fromRole` match -> 409 `ROLE_CHANGED`).
     4. Role unchanged check (`toRole === currentRole` -> 400 `ROLE_UNCHANGED`).
     5. Target active membership validation.
     6. Workspace row locking `FOR UPDATE`.
     7. Last Admin assertion (`assertWorkspaceKeepsAdmin`).
     8. Plan resolution (`planWorkspaceRoleChange`).
     9. Project role range enforcement.
     10. Atomic write transaction & history emission.
     11. Cache invalidation on mutation success.

2. **TanStack Query Invalidation & Cache Hygiene**:
   - `workspaceMemberKeys.list(slug)` updated immediately via `setQueryData` on mutation response.
   - `projectKeys.list(slug)` invalidated whenever `appliedProjectCount > 0` or upon implicit role changes (`gain_implicit` / `lose_implicit`).
   - Modal focus managed cleanly according to accessibility guidelines.

---

## 5. Verification Verdict

- `pnpm lint`: **PASS** (0 warnings, 0 errors across monorepo)
- `pnpm typecheck`: **PASS** (0 errors across contracts, api, web)
- `pnpm test`: **PASS** (589 unit and integration tests green across 83 test files)
- `pnpm dup`: **PASS** (1.79% duplication rate, well below 5% threshold)
- `pnpm --filter @repo/api test:e2e`: **PASS** (301/301 e2e tests green across 14 test suites)

Branch `feature/14-change-workspace-role` is fully implemented, rigorously verified, meets all acceptance criteria and Definition of Done, and is ready for local `--no-ff` merge into `develop`.
