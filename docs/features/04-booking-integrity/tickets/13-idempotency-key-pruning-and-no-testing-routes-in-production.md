# 13: Idempotency key pruning and no testing routes in production

**What to build:** The daily `idempotency:prune` command deletes keys older than `IDEMPOTENCY_TTL_HOURS` (AC-04.19). `/_testing/*` routes are registered only in the testing environment (AC-04.23, INV-04.14).

**Blocked by:** [12-idempotency-failure-paths-and-header-checks](12-idempotency-failure-paths-and-header-checks.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-19, TC-04-28

- [x] TC-04-19 (AC-04.19; Integration): given Row K with `expires_at` one hour ago; when `artisan idempotency:prune`; then send K; then Row deleted; the request runs as new. Test: `…::test_AC_04_19_expired_keys_are_pruned_and_ignored`.
- [x] TC-04-28 (AC-04.23, INV-04.14; Integration): given Boot the app with `APP_ENV=local` (child process); when `php artisan route:list --json`; then No route starts with `api/v1/_testing`; `testing:request` not in `list`. Test: `TestingRoutesTest::test_AC_04_23_testing_routes_only_in_testing`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
