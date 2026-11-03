# 16: Web: useIdempotencyKey

**What to build:** `useIdempotencyKey(actionInputs)` keeps one key per attempt of an action and reuses it only to resend after no response, a 5xx or 409 `REQUEST_IN_PROGRESS` (WEB-04.1, D-G.15).

**Blocked by:** [15-appointmentwriter-moveto](15-appointmentwriter-moveto.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-35

- [x] TC-04-35 (WEB-04.1; Web unit): when Call the hook with input A, A, then B, then `reset()`; then `settle()` with a no-response, a 503, a 409 `REQUEST_IN_PROGRESS`, a 412, a 409 `NO_AVAILABILITY`, a 201; then Same key for A; new for B; new after reset; kept after no-response/503/`REQUEST_IN_PROGRESS`; new after 412, `NO_AVAILABILITY`, 201. Test: `web/src/shared/idempotency/useIdempotencyKey.test.ts`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
