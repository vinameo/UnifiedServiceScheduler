# 05: Readiness check

**What to build:** `GET /health/ready` answers 503 when a dependency is down (AC-08.3).

**Blocked by:** [04-keep-metrics-internal](04-keep-metrics-internal.md)

**Status:** ready-for-agent

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-03

- [ ] TC-08-03 (AC-08.3; Integration): given The DB connection points at a host that does not exist (runtime config); when `GET /health/ready`; then 503, `checks.database = fail`. Test: `Feature/Platform/HealthTest::test_AC_08_3_ready_is_503_when_database_is_down`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
