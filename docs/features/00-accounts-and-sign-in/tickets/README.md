# F00 · Accounts and sign-in · Tickets

Branch: `feature/00-accounts-and-sign-in`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: None (can start immediately).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: Role enum, empty Gates, factories, axios mock](01-prefactor-role-enum-empty-gates-factories-axios-mock.md) | see above | TC-00-27 |
| 02 | [Accounts schema: dealerships, users role and dealership, customers](02-accounts-schema-dealerships-users-role-and-dealership.md) | 01 | TC-00-14 |
| 03 | [Seed dealership, staff and demo customers](03-seed-dealership-staff-and-demo-customers.md) | 02 | TC-00-15, TC-00-16 |
| 04 | [Staff sign-in, GET /me and the Staff sign-in screen (tracer bullet)](04-staff-sign-in-get-me-and-the-staff-sign-in-screen.md) | 03 | TC-00-07, TC-00-18, TC-00-26 |
| 05 | [RequireRole route guard and safe returnTo](05-requirerole-route-guard-and-safe-returnto.md) | 04 | TC-00-24 |
| 06 | [Staff header and menu](06-staff-header-and-menu.md) | 05 | TC-00-25 |
| 07 | [Customer sign-up](07-customer-sign-up.md) | 06 | TC-00-01, TC-00-02, TC-00-03, TC-00-04, TC-00-05, TC-00-17, TC-00-22, TC-00-31, TC-00-34 |
| 08 | [Customer sign-in](08-customer-sign-in.md) | 07 | TC-00-06, TC-00-08, TC-00-20, TC-00-21, TC-00-23, TC-00-30 |
| 09 | [Sign-in rate limits](09-sign-in-rate-limits.md) | 08 | TC-00-09, TC-00-10, TC-00-11, TC-00-19, TC-00-29, TC-00-32, TC-00-33 |
| 10 | [Sign-out, global 401 handling and role gates](10-sign-out-global-401-handling-and-role-gates.md) | 09 | TC-00-12, TC-00-13 |
| 11 | [Acceptance](11-acceptance.md) | 10 | TC-00-28 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
