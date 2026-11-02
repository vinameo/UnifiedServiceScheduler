# F03 · Free slots and resource allocation · Business analysis (BA)

---

# PART A · For readers

## A1. Why

This is where "who does it, in which bay" is decided. If ranking picked multi-skilled people for easy jobs, the hard jobs (EVs) would run out of people early. So ranking prefers people with **fewer** skills.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-03.1 | Requirements F03 | FR-03.1–03.6, BR-03.1–03.2, 8 ACs; "must not depend on HTTP". |
| E-03.2 | SDD §8 | A short list of pairs (top 5), try the next pair on 23P01. |
| E-03.3 | `CLAUDE.md` | `app/Domain/Allocation` is plain PHP, no HTTP, no Eloquent. |
| E-03.4 | New booking draft | "Technician, bay: Assigned automatically on confirm" — users do not pick. |
| E-03.5 | Master data draft | Only Ngô Bảo Em has High voltage → the EV service depends on one person. |

## A3. Priorities

Must: filtering, reasons, ranking, strategy, `OpeningHours`, snapshot. Should: `prefer*`. Could: none.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-03.1 | One person with the EV skill → EV is often "full" | Measured by M-03.2; fewest-skills-first keeps Em free for EV jobs |
| RB-03.2 | Assigning by bay code keeps Bay 1 busier than Bay 2 | Accepted (Q-03.b) |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-03.1 · Search in memory on one snapshot, not one query per slot

- **Options:** (a) run SQL filters for each search; (b) load one day's snapshot and filter in PHP.
- **Chosen: (b).** F06 needs 36 searches for one day (FR-06.4 forbids one query per slot); unit tests need no DB.
- **Constraint:** the snapshot is read-only; no cache between requests (SDD §12 "no cache"); F04's EXCLUDE is the final guard.

### ADR-03.2 · Fewest skills first

- Required by FR-03.4. Benefit: keeps versatile people for rare services. Swappable through `AllocationStrategy` (FR-03.5).

### ADR-03.3 · Busy intervals come through `BusyIntervalReader`

- F03 is built before F04 (D-03.1) and F12 adds absences/outages. One interface for every source of "busy" keeps `CandidateFinder` unchanged when sources are added.
- **Constraint:** F04 and F12 register sources by binding a composite reader (`CompositeBusyIntervalReader`) — never by editing `CandidateFinder`.

## B2. Metrics

| ID | Metric | SQL / source | Threshold |
| --- | --- | --- | --- |
| M-03.1 | `find()` time in a benchmark of 30 technicians × 20 bays × 36 slots | TC-03-21 | < 50 ms total |
| M-03.2 | 409s by reason (after F04, F08) | `SELECT detail->>'reason', count(*) FROM api_events WHERE code = 'NO_AVAILABILITY' AND occurred_at > now() - interval '7 days' GROUP BY 1;` | Watch; any `NO_QUALIFIED_TECHNICIAN` means master data is missing something (M-01.1) |
| M-03.3 | Spread of booked minutes between technicians with the same skill in a day | `SELECT resource_id, sum(upper(period)-lower(period)) FROM resource_allocations WHERE resource_type='TECHNICIAN' AND active AND lower(period)::date = :d GROUP BY 1;` | Watch |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-03.1 | Other ranking rules (load balancing, regular customers) | `AllocationStrategy` |
| FC-03.2 | SDD phase 2: `pg_advisory_xact_lock` per dealership + day before picking a pair | In F04 `AppointmentWriter`, not F03 |
| FC-03.3 | Lunch breaks, several opening intervals | `OpeningHours::windowFor` returns a list |
| FC-03.4 | Absences, bay outages (F12) | `BusyIntervalReader` |
