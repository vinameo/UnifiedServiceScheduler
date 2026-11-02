# 08: Extend retries on a race

**What to build:** When data changes under an extend, it retries once with fresh data and succeeds or answers 409, never a partial change (AC-12.8, INV-12.4).

**Blocked by:** [07-extend-displaces-the-next-booking](07-extend-displaces-the-next-booking.md)

**Status:** ready-for-agent

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-10

- [ ] TC-12-10 (AC-12.8, INV-12.4; Integration): given As TC-12-04; a test hook changes Y (version bump) right after the neighbour read on the first attempt; when Extend; then Attempt 2 succeeds with fresh data (200); never a state where X is extended but Y still holds K1. Test: `…::test_AC_12_8_neighbour_change_triggers_one_retry`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
