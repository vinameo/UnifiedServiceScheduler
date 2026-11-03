# 06: List dealerships

**What to build:** `GET /dealerships` returns the dealerships the caller may use: customers see every dealership, staff only their own (API-01.1, AC-01.5).

**Blocked by:** [05-dealership-route-binding-and-service-types](05-dealership-route-binding-and-service-types.md)

**Status:** done

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-07

- [x] TC-01-07 (AC-01.5; Integration): given Advisor of D1; D1, D2; when `GET /dealerships`; then Exactly 1 item, D1. Test: `…DealershipScopeTest::test_AC_01_5_staff_list_only_their_dealership`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
