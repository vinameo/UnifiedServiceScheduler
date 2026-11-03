# 07: Bays and technicians

**What to build:** `GET /dealerships/{d}/bays` and `/technicians` for staff (customers get 403, INV-01.4), and the Bays and Technicians tabs of Master data showing type, level and active state.

**Blocked by:** [06-list-dealerships](06-list-dealerships.md)

**Status:** done

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-05

- [x] TC-01-05 (AC-01.3, INV-01.4; Integration): given Customer; when `GET /dealerships/{d}/bays`, `/technicians`; then 403 `FORBIDDEN` for both. Test: `…/MasterData/BaysAndTechniciansTest::test_AC_01_3_customer_cannot_read_bays_or_technicians`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
