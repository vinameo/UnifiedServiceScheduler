# 11: Customer booking screens

**What to build:** The customer goes through Choose a service, Choose date and time and Contact details (adding a vehicle there), confirms and sees Booking confirmed (AC-02.20, D-02.12).

**Blocked by:** [10-customer-booking-api](10-customer-booking-api.md)

**Status:** done

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-27, TC-02-32, TC-02-34

- [x] TC-02-27 (AC-02.20; Web component): given Fake API: services, `me`, no vehicles, `POST /me/vehicles` 201, `POST /appointments` 201; when Go through 3 steps, add a vehicle, confirm; then `POST /me/vehicles` first, then `POST /appointments` with the new `vehicleId`; "Booking confirmed" page with "0903 *** 218". Test: `web/src/customer/booking/BookingFlow.test.tsx`.
- [x] TC-02-32 (D-02.11; Web component): given Draft at step 3; when Unmount + mount again (simulated F5); fake API returns 409 `NO_AVAILABILITY` on confirm; then The draft survives; on 409 back to `/book/time` with an Alert, the old time unselected. Test: `BookingFlow.test.tsx`.
- [x] TC-02-34 (D-02.12, D-02.3; Integration + Web): given Two dealerships; when Customer books without `dealershipId`; with a missing `dealershipId`; web step 1; then 400; 404 `DEALERSHIP_NOT_FOUND`; the web shows the "Dealership" field. Test: `CreateBookingTest::test_customer_must_name_a_dealership`, `ChooseServicePage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
