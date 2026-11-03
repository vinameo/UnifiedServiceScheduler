# 12: Idempotency failure paths and header checks

**What to build:** A 5xx answer is not saved and the request really runs again; a stale in-progress row is taken over; the key check runs before `If-Match`; malformed `If-Match` and `Idempotency-Key` headers get their 4xx (AC-04.17, AC-04.18, INV-04.8, D-04.13, D-04.15).

**Blocked by:** [11-idempotency-middleware-replay-mismatch-in-progress](11-idempotency-middleware-replay-mismatch-in-progress.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-17, TC-04-18, TC-04-21, TC-04-23

- [x] TC-04-17 (AC-04.17; Integration): given `POST /_testing/echo?fail=500` with K; when Send K without `fail`; then The second request really runs (counter up), 200. Test: `…::test_AC_04_17_server_errors_are_not_saved`.
- [x] TC-04-18 (AC-04.18, D-04.13; Integration): given Row K inserted by hand as `IN_PROGRESS`, `locked_until = now - 1s`; when Send K; then Runs, 200; row becomes `COMPLETED`. Test: `…::test_AC_04_18_stale_lock_is_taken_over`.
- [x] TC-04-21 (INV-04.8; Integration): given Appointment at version 2; key K already has a saved 200 from the previous change; when Send K again with `If-Match: "1"` (old); then The saved 200, **not** 412. Test: `MiddlewareOrderTest::test_INV_04_8_idempotency_runs_before_if_match`.
- [x] TC-04-23 (D-04.15, API §B4; Integration): given Appointment at version 1; when `touch` with `If-Match`: `*`, `W/"1"`, `"0"`, `"-1"`, `"1.5"`, `abc`, `"999"`; `Idempotency-Key`: missing, `abc`; then 400 ×6, 412 for `"999"`; 400 `IDEMPOTENCY_KEY_REQUIRED`, 400 `VALIDATION_ERROR`. Test: `HeaderAdversarialInputTest::test_header_adversarial_input` (data provider).
- [x] README §8 checks green; no existing test skipped, deleted or weakened
