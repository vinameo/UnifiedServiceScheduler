# F05 · View appointments · Tickets

Branch: `feature/05-view-appointments`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F02 [12-acceptance-and-performance](../../02-create-booking/tickets/12-acceptance-and-performance.md) (the whole F02 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: visible-appointment lookup, route binding, cursor paging](01-prefactor-visible-appointment-lookup-route-binding-cursor.md) | see above | — |
| 02 | [Appointment details for staff (tracer bullet)](02-appointment-details-for-staff.md) | 01 | TC-05-01, TC-05-03, TC-05-04, TC-05-05, TC-05-16 |
| 03 | [Appointment history and closed details](03-appointment-history-and-closed-details.md) | 02 | TC-05-13, TC-05-15 |
| 04 | [Staff day list API](04-staff-day-list-api.md) | 03 | TC-05-02, TC-05-07, TC-05-08, TC-05-09, TC-05-10, TC-05-12 |
| 05 | [Appointment list screen](05-appointment-list-screen.md) | 04 | TC-05-14 |
| 06 | [My bookings API](06-my-bookings-api.md) | 05 | TC-05-06 |
| 07 | [My bookings and customer details screens](07-my-bookings-and-customer-details-screens.md) | 06 | TC-05-18 |
| 08 | [Links between screens](08-links-between-screens.md) | 07 | TC-05-17 |
| 09 | [Access and adversarial sweep](09-access-and-adversarial-sweep.md) | 08 | TC-05-11, TC-05-20 |
| 10 | [Acceptance](10-acceptance.md) | 09 | TC-05-19 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
