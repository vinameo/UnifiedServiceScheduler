# 06: Leases and parallel relays

**What to build:** The relay leases rows before sending so two relays never send the same row, and an expired lease is picked up again (INV-11.3).

**Blocked by:** [05-skip-messages-that-are-no-longer-relevant](05-skip-messages-that-are-no-longer-relevant.md)

**Status:** ready-for-agent

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-07, TC-11-08

- [ ] TC-11-07 (INV-11.3; Integration): given A row claimed (lease set) and the "crash" simulated (never marked); when `travel(61)->seconds()`; `runOnce`; then The row is claimed again and sent (`attempts` 2). Test: `OutboxRelayTest::test_INV_11_3_expired_lease_is_retried`.
- [ ] TC-11-08 (INV-11.3; Concurrency): given 100 `PENDING` rows; a channel that records `eventId:channel` to a table; when 2 processes run `outbox:relay` at the same moment; then Every row sent exactly once (no duplicate records). Test: `ParallelRelayTest::test_two_relays_never_send_the_same_row`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
