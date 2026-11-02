# 01: Prefactor: ExpiredHoldReleaser and useServerClock

**What to build:** Add the `ExpiredHoldReleaser` seam (no callers yet) and the web `useServerClock` that corrects the browser clock from the server's `Date` (D-10.11). Nothing users can see changes.

**Blocked by:** F09 [10-acceptance](../../09-reschedule/tickets/10-acceptance.md) (the whole F09 branch merged to `develop`).

**Status:** ready-for-agent

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-18

- [ ] TC-10-18 (D-10.11; Web unit): given Response `Date` 10:00:00, browser 09:58:00; when `useServerClock().now()`; then 10:00:00 (± test tolerance). Test: `web/src/shared/time/useServerClock.test.ts`.
- [ ] No change users can see; existing tests stay green unchanged
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
