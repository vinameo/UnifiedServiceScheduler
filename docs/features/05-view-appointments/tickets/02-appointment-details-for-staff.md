# 02: Appointment details for staff (tracer bullet)

**What to build:** `GET /appointments/{appointment}` returns the appointment with its `ETag`; another dealership or another customer gets 404 (INV-05.1). The staff Appointment details page reads it through `appointmentQuery`, which keeps the `ETag` (WEB-05.1), and Booking confirmed links to it.

**Blocked by:** [01-prefactor-visible-appointment-lookup-route-binding-cursor](01-prefactor-visible-appointment-lookup-route-binding-cursor.md)

**Status:** ready-for-agent

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-01, TC-05-03, TC-05-04, TC-05-05, TC-05-16

- [ ] TC-05-01 (AC-05.1, INV-05.2; Integration): given Book through `POST /appointments`, keep the body; when `$this->refreshApplication()`; sign in again; `GET /appointments/{id}`; then Body identical to the creation body; `ETag: "1"`. Test: `ShowAppointmentTest::test_AC_05_1_appointment_survives_restart`.
- [ ] TC-05-03 (AC-05.3; Integration): when `GET /appointments/{random uuid}`; then 404 `APPOINTMENT_NOT_FOUND`. Test: `ShowAppointmentTest::test_AC_05_3_unknown_id`.
- [ ] TC-05-04 (AC-00.8, INV-05.1; Integration): given Customer B's booking; when Customer A `GET /appointments/{id}`; then 404 `APPOINTMENT_NOT_FOUND`, body like TC-05-03 except `correlationId`. Test: `…::test_AC_00_8_customer_cannot_see_another_customers_appointment`.
- [ ] TC-05-05 (AC-05.5, INV-05.1; Integration): given A D1 booking; when D2 advisor `GET /appointments/{id}`; then 404 `APPOINTMENT_NOT_FOUND`. Test: `…::test_AC_05_5_staff_of_another_dealership_gets_404`.
- [ ] TC-05-16 (WEB-05.1; Web unit): when `appointmentQuery` fetch with header `ETag: "3"`; `setAppointment(…, '"4"')`; then Cache has `etag '"3"'` then `'"4"'`; `withIfMatch` returns `{ 'If-Match': '"4"' }`. Test: `web/src/shared/api/appointmentQuery.test.ts`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
- [ ] Run `/code-review` on the branch and fix findings before later tickets build on it
