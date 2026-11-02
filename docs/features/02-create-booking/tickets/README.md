# F02 · Create a booking · Tickets

Branch: `feature/02-create-booking`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F04 [19-acceptance](../../04-booking-integrity/tickets/19-acceptance.md) (the whole F04 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: BookingTimeRules and web time helpers](01-prefactor-bookingtimerules-and-web-time-helpers.md) | see above | TC-02-29 |
| 02 | [Advisor books an appointment (tracer bullet)](02-advisor-books-an-appointment.md) | 01 | TC-02-01, TC-02-08, TC-02-18, TC-02-22, TC-02-25 |
| 03 | [Parallel bookings and replays through the real endpoint](03-parallel-bookings-and-replays-through-the-real-endpoint.md) | 02 | TC-02-20, TC-02-21 |
| 04 | [Booking time rules on create](04-booking-time-rules-on-create.md) | 03 | TC-02-02, TC-02-03, TC-02-12, TC-02-13, TC-02-14, TC-02-15, TC-02-16 |
| 05 | [Customer, vehicle and service checks](05-customer-vehicle-and-service-checks.md) | 04 | TC-02-04, TC-02-06, TC-02-07, TC-02-11, TC-02-17, TC-02-31 |
| 06 | [Error cards on New booking](06-error-cards-on-new-booking.md) | 05 | TC-02-26, TC-02-28, TC-02-32 |
| 07 | [Customer search for staff](07-customer-search-for-staff.md) | 06 | TC-02-19, TC-02-23, TC-02-36 |
| 08 | [Customer's vehicles on New booking](08-customer-s-vehicles-on-new-booking.md) | 07 | TC-02-37 |
| 09 | [My vehicles](09-my-vehicles.md) | 08 | TC-02-09, TC-02-24 |
| 10 | [Customer booking API](10-customer-booking-api.md) | 09 | TC-02-05, TC-02-10, TC-02-35 |
| 11 | [Customer booking screens](11-customer-booking-screens.md) | 10 | TC-02-27, TC-02-34 |
| 12 | [Acceptance and performance](12-acceptance-and-performance.md) | 11 | TC-02-30, TC-02-33 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
