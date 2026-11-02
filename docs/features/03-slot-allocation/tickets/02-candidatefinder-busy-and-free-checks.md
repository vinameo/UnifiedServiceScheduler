# 02: CandidateFinder: busy and free checks (tracer bullet)

**What to build:** `CandidateFinder::find()` returns candidate technician + bay pairs for a time range, removing technicians and bays that are busy at any part of it (AC-03.1–03.3). Bad input (empty range, `maxPairs: 0`, a snapshot of another dealership) is refused (INV-03.4).

**Blocked by:** [01-prefactor-timerange-snapshotbuilder-and-the-allocation](01-prefactor-timerange-snapshotbuilder-and-the-allocation.md)

**Status:** ready-for-agent

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-01, TC-03-02, TC-03-03, TC-03-23

- [ ] TC-03-01 (AC-03.1; Unit): given An (level 2) busy 09:00–10:00; Bay K1 free; when Search OIL 10:00–11:00; then First pair has An. Test: `CandidateFinderTest::test_AC_03_1_back_to_back_is_not_an_overlap`.
- [ ] TC-03-02 (AC-03.2; Unit): given An busy 09:00–10:00; when Search 09:30–10:30; then No pair has An. Test: `…::test_AC_03_2_partial_overlap_removes_technician`.
- [ ] TC-03-03 (AC-03.3; Unit): given An busy 08:00–12:00; when Search 09:00–10:00; then An removed. Test: `…::test_AC_03_3_range_inside_busy_removes_technician`.
- [ ] TC-03-23 (INV-03.4, API §B3; Unit): when `TimeRange(10:00, 10:00)`; `maxPairs: 0`; snapshot of another dealership; snapshot of another day; then Each throws `InvalidArgumentException`. Test: `CandidateFinderTest::test_invalid_requests_throw` (data provider).
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
- [ ] Run `/code-review` on the branch and fix findings before later tickets build on it
