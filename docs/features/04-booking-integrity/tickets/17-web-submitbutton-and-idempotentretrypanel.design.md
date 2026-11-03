# 17: Web: SubmitButton and IdempotentRetryPanel — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| `SubmitButton` double click prevention (WEB §4 WEB-04.3) | WEB-04.3, TC-04-37 | `web/src/shared/idempotency/SubmitButton.tsx` | button is `disabled` and shows `loading` with `pendingLabel` ("Confirming…") while pending | subsequent clicks ignored while pending | `CLAUDE.md:154-159` | follow |
| `IdempotentRetryPanel` for no-response and 409 REQUEST_IN_PROGRESS (WEB §4 WEB-04.4, WEB §B2, §B3) | WEB-04.4, TC-04-36, TC-04-39 | `web/src/shared/idempotency/IdempotentRetryPanel.tsx` | renders specific copy for `no-response` vs `in-progress`; staff variant displays shortened key (`aaaa…bbbb`), customer variant omits key | retry button invokes onRetry with same key and body | `docs/features/README.md:66` (D-G.15) | follow |
| `idempotentPost` client wrapper (WEB §4 WEB-04.2) | WEB-04.2 | `web/src/shared/idempotency/idempotentPost.ts` | injects `Idempotency-Key` and optional `If-Match` headers; categorizes outcomes into ok, problem, or no-response | timeout configured to 15s | `web/src/shared/api/client.ts` | follow |

