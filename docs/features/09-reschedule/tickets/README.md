# F09 · Reschedule · Tickets

Branch: `feature/09-reschedule`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F08 [11-acceptance](../../08-monitoring/tickets/11-acceptance.md) (the whole F08 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: confirm moveTo and excludeAppointmentId are reusable](01-prefactor-confirm-moveto-and-excludeappointmentid-are.md) | see above | — |
| 02 | [Advisor reschedules an appointment (tracer bullet)](02-advisor-reschedules-an-appointment.md) | 01 | TC-09-01, TC-09-02, TC-09-15, TC-09-16 |
| 03 | [Choosing the new pair](03-choosing-the-new-pair.md) | 02 | TC-09-04, TC-09-05, TC-09-06, TC-09-08 |
| 04 | [Version and retry rules on reschedule](04-version-and-retry-rules-on-reschedule.md) | 03 | TC-09-07, TC-09-22 |
| 05 | [Reschedule rules](05-reschedule-rules.md) | 04 | TC-09-09, TC-09-11, TC-09-13 |
| 06 | [Customer reschedules (API)](06-customer-reschedules-api.md) | 05 | TC-09-03, TC-09-10, TC-09-19 |
| 07 | [Reschedule existing booking from the F02 card](07-reschedule-existing-booking-from-the-f02-card.md) | 06 | TC-09-20 |
| 08 | [Customer Change time screen](08-customer-change-time-screen.md) | 07 | TC-09-17, TC-09-18 |
| 09 | [Parallel changes and adversarial input](09-parallel-changes-and-adversarial-input.md) | 08 | TC-09-12, TC-09-14, TC-09-21 |
| 10 | [Acceptance](10-acceptance.md) | 09 | TC-09-23 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
