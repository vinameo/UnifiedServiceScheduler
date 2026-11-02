# 04: Back-off and FAILED

**What to build:** A failing send is retried with waits of 10/20/40/80 s and becomes `FAILED` with `last_error` and an `api_events` row after 5 attempts (AC-11.6, INV-11.4).

**Blocked by:** [03-dms-webhook-channel](03-dms-webhook-channel.md)

**Status:** ready-for-agent

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-04, TC-11-18

- [ ] TC-11-04 (AC-11.6, INV-11.4; Integration): given One SMS row; `FailingChannel`; when `runOnce` at t, then `travel` to each `next_attempt_at` and run again, 5 times; then `attempts` 1…5; `next_attempt_at` gaps 10/20/40/80 s; after the 5th: `FAILED`, `last_error` set (redacted), 1 `api_events` row `DELIVERY_FAILED`. Test: `…::test_AC_11_6_backoff_then_failed`.
- [ ] TC-11-18 (INV-11.4; Integration): given One SMS row; the channel throws an `Error` that is not `DeliveryFailed` and the test aborts the run each time (simulated crash, no result recorded); when `runOnce` + `travel(61)->seconds()`, 6 times; then `attempts` stops at 5; after the 5th lease expires the row is `FAILED`, `last_error 'NO_RESULT'`, one `DELIVERY_FAILED` event; the claim never sees it again. Test: `OutboxRelayTest::test_INV_11_4_abandoned_attempts_end_in_failed`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
