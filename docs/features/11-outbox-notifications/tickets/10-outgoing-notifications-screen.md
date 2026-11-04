# 10: Outgoing notifications screen

**What to build:** The admin's Outgoing notifications screen filters by display status, resends failed rows and updates the counts; links lead to it from System monitoring and appointment details (AC-11.10, D-11.9).

**Blocked by:** [09-resend-a-failed-message](09-resend-a-failed-message.md)

**Status:** done

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-14, TC-11-15

- [x] TC-11-14 (AC-11.10; Web component): given Fake API: summary, a FAILED row, retry 200; when Click "Failed, needs action", then "Resend"; then The filter becomes `status=FAILED`; the retry request has an `Idempotency-Key`; summary and list refetched. Test: `web/src/staff/notifications/NotificationsPage.test.tsx`.
- [x] TC-11-15 (D-11.9; Unit): given Rows with (PENDING, 0), (PENDING, 2), FAILED, SENT, SKIPPED; when `displayStatus`; then Pending, Retrying, Failed, Sent, Skipped. Test: `api/tests/Unit/Notifications/DisplayStatusTest`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
