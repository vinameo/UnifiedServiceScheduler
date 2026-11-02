# 03: Cancel rules for staff

**What to build:** Cancelling a cancelled appointment again answers 200 without a new version (D-07.4); a status that cannot be cancelled answers 422 `INVALID_STATUS_TRANSITION`; `reason` is required for staff; a foreman gets 403.

**Blocked by:** [02-advisor-cancels-an-appointment](02-advisor-cancels-an-appointment.md)

**Status:** ready-for-agent

**Branch:** `feature/07-cancel-appointment`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-07-02, TC-07-03, TC-07-06, TC-07-08

- [ ] TC-07-02 (AC-07.2, D-07.4; Integration): given Booking already cancelled, v2; when Cancel again with `If-Match: "2"` and with `"1"`; then Both 200, `status CANCELLED`, `ETag "2"`; no new history row. Test: `…::test_AC_07_2_cancelling_twice_returns_current_state`.
- [ ] TC-07-03 (AC-07.3; Integration): given `COMPLETED` booking; when Cancel; then 422 `INVALID_STATUS_TRANSITION`, `currentStatus COMPLETED`. Test: `…::test_AC_07_3_completed_cannot_be_cancelled`.
- [ ] TC-07-06 (AC-07.6; Integration): when Advisor cancels without `reason`; then 400 `VALIDATION_ERROR` on `reason`. Test: `…::test_AC_07_6_staff_reason_required`.
- [ ] TC-07-08 (AC-07.8; Integration): given Foreman, admin; when Cancel; then 403. Test: `…::test_AC_07_8_foreman_and_admin_cannot_cancel`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
