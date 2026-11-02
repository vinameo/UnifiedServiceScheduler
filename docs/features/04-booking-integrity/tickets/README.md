# F04 · No double booking, no duplicate requests · Tickets

Branch: `feature/04-booking-integrity`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F03 [10-acceptance-and-performance](../../03-slot-allocation/tickets/10-acceptance-and-performance.md) (the whole F03 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: overlap assertion, parallel runner, testing routes](01-prefactor-overlap-assertion-parallel-runner-testing-routes.md) | see above | — |
| 02 | [Appointment enums and the status rules](02-appointment-enums-and-the-status-rules.md) | 01 | TC-04-15 |
| 03 | [Appointments table, reference sequence and version trigger](03-appointments-table-reference-sequence-and-version-trigger.md) | 02 | TC-04-13 |
| 04 | [Resource allocations with the EXCLUDE constraint](04-resource-allocations-with-the-exclude-constraint.md) | 03 | TC-04-27, TC-04-31 |
| 05 | [Idempotency keys, appointment events and outbox tables](05-idempotency-keys-appointment-events-and-outbox-tables.md) | 04 | TC-04-30 |
| 06 | [AppointmentWriter::create through a testing route (tracer bullet)](06-appointmentwriter-create-through-a-testing-route.md) | 05 | TC-04-01, TC-04-02, TC-04-09, TC-04-34 |
| 07 | [Conflict mapping and pair retries](07-conflict-mapping-and-pair-retries.md) | 06 | TC-04-08, TC-04-10, TC-04-11, TC-04-29, TC-04-41 |
| 08 | [Disabled technicians and the real upcoming-assignments reader](08-disabled-technicians-and-the-real-upcoming-assignments.md) | 07 | TC-04-42, TC-01-17, TC-01-18, TC-01-19 |
| 09 | [Versioned updates and If-Match](09-versioned-updates-and-if-match.md) | 08 | TC-04-06, TC-04-07, TC-04-22 |
| 10 | [TransitionAppointment and appointment history](10-transitionappointment-and-appointment-history.md) | 09 | TC-04-14, TC-04-26, TC-04-33 |
| 11 | [Idempotency middleware: replay, mismatch, in progress](11-idempotency-middleware-replay-mismatch-in-progress.md) | 10 | TC-04-03, TC-04-04, TC-04-05, TC-04-12, TC-04-16, TC-04-24 |
| 12 | [Idempotency failure paths and header checks](12-idempotency-failure-paths-and-header-checks.md) | 11 | TC-04-17, TC-04-18, TC-04-21, TC-04-23 |
| 13 | [Idempotency key pruning and no testing routes in production](13-idempotency-key-pruning-and-no-testing-routes-in-production.md) | 12 | TC-04-19, TC-04-28 |
| 14 | [Outbox writer in the same transaction](14-outbox-writer-in-the-same-transaction.md) | 13 | TC-04-20, TC-04-25 |
| 15 | [AppointmentWriter::moveTo](15-appointmentwriter-moveto.md) | 14 | TC-04-32 |
| 16 | [Web: useIdempotencyKey](16-web-useidempotencykey.md) | 15 | TC-04-35 |
| 17 | [Web: SubmitButton and IdempotentRetryPanel](17-web-submitbutton-and-idempotentretrypanel.md) | 16 | TC-04-36, TC-04-37, TC-04-39 |
| 18 | [Web: AppointmentModifiedAlert](18-web-appointmentmodifiedalert.md) | 17 | TC-04-38, TC-04-40 |
| 19 | [Acceptance](19-acceptance.md) | 18 | — |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
This feature also writes TC-01-17, TC-01-18, TC-01-19 from another feature's test-case file.
