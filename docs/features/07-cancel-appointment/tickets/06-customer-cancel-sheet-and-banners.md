# 06: Customer cancel sheet and banners

**What to build:** Customer details shows the cancel sheet and, inside 2 hours, no cancel button but "It's less than 2 hours…" with the dealership's number (AC-07.13, WEB-07.4).

**Blocked by:** [05-customer-cancels-api](05-customer-cancels-api.md)

**Status:** ready-for-agent

**Branch:** `feature/07-cancel-appointment`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-07-16

- [ ] TC-07-16 (AC-07.13, WEB-07.4; Web component): given Fake clock 08:00; booking 09:00; when Render customer details; then No Cancel button; "It's less than 2 hours … call 028 3812 3456 to cancel.". Test: `web/src/customer/bookings/BookingDetailsPage.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
