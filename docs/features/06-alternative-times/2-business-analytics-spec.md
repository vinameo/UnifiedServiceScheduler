# F06 · Suggest other free times · Business analysis (BA)

---

# PART A · For readers

## A1. Why

Without a free-time grid, users try time after time and keep hitting 409 (RB-02.1). When the slot is gone, "no availability" alone is not enough: users need another time right away. The drafts clearly separate "409 · business outcome, not an outage": a full workshop is normal, not a system failure.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-06.1 | Requirements F06 | FR-06.1–06.5, BR-06.1, 3 ACs. |
| E-06.2 | New booking draft | "Struck-through slots have no free bay or technician." |
| E-06.3 | Booking error states draft | "Nearest available slots: 10:00 today · 11:15 today · 08:00 Tue 3 Nov". |
| E-06.4 | Customer step 2 draft | "Morning {n} free slots", "This day is fully booked", "Next available slot for Oil change: Fri 6 Nov · 08:00". |
| E-06.5 | SDD §12 | Read straight from the DB, no cache. |
| E-06.6 | NFR-02 | Free times for one day p95 < 300 ms. |

## A3. Priorities

Should (requirements). Within F06: the grid + suggestions on 409 are the core; `next` and "free slots" counts are Should; `excludeAppointmentId` is needed by F09.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-06.1 | Users think the grid is a promise | The line "Times can be taken by someone else until you confirm." under the grid (customer) |
| RB-06.2 | A suggestion 7 days away does not suit the customer | Accepted; the customer can pick another day on the grid. Owner: product owner. Revisit when M-06.2 < 20% over 30 days. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-06.1 · One snapshot, computed in memory

- Required by FR-06.4. Reusing F03's `CandidateFinder` for each slot gives one single definition of "free" for both the grid and booking.

### ADR-06.2 · Return every slot with `available`

- **Options:** (a) return only free slots; (b) return every slot + a flag.
- **Chosen: (b):** FR-06.5 (strike through, keep the grid's shape), and the web does not have to compute opening hours itself.

### ADR-06.3 · Suggestions after rollback

- Inside a transaction broken by 23P01, PostgreSQL refuses further queries. Computing after rollback on a fresh snapshot also gives better suggestions.

## B2. Metrics

| ID | Metric | SQL / source | Threshold |
| --- | --- | --- | --- |
| M-06.1 | Availability p95 | `api_events` route `GET api/v1/dealerships/{dealership}/availability` (F08) | < 300 ms |
| M-06.2 | Share of 409s whose suggestion was used (rebooked within 5 minutes at a suggested time) | Join `api_events` 409 (`detail->alternatives`) with later bookings by the same user | Watch |
| M-06.3 | Share of 409 `NO_AVAILABILITY` with empty `alternatives` | `SELECT count(*) FILTER (WHERE (detail->>'alternativesCount')::int = 0)::float / count(*) FROM api_events WHERE code = 'NO_AVAILABILITY' AND occurred_at > now() - interval '7 days';` | Alert > 20% (capacity shortage). F08 writes `alternativesCount` into `detail` for this code. |

### Verification & Benchmark Results (Ticket 10 Acceptance, 2026-11-04)

- **M-06.1 (Availability p95 < 300 ms):** Verified by `AvailabilityPerformanceTest::test_TC_06_13_get_availability_p95_under_300ms` on real PostgreSQL with 20 bays, 30 technicians, and 200 bookings across the day.
  - Measured latencies over 30 sequential requests:
    - Min: 40.91 ms
    - Median: 41.21 ms
    - **p95: 41.63 ms** (Passing threshold < 300 ms with ~7x headroom)
    - Max: 41.84 ms
- **M-06.2 (Suggestion adoption tracking):** `BookingService::create` and `NoAvailability` exception serialize `alternatives` with `{start, end}` on 409 responses; telemetry seam ready for event correlation in F08.
- **M-06.3 (409 NO_AVAILABILITY with empty alternatives):** `AlternativesOnConflictTest` confirms empty array fallback when all 8 days are full; `detail` schema preserves `alternativesCount` for monitoring.


## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-06.1 | Suggestions by familiar technician | `AllocationRequest.preferTechnicianId` |
| FC-06.2 | Very high load | Cache forbidden (INV-06.4); SDD phase 2/3 instead |
| FC-06.3 | Suggest other dealerships | `AlternativeTimesFinder` takes a list of dealerships |
