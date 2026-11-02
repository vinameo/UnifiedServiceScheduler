# F04 · No double booking, no duplicate requests · Business analysis (BA)

---

# PART A · For readers

## A1. Why

A workshop has a fixed number of technicians and bays. Bookings come from the desk and the app at the same time. Double booking means two customers arriving at the same time for one bay: customers wait, the dealership loses trust. A lost update means a cancelled booking "comes back" and blocks a slot. F04 is the system's promise that this "never happens", even under load and with many workers.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-04.1 | SDD §5, §6 | Two risky moments: creation (the row does not exist yet) and change (old screen). |
| E-04.2 | SDD §7 | Four options compared; EXCLUDE for creation, optimistic locking for change. |
| E-04.3 | SDD §10 | Table of every operation: version check / EXCLUDE / possible errors. |
| E-04.4 | Requirements F04 | FR-04.1–04.8, BR-04.1–04.2, 7 ACs; tests on real PostgreSQL. |
| E-04.5 | "Booking error states" draft | Each error has a title + what to do; "No response · safe retry" reuses the key; the button locks while sending. |
| E-04.6 | `CLAUDE.md` "The core rules" | Order inside a time change; one update seam; no 412 retry; key middleware before If-Match. |

## A3. Priorities

Must: all of S-04.1–S-04.8. Should: web seams (S-04.9). Could: none.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-04.1 | A "hot" slot makes many requests 409 → frustrated users | Clear error + alternatives (F06); measure the retry rate (M-04.1), move to SDD phase 2 when > 5% |
| RB-04.2 | A worker dies after commit but before saving the answer → the retry gets 409 `VEHICLE_ALREADY_BOOKED` instead of the old 201 | Rare; the user sees the existing booking in the error (`existingAppointment`) and in the list |
| RB-04.3 | A user gets 412 because someone just changed the booking | By design; the 412 carries the new data, retry in one tap; measured by M-04.3 |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-04.1 · EXCLUDE for creation (SDD §8)

- **Chosen:** `EXCLUDE USING gist (resource_type WITH =, resource_id WITH =, period WITH &&) WHERE (active)`.
- **Constraints:** never remove, weaken or replace it with a `SELECT`; ranges `[)`; test on real PostgreSQL; every freeing status deactivates; the `WHERE` cannot use `now()`, so expired holds must be deactivated by code (F10).

### ADR-04.2 · Optimistic locking for changes (SDD §9)

- **Chosen:** `version` + `ETag`/`If-Match` + `UPDATE … WHERE version = ?`.
- **Constraints:** one seam `updateVersioned`; a DB trigger as safety net (D-04.7); 428 when missing; no 412 retry; background jobs follow the same rule.

### ADR-04.3 · Idempotency keys stored in PostgreSQL

- **Options:** (a) an `idempotency_keys` table; (b) cache; (c) Redis.
- **Chosen: (a).** Survives restarts, shared by workers, and `INSERT … ON CONFLICT` gives "only one runs" without an external lock. No Redis in compose.
- **Constraints:** insert the key **outside** the business transaction (auto-commit) so parallel requests see it at once; keys per user (D-04.11); 24-hour TTL + daily prune.

### ADR-04.4 · Outbox created in F04

- `CLAUDE.md` rule 4: every change writes the outbox in the same transaction. F02 is the first feature that creates real bookings; if the outbox waited for F11, F02–F10 would break rule 4 or need rework.
- **Chosen:** table + `OutboxWriter` in F04; relay in F11. From F02 on, confirmed bookings have outbox rows (unsent until F11).

### ADR-04.5 · Test-only routes

- F04 is built before F02, so there is no real endpoint yet. `/_testing/*` routes call the real seams through the real middleware, to prove the ACs at the HTTP level with parallel processes.
- **Constraint:** registered only when `APP_ENV=testing` (INV-04.14); F02/F07/F09 prove the ACs again on real endpoints.

## B2. Metrics

| ID | Metric | SQL / source | Threshold |
| --- | --- | --- | --- |
| M-04.1 | Share of bookings that needed a retry | `allocation_retries_total / booking_requests_total` (F08) | > 5% in 1 hour → look at SDD phase 2 |
| M-04.2 | Appointments with the wrong number of active allocations (INV-04.6) | `SELECT a.id FROM appointments a LEFT JOIN resource_allocations r ON r.appointment_id = a.id AND r.active GROUP BY a.id, a.status, a.vehicle_id HAVING (a.status IN ('HELD','CONFIRMED','CHECKED_IN','IN_PROGRESS') AND count(r.id) <> 2 + (a.vehicle_id IS NOT NULL)::int) OR (a.status NOT IN ('HELD','CONFIRMED','CHECKED_IN','IN_PROGRESS') AND count(r.id) > 0);` | **0** rows (run daily, alert if > 0) |
| M-04.3 | 412 rate | `appointment_modified_total` (F08) | Watch; a spike may mean an old client |
| M-04.4 | Overlapping allocations (INV-04.1) | Overlap query in README §7 | **0** rows, always |
| M-04.5 | Keys still `IN_PROGRESS` after 5 minutes | `SELECT count(*) FROM idempotency_keys WHERE status = 'IN_PROGRESS' AND locked_until < now() - interval '5 minutes';` | 0 (non-zero → workers dying often) |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-04.1 | SDD phase 2: `pg_advisory_xact_lock(dealership, day)` before picking a pair | One step at the start of the transaction in `AppointmentWriter` |
| FC-04.2 | Leaving PostgreSQL | The overlap rule lives only in the migration + `ResourceAllocationRepository`; tests describe the behaviour |
| FC-04.3 | Partner clients calling the API | Clear 428, documented headers (API §B1) |
| FC-04.4 | More notification channels | `OutboxChannel` enum + the per-event channel table in `OutboxWriter` |
