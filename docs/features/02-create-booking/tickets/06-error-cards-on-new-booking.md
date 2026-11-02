# 06: Error cards on New booking

**What to build:** New booking shows each error as its card: the opening-hours card with the latest start, the `NO_AVAILABILITY` heading and copy from `noAvailabilityReasonCopy` (F03 WEB-03.1), field errors under their fields; a reload keeps the entered form (D-02.11).

**Blocked by:** [05-customer-vehicle-and-service-checks](05-customer-vehicle-and-service-checks.md)

**Status:** ready-for-agent

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-26, TC-02-28, TC-02-32

- [ ] TC-02-26 (AC-02.19; Web component): given Fake API 422 `OUTSIDE_OPENING_HOURS` (`latestStart 15:00`); when Confirm 20,000 km at 16:00; then Card "The service would end after closing time" + "The latest start that day is 15:00.". Test: `…/NewBookingPage.test.tsx`.
- [ ] TC-02-28 (WEB-02.6; Web component): given Fake API: 409 `NO_AVAILABILITY` (`NO_BAY_AVAILABLE`); 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER`; when Confirm; then Card heading "The 09:00 slot was just taken" + "No Quick service bay is free from 09:00 to 10:00…"; the second error shows under the Vehicle field. Test: `web/src/shared/booking/BookingErrorCard.test.tsx`.
- [ ] TC-02-32 (D-02.11; Web component): given Draft at step 3; when Unmount + mount again (simulated F5); fake API returns 409 `NO_AVAILABILITY` on confirm; then The draft survives; on 409 back to `/book/time` with an Alert, the old time unselected. Test: `BookingFlow.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
