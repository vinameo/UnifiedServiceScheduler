# 09: Resend a failed message

**What to build:** Admin `POST /outbox/{id}/retry` moves a `FAILED` row back to `PENDING` so the relay sends it again; other states answer 422 `OUTBOX_NOT_RETRYABLE`; a repeated key replays (AC-11.4, INV-11.6, D-11.8). The adversarial input table of API §B4 is covered.

**Blocked by:** [08-outbox-list-and-summary-api](08-outbox-list-and-summary-api.md)

**Status:** done

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-05, TC-11-13, TC-11-16

- [x] TC-11-05 (AC-11.4, INV-11.6; Integration): given A `FAILED` row of a booking at version v; when Admin `POST /outbox/{id}/retry`; then `runOnce` with a working channel; then 200 `PENDING`, `attempts 0`; then `SENT`; the booking's `version` still v, no new history row. Test: `OutboxAdminTest::test_AC_11_4_retry_failed_message`.
- [x] TC-11-13 (D-11.8; Integration): given A `SENT` row; a `FAILED` row; when Retry the `SENT` row; retry the `FAILED` row twice with the same key; then 422 `OUTBOX_NOT_RETRYABLE`; 200 then the saved 200. Test: `…::test_only_failed_messages_retry`.
- [x] TC-11-16 (API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `OutboxAdversarialInputTest`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
