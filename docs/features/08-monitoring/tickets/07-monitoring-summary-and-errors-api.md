# 07: Monitoring summary and errors API

**What to build:** `GET /monitoring/summary?range=` and `GET /monitoring/errors?correlationId=` for admins only, counting only their dealership and rows with no dealership (AC-08.4, AC-08.8, AC-08.9). The adversarial input table of API §B4 is covered.

**Blocked by:** [06-api-events](06-api-events.md)

**Status:** done

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-04, TC-08-09, TC-08-10, TC-08-17

- [x] TC-08-04 (AC-08.4; Integration): given Advisor, foreman, customer; when `GET /monitoring/summary?range=1h`; then 403 for all three; admin 200. Test: `MonitoringApiTest::test_AC_08_4_admin_only`.
- [x] TC-08-09 (AC-08.8, D-08.9–08.11; Integration): given Admin of D1; `api_events` of D1 (2), D2 (3), NULL (1); 2 PENDING outbox rows; when `summary?range=24h`; then `recentErrors` has 3 rows (D1 + NULL); `outbox.pending 2`; `database.status UP`, `connectionsMax > 0`. Test: `MonitoringApiTest::test_AC_08_8_summary_is_scoped`.
- [x] TC-08-10 (AC-08.9; Integration): given An error with correlation X; when `errors?correlationId=X`; then 1 row. Test: `…::test_AC_08_9_search_by_correlation_id`.
- [x] TC-08-17 (API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `MonitoringAdversarialInputTest`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
