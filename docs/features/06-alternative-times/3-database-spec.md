# F06 · Suggest other free times · Database spec (DB)

---

# PART A · For readers

F06 only reads; no new tables or migrations. Each day computed reads one fixed set of queries (F03's), however many slots that day has.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables, no migrations.

## B2. Queries

| Operation | Query | Count |
| --- | --- | --- |
| One day's grid | `AllocationSnapshotRepository::forDay` (F03 DB §B2) | 1 snapshot |
| Suggestions on 409 | `forDay` for the wanted day, then up to 7 later days | ≤ 8 snapshots, stopping early |
| `next` | Same, from `after` | ≤ 8 snapshots |
| `excludeAppointmentId` | `findVisibleTo` (F05) | 1 |

Index used: `resource_allocations_dealership_period_idx` (F04) for the day's busy intervals.

The busy-interval query could merge several days into one (an 8-day window) for suggestions; a hypothesis the agent measures before doing (TC-06-13).

## B3. DB tests

TC-06-10 (fixed query count), TC-06-13 (performance).
