# 01: Prefactor: TimeGrid takes chip state from props

**What to build:** `TimeGrid` takes each chip's state (free, taken, selected) from props instead of computing it, so F06 can turn it into `SlotGrid`. Nothing users can see changes.

**Blocked by:** F05 [10-acceptance](../../05-view-appointments/tickets/10-acceptance.md) (the whole F05 branch merged to `develop`).

**Status:** done

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [x] No change users can see; existing tests stay green unchanged
- [x] README §8 checks green; no existing test skipped, deleted or weakened
