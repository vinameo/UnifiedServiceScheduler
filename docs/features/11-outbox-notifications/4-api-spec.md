# F11 · Notifications through the outbox · API spec (API)

---

# PART A · For readers

Three admin APIs: list outbox messages (with filters), a summary of counts, and resend a failed message. Sending itself is a background job with no API.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Who | Middleware | Success |
| --- | --- | --- | --- | --- |
| API-11.1 | `GET /api/v1/outbox?status=&channel=&eventType=&appointmentId=&cursor=&limit=` | admin | `auth:sanctum`, `can:admin` | 200 page of `OutboxMessage` |
| API-11.2 | `GET /api/v1/outbox/summary` | admin | same | 200 counts |
| API-11.3 | `POST /api/v1/outbox/{message}/retry` | admin | same + `idempotent` | 200 `OutboxMessage` |

## B2. Contracts

### Query (API-11.1)

| Parameter | Rule |
| --- | --- |
| `status` | Optional: `PENDING`, `RETRYING`, `FAILED`, `SENT`, `SKIPPED` (`RETRYING` = `PENDING` with `attempts > 0`, `PENDING` = `attempts = 0`) |
| `channel` | Optional: `SMS`, `EMAIL`, `DMS` |
| `eventType` | Optional: the 5 event types |
| `appointmentId` | Optional UUID |
| `limit`, `cursor` | As F05 (default 50, max 200) |

### `OutboxMessage`

```json
{ "id": "…", "eventId": "…", "eventType": "AppointmentCancelled", "channel": "EMAIL",
  "status": "FAILED", "displayStatus": "Failed", "attempts": 5,
  "nextAttemptAt": null, "lastError": "SMTP timeout after 5 s", "sentAt": null,
  "createdAt": "2026-11-02T16:20:41+07:00",
  "appointment": { "id": "…", "reference": "APT-20261102-0031", "customerName": "Nguyễn Văn Hùng" } }
```

`customerName` is shown to admins; phone and email never appear.

### API-11.2

```json
{ "data": { "pending": 1, "retrying": 1, "failed": 2, "sentToday": 214 } }
```

`sentToday` = `SENT` with `sent_at` on today's local date of the admin's dealership.

### API-11.3

No body. Returns the message with `status PENDING`, `attempts 0`.

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| 11.1 | `status=LOST`, `channel=FAX` | 400 |
| 11.1 | `appointmentId=abc` | 400 |
| 11.1 | Advisor, foreman, customer | 403 |
| 11.3 | `{message}` of another dealership / missing / `abc` | 404 `OUTBOX_MESSAGE_NOT_FOUND` |
| 11.3 | A `SENT` or `PENDING` message | 422 `OUTBOX_NOT_RETRYABLE` |
| 11.3 | No `Idempotency-Key` | 400 `IDEMPOTENCY_KEY_REQUIRED` |
| 11.3 | Same key twice | The saved 200; one reset only |
| 11.3 | Body `{ "attempts": -5 }` | Ignored |

## B5. Bounds

`limit` ≤ 200; relay batch 50 every 10 s; 5 attempts; channel timeout 5 s.
