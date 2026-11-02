# F12 · Day-of-service operations · API spec (API)

---

# PART A · For readers

Five booking actions (check in, start, complete, no-show, extend) and the workshop board. Every change needs `If-Match` and an `Idempotency-Key`. No absence or bay-outage endpoints (README D-G.16).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Who | Middleware | Success |
| --- | --- | --- | --- | --- |
| API-12.1 | `POST /api/v1/appointments/{appointment}/check-in` | advisor, foreman | `auth:sanctum`, `can:check-in`, `idempotent`, `if-match` | 200 `AppointmentResource` + `ETag` |
| API-12.2 | `POST /api/v1/appointments/{appointment}/start` | foreman | `can:run-workshop`, `idempotent`, `if-match` | 200 |
| API-12.3 | `POST /api/v1/appointments/{appointment}/complete` | foreman | same | 200 |
| API-12.4 | `POST /api/v1/appointments/{appointment}/no-show` | advisor, foreman | `can:check-in`, `idempotent`, `if-match` | 200 |
| API-12.5 | `POST /api/v1/appointments/{appointment}/extend` | foreman | `can:run-workshop`, `idempotent`, `if-match` | 200 `ExtendResult` + `ETag` |
| API-12.10 | `GET /api/v1/dealerships/{dealership}/workshop-board?date=` | every staff role | `can:view-board` | 200 `WorkshopBoard` |
| API-12.11 | `GET /api/v1/dealerships/{dealership}/appointments?contactRequired=true` | staff | F05 | F05 page |

`{appointment}` resolves through the F05 binding (404 `APPOINTMENT_NOT_FOUND` outside the staff member's dealership); `{dealership}` through the F01 binding.

## B2. Contracts

API-12.1–12.4: no body (extra fields ignored).

API-12.5 request `{ "minutes": 30 }`; response:

```json
{ "data": { "appointment": { …AppointmentResource… },
  "affected": [ { "appointmentId": "…", "reference": "APT-20261102-0051", "outcome": "REASSIGNED",
                  "technician": { "id": "…", "name": "Lý Hoàng Dũng" }, "bay": { "id": "…", "name": "Bay 2" } },
                { "appointmentId": "…", "reference": "APT-20261102-0052", "outcome": "CONTACT_REQUIRED" } ] } }
```

`WorkshopBoard` (API-12.10):

```json
{ "data": { "date": "2026-11-02", "now": "2026-11-02T10:50:00+07:00", "opensAt": "08:00", "closesAt": "17:00",
  "bays": [ { "id": "…", "code": "K3", "name": "Bay 3", "bayType": "STANDARD",
              "blocks": [ { "appointmentId": "…", "version": 3, "reference": "…", "status": "IN_PROGRESS", "start": "…", "end": "…",
                            "customerName": "Phạm Quốc Bảo", "serviceName": "20,000 km service",
                            "technicianName": "Võ Thị Chi", "overrunMinutes": 20 } ] } ],
  "displaced": [ { "appointmentId": "…", "reference": "…", "start": "…", "end": "…", "reason": "OVERRUN" } ],
  "technicians": [ { "id": "…", "name": "Trần Minh An", "level": 2, "bookedMinutes": 120, "status": "WORKING" } ],
  "alerts": [ { "type": "OVERRUN", "appointmentId": "…", "version": 3, "bayName": "Bay 3", "minutesOver": 20,
                "nextBooking": { "appointmentId": "…", "start": "…", "status": "CANCELLED" } | null } ] } }
```

`technicians` lists the dealership's **active** technicians (RQ D-12.16). `status`: `WORKING`, or `OVERRUN` when the technician has an overrunning job. `blocks[].version` and `alerts[].version` are the appointment's current version (RQ D-12.15).

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| 12.5 | `minutes: 20`, `minutes: 0`, `minutes: "30"`, `minutes: 600` | 400 |
| 12.5 | Extend a `CONFIRMED` (not checked in) booking | 422 `INVALID_STATUS_TRANSITION` |
| 12.5 | Advisor extends | 403 |
| 12.1 | Check in tomorrow's booking | 422 `NOT_SERVICE_DAY` |
| 12.4 | No-show before start | 422 `NO_SHOW_BEFORE_START` |
| 12.1 | Check in a booking displaced by an overrun | 422 `NEEDS_RESCHEDULE` |
| 12.3 | Complete a `CHECKED_IN` (not started) booking | 422 `INVALID_STATUS_TRANSITION` |
| 12.10 | `date=2026-02-30` | 400 |
| 12.1–12.5 | Booking of another dealership | 404 `APPOINTMENT_NOT_FOUND` |
| 12.1 | Same key twice | The saved answer; one `CHECKED_IN` history row |
| 12.1–12.5 | No `If-Match` | 428 |

## B5. Bounds

| Bound | Value |
| --- | --- |
| Extend minutes | 15, 30, 45, 60, 90, 120 |
| Extend retries | 1 |
