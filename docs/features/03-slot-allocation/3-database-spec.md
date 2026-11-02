# F03 · Free slots and resource allocation · Database spec (DB)

---

# PART A · For readers

F03 creates no tables. It only reads master data (F01) and, from F04 on, the active allocations. A search for one day reads a fixed set of queries, whatever the number of slots.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables. No migrations.

## B2. Queries of `AllocationSnapshotRepository::forDay` (hypothesis)

| # | Reads | Tables | Index (F01) |
| --- | --- | --- | --- |
| 1 | Dealership + time zone + opening hours | `dealerships`, `dealership_opening_hours` | PK, unique `(dealership_id, weekday)` |
| 2 | Technicians with their level (all statuses) | `technicians` | `(dealership_id, name)` |
| 3 | Bays | `bays` | `(dealership_id, bay_type) WHERE active` |
| 4 | Busy intervals within the UTC window of the local day | through `BusyIntervalReader` (F04: `resource_allocations` ⋈ `appointments`) | EXCLUDE GiST (F04) |

A fixed number of queries (≤ 6) per day, whatever the slot count (FR-06.4). The agent may merge query 2 with eager loading; the count must not grow with the number of technicians (no N+1) — TC-03-19 counts queries.

Query 4 is written in F04; its shape (_illustrative_) so F04 and F10 agree:

```sql
SELECT ra.resource_type, ra.resource_id, lower(ra.period) AS starts_at, upper(ra.period) AS ends_at, ra.appointment_id
FROM resource_allocations ra
JOIN appointments a ON a.id = ra.appointment_id
WHERE ra.dealership_id = :dealershipId
  AND ra.active
  AND ra.period && tstzrange(:windowStart, :windowEnd, '[)')
  AND NOT (a.status = 'HELD' AND a.expires_at <= :now);   -- D-03.7, :now from the app clock (README D-G.6)
```

## B3. Notes

- The local-day window: local `[00:00, 24:00)` converted to UTC (for example `2026-11-01T17:00Z` to `2026-11-02T17:00Z` for UTC+7). Never `date(...)` in SQL on a `timestamptz`, because it uses the DB session's time zone.
- No transaction: reads at READ COMMITTED, may be stale right after reading (R-03.2).
