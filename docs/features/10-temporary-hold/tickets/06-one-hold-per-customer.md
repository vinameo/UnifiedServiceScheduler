# 06: One hold per customer

**What to build:** Add the one-hold-per-customer partial index (DB-10.1). A new hold expires the customer's older one (allocations inactive), and a failed new hold leaves the old one `HELD` (AC-10.5, AC-10.6).

**Blocked by:** [05-expired-hold-screen](05-expired-hold-screen.md)

**Status:** done

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-05, TC-10-06, TC-10-17

- [x] TC-10-05 (AC-10.5; Integration): given A holds 10:00; when A holds 11:00; then 10:00 hold `EXPIRED` with inactive allocations; 11:00 `HELD`; exactly one `HELD` for A. Test: `…::test_AC_10_5_new_hold_ends_the_old_one`.
- [x] TC-10-06 (AC-10.6; Integration): given A holds 10:00; 11:00 full; when A holds 11:00; then 409 `NO_AVAILABILITY`; the 10:00 hold still `HELD`, version unchanged. Test: `…::test_AC_10_6_failed_new_hold_keeps_the_old_one`.
- [x] TC-10-17 (DB-10.1; Integration): given Two `HELD` rows for one customer inserted before the migration; when Run the migration; then It stops with the message; after expiring one, it runs. Test: `HoldIndexMigrationTest`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
