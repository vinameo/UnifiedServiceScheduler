# 06: Suggestion chips in the error card

**What to build:** The error card shows the alternatives as chips; tapping one selects that time, hides the card and the next confirm uses a new key (AC-06.15, WEB-06.4).

**Blocked by:** [05-alternatives-on-409-no-availability](05-alternatives-on-409-no-availability.md)

**Status:** done

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-16

- [x] TC-06-16 (AC-06.15, WEB-06.4; Web component): given Fake API: 409 with 3 suggestions, then 201; when Confirm; tap "10:00 today"; Confirm; then The card disappears, 10:00 selected; the second request has `desiredStart` 10:00 and a different `Idempotency-Key` from the first. Test: `web/src/staff/booking/NewBookingPage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
