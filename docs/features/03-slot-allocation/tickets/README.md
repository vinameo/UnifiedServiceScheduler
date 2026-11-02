# F03 · Free slots and resource allocation · Tickets

Branch: `feature/03-slot-allocation`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F01 [11-acceptance](../../01-master-data/tickets/11-acceptance.md) (the whole F01 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: TimeRange, SnapshotBuilder and the Allocation purity scan](01-prefactor-timerange-snapshotbuilder-and-the-allocation.md) | see above | TC-03-11, TC-03-22 |
| 02 | [CandidateFinder: busy and free checks (tracer bullet)](02-candidatefinder-busy-and-free-checks.md) | 01 | TC-03-01, TC-03-02, TC-03-03, TC-03-23 |
| 03 | [Failure reasons when resources are taken](03-failure-reasons-when-resources-are-taken.md) | 02 | TC-03-04, TC-03-05, TC-03-13 |
| 04 | [Technician level qualification](04-technician-level-qualification.md) | 03 | TC-03-06, TC-03-08, TC-03-12, TC-03-14 |
| 05 | [Candidate ranking](05-candidate-ranking.md) | 04 | TC-03-07, TC-03-09, TC-03-10, TC-03-15, TC-03-16, TC-03-24, TC-03-28 |
| 06 | [Exclude and preferred technician or bay](06-exclude-and-preferred-technician-or-bay.md) | 05 | TC-03-17, TC-03-18 |
| 07 | [Pluggable AllocationStrategy](07-pluggable-allocationstrategy.md) | 06 | TC-03-27 |
| 08 | [OpeningHours](08-openinghours.md) | 07 | TC-03-20, TC-03-25, TC-03-26 |
| 09 | [Snapshot repository for one day](09-snapshot-repository-for-one-day.md) | 08 | TC-03-19, TC-03-29 |
| 10 | [Acceptance and performance](10-acceptance-and-performance.md) | 09 | TC-03-21 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
