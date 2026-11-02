# F12 · Day-of-service operations · Database spec (DB)

---

# PART A · For readers

Two new tables record when a technician is absent and when a bay is out of service. Everything else uses existing columns (`checked_in_at`, `started_at`, `completed_at`, `contact_required_reason`, created in F04).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

### DB-12.1 · `technician_absences`

| Column | Type | Constraint |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `dealership_id` | `uuid` | NOT NULL FK |
| `technician_id` | `uuid` | NOT NULL FK `technicians` |
| `period` | `tstzrange` | NOT NULL; `CHECK (NOT isempty(period) AND lower_inc(period) AND NOT upper_inc(period))` |
| `reason` | `varchar(32)` | NOT NULL, CHECK `SICK_LEAVE`, `TRAINING`, `PERSONAL`, `OTHER` |
| `created_by_user_id` | `bigint` | NOT NULL FK `users` |
| `created_at` | `timestamptz` | NOT NULL |
| index | | GiST `(technician_id, period)`; `(dealership_id)` |

### DB-12.2 · `bay_outages`

Same as DB-12.1 with `bay_id uuid NOT NULL FK bays` instead of `technician_id`; reasons `EQUIPMENT_FAULT`, `MAINTENANCE`, `OTHER`; GiST `(bay_id, period)`.

Overlapping absences for one technician are allowed (they merge as busy intervals); no EXCLUDE is needed. No soft deletes; removing an absence is out of scope.

### Existing columns written by F12 (F04 DB-04.1)

`status`, `checked_in_at`, `started_at`, `completed_at`, `end_at` (extend), `technician_id`, `bay_id` (reassign), `contact_required_reason`.

## B2. Migrations

| Migration | Pre-check | `down()` |
| --- | --- | --- |
| `create_technician_absences_table` | `btree_gist` exists (F04) | `dropIfExists` |
| `create_bay_outages_table` | — | `dropIfExists` |

## B3. Main queries

| Query (illustrative) | Index |
| --- | --- |
| Busy intervals from absences/outages for a dealership day (composite reader) | `(dealership_id)` + GiST |
| Affected bookings of an absence: `SELECT a.* FROM appointments a JOIN resource_allocations ra ON ra.appointment_id = a.id WHERE ra.resource_type = 'TECHNICIAN' AND ra.resource_id = :tech AND ra.active AND ra.period && :range AND a.status IN ('CONFIRMED','CHECKED_IN','IN_PROGRESS') ORDER BY a.start_at` | EXCLUDE GiST |
| Extend neighbours: same join on X's technician or bay, `period && [end, newEnd)`, `a.id <> :x` | EXCLUDE GiST |
| Board: bookings of the day with `status IN (…)` + bays + technicians + shifts | `appointments_dealership_start_idx`, F01 indexes |
| Contact list: `WHERE dealership_id = :d AND contact_required_reason IS NOT NULL AND status = 'CONFIRMED'` | `appointments_contact_required_idx` (F04) |

Lock order in extend (R-12.2): the job row first (versioned update), then neighbours ordered by `id`.

## B4. Updated checks

- `AssertsNoOverlap` (F04) is unchanged (overlaps never allowed).
- M-04.2 gains the exception: `status = 'CONFIRMED' AND contact_required_reason = 'OVERRUN'` may have 0 active allocations (D-12.9).

## B5. DB tests

TC-12-03 (absence), TC-12-09 (displacement), TC-12-15 (composite reader), TC-12-18 (CHECKs).
