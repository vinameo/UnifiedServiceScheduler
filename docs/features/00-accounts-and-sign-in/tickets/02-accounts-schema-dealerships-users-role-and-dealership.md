# 02: Accounts schema: dealerships, users role and dealership, customers

**What to build:** Create `dealerships`, add `role` and `dealership_id` to `users`, and create `customers` (DB §B1, §B2), with models. The database itself refuses an advisor without a dealership, a customer with one, duplicate emails in any case, and bad phone numbers.

**Blocked by:** [01-prefactor-role-enum-empty-gates-factories-axios-mock](01-prefactor-role-enum-empty-gates-factories-axios-mock.md)

**Status:** ready-for-agent

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-14

- [ ] TC-00-14 (INV-00.1, INV-00.6, DB-00.2, DB-00.3; Integration): when Direct inserts: advisor without dealership; customer with dealership; email `A@b.c`; phone `abc`; then Each insert throws `QueryException` `23514`. Test: `Feature/Auth/AccountSchemaTest::test_DB_00_2_checks_reject_invalid_rows`.
- [ ] Each migration has the pre-check and the exact `down()` from DB §B2
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
