# 11: Idempotency middleware: replay, mismatch, in progress

**What to build:** The `Idempotency` middleware (alias `idempotent`) requires an `Idempotency-Key` on writing POSTs, replays the saved answer for the same key + body hash, answers 422 `IDEMPOTENCY_KEY_MISMATCH` for another body and 409 `REQUEST_IN_PROGRESS` while it runs; keys are scoped per user (AC-04.3–04.5, AC-04.12, AC-04.16, D-04.14).

**Blocked by:** [10-transitionappointment-and-appointment-history](10-transitionappointment-and-appointment-history.md)

**Status:** ready-for-agent

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-03, TC-04-04, TC-04-05, TC-04-12, TC-04-16, TC-04-24

- [ ] TC-04-03 (AC-04.3, INV-04.10; Integration): given `POST /_testing/appointments` with K → 201; when Send the identical request with K; then 201, same `id`, `Location`, `ETag`; header `Idempotent-Replayed: true`; 1 booking; `IdempotentReplayed` dispatched once. Test: `IdempotencyTest::test_AC_04_3_same_key_same_body_replays`.
- [ ] TC-04-04 (AC-04.4; Integration): given K used with vehicle v1; when Send K with vehicle v2; then 422 `IDEMPOTENCY_KEY_MISMATCH`; no new booking. Test: `…::test_AC_04_4_same_key_different_body_is_422`.
- [ ] TC-04-05 (AC-04.5, D-04.12; Integration): given First request with K returns 409 (no slot); when Free the slot, send K again; then Still the saved 409 (not 201); the writer does not run (`_testing/echo` counter or spy). Test: `…::test_AC_04_5_saved_409_is_replayed`.
- [ ] TC-04-12 (AC-04.12, D-04.10; Concurrency): when 10 processes, same K, same body; then 1 booking; every response is 201 with the same `id` or 409 `REQUEST_IN_PROGRESS`; ≥ 1 × 201. Test: `ParallelIdempotencyTest::test_AC_04_12_same_key_in_parallel_books_once`.
- [ ] TC-04-16 (AC-04.16, D-04.11; Integration): given User A used K → 201; when User B sends K with B's body; then 201 with **B's** booking (different id). Test: `IdempotencyTest::test_AC_04_16_keys_are_per_user`.
- [ ] TC-04-24 (D-04.14; Integration): given K used with body `{"a":1,"b":2}` at `/_testing/echo`; when Send K with `{"b":2,"a":1}`; then K at `/_testing/appointments`; then First: the saved answer; second: 422 MISMATCH. Test: `IdempotencyTest::test_D_04_14_hash_is_canonical_and_path_bound`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
