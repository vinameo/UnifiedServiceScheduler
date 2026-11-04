# 06: Customer reschedules (API)

**What to build:** A customer reschedules their own booking until 2 hours before start (`TOO_LATE_TO_RESCHEDULE`), gets 404 for someone else's, and 412 with the latest data on a stale version (AC-09.3, AC-09.8, D-09.2).

**Blocked by:** [05-reschedule-rules](05-reschedule-rules.md)

**Status:** done

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-03, TC-09-10, TC-09-19

- [x] TC-09-03 (AC-09.3; Integration): given X v3; advisor cancels → v4; when Customer reschedules with `If-Match "3"`; then 412 with `appointment.status CANCELLED`, `ETag "4"`. Test: `…::test_AC_09_3_stale_version_after_cancel`.
- [x] TC-09-10 (AC-09.8; Integration): given Clock 08:00; customer's booking at 09:00; another at 10:00; when Customer reschedules each; then 422 `TOO_LATE_TO_RESCHEDULE` (`dealershipPhone`); 200 (exactly 2 hours). Test: `…::test_AC_09_8_customer_two_hour_rule`.
- [x] TC-09-19 (D-09.2; Integration): given Foreman, admin; customer B; when Reschedule A's booking; then 403, 403, 404. Test: `RescheduleTest::test_permissions`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
