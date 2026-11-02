# 01: Prefactor: Role enum, empty Gates, factories, axios mock

**What to build:** Add the `Role` backed enum (`CUSTOMER`, `SERVICE_ADVISOR`, `WORKSHOP_FOREMAN`, `ADMIN`) and register the `customer` and `staff` Gates in `AppServiceProvider::boot()` (D-G.14) as the place later features add abilities. Add user/customer/dealership factories (README §7) and the hand-written axios mock for web tests. Nothing users can see changes.

**Blocked by:** None (can start immediately).

**Status:** done

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-27

- [x] TC-00-27 (INV-00.10; Unit): when Scan `app/Http/Controllers/**/*.php`; then No `->role ===` and no `Role::` comparison. Test: `Unit/Architecture/NoRoleChecksInControllersTest`.
- [x] `User::factory()->advisor($dealership)` and `->customer()` are available to later tests (README §7)
- [x] No change users can see; existing tests stay green unchanged
- [x] README §8 checks green; no existing test skipped, deleted or weakened
