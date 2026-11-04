# 08: holds:expire job

**What to build:** `php artisan holds:expire`, scheduled every 30 s, expires every `HELD` booking past `expires_at` through the version check and `status = 'HELD'`, freeing its allocations (AC-10.10).

**Blocked by:** [07-parallel-holds-by-one-customer](07-parallel-holds-by-one-customer.md)

**Status:** done

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-13

- [x] TC-10-13 (AC-10.10; Integration): given 3 expired holds, 1 valid; when `artisan holds:expire`; then 3 `EXPIRED` with inactive allocations and history rows (actor null); the valid one unchanged; no outbox rows. Test: `ExpireHoldsCommandTest::test_AC_10_10_job_expires_only_expired_holds`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
