# F11 · Notifications through the outbox · Database spec (DB)

---

# PART A · For readers

F11 adds no tables. It reads and updates `outbox_messages` (created in F04): claiming due rows, recording sends and failures, and adding reminder rows.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables. Columns used (F04 DB-04.5): `status` (`PENDING`, `SENT`, `FAILED`, `SKIPPED`), `attempts`, `next_attempt_at`, `last_error`, `sent_at`, `dedupe_key`, `payload`, `event_id`, `channel`, `dealership_id`, `appointment_id`.

A migration MAY add `CHECK (attempts BETWEEN 0 AND 5)` (pre-check: `SELECT count(*) FROM outbox_messages WHERE attempts > 5` = 0; `down()` drops the CHECK).

## B2. Main queries

| Query (illustrative) | Index |
| --- | --- |
| Claim: `SELECT id FROM outbox_messages WHERE status = 'PENDING' AND next_attempt_at <= :now ORDER BY next_attempt_at LIMIT 50 FOR UPDATE SKIP LOCKED` + `UPDATE … SET attempts = attempts + 1, next_attempt_at = :now + interval '60 seconds'` | `(next_attempt_at) WHERE status = 'PENDING'` (F04) |
| Record result: `UPDATE outbox_messages SET … WHERE id = :id AND status = 'PENDING'` | PK |
| Reminder candidates: `SELECT id, start_at FROM appointments WHERE status = 'CONFIRMED' AND reminder_opt_in AND start_at >= :from AND start_at < :to` | `appointments_dealership_start_idx` is per dealership; MAY add `(start_at) WHERE status = 'CONFIRMED' AND reminder_opt_in` if the job is slow |
| Insert reminder: `INSERT … ON CONFLICT (dedupe_key) DO NOTHING` | unique `dedupe_key` |
| Admin list: `WHERE dealership_id = :d [AND status/channel/event_type/appointment_id] ORDER BY created_at DESC, id DESC` + cursor | `(dealership_id, status, created_at)` (F04) |
| Summary counts | same index |
| Retry: `UPDATE … WHERE id = :id AND dealership_id = :d AND status = 'FAILED'` | PK |

`:now` comes from the app clock (README D-G.6).

## B3. DB tests

TC-11-08 (two relays, `SKIP LOCKED`), TC-11-10 (dedupe), TC-11-05 (retry).
