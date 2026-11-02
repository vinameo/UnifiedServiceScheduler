# F06 · Suggest other free times · API spec (API)

---

# PART A · For readers

Two read APIs: one day's time grid (every slot, free or not), and "the next free time". Plus an `alternatives` member on the `409 NO_AVAILABILITY` error of every book/reschedule endpoint.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Who | Middleware |
| --- | --- | --- | --- |
| API-06.1 | `GET /api/v1/dealerships/{dealership}/availability` | customer (any dealership), staff (own dealership) | `auth:sanctum`, `throttle:120,1` |
| API-06.2 | `GET /api/v1/dealerships/{dealership}/availability/next` | same | same |
| API-06.3 | `alternatives` member of `409 NO_AVAILABILITY` | — | — |

## B2. Contracts

### API-06.1

Query: `serviceTypeId` (UUID, required), `date` (`YYYY-MM-DD`, required), `excludeAppointmentId` (UUID, optional).

```json
{ "data": {
    "date": "2026-11-02", "timezone": "Asia/Ho_Chi_Minh", "closed": false,
    "opensAt": "08:00", "closesAt": "17:00", "stepMinutes": 15, "durationMinutes": 60,
    "freeCount": 30,
    "slots": [
      { "start": "2026-11-02T08:00:00+07:00", "end": "2026-11-02T09:00:00+07:00", "available": true, "reason": null },
      { "start": "2026-11-02T08:15:00+07:00", "end": "2026-11-02T09:15:00+07:00", "available": false, "reason": "NO_TECHNICIAN_AVAILABLE" }
    ] } }
```

A closed day: `closed: true`, `opensAt`/`closesAt` `null`, `slots: []`, `freeCount: 0`.

### API-06.2

Query: `serviceTypeId`, `after` (ISO 8601 with offset). Returns `{ "data": { "nextAvailable": { "start": "…", "end": "…" } | null } }`. Searches `after` … `after + 7 days`.

### API-06.3

```json
{ "code": "NO_AVAILABILITY", "status": 409, "reason": "NO_TECHNICIAN_AVAILABLE",
  "requestedStart": "2026-11-02T09:00:00+07:00", "requestedEnd": "2026-11-02T10:00:00+07:00",
  "alternatives": [
    { "start": "2026-11-02T10:00:00+07:00", "end": "2026-11-02T11:00:00+07:00" },
    { "start": "2026-11-02T11:15:00+07:00", "end": "2026-11-02T12:15:00+07:00" },
    { "start": "2026-11-03T08:00:00+07:00", "end": "2026-11-03T09:00:00+07:00" } ] }
```

A 409 with `alternatives` is saved with its idempotency key (F04): resending the same key returns the same old suggestions.

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| 06.1 | Missing `date` / `date=2026-11-31` / `date=today` | 400 |
| 06.1 | `serviceTypeId=abc` | 400 |
| 06.1 | `serviceTypeId` of another dealership | 404 `SERVICE_TYPE_NOT_FOUND` |
| 06.1 | `date` yesterday | 422 `START_IN_PAST` |
| 06.1 | `date` = today + 91 | 422 `START_TOO_FAR_AHEAD` |
| 06.1 | `excludeAppointmentId` of another customer (called by a customer) | 404 `APPOINTMENT_NOT_FOUND` |
| 06.1 | `excludeAppointmentId=abc` | 400 |
| 06.2 | `after` without an offset | 400 |
| 06.1, 06.2 | 121 requests in a minute | 429 |

## B5. Bounds

| Bound | Value |
| --- | --- |
| Date asked | today … today + 90 |
| Suggestions | ≤ 3, within 8 days (the wanted day + 7) |
| `next` | within 7 days after `after` |
| Rate limit | 120/minute per user |
