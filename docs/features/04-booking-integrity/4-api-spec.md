# F04 · No double booking, no duplicate requests · API spec (API)

---

# PART A · For readers

F04 adds no endpoint for users. It sets the **header rules** every writing endpoint follows:

- Every `POST` that writes sends `Idempotency-Key: <UUID>` (one key per user action; resending that action reuses the key).
- Every change to an existing appointment sends `If-Match: "<version>"` taken from the `ETag` of the last read.
- Every read/write of an appointment returns `ETag: "<version>"`.

And the shared JSON shape of an appointment, used by every endpoint.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Header rules

| ID | Header | Where | Rule |
| --- | --- | --- | --- |
| API-04.1 | `Idempotency-Key` (request) | Every writing `POST` except auth (README D-G.8) | A UUID (any version). Missing → 400 `IDEMPOTENCY_KEY_REQUIRED`; malformed → 400 `VALIDATION_ERROR`. The key belongs to the user (D-04.11) and lives 24 hours (`IDEMPOTENCY_TTL_HOURS`). |
| API-04.2 | `Idempotent-Replayed: true` (response) | Replayed answers | Present only when returning a saved answer. |
| API-04.3 | `If-Match` (request) | Every change to an existing appointment (F07, F09, F10 confirm, F12) | Form `"N"`. Missing → 428; malformed → 400. |
| API-04.4 | `ETag` (response) | Every response returning an appointment (200, 201, 412) | `"N"` = current `version`. |
| API-04.5 | `Location` (response) | 201 on create | `/api/v1/appointments/{id}` |

Middleware order on a change route: `auth:sanctum` → `idempotent` → `if-match` → controller (INV-04.8).

## B2. `AppointmentResource` (D-04.19)

```json
{
  "data": {
    "id": "0193…",
    "reference": "APT-20261102-0042",
    "status": "CONFIRMED",
    "start": "2026-11-02T09:00:00+07:00",
    "end": "2026-11-02T10:00:00+07:00",
    "expiresAt": null,
    "version": 1,
    "dealership": { "id": "…", "name": "Saigon Auto", "branchName": "District 7", "timezone": "Asia/Ho_Chi_Minh", "phone": "028 3812 3456" },
    "serviceType": { "id": "…", "code": "OIL_CHANGE", "name": "Oil change", "durationMinutes": 60 },
    "technician": { "id": "…", "name": "Trần Minh An" },
    "bay": { "id": "…", "code": "K2", "name": "Bay 2", "bayType": "QUICK" },
    "customer": { "id": "…", "fullName": "Nguyễn Văn Hùng", "phone": "0903123218" },
    "vehicle": { "id": "…", "licencePlate": "51H-123.45", "make": "Toyota", "model": "Vios", "year": 2021, "vin": "RL4BT3F3X0M001234" },
    "reminderOptIn": true,
    "source": "STAFF",
    "cancellation": null,
    "contactRequiredReason": null,
    "createdAt": "2026-10-28T16:40:00+07:00",
    "createdBy": { "name": "Minh Anh", "role": "SERVICE_ADVISOR" }
  }
}
```

- Times use the offset of the dealership's time zone (README D-G.7).
- `vehicle` is `null` for a `HELD` appointment without a vehicle yet (F10).
- `cancellation`: `{ "reason": "CUSTOMER_BUSY", "note": "…", "at": "…", "byRole": "SERVICE_ADVISOR" }` when `CANCELLED` (F07).
- `createdBy` only for staff; customers get `null`.
- `version` in the body equals the `ETag` value.

## B3. Error table

[1-requirement-spec.md](1-requirement-spec.md) §B8. 409 example:

```json
{ "type": "/problems/no-availability", "title": "No free technician and bay for this time.", "status": 409,
  "code": "NO_AVAILABILITY", "reason": "NO_TECHNICIAN_AVAILABLE",
  "requestedStart": "2026-11-02T09:00:00+07:00", "requestedEnd": "2026-11-02T10:00:00+07:00",
  "correlationId": "7f3c9a1e…" }
```

412 example: `{ "code": "APPOINTMENT_MODIFIED", "status": 412, "appointment": { …AppointmentResource… } }` + header `ETag: "4"`.

## B4. Adversarial input table (header rules, for every endpoint using them)

| Input | Expected |
| --- | --- |
| No `Idempotency-Key` | 400 `IDEMPOTENCY_KEY_REQUIRED` |
| `Idempotency-Key: abc` | 400 `VALIDATION_ERROR` |
| `Idempotency-Key` twice (two headers) | Take the first; if they differ → 400 `VALIDATION_ERROR` |
| Same key, different body | 422 `IDEMPOTENCY_KEY_MISMATCH` |
| Same key, same body with JSON keys in another order | Treated as equal (canonical hash) → the saved answer |
| Same key on another endpoint | 422 `IDEMPOTENCY_KEY_MISMATCH` |
| No `If-Match` | 428 `PRECONDITION_REQUIRED` |
| `If-Match: *` | 400 `VALIDATION_ERROR` |
| `If-Match: W/"3"` | 400 `VALIDATION_ERROR` |
| `If-Match: "0"`, `"-1"`, `"3.5"`, `"abc"` | 400 `VALIDATION_ERROR` |
| `If-Match: "999"` (higher than real) | 412 `APPOINTMENT_MODIFIED` |

## B5. Test-only routes (ADR-04.5, INV-04.14)

| Route | Middleware | What it does |
| --- | --- | --- |
| `POST /api/v1/_testing/appointments` | `auth:sanctum`, `idempotent` | Body `{dealershipId, customerId, vehicleId, serviceTypeId, start}` → F03 finds pairs → `AppointmentWriter::create` → 201 `AppointmentResource` / 409 |
| `POST /api/v1/_testing/appointments/{id}/touch` | `auth:sanctum`, `idempotent`, `if-match` | `updateVersioned` (toggles `reminder_opt_in`) → 200 / 412 |
| `POST /api/v1/_testing/appointments/{id}/transition` | `auth:sanctum`, `idempotent`, `if-match` | Body `{to}` → `TransitionAppointment` → 200 / 412 / 422 |
| `POST /api/v1/_testing/echo` | `auth:sanctum`, `idempotent` | Increments a counter in a test table and returns it; shows "ran again or not"; `?fail=500` returns 500 |

## B6. Bounds

| Bound | Value |
| --- | --- |
| Key TTL | 24 hours (`IDEMPOTENCY_TTL_HOURS`) |
| Stale lock | 60 seconds |
| Saved body | ≤ 64 KB |
| Pair tries | 3 |
| Pairs taken from F03 | ≤ 5 |
