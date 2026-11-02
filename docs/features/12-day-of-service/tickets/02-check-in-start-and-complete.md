# 02: Check in, start and complete (tracer bullet)

**What to build:** Advisors check in, foremen start and complete, each with `If-Match`; completing frees the allocations (AC-12.1, INV-12.1). Appointment details shows the action buttons each role may use (WEB-12.6).

**Blocked by:** [01-prefactor-resourcesnapshot-withbusy-and-appointmentwriter](01-prefactor-resourcesnapshot-withbusy-and-appointmentwriter.md)

**Status:** ready-for-agent

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-01, TC-12-07, TC-12-25

- [ ] TC-12-01 (AC-12.1; Integration): given Today's CONFIRMED booking v1; when Advisor check-in (`"1"`), foreman start (`"2"`), complete (`"3"`); then Status `COMPLETED`, `checked_in_at`, `started_at`, `completed_at` set; 0 active allocations; 3 history rows. Test: `StatusActionsTest::test_AC_12_1_check_in_start_complete`.
- [ ] TC-12-07 (INV-12.1; Integration): given CHECKED_IN booking; CONFIRMED booking; when Complete each; then 422 both. Test: `…::test_complete_requires_in_progress`.
- [ ] TC-12-25 (WEB-12.6; Web component): given Booking IN_PROGRESS; foreman vs advisor; when Render actions; then Foreman: "Mark completed", "Extend time"; advisor: none; extend menu sends `{minutes: 30}` with `If-Match`. Test: `web/src/staff/appointments/AppointmentActions.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
- [ ] Run `/code-review` on the branch and fix findings before later tickets build on it
