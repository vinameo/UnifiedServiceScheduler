# 07: Customer sign-up

**What to build:** A visitor creates a customer account with email + password on the Sign-up screen and is signed in at once. Duplicate email answers 409 `EMAIL_ALREADY_USED`; role and dealership in the body are ignored; no response or log line carries the password or email.

**Blocked by:** [06-staff-header-and-menu](06-staff-header-and-menu.md)

**Status:** done

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-01, TC-00-02, TC-00-03, TC-00-04, TC-00-05, TC-00-17, TC-00-22, TC-00-31, TC-00-34

- [x] TC-00-01 (AC-00.1; Integration): given No user `hung@mail.com`; when Valid `POST /auth/customer/sign-up`, then `GET /me` in the same session; then 201, `Me` body with role `CUSTOMER`; `GET /me` 200; 1 `customers` row with the right `user_id`. Test: `Feature/Auth/CustomerSignUpTest::test_AC_00_1_sign_up_creates_account_customer_and_session`.
- [x] TC-00-02 (AC-00.2, INV-00.1; Integration): given `hung@mail.com` exists; when Sign up with `HUNG@mail.com`; then 409 `EMAIL_ALREADY_USED`; still 1 user. Test: `…CustomerSignUpTest::test_AC_00_2_email_is_case_insensitive`.
- [x] TC-00-03 (INV-00.1; Integration): given Repository replaced by one that always says "email is free", user already exists; when Sign up with that email; then 409 `EMAIL_ALREADY_USED` (not 500); still 1 user. Test: `…CustomerSignUpTest::test_INV_00_1_unique_violation_maps_to_409`.
- [x] TC-00-04 (AC-00.3, INV-00.3; Integration): given Valid form except password `abc123`; when Sign up; then 422 `WEAK_PASSWORD`; `users`, `customers` counts unchanged. Test: `…CustomerSignUpTest::test_AC_00_3_weak_password_saves_nothing`.
- [x] TC-00-05 (INV-00.6, INV-00.7; Integration): when Sign up with `"role":"ADMIN","dealershipId":"<id>"`; then 201; user is `CUSTOMER`, `dealership_id` NULL. Test: `…CustomerSignUpTest::test_INV_00_6_role_in_body_is_ignored`.
- [x] TC-00-17 (INV-00.2; Integration): given Test log channel writes to an array; when Sign-up, failed sign-in, `GET /me`; then No response has `password`; no log line contains an email or password. Test: `Feature/Auth/NoSecretsTest::test_INV_00_2_no_password_or_email_in_responses_or_logs`.
- [x] TC-00-22 (WEB §B3; Web component): given Fake API returns 400 (`errors.phone`), 422 `WEAK_PASSWORD`, 409 in turn; when Submit Sign-up; then Error on Phone; error on Password; Alert with a "Sign in" link. Test: `web/src/customer/auth/SignUpPage.test.tsx`.
- [x] TC-00-31 (INV-00.3; Integration): given The `customers` insert is forced to fail (fake repository breaks the phone CHECK); when Sign up; then 500; no `users` row left. Test: `…CustomerSignUpTest::test_INV_00_3_sign_up_is_all_or_nothing`.
- [x] TC-00-34 (WEB §B3; Web unit): when `rules.ts` against each sign-up row of API §B4; then Same accept/reject as the API table. Test: `web/src/shared/auth/rules.test.ts`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
