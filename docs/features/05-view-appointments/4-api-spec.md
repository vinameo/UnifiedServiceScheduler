# F05 · View appointments · API spec (API)

---

# PART A · For readers

Four read APIs: one booking (with `ETag`), one booking's history (staff), the day list (staff), and My bookings (customer). Lists page with a cursor.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Who | Success |
| --- | --- | --- | --- |
| API-05.1 | `GET /api/v1/appointments/{appointment}` | customer (own), every staff role (own dealership) | 200 `AppointmentResource` + `ETag` |
| API-05.2 | `GET /api/v1/appointments/{appointment}/history` | `can:staff` | 200 `AppointmentEvent[]` |
| API-05.3 | `GET /api/v1/dealerships/{dealership}/appointments` | `can:staff` | 200 page of `AppointmentListItem` |
| API-05.4 | `GET /api/v1/me/appointments` | `can:customer` | 200 page of `AppointmentListItem` |

All under `auth:sanctum`. No write headers.

## B2. Contracts

### Query

| Endpoint | Parameter | Rule (400 if broken) |
| --- | --- | --- |
| 05.3 | `date` | Required, `YYYY-MM-DD`, a valid date |
| 05.3 | `technicianId`, `bayId` | Optional, UUID |
| 05.3 | `status` | Optional, comma-separated list, each value one of the 8 statuses; default every status except `EXPIRED` |
| 05.3, 05.4 | `limit` | 1–200, default 50 |
| 05.3, 05.4 | `cursor` | A string returned by the server; bad → 400 |
| 05.4 | `scope` | Required, `upcoming` or `past` |

### Page

```json
{ "data": [ AppointmentListItem… ], "meta": { "nextCursor": "eyJz…" | null, "limit": 50 } }
```

`AppointmentListItem`:

```json
{ "id": "…", "reference": "APT-20261102-0042", "status": "CONFIRMED",
  "start": "2026-11-02T09:00:00+07:00", "end": "2026-11-02T10:00:00+07:00",
  "customer": { "id": "…", "fullName": "Nguyễn Văn Hùng" },
  "vehicle": { "make": "Toyota", "model": "Vios", "licencePlate": "51H-123.45" },
  "serviceType": { "id": "…", "name": "Oil change", "durationMinutes": 60 },
  "technician": { "id": "…", "name": "Trần Minh An" },
  "bay": { "id": "…", "code": "K2", "name": "Bay 2" },
  "dealership": { "id": "…", "name": "Saigon Auto", "branchName": "District 7", "timezone": "Asia/Ho_Chi_Minh" },
  "contactRequiredReason": null }
```

`vehicle` may be `null` (never for customers, since `HELD` is hidden from them; staff may see a `HELD` without a vehicle).

### `AppointmentEvent` (API-05.2)

```json
{ "id": "…", "type": "CANCELLED", "fromStatus": "CONFIRMED", "toStatus": "CANCELLED", "version": 4,
  "actor": { "name": "Minh Anh", "role": "SERVICE_ADVISOR" } | null,
  "data": { "reason": "CUSTOMER_BUSY" }, "occurredAt": "2026-11-01T15:10:00+07:00" }
```

Sorted by `occurredAt` ascending. `actor: null` = the system (a job).

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| 05.1 | `{appointment}` = `abc` | 404 `APPOINTMENT_NOT_FOUND` |
| 05.1 | Another customer's / another dealership's booking | 404 `APPOINTMENT_NOT_FOUND` (body identical to a missing id except `correlationId`) |
| 05.3 | `date=2026-13-01`, `date=02/11/2026` | 400 |
| 05.3 | `status=FOO` | 400 |
| 05.3 | `status=CONFIRMED,` (empty item) | 400 |
| 05.3 | `limit=0`, `limit=201`, `limit=abc` | 400 |
| 05.3 | `cursor=xyz`, a cursor from another endpoint | 400 |
| 05.3 | `technicianId` of another dealership | 200, `data: []` |
| 05.4 | `scope=all` | 400 |
| 05.4 | `customerId=<B>` | Ignored |

## B5. Bounds

`limit` ≤ 200. History ≤ 200 rows (cut, `meta.truncated`). No dedicated rate limit; M-05.2 watches id probing.
