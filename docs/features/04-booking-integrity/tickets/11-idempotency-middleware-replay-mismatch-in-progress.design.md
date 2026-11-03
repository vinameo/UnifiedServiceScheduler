# 11: Idempotency middleware: replay, mismatch, in progress — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| Idempotency middleware execution order and key scoping (RQ D-04.10, D-04.11) | AC-04.3, AC-04.16, INV-04.9 | `api/app/Http/Middleware/Idempotency.php` | atomic insert with ON CONFLICT (user_id, key) DO NOTHING; parallel requests get 409 REQUEST_IN_PROGRESS | keys scoped to user_id; expired keys (expires_at <= now) deleted before retry | `CLAUDE.md:158` (Core rule 3) | follow |
| Body canonical hash calculation (RQ D-04.14, TC-04-24) | D-04.14, INV-04.10 | `api/app/Http/Middleware/Idempotency.php:canonicalizeBody` | SHA-256 of METHOD \n path \n canonical JSON body (keys sorted recursively); different payload returns 422 | request body invariant to JSON key order; path-bound prevents cross-endpoint key reuse | `1-requirement-spec.md:64` | follow |
| Replaying saved < 500 responses (RQ D-04.12, AC-04.5) | AC-04.5, D-04.12 | `api/app/Http/Middleware/Idempotency.php:handle` | responses with HTTP < 500 (2xx, 400, 409, 412, 422) saved as COMPLETED; HTTP >= 500 deletes key row | saved response returned with Idempotent-Replayed: true; dispatches IdempotentReplayed | `CLAUDE.md:162` | follow |
| Testing echo route for idempotency verification (API §B5, TC-04-24) | AC-04.5, TC-04-24 | `api/app/Http/Controllers/Api/V1/Testing/TestingEchoController.php` | increments counter in _testing_echo_counter table and echoes body; ?fail=500 returns 500 | test-only route isolated under testing prefix | `4-api-spec.md:107` | follow |

