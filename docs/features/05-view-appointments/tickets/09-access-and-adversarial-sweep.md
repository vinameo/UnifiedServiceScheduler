# 09: Access and adversarial sweep

**What to build:** Every appointment route answers 404 to another dealership's advisor (D-05.9) and the adversarial input table of API §B4 is covered.

**Blocked by:** [08-links-between-screens](08-links-between-screens.md)

**Status:** ready-for-agent

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-11, TC-05-20

- [ ] TC-05-11 (D-05.6, API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `AppointmentListAdversarialInputTest` (data provider).
- [ ] TC-05-20 (D-05.9, INV-05.1, INV-05.7; Integration): given A D1 booking; D2 advisor; every route whose URI contains `{appointment}` (read from `Route::getRoutes()`); when D2 advisor calls each route (GET, or POST with valid headers); then Every one: 404 `APPOINTMENT_NOT_FOUND`, nothing written. Test: `ShowAppointmentTest::test_D_05_9_every_appointment_route_is_scoped`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
