# F09 · Reschedule · API spec (API)

---

# PART A · For readers

One endpoint: `POST /appointments/{id}/reschedule` with the new start time. It needs `If-Match` (the version on screen) and an `Idempotency-Key`. Success returns the moved booking and its new version; a failure leaves the booking untouched.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoint

| ID | Method + path | Who | Middleware | Success |
| --- | --- | --- | --- | --- |
| API-09.1 | `POST /api/v1/appointments/{appointment}/reschedule` | advisor (own dealership), customer (own) | `auth:sanctum`, `can:reschedule-appointment`, `idempotent`, `if-match` | 200 `AppointmentResource` + `ETag` |

The availability grid for the screen is F06's `GET /dealerships/{d}/availability?serviceTypeId=&date=&excludeAppointmentId={id}`.

## B2. Contract

```json
{ "newStart": "2026-11-02T14:00:00+07:00", "keepTechnician": true }
```

| Field | Rule |
| --- | --- |
| `newStart` | Required, ISO 8601 with offset (same regex as F02 `desiredStart`), on a 15-minute local step |
| `keepTechnician` | Optional boolean, default `true`; ignored for customers (always `true`) |
| other fields (`serviceTypeId`, `vehicleId`, `status`…) | Ignored |

Response: `AppointmentResource` with the new `start`, `end`, `technician`, `bay`, `version`; same `id` and `reference`.

Errors: `409 NO_AVAILABILITY` with `reason`, `requestedStart`, `requestedEnd`, `alternatives` (F06, computed with `excludeAppointmentId`); `422 TOO_LATE_TO_RESCHEDULE` with `dealershipPhone`; others as RQ §B8.

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Input | Expected |
| --- | --- |
| `newStart` without offset | 400 |
| `newStart` = `2026-11-02T14:05:00+07:00` | 400 (15-minute step) |
| `newStart` equal to the current start (in another offset, same instant) | 422 `RESCHEDULE_SAME_TIME` |
| `newStart` in the past | 422 `START_IN_PAST` |
| `newStart` on a closed day | 422 `OUTSIDE_OPENING_HOURS` |
| `keepTechnician` = `"yes"` | 400 |
| Body has `serviceTypeId` of another service | Ignored |
| `{appointment}` of another customer (customer) | 404 `APPOINTMENT_NOT_FOUND` |
| No `If-Match` | 428 |
| No `Idempotency-Key` | 400 `IDEMPOTENCY_KEY_REQUIRED` |
| Same key, different `newStart` | 422 `IDEMPOTENCY_KEY_MISMATCH` |
| Same key resent after success (booking now at a newer version) | The saved 200, not 412 |

## B5. Bounds

Same as F02: 90 days ahead, 15-minute step; customers ≥ 2 hours before the current start; 3 pair tries.
