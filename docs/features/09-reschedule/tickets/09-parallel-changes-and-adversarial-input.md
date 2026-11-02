# 09: Parallel changes and adversarial input

**What to build:** A cancel and a reschedule on the same version give one 200 and one 412 (AC-09.10); two parallel reschedules give one winner (INV-09.2). The adversarial input table of API §B4 is covered.

**Blocked by:** [08-customer-change-time-screen](08-customer-change-time-screen.md)

**Status:** ready-for-agent

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-12, TC-09-14, TC-09-21

- [ ] TC-09-12 (AC-09.10, AC-04.6; Concurrency): given X v3; when Process 1 cancels, process 2 reschedules, both `If-Match "3"` at the same moment; then One 200, one 412; final `version` 4; status either `CANCELLED` (allocations inactive) or `CONFIRMED` at the new time; overlap 0. Test: `ParallelRescheduleTest::test_AC_04_6_cancel_and_reschedule_on_the_same_version`.
- [ ] TC-09-14 (INV-09.2; Concurrency): given X v1; when Two processes reschedule to different free times with `If-Match "1"`; then One 200, one 412; final version 2. Test: `ParallelRescheduleTest::test_two_reschedules_one_wins`.
- [ ] TC-09-21 (API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `RescheduleAdversarialInputTest`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
