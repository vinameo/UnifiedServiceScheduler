# F07 · Cancel an appointment · Tickets

Branch: `feature/07-cancel-appointment`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F06 [10-acceptance-and-performance](../../06-alternative-times/tickets/10-acceptance-and-performance.md) (the whole F06 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: CustomerChangePolicy](01-prefactor-customerchangepolicy.md) | see above | — |
| 02 | [Advisor cancels an appointment (tracer bullet)](02-advisor-cancels-an-appointment.md) | 01 | TC-07-01, TC-07-05, TC-07-15 |
| 03 | [Cancel rules for staff](03-cancel-rules-for-staff.md) | 02 | TC-07-02, TC-07-03, TC-07-06, TC-07-08 |
| 04 | [Versions, history and replay on cancel](04-versions-history-and-replay-on-cancel.md) | 03 | TC-07-11, TC-07-12, TC-07-14, TC-07-19 |
| 05 | [Customer cancels (API)](05-customer-cancels-api.md) | 04 | TC-07-04, TC-07-07, TC-07-10, TC-07-13, TC-07-18 |
| 06 | [Customer cancel sheet and banners](06-customer-cancel-sheet-and-banners.md) | 05 | TC-07-16 |
| 07 | [Parallel cancels and adversarial input](07-parallel-cancels-and-adversarial-input.md) | 06 | TC-07-09, TC-07-17 |
| 08 | [Acceptance](08-acceptance.md) | 07 | TC-07-20 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
