# 05: Customer, vehicle and service checks

**What to build:** The remaining D-02.7 checks run in the stated order: unknown service (404 `SERVICE_TYPE_NOT_FOUND`), vehicle not found or not owned by the customer (`VEHICLE_NOT_OWNED_BY_CUSTOMER`), inactive entities, and the vehicle already booked (409 `VEHICLE_ALREADY_BOOKED`). The adversarial input table of API §B4 is covered.

**Blocked by:** [04-booking-time-rules-on-create](04-booking-time-rules-on-create.md)

**Status:** done

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-04, TC-02-06, TC-02-07, TC-02-11, TC-02-17, TC-02-31

- [x] TC-02-04 (AC-02.4; Integration): given Advisor; customer c1, vehicle v2 of c2; when Book c1 + v2; then 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER`; 0 bookings. Test: `…::test_AC_02_4_vehicle_of_another_customer`.
- [x] TC-02-06 (AC-02.6; Integration): when `serviceTypeId` = a UUID that does not exist; then 404 `SERVICE_TYPE_NOT_FOUND`. Test: `…::test_AC_02_6_unknown_service_type`.
- [x] TC-02-07 (AC-02.7; Integration): given Vehicle booked 09:00–10:00 (An, K1); when Book the same vehicle at 09:30; then 409 `VEHICLE_ALREADY_BOOKED`, correct `existingAppointment.id`. Test: `…::test_AC_02_7_vehicle_already_booked`.
- [x] TC-02-11 (INV-02.2; Integration): when Advisor and customer book with a `vehicleId` that does not exist; then 404 `VEHICLE_NOT_FOUND` for both. Test: `…::test_unknown_vehicle_is_404`.
- [x] TC-02-17 (INV-02.3; Integration): given An inactive service; a service of dealership D2; when Book with each; then 404 `SERVICE_TYPE_NOT_FOUND` for both. Test: `…::test_inactive_or_foreign_service_type`.
- [x] TC-02-31 (API §B4, INV-02.6, D-02.7; Integration): when Each row of the adversarial input table; plus a body with both a wrong service and a past time; plus a wrong service and `09:07`; then Status + `code` as in the table; wrong service + past → 404 `SERVICE_TYPE_NOT_FOUND`; wrong service + 09:07 → 400 (the step is a format check, D-02.7). Test: `CreateBookingAdversarialInputTest` (data provider).
- [x] README §8 checks green; no existing test skipped, deleted or weakened
