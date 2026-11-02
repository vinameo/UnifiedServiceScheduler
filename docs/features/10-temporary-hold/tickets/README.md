# F10 · Temporary hold for self-booking · Tickets

Branch: `feature/10-temporary-hold`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F09 [10-acceptance](../../09-reschedule/tickets/10-acceptance.md) (the whole F09 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: ExpiredHoldReleaser and useServerClock](01-prefactor-expiredholdreleaser-and-useserverclock.md) | see above | TC-10-18 |
| 02 | [Customer holds a time and confirms it (tracer bullet)](02-customer-holds-a-time-and-confirms-it.md) | 01 | TC-10-01, TC-10-08, TC-10-14 |
| 03 | [Hold rules](03-hold-rules.md) | 02 | TC-10-04, TC-10-15, TC-10-19 |
| 04 | [Confirm rules](04-confirm-rules.md) | 03 | TC-10-02, TC-10-09, TC-10-10, TC-10-11 |
| 05 | [Expired hold screen](05-expired-hold-screen.md) | 04 | TC-10-16 |
| 06 | [One hold per customer](06-one-hold-per-customer.md) | 05 | TC-10-05, TC-10-06, TC-10-17 |
| 07 | [Parallel holds by one customer](07-parallel-holds-by-one-customer.md) | 06 | TC-10-07, TC-10-21 |
| 08 | [holds:expire job](08-holds-expire-job.md) | 07 | TC-10-13 |
| 09 | [Free expired holds in the booking flow](09-free-expired-holds-in-the-booking-flow.md) | 08 | TC-10-12 |
| 10 | [Expire and confirm race](10-expire-and-confirm-race.md) | 09 | TC-10-03 |
| 11 | [Acceptance](11-acceptance.md) | 10 | TC-10-20 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
