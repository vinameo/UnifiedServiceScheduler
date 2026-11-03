# 03: Seed dealership, staff and demo customers

**What to build:** Seeders create the demo dealership, the seed staff accounts and demo customers, with passwords from `SEED_*_PASSWORD` env only (D-00.8, D-00.9). Running `db:seed` again changes nothing.

**Blocked by:** [02-accounts-schema-dealerships-users-role-and-dealership](02-accounts-schema-dealerships-users-role-and-dealership.md)

**Status:** done

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-15, TC-00-16

- [x] TC-00-15 (AC-00.11; Integration): given All 4 password variables set; when `db:seed` twice; then 1 dealership, 3 staff, 2 customers; second run leaves the `password` hash unchanged. Test: `Feature/Auth/AccountSeederTest::test_AC_00_11_seeder_is_safe_to_run_twice`.
- [x] TC-00-16 (AC-00.11, D-00.8; Integration): given `SEED_ADMIN_PASSWORD` empty; when `db:seed`; then No admin; the other accounts exist. Test: `…AccountSeederTest::test_AC_00_11_empty_password_env_skips_the_account`.
- [x] `SEED_CUSTOMER_PASSWORD` is added to `.env.example` (S-00.7)
- [x] README §8 checks green; no existing test skipped, deleted or weakened
