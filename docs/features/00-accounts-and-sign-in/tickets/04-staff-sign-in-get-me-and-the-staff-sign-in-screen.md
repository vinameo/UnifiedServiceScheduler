# 04: Staff sign-in, GET /me and the Staff sign-in screen (tracer bullet)

**What to build:** A seeded advisor opens the Staff sign-in screen, signs in, and `GET /me` returns their `Me` (role, dealership). Wrong email or password gives the same `INVALID_CREDENTIALS` copy. Builds `useMe()` and `web/src/shared/api/errorCopy.ts` (README §5).

**Blocked by:** [03-seed-dealership-staff-and-demo-customers](03-seed-dealership-staff-and-demo-customers.md)

**Status:** done

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-07, TC-00-18, TC-00-26

- [x] TC-00-07 (INV-00.4; Integration): when Sign in with an unknown email; sign in with a real email and wrong password; then Bodies identical except `correlationId`; both call `Hash::check` (spy). Test: `…SignInTest::test_INV_00_4_unknown_email_and_wrong_password_look_the_same`.
- [x] TC-00-18 (INV-00.8; Integration): given Session id before sign-in; when Sign in; then Session id after differs from before. Test: `…SignInTest::test_INV_00_8_sign_in_regenerates_the_session`.
- [x] TC-00-26 (INV-00.9; Integration): when Read `Route::getRoutes()`; then No route matches `auth/staff/` other than `sign-in`. Test: `Feature/Auth/RouteListTest::test_INV_00_9_no_staff_sign_up_route`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
- [x] Run `/code-review` on the branch and fix findings before later tickets build on it
