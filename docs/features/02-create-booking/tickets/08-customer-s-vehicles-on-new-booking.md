# 08: Customer's vehicles on New booking

**What to build:** `GET /customers/{customer}/vehicles` feeds the vehicle picker, and the New booking form is complete: its date picker starts at today in the dealership's time zone, not the browser's (WEB §B3).

**Blocked by:** [07-customer-search-for-staff](07-customer-search-for-staff.md)

**Status:** done

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-37

- [x] TC-02-37 (WEB §B3; Web component): given Clock 2026-11-02T18:30Z (01:30 on 3 Nov in Ho Chi Minh); browser `TZ=America/New_York`; when Render New booking; then The date picker's minimum is 2026-11-03. Test: `web/src/staff/booking/NewBookingPage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
