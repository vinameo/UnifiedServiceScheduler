# F11 · Notifications through the outbox · Tickets

Branch: `feature/11-outbox-notifications`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F10 [11-acceptance](../../10-temporary-hold/tickets/11-acceptance.md) (the whole F10 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: notification channels and recipient resolver](01-prefactor-notification-channels-and-recipient-resolver.md) | see above | TC-11-03 |
| 02 | [Outbox relay sends pending messages (tracer bullet)](02-outbox-relay-sends-pending-messages.md) | 01 | TC-11-01, TC-11-02 |
| 03 | [DMS webhook channel](03-dms-webhook-channel.md) | 02 | TC-11-06 |
| 04 | [Back-off and FAILED](04-back-off-and-failed.md) | 03 | TC-11-04, TC-11-18 |
| 05 | [Skip messages that are no longer relevant](05-skip-messages-that-are-no-longer-relevant.md) | 04 | TC-11-09 |
| 06 | [Leases and parallel relays](06-leases-and-parallel-relays.md) | 05 | TC-11-07, TC-11-08 |
| 07 | [Appointment reminders](07-appointment-reminders.md) | 06 | TC-11-10, TC-11-11 |
| 08 | [Outbox list and summary API](08-outbox-list-and-summary-api.md) | 07 | TC-11-12 |
| 09 | [Resend a failed message](09-resend-a-failed-message.md) | 08 | TC-11-05, TC-11-13, TC-11-16 |
| 10 | [Outgoing notifications screen](10-outgoing-notifications-screen.md) | 09 | TC-11-14, TC-11-15 |
| 11 | [Acceptance](11-acceptance.md) | 10 | TC-11-17 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
