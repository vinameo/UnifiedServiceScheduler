---
name: implement-ticket
description: Implement one ticket of a Unified Service Scheduler feature (docs/features/<n>-<feature>/tickets/NN-*.md) on its feature branch, from design pass to a commit reviewed against the spec and ticket; after the last ticket, review the whole feature (spec, SOLID, OWASP Top 10, extensibility), fix the findings and merge. Use when asked to implement, code or build a ticket or a feature spec.
---

# Implement a ticket

## Steps

1. **Branch.** Follow `CLAUDE.md` "Branch workflow for agents". Done when you are on `feature/<docs-dir>` with a clean tree, and, for ticket 01, the previous feature is merged into `develop`.
2. **Read.** The ticket, every spec section it cites (its "Read first" line and the IDs in its checkboxes), the tickets before it (their code is now on the branch), the shared conventions in `docs/features/README.md` (decisions, permission matrix, error copy, seams, fixtures), and the code it touches. Done when you can name each file you will change and the existing shared piece (README §6) each change builds on.
3. **Load skills.** For each layer the ticket touches, load the installed skill with the Skill tool, unless it is already preloaded (as in the `deep-coder` subagent):

   | Layer                                                         | Skill                                       |
   | ------------------------------------------------------------- | ------------------------------------------- |
   | Database: migration, index, constraint, raw SQL in a repository | `postgres-skills:postgresql-best-practices` |
   | API: route, FormRequest, controller, service, repository, policy | `backend-tools:backend-development`         |
   | Web: page, component, hook, query                             | `web-frontend` (project skill)              |

   `CLAUDE.md` overrides these skills where they disagree: Laravel 13 with Repository → Service → Controller layers. Before you write a migration or a query, inspect the real schema, indexes and row counts read-only, with `docker compose exec postgres psql -U scheduler -d scheduler -c '<query>'` or the `postgres-mcp` query tool (`postgres_mcp_query`). Never write to the database that way; change the schema only through migrations.

4. **Design pass: every sketch is a hypothesis.** The spec's code, queries, locks and algorithm steps, and the function names and step orders in the ticket, were written without running code. The requirement is the INVs, ACs, permissions and copy they serve. Test each hypothesis against the code at HEAD, the real schema and rows, and `CLAUDE.md` (core rules, layer conventions, and the seven rules of "Design pass and self-review"). Then write `docs/features/<n>-<feature>/tickets/NN-<slug>.design.md` with this table, one row per sketch or rule the ticket builds on:

   | Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
   | ----------------------- | ------------------ | ------------------------- | ------------------------------ | ------------------------ | ------------------- | ------- |
   - **Sketch or rule:** where it is (`API B2`, `DB B1`) and what it says, in one line.
   - **Invariant:** the INV or AC it serves. When the spec states none, write the one the sketch needs and mark it `(added)`.
   - **Checked against:** the `file:line` or query you read at HEAD, in backticks. When the spec's "current code state" names a commit older than HEAD, read `git diff <that commit>..HEAD` on the files it cites.
   - **Changes between read and write:** what another request can commit between the read that decides and the write (a status change, a cancel, a disabled technician, the same write twice, an expired hold), and which read the decision uses: a row locked in the same transaction, the version check of `updateVersioned()`, the EXCLUDE constraint, or a value read earlier.
   - **Deleted or inactive rows:** inactive technicians, bays and service types, inactive allocations, `CANCELLED`/`EXPIRED`/`NO_SHOW`/`COMPLETED` appointments, another dealership's rows, and how each is excluded or answered (404 vs 403 per README §4).
   - **Same rule elsewhere:** the existing seam (README §6.2) or the other place in the spec or code that states the same rule, and the one seam both will use.
   - **Verdict:** `follow`, or `deviate: <the sound version>`.

   A deviation that keeps every AC, permission and user-facing text is yours to build: build it, test it, name it in the commit body (step 9). Stop and ask the user only when the sound version changes what users see. Done when every sketch and rule the ticket cites has a row and every cell holds evidence. The `requireTicketDesignNotes` commit hook refuses a ticket commit without notes written since the last commit, or with an empty or placeholder cell.

5. **Prefactor first.** If the change would copy existing code, extract the shared piece in its own commit with no behaviour change, and show existing tests green.
6. **Build with tests.** Implement the ticket and make its `TC-` rows pass. How you order code and tests is your call; keep the feedback loop short:
   - While building, run the narrow command for what you are working on, not the whole suite:
     - API: `docker compose exec php php artisan test --filter <TestClass or test name>` (real PostgreSQL, database `scheduler_testing`).
     - Web: `cd web && npx vitest run <file>`.
   - Name each test after the requirement it proves, as its `TC-` row's "Where" column says (`test_AC_04_1_…`). Read expected values (copy, codes, formats) from the source the code uses (`docs/features/README.md` §5, the WEB spec's copy table, `copy.ts`, `errorCopy.ts`) instead of writing them from memory, and reuse the shared fixtures (README §7: `World`, `SnapshotBuilder`, `AssertsNoOverlap`, `Parallel`, `FreezesClock`).
   - Test what this ticket builds. Behaviour that a later ticket delivers is tested in that ticket.
   - Booking tests assert no overlapping active allocations (`AssertsNoOverlap`). Concurrency tests run real parallel processes through `Parallel` and use `DatabaseTruncation`, never `RefreshDatabase` (`CLAUDE.md` §Testing rules).
   - A new migration: run `docker compose exec php php artisan migrate`, then `migrate:rollback --step=1` and `migrate` again to prove its `down()`. Never edit a migration that has already run.
   - A new route under `/api/v1` has `auth:sanctum` and a `can:` ability or Policy (README §4); dealership routes use the `{dealership}` binding and appointment routes the `{appointment}` binding (README §6.2). A writing `POST` has `idempotent`; a change to an existing appointment has `if-match`.

   Before step 7, run in each area you changed: `docker compose exec php ./vendor/bin/pint --test` and `./vendor/bin/phpstan analyse` (API); `cd web && npm run lint && npx vitest related --run <changed files>` (web). A regression in shared code then shows up here rather than in the full run.

7. **Check.** The README §8 commands:

   ```bash
   docker compose exec php php artisan test
   docker compose exec php ./vendor/bin/pint --test
   docker compose exec php ./vendor/bin/phpstan analyse
   cd web && npm run lint && npm run test
   ```

   If a test fails only in the full run, rerun once before debugging; if it keeps failing and is unrelated to this branch, note it in the report. Done when all are green, apart from such a noted failure.

8. **Review against the spec and the ticket.** Read `git diff` (staged and unstaged) for this ticket next to the ticket file and the spec sections it cites:
   - Every checkbox in the ticket is done, or you note why not.
   - Every AC, INV and spec section the ticket cites has the code that implements it and a test that fails without it. Name them. A cited item with no test is a finding.
   - Nothing beyond the ticket's scope slipped in. Work that belongs to a later ticket waits for it.
   - User-facing text comes from the spec, through the area's `copy.ts` or `web/src/shared/api/errorCopy.ts`; clients act on the error `code` only.
   - `CLAUDE.md` core rules hold: no `save()`/`update()` on an appointment outside `updateVersioned()`, no SELECT check instead of the EXCLUDE constraint, no outside call inside a request, no Eloquent outside repositories, controllers call only service interfaces.
   - Every `deviate` row of the design notes (step 4) is built and has a test that fails on the spec's sketch.

   Fix each finding and re-run step 7. Done when the review finds nothing new.

9. **Commit** one Conventional Commit per ticket (`CLAUDE.md` "Branch workflow for agents"); the body names the ticket file and every `deviate` row. Rewrite the spec's sketch for each deviation as the invariant it missed, so later tickets and features build on the sound version. Tick the ticket's checkboxes and set its **Status** to `done` in the same commit.
10. **After the tracer-bullet ticket** (marked "(tracer bullet)" in its title), run `/code-review` on the branch and fix its findings before starting the next ticket: later tickets copy whatever the tracer establishes.

## After the last ticket: feature review

Run these in order.

### 1. Read the whole feature

The full spec set (`1-…` to `6-…`), `tickets/README.md`, and every ticket file with its `.design.md`. Then read `git diff develop...HEAD` as one change, not ticket by ticket.

### 2. Test the feature

Run the tests the branch added or changed (`git diff --name-only develop...HEAD`): `docker compose exec php php artisan test <test files>` for the API and `cd web && npx vitest related --run <changed files>` for the web. Fix the failures before reviewing.

### 3. Review

Run `/code-review` and `/security-review` on the branch, then check each area below yourself. Tickets built in separate contexts duplicate each other: look for two implementations of the same idea.

1. **Spec complete.** Every AC and INV in `1-requirement-spec.md` has code and a test. Every ticket in `tickets/README.md` is done and has its `.design.md`. Every `deviate` row is written back into the spec.
2. **SOLID.**
   - S: controllers take a FormRequest, call one service interface method and return a Resource; each service and repository has one job; no god-service.
   - O: a new role, ability or status is added through the `Role` enum and a Gate in `AppServiceProvider::boot()`, a Policy, or `AppointmentStatus::canMoveTo()`, not by editing `if` branches in existing code.
   - L: every implementation of an interface (repository, `NotificationChannel`, `AllocationStrategy`, test fakes) honours the same contract, including exceptions and ordering.
   - I: repository and service interfaces expose only what their callers use.
   - D: services depend on interfaces bound in `DomainServiceProvider::$bindings`, never on Eloquent, `DB` or a concrete class; `App\Domain\Allocation` stays free of HTTP and Eloquent.
3. **Security, OWASP Top 10:**
   - A01 Broken access control: every route has `auth:sanctum` and its `can:` ability or Policy (README §4). Staff are scoped to their dealership through the `{dealership}` binding; appointments load only through `findVisibleTo` (404, not 403, for another customer's or dealership's). The customer id always comes from `auth()->user()`.
   - A02 Cryptographic failures: no secret, token or password in code, logs or responses; passwords through `Hash`; seed passwords only from env; session cookies keep their flags.
   - A03 Injection: every input through a FormRequest; raw SQL only in repositories and migrations, always with bindings; `LIKE` input escaped.
   - A04 Insecure design: rate limits on sign-in and on lookups whose 404 reveals existence; multi-write operations in one `DB::transaction()`; concurrent identical writes answer 4xx (`Idempotency-Key`, `If-Match`, EXCLUDE).
   - A05 Security misconfiguration: errors as problem+json without stack, SQL or exception messages; `display_errors=Off`; Nginx security headers kept (`expires`, not `add_header`, in locations); `/metrics` not public.
   - A06 Vulnerable components: run `docker compose exec php composer audit` and `cd web && npm audit` if dependencies changed; no new package without a `D` row (D-G.11).
   - A07 Identification and authentication failures: a session on every route outside the public allowlist (sign-up, sign-in, `/health`); sign-in errors never say whether the email or the password was wrong.
   - A08 Software and data integrity failures: `appointment_events` is append-only; `appointments.version` rises on every write; migrations roll back cleanly and are never edited after they ran.
   - A09 Logging and monitoring failures: JSON logs carry `correlationId` and never emails, phone numbers, passwords or tokens; log lines an outsider can trigger are bounded.
   - A10 SSRF: no server-side request to a user-supplied URL (webhook URLs come from env only).
4. **Extensibility.** Read the `FC-` constraints in `2-business-analytics-spec.md` and the seam table in README §6.2. For each one, name the seam that the next feature will extend and check it needs no rewrite. Every list has a limit and a real count (D-G.12); every new WHERE, ORDER BY and FK column has an index; there are no N+1 queries and nothing is hardcoded that a later feature will vary.

### 4. Fix the findings

Fix each finding in a `review(<scope>)` commit and rerun the feature tests (section 2) for what the fix touched. Defer a finding only with a reason: it belongs to a later feature in README §1, or it needs the user's decision.

### 5. Test the whole app

Run every check, not only the feature's: the four commands of step 7, with the API suite even when the feature changed only the web. Fix each failure in a `review(<scope>)` commit and rerun the command that failed; if the fix changed code, rerun them all. A failure unrelated to the branch is handled as in step 7. Done when every command is green in one pass.

### 6. Report and merge

Write `.claude/reviews/<branch with "/" as "__">.md` (for example `feature__04-booking-integrity.md`) after the final commit: one section per review area (findings, what was fixed with its commit, what was deferred and why) and the results of sections 2 and 5. The merge hook refuses a branch whose report is missing or older than its last commit. Then merge as in `CLAUDE.md` "Branch workflow for agents".
