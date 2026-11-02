# 10: Workshop board screen

**What to build:** The Workshop board shows the `BayTimeline` per bay with the overrun alert (WEB-12.5).

**Blocked by:** [09-workshop-board-api](09-workshop-board-api.md)

**Status:** ready-for-agent

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-24

- [ ] TC-12-24 (WEB-12.5; Web component): given Fake board with a block 09:00–10:00 and an overrun block; `now` 10:50; when Render `BayTimeline`; then The block is placed at the 09:00 column; overrun part hatched; the now-line at 10:50; cancelled blocks absent. Test: `web/src/staff/workshop/BayTimeline.test.tsx` (run with `TZ=America/New_York`).
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
