# 02: Customer holds a time and confirms it (tracer bullet)

**What to build:** `POST /appointments` with `mode: HOLD` creates a `HELD` booking with `expires_at = now + HOLD_MINUTES` and no vehicle (AC-10.1); `POST /appointments/{id}/confirm` with a vehicle and `If-Match` makes it `CONFIRMED` with one `AppointmentConfirmed` event (AC-10.7). The customer taps "Hold and continue" and sees the Temporary hold screen with a 05:00 countdown.

**Blocked by:** [01-prefactor-expiredholdreleaser-and-useserverclock](01-prefactor-expiredholdreleaser-and-useserverclock.md)

**Status:** done

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-01, TC-10-08, TC-10-14

- [x] TC-10-01 (AC-10.1; Integration): given Customer; clock 08:00; when `POST /appointments` `mode HOLD` 10:00, no vehicle; then 201, `status HELD`, `expiresAt` = 08:05+07:00, `vehicle null`; 2 active allocations (TECHNICIAN, BAY). Test: `HoldTest::test_AC_10_1_hold_blocks_the_slot`.
- [x] TC-10-08 (AC-10.7; Integration): given A's hold, vehicle V of A; when Confirm `{vehicleId: V}` with `If-Match "1"`; then 200 `CONFIRMED`, `expiresAt null`, `ETag "2"`; 3 active allocations; 1 `AppointmentConfirmed` event; history `CONFIRMED`. Test: `ConfirmHoldTest::test_AC_10_7_confirm_adds_the_vehicle`.
- [x] TC-10-14 (AC-10.1 (web); Web component): given Fake API: hold 201 with `expiresAt` 5 minutes after the `Date` header; browser clock 2 minutes off; when Tap "Hold and continue"; then Navigates to the hold page; countdown shows 05:00 (server time), not 03:00. Test: `web/src/customer/booking/HoldPage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
- [x] Run `/code-review` on the branch and fix findings before later tickets build on it
