# Feature Review: 22 Priority Work Item

**Branch:** `feature/22-priority-work-item`  
**Target:** `develop`  
**Status:** Ready to merge  

---

## 1. Executive Summary & Spec Completeness

Feature 22 implements work item priority tracking across database, API, and web tiers using a three-level inheritance model with 5 canonical default priorities:
- **Three-Level Inheritance & Reference Architecture:** Work item priorities are structured across three hierarchical levels: 5 immutable system-wide `default` priorities (`urgent`, `high`, `medium`, `low`, `none`), `workspace`-level priorities, and `project`-level priorities. Projects inherit default and workspace priorities by reference without copying (BA ADR-01, RQ D6).
- **Mandatory Non-Null Priority & Real Default None:** Every work item has exactly one priority via a mandatory `work_items.priority_id` NOT NULL foreign key constraint (ON DELETE RESTRICT). `None` is an actual default row (`default_key = 'none'`), not a NULL sentinel, eliminating NULL-handling edge cases in ordering, filtering, and aggregation (RQ D1, D7, BA ADR-02).
- **5 Fixed Bands & Canonical Ordering:** 5 fixed priority bands (`urgent` > `high` > `medium` > `low` > `none`). Priority ordering is computed at a single authoritative seam in API (`PriorityCatalog`): band order -> level order (`default` < `workspace` < `project`) -> `created_at` -> `id` (RQ D2, INV-07). The web client never reorders received priorities.
- **Translated Default Names & Dynamic Custom Names:** Default priority names are localized in the web client based on `defaultKey` (`t('priority:defaults.<key>')`) to Vietnamese (Khẩn cấp, Cao, Trung bình, Thấp, Không), while custom workspace/project priorities preserve their stored English/user names (RQ D3, INV-09, BA ADR-04).
- **Scoped Read Permissions & Uncoupled Creation:** The effective project priorities list is read via `GET /api/workspaces/:slug/projects/:identifier/priorities`, gated by `project.workitems.view` (added to `ENFORCED_PROJECT_PERMISSIONS`). Specifying a `priorityId` during work item creation requires only `project.workitems.create` (RQ D4, D20, D29). Lacking view permission locks the picker chip to "None" with a tooltip and defaults creation to None.
- **Defensive Transaction Locking & Conflict Handling:** Work item creation resolves `priorityId` at Step 7b using `PriorityCatalog.lockEffectivePriority` with a single `pessimistic_read` lock (FOR SHARE) evaluated against the latest committed row version. Unavailable, deleted, or cross-tenant priorities return a uniform 409 `PRIORITY_UNAVAILABLE` without incrementing `last_work_item_number` (RQ D9, D10, INV-04, INV-05). State validation at Step 7a strictly precedes priority validation at Step 7b (RQ D11).
- **Shared Seams & Zero Duplication:** Prior to writing priority code, shared abstractions were extracted in Ticket 01: API three-level query helpers (`levels.ts`), e2e database fixture helpers (`insertWorkItem`, `undoUntil`), and a shared combobox component (`PropertyPicker.tsx`) utilized by both `StatePicker` and `PriorityPicker` (RQ D17, INV-12, BA ADR-07).
- **Full Keyboard & Screen Reader Accessibility:** Locked chips across both State and Priority use `aria-disabled="true"`, `tabIndex=0`, and focusable tooltips, resolving screen reader opacity while preventing dropdown activation (RQ D26, AC-24). The Priority chip announces `Priority: {name}` (vi: `Mức ưu tiên: {tên}`) as its accessible name (RQ D24, AC-20).

All 5 tickets (01–05) have been implemented and verified:
- **01 (Prefactor):** Shared `levels.ts` level filter/comparison seam, `createE2eApp.ts:reset()` multi-table default preservation loop, e2e helpers `insertWorkItem()` and `undoUntil()`, and accessible `PropertyPicker` component with `StatePicker` refactored onto it.
- **02 (Tracer Bullet):** DB migration `CreateWorkItemPriorities1791610700000`, `WorkItemPriority` entity, contracts (`PRIORITY_BANDS`, `DEFAULT_PRIORITY_KEYS`, `DEFAULT_PRIORITIES`, `PriorityResponse`), `PriorityCatalog` initial implementation, `GET .../priorities` route, Step 7b repository integration, and initial `PriorityPicker` in work item creation modal.
- **03 (API Hardening):** 3-level ordering e2e tests, adversarial input validation, concurrent work item creation vs priority soft-deletion tests (AC-18 Order 1 & Order 2), DB protection triggers and CHECK constraints tests, permission decoupling tests, and missing default initial priority handling (500 with logging).
- **04 (Web Polish & Roles):** Accessible locked chip focus/tooltip handling, search with NFC normalization, 320 px viewport responsiveness, full Vietnamese translations, discard confirmation on priority change, error handling on 409 `PRIORITY_UNAVAILABLE` and 400 validation error, and contracts-driven "enforced" permissions in Roles page matrix.
- **05 (Acceptance & Definition of Done):** AC-01…AC-29 verification matrix, dev PostgreSQL database metrics measurement (M-1…M-4), `CONTEXT.md` glossary additions, whole-branch code review, and this comprehensive review report.

---

## 2. Acceptance Criteria Traceability Matrix (AC-01 … AC-29)

| AC | Requirement Summary | Verification Test File & Case | Status |
| --- | --- | --- | --- |
| **AC-01** | Create work item modal: Priority chip shows "None" with none-band icon; clicking opens Search and 5 levels in canonical order: Urgent, High, Medium, Low, None; checkmark at None | `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "AC-01: renders 5 priority options in order, defaults to None, searches, and selects", `apps/web/src/features/workItems/create/WorkItemPropertyChips.test.tsx` > "renders State and Priority chips alongside coming-soon property chips in correct order (AC-01)", `packages/contracts/src/priority.test.ts` > "orders priority bands correctly" | PASS |
| **AC-02** | Search input: typing "HI" matches only High; typing "zz" displays "No priorities found"; Vietnamese UI typing "cao" matches only "Cao" | `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "AC-02: searching 'HI' in uppercase only matches High and filters out other priorities", `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "shows empty label when no priorities match search query", `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "translates priority names in Vietnamese and supports NFC search (RQ INV-09, AC-29)" | PASS |
| **AC-03** | Modal Save: saving without changing chip omits `priorityId`, work item receives `priorityId` of `none`; selecting High sends High `priorityId` | `apps/api/test/createWorkItemPriority.e2e-spec.ts` > "AC-03: creates work item with default None priority when omitted, or specified priority when provided", `apps/web/src/features/workItems/create/useCreateWorkItemForm.test.ts` > "omits priorityId when selecting None (AC-11, RQ D14, D30)" | PASS |
| **AC-04** | Project members across roles (Admin, Contributor, Commenter, Viewer) and Workspace Admin GET priorities: 200 OK, 5 canonical defaults in order, `initialPriorityId` = id of none, exact response fields (`id, name, band, color, level, defaultKey`) | `apps/api/test/projectPriorities.e2e-spec.ts` > "AC-04, INV-08: GET priorities returns 5 default priorities in order, initialPriorityId, exact response fields for project roles and workspace admin", `apps/api/src/priorities/priorities.service.spec.ts` > "lists priorities for project and identifies initialPriorityId (AC-04)" | PASS |
| **AC-05** | Actor lacking `project.workitems.view`: GET priorities returns 403 FORBIDDEN; modal Priority chip locked displaying "None" + tooltip `priority:picker.noPermission`, skips GET call; Save creates work item at None | `apps/api/test/projectPriorities.e2e-spec.ts` > "AC-05: returns 403 FORBIDDEN when user lacks project.workitems.view permission", `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "renders locked chip with noPermission tooltip and skips fetch when lacking permission", `apps/web/src/features/priorities/Priorities.vi.test.tsx` > "renders locked chip with Vietnamese tooltip when lacking permission (AC-05, AC-17)" | PASS |
| **AC-06** | GET priorities network / 500 failure: Priority chip locked with tooltip `priority:picker.loadFailed`; Save still succeeds creating at None | `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "renders locked chip with loadFailed tooltip when query fails", `apps/web/src/features/priorities/Priorities.vi.test.tsx` > "renders locked chip with Vietnamese tooltip when query fails (AC-06, AC-17)" | PASS |
| **AC-07** | POST work item with unavailable `priorityId` (other project, other workspace, soft-deleted, random UUID): returns 409 `PRIORITY_UNAVAILABLE`, uniform body, no work item created, `last_work_item_number` unchanged | `apps/api/test/createWorkItemPriority.e2e-spec.ts` > "AC-07: returns 409 PRIORITY_UNAVAILABLE for unavailable priorities (other project, other workspace, soft-deleted, random UUID, state id) without modifying counter", `apps/api/src/workItems/workItems.service.spec.ts` > "maps priority_unavailable to 409 PRIORITY_UNAVAILABLE (AC-07)" | PASS |
| **AC-08** | Custom workspace-level priority and project-level priority created directly in DB: creating work item with either ID returns 201 with correct `priorityId` | `apps/api/test/createWorkItemPriority.e2e-spec.ts` > "AC-08, API B4: creates work item with valid workspace priority, project priority, or mixed-case UUID" | PASS |
| **AC-09** | Malformed `priorityId` (`"abc"`, `null`, `1`, `[]`, `{}`): returns 400 `VALIDATION_ERROR`, `fields.priorityId` = `workItem:errors.priorityInvalid` | `apps/api/test/adversarialInputs.e2e-spec.ts` > "rejects invalid priorityId payloads on POST work-items with 400 VALIDATION_ERROR (AC-09)" | PASS |
| **AC-10** | Replay work item creation key K with High: resending K with same Title/Description and Low returns 200 with original item (High); resending K with different Title returns 409 `WORK_ITEM_REQUEST_REUSED` | `apps/api/test/createWorkItemPriority.e2e-spec.ts` > "AC-10, INV-06: replaying with same key and same title/desc returns 200 with original priority; different title returns 409 WORK_ITEM_REQUEST_REUSED" | PASS |
| **AC-11** | Changing priority updates content fingerprint and generates fresh idempotency key; selecting None omits `priorityId`. 409 `PRIORITY_UNAVAILABLE` resets chip to None, refetches list, shows alert, retains Title/Description | `apps/web/src/features/workItems/create/useCreateWorkItemForm.test.ts` > "generates a new idempotency key when changing priority and omits priorityId when selecting None (AC-11, RQ D14, D30)", `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-11: handles 409 PRIORITY_UNAVAILABLE by showing alert, resetting chip to None, retaining title/desc, and generating new key on next save" | PASS |
| **AC-12** | Database with work items from feature 20/21: migration backfills `priority_id` to `none`; `down()` cleanly restores previous schema with real 3-level data | `apps/api/test/migrations.e2e-spec.ts` > "CreateWorkItemPriorities: backfills priority_id to none, protects defaults with trigger, and reverts cleanly on down (AC-12, DB B2)" | PASS |
| **AC-13** | Direct SQL commands: `UPDATE`/`DELETE` on default rows, `INSERT` with `level = 'default'`, or altering `level`/`workspace_id`/`project_id` are rejected by trigger | `apps/api/test/workItemPrioritiesDb.e2e-spec.ts` > "AC-13: trigger rejects modifying, deleting, or inserting default level rows", "AC-13: trigger rejects changing level, workspace_id, or project_id of existing rows" | PASS |
| **AC-14** | Direct SQL: project row missing `workspace_id`, workspace row with `project_id`, invalid band `'critical'`, invalid color `'red'`, or duplicate live name in same level rejected by DB; soft-deleted row allows reuse | `apps/api/test/workItemPrioritiesDb.e2e-spec.ts` > "AC-14: rejects rows violating work_item_priorities_level_shape_check constraint", "AC-14: rejects rows violating band and color CHECK constraints", "AC-14: rejects duplicate live name within same workspace level, but permits when first row is soft-deleted", "AC-14: rejects duplicate live name within same project level, but permits when first row is soft-deleted" | PASS |
| **AC-15** | E2E `reset()` retains exactly 5 default priority rows and 5 default state rows with identical IDs | `apps/api/test/workItemPrioritiesDb.e2e-spec.ts` > "preserves exactly 5 default rows with identical IDs across e2e.reset() (AC-15, RQ D19, DB B5)" | PASS |
| **AC-16** | Workspace W has "Blocker" (urgent) & deleted priority; Project P has "Nice to have" (low); Project Q has "Q-only"; other workspace has "Other": GET priorities of P returns: Urgent, Blocker, High, Medium, Low, Nice to have, None; excludes deleted, Q-only, Other | `apps/api/test/projectPriorities.e2e-spec.ts` > "AC-16, INV-07: GET priorities returns 3-level priorities in correct order (band -> level -> createdAt -> id), excluding deleted, other project, other workspace; ignores query string", `apps/api/src/priorities/priorityCatalog.spec.ts` > "orders priorities strictly by band, level, createdAt, and id (AC-16, INV-07)" | PASS |
| **AC-17** | Vietnamese UI: 5 levels display Khẩn cấp, Cao, Trung bình, Thấp, Không; tooltips, search placeholder, empty label, and error alerts localized in Vietnamese; custom priorities display stored name | `apps/web/src/features/priorities/Priorities.vi.test.tsx` > "translates 5 default priorities into Vietnamese while custom priority preserves its stored name (AC-17, RQ INV-09)", "updates accessible chip label in Vietnamese when a priority is selected (AC-17, AC-20)", "renders locked chip with Vietnamese tooltip when lacking permission (AC-05, AC-17)", "renders locked chip with Vietnamese tooltip when query fails (AC-06, AC-17)" | PASS |
| **AC-18** | Concurrency: Work item creation selecting priority X waits on test transaction holding `FOR UPDATE` on X; delete commits -> creation gets 409 `PRIORITY_UNAVAILABLE`. Reverse order: creation holds SHARE first -> delete waits until creation commits | `apps/api/test/createWorkItemPriority.e2e-spec.ts` > "AC-18, INV-05: Work Item Creation vs Priority Soft-Deletion Concurrency: Order 1: Create work item locks priority SHARE before delete -> delete waits, then succeeds after create commits", "Order 2: Delete transaction locks priority FOR UPDATE before create -> create waits, then sees priority deleted -> 409 PRIORITY_UNAVAILABLE" | PASS |
| **AC-19** | Both `stateId` and `priorityId` unavailable: returns 409 `STATE_UNAVAILABLE` (Step 7a runs first); only `priorityId` unavailable: returns 409 `PRIORITY_UNAVAILABLE` | `apps/api/test/createWorkItemPriority.e2e-spec.ts` > "AC-19, RQ D11: returns 409 STATE_UNAVAILABLE when both stateId and priorityId are unavailable; PRIORITY_UNAVAILABLE when only priorityId is unavailable" | PASS |
| **AC-20** | Keyboard navigation: Up/Down navigates, Enter selects; Escape closes dropdown without closing modal. Chip accessible name updates to "Priority: {level}" (vi: "Mức ưu tiên: {mức}") | `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "AC-20: closes dropdown on Escape without propagating to parent modal", "AC-20: updates accessible name 'Priority: {mức}' according to the selected priority", `apps/web/src/features/priorities/Priorities.vi.test.tsx` > "updates accessible chip label in Vietnamese when a priority is selected (AC-17, AC-20)" | PASS |
| **AC-21** | Changing only Priority chip prompts discard confirmation on modal close; saving with "Create more" enabled resets chip to None for next work item | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-21: prompts discard confirmation when only Priority chip is changed (Title and description empty)", "AC-21: with Create more enabled, saving work item with chosen priority resets chip to None for next item" | PASS |
| **AC-22** | Pre-existing state tests (API unit, e2e, web) pass without changing existing assertions; `StatePicker` preserves all behaviors | `apps/web/src/features/states/StatePicker.test.tsx`, `apps/api/test/projectStates.e2e-spec.ts`, `apps/api/test/workspaceStates.e2e-spec.ts`, `apps/api/test/stateConcurrency.e2e-spec.ts` | PASS |
| **AC-23** | `architecture.spec.ts`: GET priorities endpoint strictly adheres to route patterns under `/projects/:identifier` with `ProjectRead('project.workitems.view')` and session guard | `apps/api/src/architecture.spec.ts` > "ProjectRead routes under /projects/:identifier have ProjectAccessGuard and session guard" | PASS |
| **AC-24** | Locked Priority or State chip (no permission / load error): Tab focuses chip, renders `aria-disabled="true"`, tooltip visible on focus; Enter/Space/click does not open dropdown | `apps/web/src/features/workItems/properties/PropertyPicker.test.tsx` > "supports keyboard focus with aria-disabled='true' when status is locked, displaying tooltip on focus and ignoring click/keyboard open", `apps/web/src/features/states/StatePicker.test.tsx` > "renders aria-disabled='true' and tabIndex=0 when locked (AC-24)" | PASS |
| **AC-25** | Roles page permissions matrix: "View work items", "View states", "Manage states", and "Manage workspace states" no longer display "Not enforced yet" badge; unenforced permissions still show badge | `apps/web/src/features/admin/roles/permissionMatrix.test.ts` > "AC-25: marks permissions as enforced if and only if they exist in ENFORCED_WORKSPACE_PERMISSIONS or ENFORCED_PROJECT_PERMISSIONS" | PASS |
| **AC-26** | E2E suites (`projectStates`, `workspaceStates`, `stateConcurrency`, `migrations`, `createWorkItem`) use `insertWorkItem()` helper; position-based `undoLastMigration` chains replaced with `undoUntil()` | `apps/api/test/projectStates.e2e-spec.ts`, `apps/api/test/workspaceStates.e2e-spec.ts`, `apps/api/test/stateConcurrency.e2e-spec.ts`, `apps/api/test/migrations.e2e-spec.ts`, `apps/api/test/createWorkItem.e2e-spec.ts` | PASS |
| **AC-27** | Missing default initial priority 'none' in corrupt database: work item creation fails with 500 internal server error and logs error with `projectId`; never returns `PRIORITY_UNAVAILABLE` | `apps/api/src/workItems/workItems.service.spec.ts` > "logs error with projectId and throws 500 when default initial priority 'none' is missing (AC-27, RQ D28)" | PASS |
| **AC-28** | User without `project.workitems.view` permission can still POST work item with specified valid `priorityId` (needs only `project.workitems.create`) | `apps/api/test/createWorkItemPriority.e2e-spec.ts` > "AC-28, RQ D29: user lacking project.workitems.view can still create work item with specified priorityId (needs only project.workitems.create)" | PASS |
| **AC-29** | 320 px screen width: dropdown stays within viewport without horizontal overflow; search input with Vietnamese NFD forms matches NFC options | `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "AC-29: keeps dropdown width bounded and within viewport on 320 px screen", `apps/web/src/features/priorities/PriorityPicker.test.tsx` > "translates priority names in Vietnamese and supports NFC search (RQ INV-09, AC-29)", `apps/web/src/features/workItems/properties/PropertyPicker.test.tsx` > "filters options case-insensitively with NFC normalization (AC-29)" | PASS |

---

## 3. SOLID & Architecture Principles

1. **Single Responsibility Principle (SRP):**
   - `PriorityCatalog`: Pure query resolution seam for effective project priorities, initial priority discovery, and transactional pessimistic locking. Contains zero HTTP handling, mutation logic, or rate limiting.
   - `PrioritiesService`: Handles read operations for project priority lists, mapping entities to API contracts DTOs (`toPriorityResponse`).
   - `TypeOrmWorkItemsRepository`: Orchestrates the 10-step atomic work item creation transaction, verifying permissions, idempotency, state resolution, priority resolution, sequence numbering, and persistence.
   - `PropertyPicker`: Reusable Mantine Combobox wrapper managing search filtering, NFC normalization, dropdown position, keyboard navigation, and accessible locked states for both State and Priority.
   - `PriorityBandIcon`: Geometric vector representation of the 5 priority bands, decoupled from domain models.
2. **Open/Closed Principle (OCP):**
   - Extension to workspace and project custom priorities (BA FC-2) requires zero schema modifications or migrations to the `work_items` table: the three-level entity shape and `PriorityCatalog` queries already support workspace and project levels.
   - Adding project-level default priorities (BA FC-3) or band-grouped views (BA FC-2) builds directly on top of `PriorityCatalog` and `PropertyPicker` group rendering without altering existing consumer contracts.
3. **Liskov Substitution Principle (LSP):**
   - `WorkItemsService` interacts with `WorkItemsRepository` and `PriorityCatalog` via abstract interfaces. Unit tests in `workItems.service.spec.ts` mock `PriorityCatalog` seamlessly to verify initial priority missing (500) and priority unavailable (409) branches.
   - `PropertyPicker` provides an identical contract (`PropertyOption[]`, `triggerLabel`, `triggerIcon`, `status`, `onChange`) fulfilled cleanly by both `StatePicker` and `PriorityPicker`.
4. **Interface Segregation Principle (ISP):**
   - `PriorityResponse` exposes only non-sensitive consumer fields (`id`, `name`, `band`, `color`, `level`, `defaultKey`), strictly segregating internal audit and tenancy columns (`workspaceId`, `projectId`, `createdById`, timestamps) away from public read contracts.
5. **Dependency Inversion Principle (DIP):**
   - Controllers and services inject repository tokens and catalog providers (`PriorityCatalog`, `StateCatalog`), decoupling HTTP transport and presentation layers from TypeORM persistence details.

---

## 4. OWASP Top 10 Security Audit

- **A01: Broken Access Control:**
   - `GET /api/workspaces/:slug/projects/:identifier/priorities` is protected by `ProjectRead('project.workitems.view')` and `ProjectAccessGuard`, preventing cross-workspace or cross-project data leakage (AC-05, AC-23).
   - In accordance with principle of least privilege, work item creation with a chosen `priorityId` requires only `project.workitems.create`, deliberately decoupled from `project.workitems.view` (AC-28, RQ D29).
   - `lockEffectivePriority` enforces tenant boundaries at database query time via `visibleLevelRows(projectScope)`: referencing a priority belonging to another workspace or another project returns a uniform 409 `PRIORITY_UNAVAILABLE` without disclosing resource existence (AC-07, AC-08).
- **A02: Cryptographic Failures & Sensitive Data Exposure:**
   - `PriorityResponse` DTO strictly scrubs database keys: `workspace_id`, `project_id`, `created_by_id`, `updated_by_id`, and `deleted_by_id` are never returned to clients (RQ INV-08, AC-04).
   - No sensitive authentication tokens or user credentials are involved or exposed in priority operations.
- **A03: Injection:**
   - All queries use TypeORM parameterized criteria and TypeORM QueryBuilder mechanisms. No raw dynamic SQL concatenation exists in application code (CLAUDE.md §5).
   - Input `priorityId` is sanitized and validated via NestJS `ValidationPipe` with `@IsUUID('all')` and `@IsString()`, rejecting SQL injection vectors and non-UUID payloads with 400 `VALIDATION_ERROR` (AC-09).
   - PostgreSQL trigger `work_item_priorities_protect_defaults` strictly forbids inserting, updating, or deleting default rows even against direct SQL queries (AC-13).
- **A04: Insecure Design & Anti-automation:**
   - Strict lock ordering in DB B3.3 and DB B3.4 (`projects` NO KEY UPDATE -> `work_item_states` SHARE -> `work_item_priorities` SHARE -> insert `work_items`) ensures mathematical freedom from deadlocks (AC-18, DB B3.4).
   - Idempotency replay checking occurs at Step 6 before acquiring state or priority locks. Replayed requests with identical content return 200 without modifying sequence counters or triggering lock wait overhead (AC-10, RQ INV-06).
- **A05: Security Misconfiguration:**
   - Global `ApiExceptionFilter` sanitizes all application exceptions into structured payloads with correlation request IDs.
   - Missing initial priority (corrupt database) emits an internal server error (500) with diagnostic logging while shielding internal stack traces from clients (AC-27).
- **A06: Vulnerable and Outdated Components:**
   - Implemented using audited workspace dependencies without introducing new third-party packages.
- **A07: Identification and Authentication Failures:**
   - Active user session and valid workspace/project membership are verified before evaluating business operations. Deactivated or removed users fail immediately at transaction Step 1–5.
- **A08: Software and Data Integrity Failures:**
   - Clean, reversible migration `1791610700000-CreateWorkItemPriorities` with schema assertions and bidirectional `up`/`down` verification with live 3-level data (AC-12).
   - Foreign key constraint `work_items_priority_id_fkey` with `ON DELETE RESTRICT` guarantees referential integrity and prevents orphaned work items.
- **A09: Security Logging and Monitoring Failures:**
   - Corrupt state logging (`initial_priority_missing`) emits structured error logs containing `projectId` and `requestId` (RQ D28, AC-27).
   - Expected 409 rejections (`PRIORITY_UNAVAILABLE`) do not write to server logs, preventing denial-of-service log saturation attacks (API B6).
- **A10: Server-Side Request Forgery (SSRF):**
   - Zero external outbound HTTP network requests initiated by priority services or controllers.

---

## 5. Metrics Verification (BA B4: M-1 … M-4)

Metrics were executed directly against PostgreSQL database `openplany` using the MCP tool `open-plany-postgres`:

- **M-1 (Distribution of live work items created in last 28 days by priority band):**
  - Query:
    ```sql
    SELECT pr.priority_band, count(*)
    FROM work_items w
    JOIN projects p ON p.id = w.project_id AND p.deleted_at IS NULL
    JOIN work_item_priorities pr ON pr.id = w.priority_id
    WHERE w.deleted_at IS NULL AND w.created_at > now() - interval '28 days'
    GROUP BY pr.priority_band;
    ```
  - Result: `[]` (0 rows, baseline verified on empty dev database). Threshold will be monitored after 4 weeks (BA BR1).
- **M-2 (Live work items referencing an invalid/out-of-scope priority):**
  - Query:
    ```sql
    SELECT count(*)
    FROM work_items w
    JOIN projects p ON p.id = w.project_id AND p.deleted_at IS NULL
    JOIN work_item_priorities pr ON pr.id = w.priority_id
    WHERE w.deleted_at IS NULL
      AND NOT (pr.level = 'default'
               OR (pr.level = 'workspace' AND pr.workspace_id = w.workspace_id)
               OR (pr.level = 'project' AND pr.project_id = w.project_id));
    ```
  - Result: `count = 0` (Target: = 0). PASS.
- **M-3 (Live work items referencing a soft-deleted priority):**
  - Query:
    ```sql
    SELECT count(*)
    FROM work_items w
    JOIN projects p ON p.id = w.project_id AND p.deleted_at IS NULL
    JOIN work_item_priorities pr ON pr.id = w.priority_id
    WHERE w.deleted_at IS NULL AND pr.deleted_at IS NOT NULL;
    ```
  - Result: `count = 0` (Target: = 0). PASS.
- **M-4 (Canonical default priority count and consistency):**
  - Query:
    ```sql
    SELECT count(*)
    FROM work_item_priorities
    WHERE level = 'default'
      AND deleted_at IS NULL
      AND default_key = priority_band
      AND default_key IN ('urgent', 'high', 'medium', 'low', 'none');
    ```
  - Result: `count = 5` (Target: = 5). PASS.

---

## 6. Design Pass & Cross-Ticket Consistency Review

- **Ticket Design Notes:** All 5 tickets possess verified `.design.md` files:
  - `01-prefactor.design.md`: 7 rows, all Verdict = `follow`.
  - `02-work-item-priority-tracer.design.md`: 8 rows, all Verdict = `follow`.
  - `03-api-hardening.design.md`: 10 rows, all Verdict = `follow`.
  - `04-web-polish-and-roles.design.md`: 9 rows, all Verdict = `follow`.
  - `05-acceptance-and-definition-of-done.design.md`: 7 rows, all Verdict = `follow`.
  - Total `deviate` rows across feature: 0. Every implemented mechanism adheres strictly to spec.
- **Single Shared Seams:**
  - Shared query seam `apps/api/src/database/levels.ts` standardizes `visibleLevelRows`, `ownLevelRows`, `withEach`, and `compareLevelThenAge` across both State and Priority without code bifurcation.
  - Shared UI seam `PropertyPicker.tsx` unifies combobox rendering, search filtering, NFC normalization, and accessible locked states for both `StatePicker` and `PriorityPicker`.
  - Shared e2e helpers `insertWorkItem()` and `undoUntil()` eliminate repeated fragile SQL insertions and index-based rollback dependencies across all migration tests.
- **Lock Graph Specification:**
  - `docs/features/22-priority-work-item/3-database-spec.md` DB B3.3 and DB B3.4 now serve as the single, authoritative lock graph specification for the entire application, accurately incorporating Step 7b priority locking without deadlocks.

---

## 7. Extensibility for Future Capabilities (BA FC-1 … FC-10)

| FC | Capability | Seam Established in Feature 22 |
| --- | --- | --- |
| **FC-1** | Change priority of existing work item | `PriorityCatalog.lockEffectivePriority` provides the exact locking mechanism needed; lock order `priority` (SHARE) -> `work_items` (UPDATE) conforms to lock graph DB B3.4. |
| **FC-2** | Custom workspace & project priorities + Settings UI | 3-level schema (`work_item_priorities`), level shape checks, partial unique indexes, and `PriorityCatalog` queries already fully support workspace and project levels. |
| **FC-3** | Project-level default priority override | `INITIAL_PRIORITY_KEY` is isolated in contracts; API returns `initialPriorityId` dynamically in `PriorityListResponse`, ready for `projects.default_priority_id`. |
| **FC-4** | Bi-lingual support for future priority additions | Translation namespace `priority` configured with complete English and Vietnamese catalogs. |
| **FC-5** | Sorting, filtering, and reporting by priority | `work_items_priority_id_idx` index created in migration; `PRIORITY_BANDS` contracts constant defines stable cross-project ordering. |
| **FC-6** | Cross-project work item migration | `band` and `level` returned in `PriorityResponse` allow mapping project-level priorities to equivalent bands. |
| **FC-7** | Restore soft-deleted project/work item | Soft-delete semantics preserve original references with M-2/M-3 validation. |
| **FC-8** | Lock graph updates for new write flows | DB B3.4 established as the single repository of transaction lock ordering. |
| **FC-9** | Color palette for custom priorities | Default priority colors in `DEFAULT_PRIORITIES` sourced directly from shared `STATE_COLORS`. |
| **FC-10** | Hiding default priorities per project | `PriorityCatalog` centralizes all effective priority calculations in one place. |

---

## 8. Git Diff & Quality Gate Summary

All automated checks are green:
- `pnpm lint`: Pass (clean, no warnings or errors).
- `pnpm typecheck`: Pass (all workspaces typecheck cleanly).
- `pnpm test`: Pass (all unit and component test suites passing).
- `pnpm --filter @repo/api test:e2e`: Pass (all 22 e2e test suites passing).
- `pnpm dup`: Pass (no duplicate blocks exceeding thresholds).
- Prettier: Verified on all touched files.

Feature 22 is complete, verified, and ready for merge into `develop`.
