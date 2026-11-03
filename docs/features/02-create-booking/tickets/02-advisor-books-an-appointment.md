# 02: Advisor books an appointment (tracer bullet)

**What to build:** A service advisor picks a customer, vehicle, service and time on a minimal New booking screen and confirms; `POST /api/v1/appointments` (`CONFIRM`) runs `BookingService::create` over F03 + F04 and answers 201 with `Location` and `ETag: "1"`, and the Booking confirmed screen shows the `APT-…` reference in the dealership's time zone. One `AppointmentConfirmed` event goes to the outbox; foremen get 403.

**Blocked by:** [01-prefactor-bookingtimerules-and-web-time-helpers](01-prefactor-bookingtimerules-and-web-time-helpers.md)

**Status:** done

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-01, TC-02-08, TC-02-18, TC-02-22, TC-02-25

- [x] TC-02-01 (AC-02.1, AC-02.17; Integration): given `World`; advisor; Hùng + Vios; An + K1 free; when `POST /appointments` OIL 2026-11-02 09:00+07; then 201; `Location: /api/v1/appointments/{id}`; `ETag: "1"`; body `status CONFIRMED`, `start 09:00+07:00`, `end 10:00+07:00`, `technician`, `bay`, `serviceType`, `vehicle`, `createdAt`; 1 `appointments` row. Test: `CreateBookingTest::test_AC_02_1_advisor_books_and_gets_confirmed_appointment`.
- [x] TC-02-08 (AC-02.8; Integration): when No `Idempotency-Key` header; then 400 `IDEMPOTENCY_KEY_REQUIRED`; 0 bookings. Test: `…::test_AC_02_8_missing_idempotency_key`.
- [x] TC-02-18 (AC-02.14; Integration): given Foreman, admin; when `POST /appointments`; then 403 `FORBIDDEN`. Test: `…::test_AC_02_14_foreman_and_admin_cannot_book`.
- [x] TC-02-22 (AC-02.16, INV-02.7, AC-11.1; Integration): when A successful booking; then `outbox_messages`: 1 `AppointmentConfirmed` `event_id`, 3 channels, right `appointment_id`; 3 active allocations. Test: `…::test_AC_02_16_confirmed_booking_writes_outbox`.
- [x] TC-02-25 (AC-02.18; Web component): given Fake API: customer search, vehicles, services, 201; when Pick customer → vehicle → Oil change → 09:00 → Confirm; then The request has `Idempotency-Key`, `desiredStart …T09:00:00+07:00`; goes to the confirmed page: "Appointment confirmed", "APT-20261102-0042", "Monday, 2 Nov 2026, 09:00–10:00". Test: `web/src/staff/booking/NewBookingPage.test.tsx` (run with `TZ=America/New_York`).
- [x] README §8 checks green; no existing test skipped, deleted or weakened
- [x] Run `/code-review` on the branch and fix findings before later tickets build on it
