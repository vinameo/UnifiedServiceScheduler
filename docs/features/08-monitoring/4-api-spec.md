# F08 · Monitoring · API spec (API)

---

# PART A · For readers

Two APIs for the admin screen (a summary per time range; error search), plus `/metrics` for monitoring systems on the internal network, and the two existing health endpoints.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Who | Note |
| --- | --- | --- | --- |
| API-08.1 | `GET /api/v1/monitoring/summary?range=1h|24h|7d` | `can:admin` | |
| API-08.2 | `GET /api/v1/monitoring/errors?correlationId=&cursor=&limit=` | `can:admin` | Cursor paging (F05), default 20 |
| API-08.3 | `GET /metrics` | Internal (D-08.7) | `text/plain; version=0.0.4` |
| API-08.4 | `GET /health/live`, `GET /health/ready` | No auth | Existing |

## B2. Contracts

### API-08.1

```json
{ "data": {
  "range": "24h", "from": "2026-11-01T17:00:00+07:00", "to": "2026-11-02T17:00:00+07:00",
  "health": {
    "app": { "status": "UP", "bookingP95Ms": 184 },
    "database": { "status": "UP", "connectionsUsed": 8, "connectionsMax": 100 },
    "outbox": { "pending": 2, "oldestPendingAgeSeconds": 420 } },
  "bookings": { "total": 1248, "confirmed": 1102, "noAvailability": 131, "validationError": 0,
                "error": 15, "p95Ms": 184, "allocationRetries": 23, "idempotentReplays": 41,
                "appointmentModified": 7 },
  "recentErrors": [ ApiEvent… (≤ 20, newest first) ] } }
```

The `bookings` figures are counted from `api_events` within the range (not from `metric_counters`, which hold totals since the start). `database.status = DOWN` when `select 1` fails (the API still returns 200 if it can read the rest; if the DB is fully down the endpoint returns 500 and the screen shows the error).

### `ApiEvent`

```json
{ "id": 812, "occurredAt": "2026-11-02T16:44:03+07:00", "endpoint": "POST /appointments",
  "code": "NO_AVAILABILITY", "status": 409, "correlationId": "7f3c9a1e-…",
  "details": "No Standard service bay free 10:00–12:00" }
```

`details` is built from the redacted `detail`: 409 `NO_AVAILABILITY` → a sentence from `reason` and the time range; 5xx → exception class + redacted message, ≤ 200 characters; `DELIVERY_FAILED` (F11) → "{event} for {reference}, all 5 attempts used".

### API-08.3 (illustrative)

```
# TYPE booking_requests_total counter
booking_requests_total{outcome="confirmed"} 1102
booking_requests_total{outcome="no_availability"} 131
# TYPE booking_duration_seconds histogram
booking_duration_seconds_bucket{le="0.3"} 1180
…
# TYPE outbox_pending_messages gauge
outbox_pending_messages 2
```

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| 08.1 | `range=30d`, missing `range` | 400 |
| 08.2 | `correlationId` > 128 characters or with characters outside `[A-Za-z0-9._-]` | 400 |
| 08.2 | `correlationId` of an error at another dealership | 200, `data: []` |
| 08.1, 08.2 | Advisor, foreman, customer | 403 |
| 08.3 | Header `X-Forwarded-For: 10.0.0.1` from outside | 404 (header not trusted) |
| 08.3 | Through the public Nginx | 404 |

## B5. Bounds

`recentErrors` ≤ 20; `errors` `limit` ≤ 100; 7 days of data.
