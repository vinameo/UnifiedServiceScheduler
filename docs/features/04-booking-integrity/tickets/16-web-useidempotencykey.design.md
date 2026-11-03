# 16: Web: useIdempotencyKey — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| `useIdempotencyKey(actionInput)` per-attempt key retention (WEB §4 WEB-04.1) | WEB-04.1, D-G.15 | `web/src/shared/idempotency/useIdempotencyKey.ts` | key state retained across renders while canonical JSON of input is identical | in-memory state only (never written to localStorage or cookies) | `docs/features/README.md:66` (D-G.15) | follow |
| Input change produces a fresh UUID (WEB §4 WEB-04.1) | WEB-04.1, TC-04-35 | `web/src/shared/idempotency/useIdempotencyKey.ts` | input hash/stable string changes triggers generateKey() | old UUID replaced in React state | `CLAUDE.md:154-159` | follow |
| `settle(result)` resets key on 2xx and terminal 4xx; keeps key on network error, 5xx, or 409 REQUEST_IN_PROGRESS (WEB §4 WEB-04.1) | WEB-04.1, D-04.12, TC-04-35 | `web/src/shared/idempotency/useIdempotencyKey.ts` | inspects status and problemCode: keeps on null/undefined, status >= 500, or (status === 409 && problemCode === 'REQUEST_IN_PROGRESS'); resets on all others | previous key discarded on reset; fresh key generated on next access or immediately | `web/src/shared/api/problem.ts` | follow |

