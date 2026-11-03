# 10: Customer booking API

**What to build:** A customer books through the same endpoint: the customer id always comes from the session, another customer's vehicle answers 404 `VEHICLE_NOT_FOUND`, and `dealershipId` follows D-02.3 (AC-02.5, AC-02.10, INV-02.1, INV-02.9).

**Blocked by:** [09-my-vehicles](09-my-vehicles.md)

**Status:** done

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-05, TC-02-10, TC-02-35

- [x] TC-02-05 (AC-02.5, INV-02.1; Integration): given Customer A with a vehicle; customer B; when A sends `customerId: B` + A's vehicle; then 201; `customer.id = A`. Test: `…::test_AC_02_5_customer_id_in_body_is_ignored`.
- [x] TC-02-10 (AC-02.10, D-02.5; Integration): given B's vehicle; when A books with it; then 404 `VEHICLE_NOT_FOUND` (not 422). Test: `CreateBookingTest::test_AC_02_10_customer_cannot_use_foreign_vehicle`.
- [x] TC-02-35 (INV-02.9, D-02.3; Integration): given Advisor of D1; when Sends `dealershipId: D2`; then 201; the booking belongs to D1. Test: `CreateBookingTest::test_advisor_dealership_comes_from_account`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
