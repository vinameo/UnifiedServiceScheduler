# 09: Ignore an appointment's own slot

**What to build:** `excludeAppointmentId` lets the owner (or staff) see their booking's own slot as free; anyone else gets 404 (AC-06.11, INV-06.7). The adversarial input table of API §B4 is covered.

**Blocked by:** [08-free-slot-counts-and-fully-booked-day-for-customers](08-free-slot-counts-and-fully-booked-day-for-customers.md)

**Status:** ready-for-agent

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-11, TC-06-17

- [ ] TC-06-11 (AC-06.11, INV-06.7; Integration): given A's booking X holds An 10–11; An is the only one; when A asks with `excludeAppointmentId=X`; B asks with X; then A: 10:00 `true`; B: 404 `APPOINTMENT_NOT_FOUND`. Test: `…::test_AC_06_11_exclude_own_appointment_only`.
- [ ] TC-06-17 (D-06.8, API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `AvailabilityAdversarialInputTest`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
