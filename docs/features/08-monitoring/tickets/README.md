# F08 · Monitoring · Tickets

Branch: `feature/08-monitoring`. Implement one ticket at a time with the `implement-ticket` skill.

Tickets form one chain: each ticket is blocked by the one before it, never run in parallel, so no two tickets edit the same file at once. Ticket 01 is blocked by: F07 [08-acceptance](../../07-cancel-appointment/tickets/08-acceptance.md) (the whole F07 branch merged to `develop`).

| # | Ticket | Blocked by | Test cases |
| --- | --- | --- | --- |
| 01 | [Prefactor: personal-data redaction processor](01-prefactor-personal-data-redaction-processor.md) | see above | TC-08-14 |
| 02 | [JSON logs with context and correlation id](02-json-logs-with-context-and-correlation-id.md) | 01 | TC-08-01, TC-08-05 |
| 03 | [Request outcome counters and /metrics (tracer bullet)](03-request-outcome-counters-and-metrics.md) | 02 | TC-08-02, TC-08-06, TC-08-15 |
| 04 | [Keep /metrics internal](04-keep-metrics-internal.md) | 03 | TC-08-07, TC-08-08 |
| 05 | [Readiness check](05-readiness-check.md) | 04 | TC-08-03 |
| 06 | [API events](06-api-events.md) | 05 | TC-08-16 |
| 07 | [Monitoring summary and errors API](07-monitoring-summary-and-errors-api.md) | 06 | TC-08-04, TC-08-09, TC-08-10, TC-08-17 |
| 08 | [Prune monitoring data](08-prune-monitoring-data.md) | 07 | TC-08-12 |
| 09 | [System monitoring screen](09-system-monitoring-screen.md) | 08 | TC-08-13 |
| 10 | [Tracing](10-tracing.md) | 09 | TC-08-11 |
| 11 | [Acceptance](11-acceptance.md) | 10 | TC-08-18 |

## Coverage

Every `TC-` row of [6-test-case.md](../6-test-case.md) belongs to exactly one ticket.
