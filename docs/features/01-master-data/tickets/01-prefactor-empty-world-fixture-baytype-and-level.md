# 01: Prefactor: empty World fixture, BayType and Level

**What to build:** Add an empty `Tests\Support\World` fixture (README §7), the `BayType` enum and the `Level` value object (1–7, D-G.16). Nothing users can see changes.

**Blocked by:** F00 [11-acceptance](../../00-accounts-and-sign-in/tickets/11-acceptance.md) (the whole F00 branch merged to `develop`).

**Status:** done

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [x] `Level` refuses values outside 1–7 and `BayType` lists the bay types from DB §B1
- [x] No change users can see; existing tests stay green unchanged
- [x] README §8 checks green; no existing test skipped, deleted or weakened
