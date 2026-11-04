# 07: Parallel cancels and adversarial input

**What to build:** 5 parallel cancels with the same version all end `CANCELLED` with the version raised once, one history row and one outbox event (AC-07.9). The adversarial input table of API §B4 is covered.

**Blocked by:** [06-customer-cancel-sheet-and-banners](06-customer-cancel-sheet-and-banners.md)

**Status:** done

**Branch:** `feature/07-cancel-appointment`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-07-09, TC-07-17

- [x] TC-07-09 (AC-07.9; Concurrency): given Booking v1; when 5 processes cancel with `If-Match: "1"`, different keys; then 5 × 200 `CANCELLED`; `version` = 2; 1 `CANCELLED` history row; 1 outbox `event_id`. Test: `ParallelCancelTest::test_AC_07_9_parallel_cancels_all_succeed_once`.
- [x] TC-07-17 (API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `CancelAdversarialInputTest`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
