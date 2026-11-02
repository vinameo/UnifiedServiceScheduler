# 04: Version and retry rules on reschedule

**What to build:** A retry after `23P01` keeps the same expected version (D-09.10, INV-09.2), and resending the key after success replays the answer (SDD §13).

**Blocked by:** [03-choosing-the-new-pair](03-choosing-the-new-pair.md)

**Status:** ready-for-agent

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-07, TC-09-22

- [ ] TC-09-07 (D-09.10, INV-09.2; Integration): given All candidate technicians get conflicting allocations inserted by hand after the snapshot; when Reschedule; then 409 `NO_AVAILABILITY` `SLOT_TAKEN` after 3 tries with the same expected version; `alternatives` present; X unchanged. Test: `…::test_three_tries_then_slot_taken_with_alternatives`.
- [ ] TC-09-22 (SDD §13 "retry after success"; Integration): given Reschedule with K → 200 (v2); when Resend K with `If-Match "1"`; then The saved 200, `Idempotent-Replayed: true`. Test: `RescheduleTest::test_retry_after_success_replays`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
