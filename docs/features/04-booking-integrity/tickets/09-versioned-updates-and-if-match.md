# 09: Versioned updates and If-Match

**What to build:** `AppointmentRepository::updateVersioned()` is the only way to change an appointment (CLAUDE.md core rule 2). The `IfMatch` middleware and `ExpectedVersion::from($request)` answer 428 `PRECONDITION_REQUIRED` without the header and 412 `APPOINTMENT_MODIFIED` with the latest appointment and `ETag` on a stale version (AC-04.6, AC-04.7, AC-04.22).

**Blocked by:** [08-disabled-technicians-and-the-real-upcoming-assignments](08-disabled-technicians-and-the-real-upcoming-assignments.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-06, TC-04-07, TC-04-22

- [x] TC-04-06 (AC-04.6; Concurrency): given Appointment at version 3; when 2 processes `POST /_testing/appointments/{id}/touch` with `If-Match: "3"`, different keys; then One 200 (`ETag: "4"`), one 412; final `version` 4. Test: `ParallelVersionTest::test_AC_04_6_two_changes_on_same_version_one_wins`.
- [x] TC-04-07 (AC-04.7; Integration): given Appointment at version 1; when `touch` without `If-Match`; then 428 `PRECONDITION_REQUIRED`; `version` still 1; no new history row. Test: `IfMatchTest::test_AC_04_7_missing_if_match_is_428`.
- [x] TC-04-22 (AC-04.22, INV-04.4; Integration): given Appointment at version 4; when `touch` with `If-Match: "3"`; then 412 `APPOINTMENT_MODIFIED`; body `appointment.version = 4`; header `ETag: "4"`; `AppointmentModifiedDetected` dispatched. Test: `IfMatchTest::test_AC_04_22_412_carries_latest_data`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
