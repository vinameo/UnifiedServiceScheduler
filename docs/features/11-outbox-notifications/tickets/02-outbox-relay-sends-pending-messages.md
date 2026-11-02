# 02: Outbox relay sends pending messages (tracer bullet)

**What to build:** `php artisan outbox:relay` (every 10 s, `withoutOverlapping()`) sends `PENDING` rows through their channel and marks them `SENT`; the email goes to the customer's account email and the log line has no phone or email (AC-11.5, AC-11.1). A failing channel never fails the booking (AC-11.2).

**Blocked by:** [01-prefactor-notification-channels-and-recipient-resolver](01-prefactor-notification-channels-and-recipient-resolver.md)

**Status:** ready-for-agent

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-01, TC-11-02

- [ ] TC-11-01 (AC-11.5, AC-11.1; Integration): given A confirmed booking → 3 `PENDING` rows; recording fake channels; `Mail::fake()`; when `runOnce(now)`; then 3 rows `SENT`, `sent_at` = now, `attempts` 1; the SMS fake got the customer's phone; the mailable went to the account email; log lines have no phone/email. Test: `OutboxRelayTest::test_AC_11_5_relay_sends_all_channels`.
- [ ] TC-11-02 (AC-11.2, BR-11.1; Integration): given Every channel bound to `FailingChannel`; when `POST /appointments`; then 201; response time comparable to normal (no channel call); rows `PENDING`. Test: `…::test_AC_11_2_failing_channels_do_not_affect_booking`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
- [ ] Run `/code-review` on the branch and fix findings before later tickets build on it
