# F01 · Master data · Tickets

Branch: `feature/01-master-data`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F00 [11-acceptance](../../00-accounts-and-sign-in/tickets/11-acceptance.md) (the whole F00 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: empty World fixture, BayType and Level](01-prefactor-empty-world-fixture-baytype-and-level.md) | see above | — |
| 02 | [Schema: opening hours, service types, bays, technicians](02-schema-opening-hours-service-types-bays-technicians.md) | 01 | TC-01-10 |
| 03 | [Schema: vehicles](03-schema-vehicles.md) | 02 | TC-01-08 |
| 04 | [Master data seed and full World fixture](04-master-data-seed-and-full-world-fixture.md) | 03 | TC-01-09 |
| 05 | [Dealership route binding and service types (tracer bullet)](05-dealership-route-binding-and-service-types.md) | 04 | TC-01-01, TC-01-03, TC-01-04, TC-01-06, TC-01-11, TC-01-13 |
| 06 | [List dealerships](06-list-dealerships.md) | 05 | TC-01-07 |
| 07 | [Bays and technicians](07-bays-and-technicians.md) | 06 | TC-01-05 |
| 08 | [Dealership details and opening hours](08-dealership-details-and-opening-hours.md) | 07 | TC-01-02, TC-01-12, TC-01-14 |
| 09 | [Disable and enable a technician (API)](09-disable-and-enable-a-technician-api.md) | 08 | TC-01-16, TC-01-20 |
| 10 | [Disable and enable action on the Technicians tab](10-disable-and-enable-action-on-the-technicians-tab.md) | 09 | TC-01-21 |
| 11 | [Acceptance](11-acceptance.md) | 10 | TC-01-15 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
TC-01-17, TC-01-18, TC-01-19 are written in F04 [08-disabled-technicians-and-the-real-upcoming-assignments](../../04-booking-integrity/tickets/08-disabled-technicians-and-the-real-upcoming-assignments.md).
