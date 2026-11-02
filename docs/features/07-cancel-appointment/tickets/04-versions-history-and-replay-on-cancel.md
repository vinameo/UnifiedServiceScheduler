# 04: Versions, history and replay on cancel

**What to build:** A stale `If-Match` answers 412 with the latest appointment, a missing one 428; a cancel writes one history row; resending the key replays; the dialog shows `AppointmentModifiedAlert` on 412 (AC-07.11, INV-07.2).

**Blocked by:** [03-cancel-rules-for-staff](03-cancel-rules-for-staff.md)

**Status:** ready-for-agent

**Branch:** `feature/07-cancel-appointment`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-07-11, TC-07-12, TC-07-14, TC-07-19

- [ ] TC-07-11 (AC-07.11; Integration): given Booking v2 `CONFIRMED`; when Cancel with `"1"`; cancel without `If-Match`; then 412, body `appointment.version 2`, `ETag "2"`; 428. Test: `…::test_AC_07_11_stale_and_missing_if_match`.
- [ ] TC-07-12 (INV-07.2; Integration): given Bookings in `CHECKED_IN`, `IN_PROGRESS`, `NO_SHOW`, `EXPIRED`; when Cancel; then 422 each. Test: `…::test_INV_07_2_non_cancellable_statuses` (data provider).
- [ ] TC-07-14 (F04 + F07; Integration): given Cancel with K → 200; when Resend K (the booking is now v2); then The saved 200, `Idempotent-Replayed: true`. Test: `…::test_retry_with_same_key_replays`.
- [ ] TC-07-19 (WEB-07.1, 412; Web component): given Fake API: cancel 412 with a rescheduled booking; when Confirm the cancel; then `AppointmentModifiedAlert` "Time: 09:00–10:00 → 14:00–15:00"; no automatic resend. Test: `CancelAppointmentModal.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
