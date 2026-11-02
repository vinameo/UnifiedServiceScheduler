# F09 · Reschedule · Database spec (DB)

---

# PART A · For readers

F09 adds no tables. A reschedule updates the booking's time, technician and bay, swaps its allocations, and adds a history row and an outbox message — all in one transaction, so a failure leaves everything as it was.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables, no migrations.

## B2. Writes in one transaction (SDD §10, INV-09.1–09.3)

| # | Statement (illustrative) | By |
| --- | --- | --- |
| 1 | `UPDATE appointments SET start_at = :s, end_at = :e, technician_id = :t, bay_id = :b, version = version + 1, updated_at = :now WHERE id = :id AND version = :expected` → 0 rows: rollback, 412 | `updateVersioned` |
| 2 | `UPDATE resource_allocations SET active = false, deactivated_at = :now WHERE appointment_id = :id AND active` | `deactivateFor` |
| 3 | `INSERT resource_allocations` × 3 (`TECHNICIAN`, `BAY`, `VEHICLE`) with `tstzrange(:s, :e, '[)')` → 23P01: rollback, next pair or 409 | `insertFor` |
| 4 | `INSERT appointment_events (type RESCHEDULED, data {from, to})` | `AppointmentEventRecorder` |
| 5 | `INSERT outbox_messages` × 3 channels `AppointmentRescheduled` | `OutboxWriter` |

Indexes used: PK of `appointments`; `resource_allocations_one_active_per_type`; the EXCLUDE GiST.

## B3. DB tests

TC-09-01 (nothing changes on 409), TC-09-02 (own slot), TC-09-12 (concurrency), TC-09-15 (history + outbox).
