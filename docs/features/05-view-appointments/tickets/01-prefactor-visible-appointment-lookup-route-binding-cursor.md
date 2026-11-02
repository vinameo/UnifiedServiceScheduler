# 01: Prefactor: visible-appointment lookup, route binding, cursor paging

**What to build:** Add `AppointmentRepository::findVisibleTo(User, id)`, the explicit `{appointment}` route binding that calls it (404 `APPOINTMENT_NOT_FOUND` when not visible, D-05.9), `AppointmentPolicy` and `CursorPaginator` (D-G.12). Nothing users can see changes.

**Blocked by:** F02 [12-acceptance-and-performance](../../02-create-booking/tickets/12-acceptance-and-performance.md) (the whole F02 branch merged to `develop`).

**Status:** ready-for-agent

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [ ] No change users can see; existing tests stay green unchanged
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
