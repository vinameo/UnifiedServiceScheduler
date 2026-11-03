# 07: Pluggable AllocationStrategy

**What to build:** Ranking sits behind the `AllocationStrategy` interface with `LowestLevelFirstStrategy` as the default, so another strategy can replace it without changing `CandidateFinder` (INV-03.8, FR-03.5).

**Blocked by:** [06-exclude-and-preferred-technician-or-bay](06-exclude-and-preferred-technician-or-bay.md)

**Status:** done

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-27

- [x] TC-03-27 (INV-03.8, FR-03.5; Unit): given A fake strategy that reverses the order; when `CandidateFinder` with that strategy; then Pair order is reversed too. Test: `CandidateFinderTest::test_INV_03_8_ranking_comes_from_the_strategy`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
