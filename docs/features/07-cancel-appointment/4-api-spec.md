# F07 · Cancel an appointment · API spec (API)

---

# PART A · For readers

One endpoint: `POST /appointments/{id}/cancel` with a reason. It needs `If-Match` (the version on screen) and an `Idempotency-Key`. Success returns the cancelled booking and its new version.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoint

| ID | Method + path | Who | Middleware | Success |
| --- | --- | --- | --- | --- |
| API-07.1 | `POST /api/v1/appointments/{appointment}/cancel` | advisor (own dealership), customer (own) | `auth:sanctum`, `can:cancel-appointment`, `idempotent`, `if-match` | 200 `AppointmentResource` + `ETag` |

## B2. Contract

```json
{ "reason": "CUSTOMER_BUSY", "note": "Customer will call back to book next week." }
```

| Field | Rule |
| --- | --- |
| `reason` | Advisor: required, one of `CUSTOMER_REQUEST`, `CUSTOMER_BUSY`, `DONE_ELSEWHERE`, `DEALERSHIP_ISSUE`, `OTHER`. Customer: optional, one of `SOMETHING_CAME_UP`, `DONE_ELSEWHERE`, `OTHER`. |
| `note` | Optional, string, trimmed, ≤ 500 |

Response: `AppointmentResource` with `status: CANCELLED`, `cancellation: { reason, note, at, byRole }` (customers get `reason`/`note` only when they cancelled themselves; when staff cancelled, the customer sees `byRole: SERVICE_ADVISOR`, `reason: null`, `note: null`).

`422 TOO_LATE_TO_CANCEL`: `{ "dealershipPhone": "028 3812 3456", "start": "…" }`.

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Input | Expected |
| --- | --- |
| An advisor sends `reason: "SOMETHING_CAME_UP"` (a customer reason) | 400 |
| A customer sends `reason: "DEALERSHIP_ISSUE"` | 400 |
| `reason: "customer_busy"` (lower case) | 400 |
| `note` of 501 characters | 400 |
| `note` is a number | 400 |
| `{appointment}` = `abc` | 404 `APPOINTMENT_NOT_FOUND` |
| No `If-Match` | 428 |
| `If-Match: W/"1"` | 400 |
| No `Idempotency-Key` | 400 `IDEMPOTENCY_KEY_REQUIRED` |
| Same key, different `reason` | 422 `IDEMPOTENCY_KEY_MISMATCH` |
| Body has `status: "CONFIRMED"` | Ignored |

## B5. Bounds

`note` ≤ 500. The 2-hour customer limit. No dedicated rate limit (a booking can be cancelled only once).
