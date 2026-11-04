# 01: Prefactor: confirm moveTo and excludeAppointmentId are reusable

**What to build:** Check that F04 `AppointmentWriter::moveTo` and F06 `excludeAppointmentId` serve reschedule as they are; record the check in the design notes. Extract anything that would otherwise be copied. Nothing users can see changes.

**Blocked by:** F08 [11-acceptance](../../08-monitoring/tickets/11-acceptance.md) (the whole F08 branch merged to `develop`).

**Status:** done

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [x] No change users can see; existing tests stay green unchanged
- [x] README §8 checks green; no existing test skipped, deleted or weakened
