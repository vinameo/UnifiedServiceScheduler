# 03: Failure reasons when resources are taken

**What to build:** When no pair is left, the result carries `NO_TECHNICIAN_AVAILABLE` or `NO_BAY_AVAILABLE` per the rule in RQ §B7 (AC-03.4, AC-03.5, AC-03.11).

**Blocked by:** [02-candidatefinder-busy-and-free-checks](02-candidatefinder-busy-and-free-checks.md)

**Status:** ready-for-agent

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-04, TC-03-05, TC-03-13

- [ ] TC-03-04 (AC-03.4; Unit): given An (level 2), Dũng (level 3), the only technicians, both busy 09–10; K1 free; when Search 09:00–10:00; then `pairs = []`, `failure = NO_TECHNICIAN_AVAILABLE`. Test: `…::test_AC_03_4_all_qualified_busy_gives_no_technician_available`.
- [ ] TC-03-05 (AC-03.5; Unit): given An free; K1, K2 busy 09–10; when Search OIL 09:00–10:00; then `NO_BAY_AVAILABLE`. Test: `…::test_AC_03_5_all_bays_busy_gives_no_bay_available`.
- [ ] TC-03-13 (AC-03.11; Unit): given KEV `active=false`; Em free; when Search EV_BATTERY; then `NO_BAY_AVAILABLE`. Test: `…::test_AC_03_11_inactive_bay_is_not_available`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
