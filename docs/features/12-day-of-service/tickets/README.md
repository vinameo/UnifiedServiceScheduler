# F12 · Day-of-service operations · Tickets

Branch: `feature/12-day-of-service`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F11 [11-acceptance](../../11-outbox-notifications/tickets/11-acceptance.md) (the whole F11 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: ResourceSnapshot::withBusy and AppointmentWriter::displace](01-prefactor-resourcesnapshot-withbusy-and-appointmentwriter.md) | see above | — |
| 02 | [Check in, start and complete (tracer bullet)](02-check-in-start-and-complete.md) | 01 | TC-12-01, TC-12-07, TC-12-25 |
| 03 | [Check-in rules](03-check-in-rules.md) | 02 | TC-12-02, TC-12-05, TC-12-27 |
| 04 | [No-show](04-no-show.md) | 03 | TC-12-06 |
| 05 | [Permissions per workshop action](05-permissions-per-workshop-action.md) | 04 | TC-12-22 |
| 06 | [Extend an appointment](06-extend-an-appointment.md) | 05 | TC-12-08, TC-12-11, TC-12-28 |
| 07 | [Extend displaces the next booking](07-extend-displaces-the-next-booking.md) | 06 | TC-12-04, TC-12-09 |
| 08 | [Extend retries on a race](08-extend-retries-on-a-race.md) | 07 | TC-12-10 |
| 09 | [Workshop board API](09-workshop-board-api.md) | 08 | TC-12-16, TC-12-17 |
| 10 | [Workshop board screen](10-workshop-board-screen.md) | 09 | TC-12-24 |
| 11 | [Bookings that need contact](11-bookings-that-need-contact.md) | 10 | TC-12-20, TC-12-21 |
| 12 | [Acceptance](12-acceptance.md) | 11 | TC-12-23, TC-12-26 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
