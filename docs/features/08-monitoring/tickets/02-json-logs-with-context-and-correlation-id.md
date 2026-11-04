# 02: JSON logs with context and correlation id

**What to build:** Logs are JSON lines with `correlationId`, `dealershipId`, `userId`, `appointmentId`; every response carries `X-Correlation-Id`, taken from Nginx's request id when present (AC-08.1, AC-08.5, INV-08.1).

**Blocked by:** [01-prefactor-personal-data-redaction-processor](01-prefactor-personal-data-redaction-processor.md)

**Status:** done

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-01, TC-08-05

- [x] TC-08-01 (AC-08.1, D-08.3; Integration): given Logs go to `TestHandler`; when `GET /api/v1/me` (401) without the header; then Header `X-Correlation-Id` = X; exactly 1 `request` log line with `correlationId` X, `status 401`, `durationMs`. Test: `RequestLogTest::test_AC_08_1_response_and_log_share_correlation_id`.
- [x] TC-08-05 (AC-08.5, INV-08.1; Integration): given `TestHandler`; when Sign-up (email, phone), failed sign-in, booking, cancel (F07), a deliberate 500 (test route); then Every record is `json_encode`-able; each has `correlationId`; none matches an email regex, `\b0\d{9}\b`, `"password"`, `XSRF`, `laravel_session`. Test: `LogRedactionTest::test_AC_08_5_logs_have_no_personal_data`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
