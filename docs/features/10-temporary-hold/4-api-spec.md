# F10 · Temporary hold for self-booking · API spec (API)

---

# PART A · For readers

The existing booking endpoint gains `mode: HOLD` for customers: it returns a booking with status `HELD` and an `expiresAt`. A new endpoint confirms the hold with the chosen vehicle. Both need an `Idempotency-Key`; confirm also needs `If-Match`.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Who | Middleware | Success |
| --- | --- | --- | --- | --- |
| API-10.1 | `POST /api/v1/appointments` with `mode: HOLD` | customer | as F02 API-02.1 | 201 `AppointmentResource` (`status HELD`, `expiresAt`) + `ETag: "1"` |
| API-10.2 | `POST /api/v1/appointments/{appointment}/confirm` | customer (own hold) | `auth:sanctum`, `can:confirm-hold`, `idempotent`, `if-match` | 200 `AppointmentResource` (`status CONFIRMED`) + `ETag` |

## B2. Contracts

### API-10.1

```json
{ "dealershipId": "…", "serviceTypeId": "…", "desiredStart": "2026-11-02T10:00:00+07:00", "mode": "HOLD", "vehicleId": null }
```

Same rules as F02 API §B2, except: `mode` ∈ `CONFIRM`, `HOLD`; with `HOLD`, `vehicleId` is optional (`null` or omitted). Response `expiresAt` = creation time + `HOLD_MINUTES`, ISO with the dealership offset. The response also carries a `Date` header (standard) used by the web to align clocks.

### API-10.2

```json
{ "vehicleId": "0193…", "reminderOptIn": true }
```

| Field | Rule |
| --- | --- |
| `vehicleId` | Required, UUID, a vehicle of the signed-in customer |
| `reminderOptIn` | Optional boolean, default `true` |

Response: `AppointmentResource` with `status CONFIRMED`, `expiresAt null`, `vehicle` set.

## B3. Error table

RQ §B8.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| 10.1 | Advisor sends `mode: HOLD` | 422 `HOLD_NOT_ALLOWED` |
| 10.1 | `mode: "hold"` (lower case) / `"LOCK"` | 400 |
| 10.1 | `vehicleId` of another customer with `HOLD` | 404 `VEHICLE_NOT_FOUND` |
| 10.1 | Two holds in parallel by one customer (with or without a current hold) | Both 201; the later one ended the earlier one; never two `HELD`, never 412 |
| 10.2 | Missing `vehicleId` | 400 |
| 10.2 | `vehicleId` of another customer | 404 `VEHICLE_NOT_FOUND` |
| 10.2 | `{appointment}` of another customer | 404 `APPOINTMENT_NOT_FOUND` |
| 10.2 | Confirm a `CONFIRMED` booking with a new key | 422 `INVALID_STATUS_TRANSITION` (or 412 if the version is old) |
| 10.2 | Confirm again with the same key | The saved 200 |
| 10.2 | No `If-Match` | 428 |
| 10.2 | Staff call it | 403 |
| 10.2 | Body has `expiresAt` far in the future | Ignored |

## B5. Bounds

| Bound | Value |
| --- | --- |
| Hold length | `HOLD_MINUTES` (default 5) |
| Holds per customer | 1 |
| Job interval | 30 s, ≤ 500 holds per run |
