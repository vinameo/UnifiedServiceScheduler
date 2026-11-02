# F07 · Cancel an appointment · Database spec (DB)

---

# PART A · For readers

F07 adds no tables. A cancel writes the status and reason on the appointment row (columns that exist since F04), deactivates the allocations, adds a history row and (for confirmed bookings) the cancellation message in the outbox — all in one save.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables, no migrations. Columns used (F04 DB-04.1): `status`, `cancel_reason` (`varchar(32)`, D-07.1 codes), `cancel_note` (`varchar(500)`), `cancelled_at`, `cancelled_by_user_id`, `version`, `updated_at`.

No CHECK on `cancel_reason` (the list may change; checked in the app). The agent MAY add `CHECK (status <> 'CANCELLED' OR cancelled_at IS NOT NULL)` in a new migration if useful — with a pre-check: `SELECT count(*) FROM appointments WHERE status = 'CANCELLED' AND cancelled_at IS NULL` must be 0.

## B2. Writes in one transaction (INV-07.1)

| # | Statement | By |
| --- | --- | --- |
| 1 | `UPDATE appointments SET status='CANCELLED', cancel_reason, cancel_note, cancelled_at, cancelled_by_user_id, version = version + 1, updated_at WHERE id AND version` | `updateVersioned` |
| 2 | `UPDATE resource_allocations SET active=false, deactivated_at=:now WHERE appointment_id = :id AND active` | `deactivateFor` |
| 3 | `INSERT appointment_events (type CANCELLED, data {reason})` | `AppointmentEventRecorder` |
| 4 | `INSERT outbox_messages` × 3 channels (only from `CONFIRMED`) | `OutboxWriter` |

## B3. DB tests

TC-07-01 (slot released), TC-07-09 (concurrency), TC-07-10 (outbox).
