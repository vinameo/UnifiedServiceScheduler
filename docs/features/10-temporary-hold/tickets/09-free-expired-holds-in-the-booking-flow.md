# 09: Free expired holds in the booking flow

**What to build:** Wire `ExpiredHoldReleaser` into F02 create and F09 reschedule, so an expired hold that blocks the wanted time is expired on the way and the new booking succeeds (AC-10.9).

**Blocked by:** [08-holds-expire-job](08-holds-expire-job.md)

**Status:** ready-for-agent

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-12

- [ ] TC-10-12 (AC-10.9; Integration): given Expired hold (job not run) holding An, the only technician, 10:00; when Customer B books 10:00; then 201; the old hold `EXPIRED`. Test: `ExpiredHoldReleaserTest::test_AC_10_9_booking_flow_frees_expired_holds`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
