# 09: My vehicles

**What to build:** `GET /me/vehicles` and `POST /me/vehicles` let a customer list and add vehicles: plates are normalised (`PLATE_ALREADY_REGISTERED` on a duplicate), VINs too, and at most 10 vehicles (`VEHICLE_LIMIT_REACHED`) (AC-02.9, D-02.9, INV-02.10).

**Blocked by:** [08-customer-s-vehicles-on-new-booking](08-customer-s-vehicles-on-new-booking.md)

**Status:** done

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-09, TC-02-24

- [x] TC-02-09 (AC-02.9, INV-02.10; Integration): given Customer; when `POST /me/vehicles` `51G-999.99`; then `51g 99999` (different key); then 201 (`licencePlate "51G-999.99"`); 409 `PLATE_ALREADY_REGISTERED`. Test: `Feature/Customers/MyVehiclesTest::test_AC_02_9_plate_is_unique_across_spellings`.
- [x] TC-02-24 (D-02.9; Integration): given Customer with 10 vehicles; another vehicle with VIN V; when Add vehicle 11; add a vehicle with VIN V in lower case; then 422 `VEHICLE_LIMIT_REACHED`; 409 `VIN_ALREADY_REGISTERED`. Test: `MyVehiclesTest::test_vehicle_limit_and_vin_unique`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
