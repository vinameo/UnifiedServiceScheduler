# 01: Prefactor: overlap assertion, parallel runner, testing routes

**What to build:** Add the `AssertsNoOverlap` trait, the `testing:request` command with `Tests\Support\Concurrency\Parallel`, and an empty `TestingRoutesServiceProvider` (README §7, S-04.8). Nothing users can see changes.

**Blocked by:** F03 [10-acceptance-and-performance](../../03-slot-allocation/tickets/10-acceptance-and-performance.md) (the whole F03 branch merged to `develop`).

**Status:** ready-for-agent

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [README §7](../../README.md#7-shared-fixtures-and-test-infrastructure); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [ ] `Parallel` starts N PHP processes that wait for the same start time and return `{status, body, headers}` as JSON
- [ ] No change users can see; existing tests stay green unchanged
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
