# 05: Expired hold screen

**What to build:** When the countdown reaches 0, or confirm answers 410 or 412 with `status EXPIRED`, the screen shows "Your hold has expired" and "Pick a time again", keeping the entered vehicle (AC-10.12).

**Blocked by:** [04-confirm-rules](04-confirm-rules.md)

**Status:** done

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-16

- [x] TC-10-16 (AC-10.12; Web component): given Fake timers; fake API confirm → 410; when Let the countdown reach 0; separately confirm and get 410; also 412 with `status EXPIRED`; then Expired state with the copy; "Pick a time again" → `/book/time` with the vehicle kept in the draft. Test: `…/HoldPage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
