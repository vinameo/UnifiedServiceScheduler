# 04: AlternativeTimesFinder::near

**What to build:** `AlternativeTimesFinder::near()` finds free times close to the wanted one, earliest first (AC-06.9).

**Blocked by:** [03-availability-edge-cases](03-availability-edge-cases.md)

**Status:** ready-for-agent

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-09

- [ ] TC-06-09 (AC-06.9; Unit): given Free slots 08:00, 10:00; wanted 09:00; when `near`; then [08:00, 10:00]. Test: `api/tests/Unit/Booking/AlternativeOrderTest::test_AC_06_9_ties_prefer_earlier`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
