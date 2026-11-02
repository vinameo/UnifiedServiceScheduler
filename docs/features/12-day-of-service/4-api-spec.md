# F12 · Day-of-service operations · API spec (API)

---

# PART A · For readers

Five booking actions (check in, start, complete, no-show, extend), absence and out-of-service reports with a preview, and the workshop board. Every change needs `If-Match` (booking actions) and an `Idempotency-Key`.

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
| API-12.6 | `GET /api/v1/technicians/{technician}/absence-impact?from=&to=` | foreman | `can:run-workshop` | 200 `ImpactPreview` |
| API-12.7 | `POST /api/v1/technicians/{technician}/absences` | foreman | `can:run-workshop`, `idempotent` | 201 `ApplyResult` |
| API-12.8 | `GET /api/v1/bays/{bay}/outage-impact?from=&to=` | foreman | `can:run-workshop` | 200 `ImpactPreview` |
| API-12.9 | `POST /api/v1/bays/{bay}/out-of-service` | foreman | `can:run-workshop`, `idempotent` | 201 `ApplyResult` |
| API-12.10 | `GET /api/v1/dealerships/{dealership}/workshop-board?date=` | every staff role | `can:view-board` | 200 `WorkshopBoard` |
| API-12.11 | `GET /api/v1/dealerships/{dealership}/appointments?contactRequired=true` | staff | F05 | F05 page |

`{technician}` and `{bay}` resolve only inside the staff member's dealership (404 `TECHNICIAN_NOT_FOUND` / `BAY_NOT_FOUND`).

## B2. Contracts

API-12.1–12.4: no body (extra fields ignored).

API-12.5 request `{ "minutes": 30 }`; response:

```json
{ "data": { "appointment": { …AppointmentResource… },
  "affected": [ { "appointmentId": "…", "reference": "APT-20261102-0051", "outcome": "REASSIGNED",
                  "technician": { "id": "…", "name": "Lý Hoàng Dũng" }, "bay": { "id": "…", "name": "Bay 2" } },
                { "appointmentId": "…", "reference": "APT-20261102-0052", "outcome": "CONTACT_REQUIRED" } ] } }
```

API-12.7 / 12.9 request:

```json
{ "from": "2026-11-03T08:00:00+07:00", "to": "2026-11-03T17:00:00+07:00", "reason": "SICK_LEAVE" }
```

`reason`: absences `SICK_LEAVE`, `TRAINING`, `PERSONAL`, `OTHER`; outages `EQUIPMENT_FAULT`, `MAINTENANCE`, `OTHER`.

`ImpactPreview` (API-12.6, 12.8) and `ApplyResult` (API-12.7, 12.9):

```json
{ "data": { "absence": { "id": "…", "from": "…", "to": "…", "reason": "SICK_LEAVE" },
  "summary": { "affected": 3, "reassigned": 2, "contactRequired": 1, "modified": 0 },
  "results": [
    { "appointmentId": "…", "reference": "…", "start": "…", "end": "…", "customerName": "Đặng Hữu Phúc",
      "vehicle": "Toyota Corolla Cross", "serviceName": "20,000 km service",
      "outcome": "REASSIGNED", "from": { "technician": "Ngô Bảo Em", "bay": "Bay 3" },
      "to": { "technician": "Lý Hoàng Dũng", "bay": "Bay 3" } },
    { "appointmentId": "…", "outcome": "CONTACT_REQUIRED", "explanation": "NO_QUALIFIED_TECHNICIAN", "customerPhone": "0912555019", … } ] } }
```

Preview: `absence` is `null` and the outcomes are proposals (`REASSIGNABLE`, `CONTACT_REQUIRED`). `customerPhone` is returned to staff for the "Call customer" action.

`WorkshopBoard` (API-12.10):

```json
{ "data": { "date": "2026-11-02", "now": "2026-11-02T10:50:00+07:00", "opensAt": "08:00", "closesAt": "17:00",
  "bays": [ { "id": "…", "code": "K3", "name": "Bay 3", "bayType": "STANDARD",
              "blocks": [ { "appointmentId": "…", "reference": "…", "status": "IN_PROGRESS", "start": "…", "end": "…",
                            "customerName": "Phạm Quốc Bảo", "serviceName": "20,000 km service",
                            "technicianName": "Võ Thị Chi", "overrunMinutes": 20 } ] } ],
  "displaced": [ { "appointmentId": "…", "reference": "…", "start": "…", "end": "…", "reason": "OVERRUN" } ],
  "technicians": [ { "id": "…", "name": "Trần Minh An", "skills": ["BASIC"], "shift": { "startsAt": "08:00", "endsAt": "17:00" },
                     "bookedMinutes": 120, "availability": "PRESENT" } ],
  "alerts": [ { "type": "OVERRUN", "appointmentId": "…", "bayName": "Bay 3", "minutesOver": 20,
                "nextBooking": { "appointmentId": "…", "start": "…", "status": "CANCELLED" } | null } ] } }
```

`availability`: `PRESENT`, `ABSENT` (absence overlapping now/the day), `OVERRUN` (has an overrunning job).

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
| 12.3 | Complete a `CHECKED_IN` (not started) booking | 422 `INVALID_STATUS_TRANSITION` |
| 12.7 | `to <= from`; range 15 days; `from` yesterday; no offset | 400 |
| 12.7 | `reason: "EQUIPMENT_FAULT"` for an absence | 400 |
| 12.7 | Technician of another dealership / `abc` | 404 `TECHNICIAN_NOT_FOUND` |
| 12.9 | Bay of another dealership | 404 `BAY_NOT_FOUND` |
| 12.7 | Same key twice | The saved 201; one absence row |
| 12.10 | `date=2026-02-30` | 400 |
| 12.1–12.5 | No `If-Match` | 428 |

## B5. Bounds

| Bound | Value |
| --- | --- |
| Extend minutes | 15, 30, 45, 60, 90, 120 |
| Absence/outage length | ≤ 14 days, not in the past |
| Affected bookings per apply | ≤ 200 (more → 400 asking for a shorter range) |
| Extend retries | 1 |
