# F06 · Suggest other free times · Tickets

Branch: `feature/06-alternative-times`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F05 [10-acceptance](../../05-view-appointments/tickets/10-acceptance.md) (the whole F05 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: TimeGrid takes chip state from props](01-prefactor-timegrid-takes-chip-state-from-props.md) | see above | — |
| 02 | [Free times for one day on New booking (tracer bullet)](02-free-times-for-one-day-on-new-booking.md) | 01 | TC-06-01, TC-06-02, TC-06-14 |
| 03 | [Availability edge cases](03-availability-edge-cases.md) | 02 | TC-06-04, TC-06-05, TC-06-06, TC-06-10, TC-06-18 |
| 04 | [AlternativeTimesFinder::near](04-alternativetimesfinder-near.md) | 03 | TC-06-09 |
| 05 | [Alternatives on 409 NO_AVAILABILITY](05-alternatives-on-409-no-availability.md) | 04 | TC-06-03, TC-06-07, TC-06-08, TC-06-19 |
| 06 | [Suggestion chips in the error card](06-suggestion-chips-in-the-error-card.md) | 05 | TC-06-16 |
| 07 | [Next available time](07-next-available-time.md) | 06 | TC-06-12, TC-06-21 |
| 08 | [Free-slot counts and fully booked day for customers](08-free-slot-counts-and-fully-booked-day-for-customers.md) | 07 | TC-06-15 |
| 09 | [Ignore an appointment's own slot](09-ignore-an-appointment-s-own-slot.md) | 08 | TC-06-11, TC-06-17 |
| 10 | [Acceptance and performance](10-acceptance-and-performance.md) | 09 | TC-06-13, TC-06-20 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
