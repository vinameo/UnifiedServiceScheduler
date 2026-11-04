# 08: Outbox list and summary API

**What to build:** Admin `GET /outbox` and `/outbox/summary` show only their dealership's rows and counts by state; another dealership's row is 404 (AC-11.9, INV-11.8).

**Blocked by:** [07-appointment-reminders](07-appointment-reminders.md)

**Status:** done

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-12

- [x] TC-11-12 (AC-11.9, INV-11.8; Integration): given Rows of D1 and D2; admin of D1; when List; summary; retry a D2 row; then Only D1 rows; counts by state (`retrying` = PENDING with attempts > 0); 404 `OUTBOX_MESSAGE_NOT_FOUND`. Test: `OutboxAdminTest::test_AC_11_9_admin_sees_own_dealership`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
