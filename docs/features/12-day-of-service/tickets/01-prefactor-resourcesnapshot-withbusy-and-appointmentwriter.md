# 01: Prefactor: ResourceSnapshot::withBusy and AppointmentWriter::displace

**What to build:** Add `ResourceSnapshot::withBusy` and `AppointmentWriter::displace` for extend and reassignment. Nothing users can see changes.

**Blocked by:** F11 [11-acceptance](../../11-outbox-notifications/tickets/11-acceptance.md) (the whole F11 branch merged to `develop`).

**Status:** ready-for-agent

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [ ] No change users can see; existing tests stay green unchanged
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
