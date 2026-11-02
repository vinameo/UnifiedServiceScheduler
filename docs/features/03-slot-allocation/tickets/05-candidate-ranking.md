# 05: Candidate ranking

**What to build:** Pairs are ranked lowest qualifying level first, then fewer booked minutes, then id, so the same input always gives the same order whatever the input order (AC-03.7, AC-03.9, INV-03.3, D-03.2, D-03.3, D-03.5).

**Blocked by:** [04-technician-level-qualification](04-technician-level-qualification.md)

**Status:** ready-for-agent

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-07, TC-03-09, TC-03-10, TC-03-15, TC-03-16, TC-03-24, TC-03-28

- [ ] TC-03-07 (AC-03.7; Unit): given An (2) and Em (6), both free, 0 booked minutes; when Search OIL (level 1); then First pair is An. Test: `LowestLevelFirstStrategyTest::test_AC_03_7_lowest_level_ranks_first`.
- [ ] TC-03-09 (AC-03.9; Unit): given Dũng and Chi both level 3; Dũng booked 120 min that day, Chi 60; when Search OIL 15:00–16:00; then Chi before Dũng. Test: `LowestLevelFirstStrategyTest::test_AC_03_9_fewer_booked_minutes_ranks_first`.
- [ ] TC-03-10 (AC-03.9, INV-03.3; Unit): given Two technicians identical except id; when Search twice, second time with the input array reversed; then Equal results; smaller id (strcmp) first. Test: `…::test_INV_03_3_same_input_same_order`.
- [ ] TC-03-15 (D-03.10, D-03.5; Unit): given An (2) free; Em (6) busy; when Search MAINT_20K (level 3); then `NO_TECHNICIAN_AVAILABLE` (Em qualifies but is busy; An does not qualify). Test: `…::test_D_03_10_free_but_too_junior_is_not_used`.
- [ ] TC-03-16 (D-03.5; Unit): given Every technician and bay busy; when Search; then `NO_TECHNICIAN_AVAILABLE`. Test: `…::test_D_03_5_both_busy_reports_technician_first`.
- [ ] TC-03-24 (D-03.2, D-03.3, BR-03.2; Unit): given 3 technicians T1<T2<T3, 2 bays K1<K2, all free; when Search; then Exactly 5 pairs: (T1,K1),(T1,K2),(T2,K1),(T2,K2),(T3,K1). Test: `…::test_D_03_3_pairs_order_and_limit`.
- [ ] TC-03-28 (D-03.3; Unit): given 2 technicians T1<T2 (both qualify for OIL), 5 QUICK bays B1<…<B5, all free; when Search; then Pairs (T1,B1),(T1,B2),(T2,B1),(T1,B3),(T2,B2); both technicians in the first 5. Test: `CandidateFinderTest::test_D_03_3_first_pairs_spread_over_technicians`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
