# 04: Technician level qualification

**What to build:** Only active technicians with `level ≥ required level` qualify (D-G.16, D-03.10). When nobody at the dealership qualifies the reason is `NO_QUALIFIED_TECHNICIAN` (AC-03.8, AC-03.10).

**Blocked by:** [03-failure-reasons-when-resources-are-taken](03-failure-reasons-when-resources-are-taken.md)

**Status:** done

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-06, TC-03-08, TC-03-12, TC-03-14

- [x] TC-03-06 (AC-03.6, D-03.10; Unit): given An (2), Dũng (3), Em (6), all free; when Search MAINT_20K (level 3); then Candidates Dũng and Em; An never in a pair. Test: `…::test_D_03_10_level_at_or_above_qualifies`.
- [x] TC-03-08 (AC-03.8; Unit): given An (2), Dũng (3), Chi (4), all free; KEV free; when Search EV_BATTERY (level 5); then `NO_QUALIFIED_TECHNICIAN`. Test: `CandidateFinderTest::test_AC_03_8_no_high_enough_level_gives_no_qualified_technician`.
- [x] TC-03-12 (AC-03.10; Unit): given Em (level 6, the only one ≥ 5) `active=false`; KEV free; when Search EV_BATTERY; then `NO_QUALIFIED_TECHNICIAN`. Test: `CandidateFinderTest::test_AC_03_10_inactive_technician_is_not_qualified`.
- [x] TC-03-14 (D-03.10; Unit): given Only Chi (level 4), free; when Search a level-4 service; then Chi is a candidate. Test: `…::test_D_03_10_equal_level_qualifies`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
