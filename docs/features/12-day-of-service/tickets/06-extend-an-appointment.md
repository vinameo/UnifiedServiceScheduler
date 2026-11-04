# 06: Extend an appointment

**What to build:** A foreman extends an in-progress booking by 30 minutes: `end` and allocations grow (AC-12.7); a stale version answers 412 and nothing is extended (AC-12.15). The "Extend 30 min" action sends `If-Match`.

**Blocked by:** [05-permissions-per-workshop-action](05-permissions-per-workshop-action.md)

**Status:** done

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-08, TC-12-11, TC-12-28

- [x] TC-12-08 (AC-12.7; Integration): given X IN_PROGRESS, nothing after it; when Extend 30; then 200; `end` +30; X's 3 allocations have the new range; 1 `EXTENDED` history row with `minutes 30`. Test: `ExtendTest::test_AC_12_7_extend_without_neighbours`.
- [x] TC-12-11 (Stale job version; Integration): given X v3; someone completes X (v4); when Extend with `If-Match "3"`; then 412 with `status COMPLETED`; neighbours untouched. Test: `…::test_stale_job_version_changes_nothing`.
- [x] TC-12-28 (AC-12.15, D-12.15; Web component): given Fake API: board with an alert for X `version 3`; extend → 412 with X `COMPLETED` (v4); when Tap "Extend 30 min"; then The request has `If-Match: "3"`; the 412 alert shows "Status: In progress → Completed"; the board refetches. Test: `web/src/staff/workshop/WorkshopBoardPage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
