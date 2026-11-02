# 17: Web: SubmitButton and IdempotentRetryPanel

**What to build:** `SubmitButton` blocks a double submit and `IdempotentRetryPanel` shows the in-progress and no-response copy with a "Retry" that resends with the same key (WEB-04.2–04.4, WEB §B2).

**Blocked by:** [16-web-useidempotencykey](16-web-useidempotencykey.md)

**Status:** ready-for-agent

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-36, TC-04-37, TC-04-39

- [ ] TC-04-36 (WEB-04.2, WEB-04.4; Web component): given Fake API: first call times out, second 201; when Submit; see the panel; tap "Retry"; then Panel "No response from the server yet"; the second call sends the **same** `Idempotency-Key`. Test: `…/IdempotentRetryPanel.test.tsx`.
- [ ] TC-04-37 (WEB-04.3; Web component): given Fake API hangs; when Click submit twice quickly; then Exactly 1 request; the button shows "Confirming…" and is `disabled`. Test: `…/SubmitButton.test.tsx`.
- [ ] TC-04-39 (WEB §B2; Web component): given Fake API returns 409 `REQUEST_IN_PROGRESS`; when Submit; then `inProgress.body` copy; "Retry" uses the same key. Test: `…/IdempotentRetryPanel.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
