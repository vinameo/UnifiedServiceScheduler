# 05: RequireRole route guard and safe returnTo

**What to build:** Staff routes are wrapped in `RequireRole`: without a session the user is sent to the right sign-in screen with a `returnTo`, and an external `returnTo` (`//evil.com`) is ignored (WEB-00.4).

**Blocked by:** [04-staff-sign-in-get-me-and-the-staff-sign-in-screen](04-staff-sign-in-get-me-and-the-staff-sign-in-screen.md)

**Status:** ready-for-agent

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-24

- [ ] TC-00-24 (AC-00.12, WEB-00.4; Web component): given `GET /me` → 401; when Render `/staff/appointments`; then sign in with `returnTo=//evil.com`; then At `/staff/sign-in?returnTo=…`; after sign-in lands on the role home, never `//evil.com`. Test: `…RequireRole.test.tsx`, `web/src/shared/auth/returnTo.test.ts`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
