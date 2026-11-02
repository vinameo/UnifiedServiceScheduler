# 09: System monitoring screen

**What to build:** The admin's System monitoring screen shows health cards, booking figures and the error table, refreshes every 30 s, and switches 1 hour / 24 hours / 7 days through `?range=` (AC-08.12, WEB-08.1, WEB-08.2).

**Blocked by:** [08-prune-monitoring-data](08-prune-monitoring-data.md)

**Status:** ready-for-agent

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-13

- [ ] TC-08-13 (AC-08.12; Web component): given Fake API summary 24h; when Render, click "7 days"; then Card "No availability (409)" "131" "10.5% · business outcome, not an outage"; URL `?range=7d`, API called again with `7d`. Test: `web/src/staff/monitoring/MonitoringPage.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
