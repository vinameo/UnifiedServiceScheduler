# 10: Expire and confirm race

**What to build:** `holds:expire` and confirm released together end either `CONFIRMED` or `EXPIRED`, never both; the loser gets 412 (AC-10.3, INV-10.3).

**Blocked by:** [09-free-expired-holds-in-the-booking-flow](09-free-expired-holds-in-the-booking-flow.md)

**Status:** ready-for-agent

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-03

- [ ] TC-10-03 (AC-10.3, INV-10.3; Concurrency (`@group slow`)): given 100 holds (half with a vehicle, half without), each with `expires_at` = T; when For each, process 1 runs `holds:expire --now=T+1s`, process 2 confirms at T − 300 ms, released together; then Each booking ends `CONFIRMED` (3 active allocations) or `EXPIRED` (0 active); never mixed; the losing confirm got 412 (status `EXPIRED`); across the 100 runs **both** outcomes occur at least once (otherwise the test did not race). Test: `ParallelHoldExpiryTest::test_AC_10_3_job_and_confirm_never_both_win`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
