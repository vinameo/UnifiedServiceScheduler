# Design Notes: 08-acceptance

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| TC-07-20: M1–M6 manual scenario verification and recording in `6-test-case.md`. All automated test cases TC-07-01 to TC-07-19 green. | TC-07-20, AC-07.1 to AC-07.13 | `api/tests/Feature/Booking/Cancel/`, `web/src/` | Validated end-to-end against database state, UI components, and API responses. | Resource allocations deactivated upon cancellation across M1–M6 scenarios. | Conforms to feature acceptance conventions. | follow |
| BA §B2: Metrics M-07.1 (cancellations by reason/channel), M-07.2 (blocked for too late), M-07.3 (cancelled slots rebooked). | BA §B2 (M-07.1–M-07.3) | `appointments`, `api_events`, `resource_allocations` | Read-only analytics queries over PostgreSQL. | Inactive rows included in cancellation metrics via status and timestamps. | Standard operational metrics per BA spec. | follow |

