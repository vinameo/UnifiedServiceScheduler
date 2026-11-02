# 08: Prune monitoring data

**What to build:** `monitoring:prune` deletes `api_events` and counters older than the retention, keeping recent rows (AC-08.11).

**Blocked by:** [07-monitoring-summary-and-errors-api](07-monitoring-summary-and-errors-api.md)

**Status:** ready-for-agent

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-12

- [ ] TC-08-12 (AC-08.11; Integration): given `api_events` 8 days and 6 days old; when `monitoring:prune`; then The 8-day row is deleted; the 6-day row stays. Test: `PruneTest::test_AC_08_11_events_older_than_7_days_are_pruned`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
