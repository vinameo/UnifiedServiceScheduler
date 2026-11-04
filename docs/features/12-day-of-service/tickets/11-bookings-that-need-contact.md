# 11: Bookings that need contact

**What to build:** `GET /dealerships/{d}/appointments?contactRequired=true` lists flagged bookings without a date; rescheduling (F09) or cancelling (F07) clears the flag (INV-12.9, D-12.8, D-12.14).

**Blocked by:** [10-workshop-board-screen](10-workshop-board-screen.md)

**Status:** done

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-20, TC-12-21

- [x] TC-12-20 (INV-12.9, D-12.8; Integration): given A booking flagged `OVERRUN` by an extend; when Advisor reschedules it (F09); another flagged booking cancelled (F07); then Flag cleared in the same versioned write; history rows present. Test: `ContactFlagTest::test_flag_cleared_by_reschedule_and_cancel`.
- [x] TC-12-21 (D-12.14; Integration): given 2 flagged today, 1 flagged in 3 days, 1 flagged yesterday, 3 normal bookings; when `GET /dealerships/{d}/appointments?contactRequired=true` (no `date`); then The 3 flagged from today on, by start; `meta.total` 3. Test: `ContactFlagTest::test_contact_list_filter`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
