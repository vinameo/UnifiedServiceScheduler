# Feature Review: 20 New Work Item

**Branch:** `feature/20-new-work-item`  
**Target:** `develop`  
**Status:** Ready to merge  

---

## 1. Executive Summary & Spec Completeness

Feature 20 implements the complete end-to-end flow for creating work items within an OpenPlany project:
- **Project Sidebar & Entry Point:** "New work item" button in project sidebar, enabled only for actors with `project.workitems.create`, with tooltips and disabled states for unpermitted members.
- **Transactional Counter & Numbering:** Sequential, contiguous work item numbers (`OPS-1`, `OPS-2`) managed via pessimistic row lock on `projects` (`last_work_item_number`) with automatic retry on deadlocks.
- **Idempotency & Replay:** Client-side content fingerprinting in `Map<fingerprint, key>` reuses the UUID on network errors, 5xx, or identical content, and purges keys on 409 `WORK_ITEM_REQUEST_REUSED`.
- **Markdown Editor & Secure Preview:** Dedicated `MarkdownEditor` with Write/Preview tabs, autosize textarea, GFM support, plain-text rendering for raw HTML (XSS prevention), image alt-text suppression (IP leakage prevention), and `safeUrlTransform` allowing only `http:`, `https:`, and `mailto:` schemes.
- **Modal Polish & Resilience:** 7 locked property chips with neutral labels and `ComingSoonHint`, "Create more" switch with auto-focus and field reset, `useDiscardGuard` with "Discard unsaved changes?" prompt, and layout resilience keeping the modal mounted and inputs intact when project access is lost (403/404) during reload.
- **Rate Limiting & Body Parser:** Dedicated 30 creations/minute rate limit (`bucket=work_item_create`) with 512 KB request limit and global mapping of body-parser errors (413, 415, 400) to standard localized 4xx API errors.

All 7 tickets (01–07) have been implemented and verified:
- **01 (Prefactor):** Rate limit bucket seam via `@RateLimitBucket()`, `lockProjectForActor` database helper, `projectApiPath()` URL helper, and shared multi-line text constants.
- **02 (Body Parser Errors):** Mapped body-parser 4xx errors (413, 415, 400) globally in `ApiExceptionFilter`, raised JSON body limit to 512 KB, and verified with e2e tests across existing and new routes.
- **03 (Tracer Bullet):** DB migration `CreateWorkItems1791610500000`, TypeORM entity `WorkItem`, `POST /workspaces/:slug/projects/:identifier/work-items` with pessimistic lock and retry, project sidebar integration, and minimal modal.
- **04 (Idempotency Web):** Web content fingerprinting `createContentFingerprint(title, description)`, `idempotencyMapRef`, double-click protection, read-only inputs during submission, and 409 handling.
- **05 (Markdown Editor):** `react-markdown` + `remark-gfm` in `MarkdownPreview`, custom `safeUrlTransform`, `MarkdownEditor` with Write/Preview tabs, and R-06 performance benchmark (< 500ms for 50,000 characters).
- **06 (Modal Polish):** 7 property chips in RQ D2 order with `ComingSoonHint` and `aria-disabled`, "Create more" switch, `useDiscardGuard`, error table mapped by `code` without reading `err.message`, modal survival on 403/404 project reload (AC-35), and mobile fullscreen responsiveness (< 768px).
- **07 (Acceptance & Definition of Done):** Mapped AC-01…AC-37 to tests, verified BA metrics M-1…M-6 on Postgres, updated domain glossary in `CONTEXT.md`, and completed feature review.

---

## 2. Acceptance Criteria Traceability Matrix (AC-01 … AC-37)

| AC | Requirement Summary | Verification Test File & Case | Status |
| --- | --- | --- | --- |
| **AC-01** | Contributor opens modal: project chip, autofocus Title, Description tabs + note, 7 locked chips, Create more off, Discard, Save | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-01: renders modal with project chip, title autofocus, description tabs and note, 7 property chips, create more switch off, discard and save buttons" | PASS |
| **AC-02** | Commenter/Viewer has disabled New work item button with tooltip; Workspace Admin has enabled button | `apps/web/src/features/projects/detail/ProjectLayout.test.tsx` > "sidebar item order, icons, and that only Home is an anchor link" & `ProjectNewWorkItemButton.tsx` | PASS |
| **AC-03** | Workspace sidebar New work item button remains locked with "Coming soon" | `apps/web/src/features/projects/detail/ProjectLayout.test.tsx` > "sidebar item order, icons, and that only Home is an anchor link" | PASS |
| **AC-04** | An creates first work item: number 1, key OPS-1, normalized description, createdById = An, toast, modal closes, DB row created | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-04: creates work item with number 1, key OPS-1, normalized description" | PASS |
| **AC-05** | Sequential numbering per project: OPS-2 for second work item; MKT-1 for first work item in project MKT | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-05: increments number sequentially per project (OPS-2, MKT-1)" | PASS |
| **AC-06** | 10 concurrent valid creation requests in project OPS yield 1..10 sequential numbers without collisions, gaps, or 500 errors | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-06: handles 10 concurrent valid creation requests without collisions or gaps" | PASS |
| **AC-07** | Rejected requests (400, 403, 409) do not increment counter or leave gaps in sequence | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-07: failed requests do not increment counter or leave gaps" | PASS |
| **AC-08** | Create more enabled: toast shown, modal stays open, fields emptied, title focused, switch remains on, next save uses new key | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-08: with Create more enabled, saving shows toast, keeps modal open, empties fields, focuses title, keeps switch enabled, and next save uses fresh key" | PASS |
| **AC-09** | Replaying identical body and key returns 200 with same id/number; one DB row; logs `work_item.create.replayed` | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-09: replays 200 with identical body for sequential and concurrent requests" | PASS |
| **AC-10** | Reused key with different payload, different project, or after soft delete returns 409 WORK_ITEM_REQUEST_REUSED; different actors independent | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-10: returns 409 WORK_ITEM_REQUEST_REUSED when key reused with different payload, in different project, or after soft delete" | PASS |
| **AC-11** | Web: double-click prevented; network failure reuses key; editing content changes key; editing away and back reuses key; inputs read-only while saving | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "preserves idempotency key on network failure / 5xx error and reuses it on retry", "reuses idempotency key when editing content away and reverting back", "makes inputs read-only while saving and prevents double submission" | PASS |
| **AC-12** | Validation: rejects empty title, > 255 chars, newlines, hidden control chars, description > 50,000 chars, hidden control chars with 400 | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-12: rejects invalid title or description with 400 VALIDATION_ERROR" & `packages/contracts/src/workItem.test.ts` | PASS |
| **AC-13** | Normalization: trims title, normalizes CRLF/CR to LF in description, trims empty whitespace description to empty string | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-13: normalizes whitespace and CRLF" & `packages/contracts/src/workItem.test.ts` | PASS |
| **AC-14** | API access: Commenter, Viewer, Guest, or non-member calling API directly receives 403 or 404 without database mutations | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-14: rejects commenter, viewer, and non-project members with 403 or 404" | PASS |
| **AC-15** | Removing `project.workitems.create` from role rejects API requests with 403 and disables web button | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-15: rejects request when permission removed from role" | PASS |
| **AC-16** | Concurrent deactivation, demotion to viewer, project soft-deletion, or permission revocation before transaction lock yields 403/404 | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-16: rejects with 403/404 when membership, project, or permission changed concurrently before transaction locks" | PASS |
| **AC-17** | Workspace Admin not belonging to project member list creates work item successfully (201) | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-17: allows Workspace Admin who is not a project member to create work item" | PASS |
| **AC-18** | Discard guard: dirty form prompts "Discard unsaved changes?"; Keep editing retains content; Discard closes; empty form closes immediately | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-18: asks before discarding changes; Keep editing retains content; Discard closes immediately when empty", "AC-18: prompts confirmation when dirty; Keep editing keeps content; Discard closes" | PASS |
| **AC-19** | Adversarial inputs (null, array, non-UUID keys, extra properties) return 4xx without 500 | `apps/api/test/adversarialInputs.e2e-spec.ts` > "POST /api/workspaces/:slug/projects/:identifier/work-items rejects adversarial inputs with 4xx without 500" | PASS |
| **AC-20** | Full Vietnamese localization for modal copy, chips, switches, buttons, and API errors | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-20: renders Vietnamese copy when i18n language is vi" & `apps/api/test/createWorkItem.e2e-spec.ts` | PASS |
| **AC-21** | Markdown preview: GFM headings, tables, task lists, code blocks; raw HTML as plain text; javascript: links inert; images as alt text; safe links | `apps/web/src/features/markdown/MarkdownPreview.test.tsx` (10 tests) | PASS |
| **AC-22** | Narrow screen (< 768px): modal switches to fullscreen, property chips wrap without horizontal overflow | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-22: sets fullScreen on narrow screens (< 768px)" | PASS |
| **AC-23** | Accessibility: aria-disabled chips focusable for tooltip reading; Write/Preview tabs arrow-key navigable; Create more switch labeled | `apps/web/src/features/workItems/create/WorkItemPropertyChips.test.tsx` & `apps/web/src/features/markdown/MarkdownEditor.test.tsx` | PASS |
| **AC-24** | Dedicated rate limit: 30 creations/minute returns 429 TOO_MANY_ATTEMPTS with Retry-After; modal shows localized minutes alert without blocking other workspace writes | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-24: enforces dedicated rate limit (30/min) returning 429 TOO_MANY_ATTEMPTS with Retry-After" & `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "AC-24: formats 429 TOO_MANY_ATTEMPTS with retryAfterMinutes in alert and preserves content" | PASS |
| **AC-25** | 403 on save shows alert, locks save, calls `reloadProject()`, and preserves input content | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "handles 403 error by showing alert, locking save, and triggering reloadProject" | PASS |
| **AC-26** | 404 on save shows alert, locks save, and preserves input content | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "handles 404 error by showing alert and locking save" | PASS |
| **AC-27** | Audit log `work_item.created` records workItemId, projectId, workspaceId, actorId, number, requestId; omits title, description, email | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-27: audit logs work_item.created without title, description, or email" | PASS |
| **AC-28** | 409 WORK_ITEM_REQUEST_REUSED clears key from map, alerts user, preserves content, and generates fresh key on next save | `apps/web/src/features/workItems/create/CreateWorkItemModal.test.tsx` > "handles 409 WORK_ITEM_REQUEST_REUSED by clearing key from map and generating new key on next save (AC-28)" | PASS |
| **AC-29** | TypeORM migration: initializes `last_work_item_number = 0` on existing projects; down() reverses cleanly; passes migration replay test | `apps/api/test/migrations.e2e-spec.ts` > "reverts and re-applies all migrations including CreateWorkItems" | PASS |
| **AC-30** | Route metadata verified: ProjectWrite guard chain with `project.workitems.create` and dedicated rate limit bucket | `apps/api/src/architecture.spec.ts` > "work items endpoint uses ProjectWrite guard and dedicated rate limit" | PASS |
| **AC-31** | API response strictly conforms to WorkItemResponse DTO, omitting internal columns (idempotencyKey, deletedAt) | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-31: response shape strictly matches WorkItemResponse" | PASS |
| **AC-32** | Soft-deleted work items do not cause number reuse; unique constraint on `(project_id, number)` enforced | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-32: soft-deleted work items do not cause number reuse; unique constraint enforced" | PASS |
| **AC-33** | Accepts 50,000 char unicode description; rejects payloads exceeding 512 KB with 413 PAYLOAD_TOO_LARGE | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-33: handles 50,000 char unicode description and returns 413 for oversized body" | PASS |
| **AC-34** | Concurrent requests with identical idempotency key across different projects return 201 and 409 | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-34: concurrent requests with identical key in different projects return 201 and 409" | PASS |
| **AC-35** | Modal survives project reload to 403 without unmounting or losing typed text; closing reveals 403 Forbidden page | `apps/web/src/features/projects/detail/ProjectLayout.test.tsx` > "AC-35: modal survives project reload to 403 without unmounting or losing typed text; closing reveals 403 page" | PASS |
| **AC-36** | Body parser 413, 415, 400 errors across all routes map to standard 4xx JSON responses with requestId and localized message | `apps/api/test/bodyParserErrors.e2e-spec.ts` (covers all routes) | PASS |
| **AC-37** | Account deactivated between guard and transaction returns 401 UNAUTHENTICATED without incrementing sequence | `apps/api/test/createWorkItem.e2e-spec.ts` > "AC-37: returns 401 UNAUTHENTICATED when actor account deactivated between guard and transaction" | PASS |

---

## 3. SOLID & Architecture Principles

1. **Single Responsibility Principle (SRP):**
   - `WorkItemsService`: Coordinates the creation sequence: transaction management, pessimistic project locking, idempotency check, number generation, entity persistence, and structured audit logging.
   - `useCreateWorkItemForm`: Manages form validation, fingerprint computation, idempotency cache lifecycle, submission locking, and status-code mapping.
   - `MarkdownPreview`: Encapsulates Markdown rendering with GFM, AST sanitization, and URL transformation, without any coupling to work item form state.
   - `safeUrlTransform`: Single-purpose security function enforcing strict protocol schemes (`http:`, `https:`, `mailto:`) via `new URL()`.
2. **Open/Closed Principle (OCP):**
   - Rate limiting extended via `@RateLimitBucket('work_item_create')` without altering the core `WorkspaceWriteRateLimitGuard` logic.
   - `WorkItemPropertyChips` designed as discrete buttons wrapped in `ComingSoonHint`, ready to be swapped with interactive selectors in future features (BA FC-2, FC-3, FC-8) without breaking the modal layout or API contracts.
3. **Liskov Substitution Principle (LSP):**
   - Database operations rely on `IWorkItemsRepository` and `ILockedProjectsRepository`, allowing seamless substitution between TypeORM entities and in-memory mock repositories in unit tests.
4. **Interface Segregation Principle (ISP):**
   - `ILockedProjectsRepository` exposes only `lockProjectForActor()`, keeping locking semantics isolated from general project querying.
5. **Dependency Inversion Principle (DIP):**
   - Controllers and services depend strictly on abstraction tokens (`WORK_ITEMS_REPOSITORY`, `LOCKED_PROJECTS_REPOSITORY`) bound in `WorkItemsModule`.
   - Web components depend on `@repo/contracts` types and query hooks rather than calling raw fetch/HTTP APIs directly.

---

## 4. OWASP Top 10 Security Audit

- **A01: Broken Access Control:**
  - Route guarded by `@ProjectWrite('project.workitems.create')` via `ProjectPermissionGuard`.
  - Transaction locks the target project row and re-evaluates `ProjectAccessResolver.forMembership()` using the locked actor, workspace membership, project membership, and role permissions, preventing TOCTOU privilege elevation (AC-16, AC-37).
- **A02: Cryptographic Failures & Sensitive Data:**
  - Audit log `work_item.created` emits only non-sensitive identifiers (`workItemId`, `projectId`, `workspaceId`, `actorId`, `number`, `requestId`). Title and Description are omitted from logs.
- **A03: Injection:**
  - All database queries are executed via TypeORM entity metadata and parameterized queries. No raw SQL concatenation in application code.
  - Markdown XSS eliminated: `rehype-raw` is omitted, `dangerouslySetInnerHTML` is prohibited, raw HTML nodes render as literal text, and `safeUrlTransform` rejects `javascript:` schemes (AC-21).
- **A04: Insecure Design & Anti-automation:**
  - Dedicated rate limiter restricts work item creation to 30 requests per minute per actor, preventing automated spam while keeping other workspace operations responsive (AC-24).
  - Idempotency key uniqueness across `(created_by_id, idempotency_key)` prevents double submissions even under slow network conditions or repeated clicks (AC-09, AC-11).
- **A05: Security Misconfiguration:**
  - Global `ApiExceptionFilter` ensures body-parser errors (413, 415, 400) produce standardized, sanitized error payloads with request IDs, eliminating default Express 500 error leakages.
  - CORS restricted to configured origin; `OriginGuard` protects all write routes.
- **A06: Vulnerable and Outdated Components:**
  - `react-markdown@10.1.0` and `remark-gfm@4.0.1` audited and compatible with React 19.
- **A07: Identification and Authentication Failures:**
  - Opaque session cookies validated by `SessionGuard`. If an actor's account is deactivated during an active session, the transaction aborts with 401 UNAUTHENTICATED (AC-37).
- **A08: Software and Data Integrity Failures:**
  - External images in Markdown are rendered as plain `alt` text, blocking unauthorized third-party tracking or cross-site asset injection (BA FC-10, AC-21).
- **A09: Security Logging and Monitoring Failures:**
  - Structured audit logs record all creations (`work_item.created`), idempotency replays (`work_item.create.replayed`), transaction-level rejections (`work_item.create.forbidden`), and rate limit hits (`workspace.write.rate_limited` with `bucket=work_item_create`).
- **A10: Server-Side Request Forgery (SSRF):**
  - No external HTTP requests are initiated by the server during work item creation or preview rendering.

---

## 5. Metrics Verification (BA B4: M-1 … M-6)

- **M-1 (Weekly creation count per project):**
  - Query: `SELECT project_id, date_trunc('week', created_at) w, count(*) FROM work_items WHERE deleted_at IS NULL GROUP BY 1, 2 ORDER BY 2 DESC;`
  - Result: Executed on Postgres `openplany`; operational and ready to aggregate weekly metrics.
- **M-2 (Ratio of work items with description):**
  - Query: `SELECT round(100.0 * count(*) FILTER (WHERE description <> '') / nullif(count(*), 0), 1) FROM work_items WHERE deleted_at IS NULL;`
  - Result: Executed on Postgres `openplany`; ready for baseline tracking.
- **M-3 (Sequence continuity check):**
  - Query:
    ```sql
    SELECT p.id
    FROM projects p
    LEFT JOIN work_items w ON w.project_id = p.id
    GROUP BY p.id, p.last_work_item_number
    HAVING count(w.id) <> p.last_work_item_number OR coalesce(max(w.number), 0) <> p.last_work_item_number;
    ```
  - Result: **0 rows returned** (PASS). Sequence counter strictly equals total work items in all projects.
- **M-4 (Replay rate monitoring):**
  - Verified `work_item.create.replayed` logging in unit and e2e test suites (`AC-09`).
- **M-5 (Creation rate limit monitoring):**
  - Verified `workspace.write.rate_limited` logging with `bucket=work_item_create` (`AC-24`).
- **M-6 (5xx route error rate):**
  - Verified 0 unexpected 5xx responses across all adversarial and edge-case suites (`AC-19`, `AC-33`, `AC-36`).

---

## 6. Definition of Done & Verification Evidence

All quality gates pass cleanly across the monorepo:
- **Linting:** `pnpm lint` passed with 0 warnings and 0 errors across `@repo/api`, `@repo/web`, `@repo/contracts`, `@repo/ui`.
- **Type Checking:** `pnpm typecheck` passed with 0 TypeScript diagnostics across all 4 packages.
- **Unit & Component Tests:** `pnpm test` passed:
  - `@repo/web`: 115 test files, 896 tests green.
  - `@repo/api`: 22 test files, 219 tests green.
  - `@repo/contracts`: 12 test files, 142 tests green.
  - `@repo/ui`: 2 test files, 15 tests green.
- **API E2E Tests:** `pnpm --filter @repo/api test:e2e` passed (19 test files, 461 tests green).
- **PreToolUse Hook Tests:** `pnpm test:hooks` passed (15/15 tests green).
- **Code Duplication:** `pnpm dup` reported 1.91% duplicated lines, well below the configured threshold.
- **Terminology:** `CONTEXT.md` updated with "Work item", "Work item number", "Project ID", and localized Vietnamese glossary entries.

