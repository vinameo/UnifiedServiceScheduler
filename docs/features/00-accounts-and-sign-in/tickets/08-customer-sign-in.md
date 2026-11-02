# 08: Customer sign-in

**What to build:** A customer signs in on the customer Sign-in screen and lands on My bookings. Staff accounts cannot use the customer portal and customers cannot use the staff portal (AC-00.6). The adversarial input table of API §B4 is covered for both auth endpoints.

**Blocked by:** [07-customer-sign-up](07-customer-sign-up.md)

**Status:** ready-for-agent

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-06, TC-00-08, TC-00-20, TC-00-21, TC-00-23, TC-00-30

- [ ] TC-00-06 (AC-00.4; Integration): given Customer with password `secret12`; when Customer sign-in with `wrong123`; then 401 `INVALID_CREDENTIALS`, `title` = "Email or password is incorrect.". Test: `Feature/Auth/SignInTest::test_AC_00_4_wrong_password_is_401`.
- [ ] TC-00-08 (AC-00.6, INV-00.4; Integration): given Advisor and customer; when Advisor on the customer portal; customer on the staff portal; then Both 401 `INVALID_CREDENTIALS`. Test: `…SignInTest::test_AC_00_6_wrong_portal_is_401` (data provider).
- [ ] TC-00-20 (API §B4; Integration): when Each row of the adversarial input table; then Status and `code` as in the table. Test: `Feature/Auth/AuthAdversarialInputTest::test_adversarial_input` (data provider named per row).
- [ ] TC-00-21 (AC-00.4, WEB-00.5; Web component): given Fake API returns 401 `INVALID_CREDENTIALS`; when Submit Sign-in; then `role=alert` shows "Email or password is incorrect."; password empty, email kept. Test: `web/src/customer/auth/SignInPage.test.tsx`.
- [ ] TC-00-23 (AC-00.12, WEB-00.2; Web component): given `GET /me` → 401; when Render `/my-bookings`; then At `/sign-in?returnTo=%2Fmy-bookings`. Test: `web/src/shared/auth/RequireRole.test.tsx`.
- [ ] TC-00-30 (API §B4; Integration): given Stateful request without the XSRF header; when `POST /auth/customer/sign-in`; then 419 `CSRF_TOKEN_MISMATCH`. Test: `…AuthAdversarialInputTest::test_missing_xsrf_is_419`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
