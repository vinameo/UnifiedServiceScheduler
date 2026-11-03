# 05: Dealership route binding and service types (tracer bullet)

**What to build:** Add the `{dealership}` route binding that only returns the staff member's own dealership (404 `DEALERSHIP_NOT_FOUND` otherwise, INV-01.3) and `GET /dealerships/{d}/service-types`. The admin opens Master data and sees the Service types tab.

**Blocked by:** [04-master-data-seed-and-full-world-fixture](04-master-data-seed-and-full-world-fixture.md)

**Status:** done

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-01, TC-01-03, TC-01-04, TC-01-06, TC-01-11, TC-01-13

- [x] TC-01-01 (AC-01.1, BR-01.1; Integration): given `migrate:fresh` + `db:seed` (password variables set); when Admin `GET /dealerships/{d}/service-types`; then 200; 3 items; each has `durationMinutes > 0`, one `requiredLevel` (1–7), one `requiredBayType`. Test: `api/tests/Feature/MasterData/ServiceTypesTest::test_AC_01_1_seeded_service_types_are_returned`.
- [x] TC-01-03 (INV-01.3, API §B4; Integration): given Advisor of D1; when Calls with `{dealership}` = `abc`, a missing UUID, D2's UUID; then All three 404 `DEALERSHIP_NOT_FOUND`; bodies identical except `correlationId`. Test: `…DealershipScopeTest::test_INV_01_3_missing_malformed_and_foreign_look_the_same`.
- [x] TC-01-04 (INV-01.3; Integration): given Customer; D1, D2; when Customer calls `GET /dealerships`, `/dealerships/{D2}/service-types`; then List has D1, D2; service-types 200. Test: `…DealershipScopeTest::test_customer_reads_any_dealership_catalogue`.
- [x] TC-01-06 (AC-01.4, INV-01.4; Integration): given `World` + one service with `active=false`; when Customer and admin call service-types; then Customer: 3; admin: 4, the new one `active:false`. Test: `…ServiceTypesTest::test_AC_01_4_inactive_service_hidden_from_customers`.
- [x] TC-01-11 (AC-01.6; Web component): given Fake API returns the seed data; when Render `MasterDataPage`; then 4 tabs with counts 3/4/5; "Read only"; "Only technician status can be changed here."; "This version loads this data from seed files"; no "holiday" text. Test: `web/src/staff/master-data/MasterDataPage.test.tsx`.
- [x] TC-01-13 (API §B4; Integration): given Customer; when `service-types?includeInactive=true`; then Still only `active`. Test: `…ServiceTypesTest::test_customer_cannot_include_inactive`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
- [x] Run `/code-review` on the branch and fix findings before later tickets build on it
