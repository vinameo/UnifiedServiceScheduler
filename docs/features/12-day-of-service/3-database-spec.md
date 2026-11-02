# F12 · Day-of-service operations · Database spec (DB)

---

# PART A · For readers

F12 adds no table. It writes existing columns of `appointments` (`checked_in_at`, `started_at`, `completed_at`, `end_at` on extend, `technician_id`/`bay_id` on reassign, `contact_required_reason`, all created in F04). Technician absences and bay outages are out of scope (README D-G.16), so there are no `technician_absences` or `bay_outages` tables.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables, no migrations.

Existing columns written by F12 (F04 DB-04.1): `status`, `checked_in_at`, `started_at`, `completed_at`, `end_at` (extend), `technician_id`, `bay_id` (reassign), `contact_required_reason` (only `OVERRUN`, F04 CHECK).

## B2. Main queries

| Query (illustrative) | Index |
| --- | --- |
| Extend neighbours: `SELECT a.* FROM appointments a JOIN resource_allocations ra ON ra.appointment_id = a.id WHERE ra.active AND ((ra.resource_type = 'TECHNICIAN' AND ra.resource_id = :tech) OR (ra.resource_type = 'BAY' AND ra.resource_id = :bay)) AND ra.period && :ext AND a.id <> :x` | EXCLUDE GiST |
| Board: bookings of the day with `status IN (…)` + bays + technicians (with `level`) | `appointments_dealership_start_idx`, F01 indexes |
| Contact list: `WHERE dealership_id = :d AND contact_required_reason IS NOT NULL AND status = 'CONFIRMED' AND start_at >= :todayStartUtc` | `appointments_contact_required_idx` (F04) |

Lock order in extend (R-12.2): the job row first (versioned update), then neighbours ordered by `id`.

## B3. Updated checks

- `AssertsNoOverlap` (F04) is unchanged (overlaps never allowed).
- M-04.2 gains the exception: `status = 'CONFIRMED' AND contact_required_reason = 'OVERRUN'` may have 0 active allocations (D-12.9).

## B4. DB tests

TC-12-09 (displacement), TC-12-10 (extend all-or-nothing).
