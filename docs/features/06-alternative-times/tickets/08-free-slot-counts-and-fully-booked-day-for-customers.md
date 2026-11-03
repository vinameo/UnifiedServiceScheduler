# 08: Free-slot counts and fully booked day for customers

**What to build:** The customer's Choose date and time shows free-slot counts per day and, for a full day, "This day is fully booked" with the next available slot as a chip (AC-06.14, WEB-06.5).

**Blocked by:** [07-next-available-time](07-next-available-time.md)

**Status:** done

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-15

- [x] TC-06-15 (AC-06.14, WEB-06.5; Web component): given Fake API: `freeCount 0`; `next` = Fri 6 Nov 08:00; when Render step 2, tap the chip; then "This day is fully booked"; "Next available slot for Oil change:"; "Fri 6 Nov · 08:00"; the chosen day becomes 6 Nov, time 08:00. Test: `web/src/customer/booking/ChooseTimePage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
