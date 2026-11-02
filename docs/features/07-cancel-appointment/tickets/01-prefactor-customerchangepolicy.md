# 01: Prefactor: CustomerChangePolicy

**What to build:** Split the "customer may change only until 2 hours before start" rule into `CustomerChangePolicy` (and its web twin), for F07 and F09. Nothing users can see changes.

**Blocked by:** F06 [10-acceptance-and-performance](../../06-alternative-times/tickets/10-acceptance-and-performance.md) (the whole F06 branch merged to `develop`).

**Status:** ready-for-agent

**Branch:** `feature/07-cancel-appointment`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [ ] No change users can see; existing tests stay green unchanged
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
