# 09: Sign-in rate limits

**What to build:** Five failed sign-ins per email within 15 minutes lock that email (429 `TOO_MANY_ATTEMPTS` with `Retry-After`), a success resets the counter, and one IP is capped (API §B5). The counter lives in the shared `database` cache (ADR-00.2) and holds under parallel attempts. The web shows the 429 copy with the minutes.

**Blocked by:** [08-customer-sign-in](08-customer-sign-in.md)

**Status:** ready-for-agent

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-09, TC-00-10, TC-00-11, TC-00-19, TC-00-29, TC-00-32, TC-00-33

- [ ] TC-00-09 (AC-00.5, INV-00.5; Integration): given Customer; when 5 failures, then the right password; then Try 6: 429 `TOO_MANY_ATTEMPTS` with `Retry-After` ≤ 900. Test: `…SignInTest::test_AC_00_5_sixth_attempt_is_blocked_even_with_right_password`.
- [ ] TC-00-10 (AC-00.10, INV-00.5; Integration): given As TC-00-09; when `travel(16)->minutes()`, correct sign-in; then 200. Test: `…SignInTest::test_AC_00_10_lock_ends_after_15_minutes`.
- [ ] TC-00-11 (INV-00.5; Integration): given Customer; when 4 failures, 1 success, 4 failures; then Last try 401 (not 429). Test: `…SignInTest::test_INV_00_5_success_clears_the_counter`.
- [ ] TC-00-19 (API §B5; Integration): when 31 sign-ins (different emails) from one IP within 1 minute; then Try 31: 429. Test: `…SignInTest::test_ip_limit_blocks_the_31st_request`.
- [ ] TC-00-29 (AC-00.5, WEB §B3; Web component): given Fake API returns 429, `Retry-After: 840`; when Submit Sign-in; then "Too many attempts. Try again in 14 minutes.". Test: `…SignInPage.test.tsx`.
- [ ] TC-00-32 (INV-00.5; Concurrency): given Customer; `CACHE_STORE=database` passed to the child processes; when 10 processes sign in with a wrong password at the same moment (`Parallel`); then At most 5 responses are 401, the rest 429; at most 5 `Hash::check` calls (counted in the request log lines). Test: `Feature/Auth/ParallelSignInTest::test_INV_00_5_parallel_attempts_cannot_bypass_the_lock`.
- [ ] TC-00-33 (R-00.1; Unit): when Read `config('cache.default')` with the `.env.example` values; then `database`. Test: `Unit/Config/SharedCacheStoreTest`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
