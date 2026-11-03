# 10: Sign-out, global 401 handling and role gates

**What to build:** Sign-out ends the session so the old cookie gets 401. Every web call that answers 401 sends the user to the right sign-in screen. Customer-only and staff-only routes answer 403 to the wrong role and 401 without a session, through Gates, not controller `if`s (INV-00.10).

**Blocked by:** [09-sign-in-rate-limits](09-sign-in-rate-limits.md)

**Status:** done

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-12, TC-00-13

- [x] TC-00-12 (AC-00.9, INV-00.8; Integration): given Signed-in customer; when Sign out, then `GET /me` with the old cookie; then 204; then 401 `UNAUTHENTICATED`. Test: `Feature/Auth/SignOutTest::test_AC_00_9_old_session_is_rejected_after_sign_out`.
- [x] TC-00-13 (AC-00.7, INV-00.10; Integration): given The test registers temporary routes `GET /api/v1/_test/staff-only` (`can:staff`) and `…/customer-only` (`can:customer`); when Customer calls staff-only; advisor calls customer-only; no session calls both; then 403 `FORBIDDEN`, 403, 401. Test: `Feature/Auth/GatesTest::test_AC_00_7_customer_cannot_use_staff_routes`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
