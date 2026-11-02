# F02 · Create a booking · API spec (API)

---

# PART A · For readers

One booking endpoint (`POST /appointments`) for both advisors and customers, plus four helper endpoints: advisors search customers and read a customer's vehicles; customers read and add their own vehicles. Booking needs an `Idempotency-Key`; success returns 201 with the reference and an `ETag`.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Who | Middleware | Success |
| --- | --- | --- | --- | --- |
| API-02.1 | `POST /api/v1/appointments` | advisor, customer | `auth:sanctum`, `can:create-appointment`, `idempotent` | 201 `AppointmentResource` + `Location` + `ETag: "1"` |
| API-02.2 | `GET /api/v1/customers?query=` | advisor | `auth:sanctum`, `can:search-customers`, `throttle:60,1` | 200 `CustomerSummary[]` |
| API-02.3 | `GET /api/v1/customers/{customer}/vehicles` | advisor | `auth:sanctum`, `can:search-customers` | 200 `Vehicle[]` |
| API-02.4 | `GET /api/v1/me/vehicles` | customer | `auth:sanctum`, `can:customer` | 200 `Vehicle[]` |
| API-02.5 | `POST /api/v1/me/vehicles` | customer | `auth:sanctum`, `can:customer`, `idempotent` | 201 `Vehicle` |

## B2. Contracts

### API-02.1 request

Advisor:
```json
{ "customerId": "0193…", "vehicleId": "0193…", "serviceTypeId": "0193…",
  "desiredStart": "2026-11-02T09:00:00+07:00", "reminderOptIn": true }
```

Customer:
```json
{ "dealershipId": "0193…", "vehicleId": "0193…", "serviceTypeId": "0193…",
  "desiredStart": "2026-11-02T10:00:00+07:00", "reminderOptIn": true, "mode": "CONFIRM" }
```

| Field | Rule (400 if broken) |
| --- | --- |
| `customerId` | Advisor: required, UUID. Customer: ignored. |
| `dealershipId` | Customer: required, UUID. Advisor: ignored. |
| `vehicleId`, `serviceTypeId` | Required, UUID |
| `desiredStart` | Required, ISO 8601 string **with** an offset (`Z` or `±HH:MM`), regex `^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$`; on a 15-minute step in local time, seconds = 0 |
| `reminderOptIn` | Optional, boolean, default `true` |
| `mode` | Optional, only `CONFIRM` (F10 adds `HOLD`) |
| other fields | Ignored |

Response: `AppointmentResource` (F04 API §B2).

### API-02.2 `CustomerSummary`

`query`: required, trimmed, 3–100 characters. Returns at most 20, ordered by `fullName`.

```json
{ "data": [ { "id": "…", "fullName": "Nguyễn Văn Hùng", "phone": "0903123218", "email": "hung@example.test", "vehicleCount": 1 } ] }
```

### `Vehicle` (API-02.3, 02.4, 02.5)

```json
{ "id": "…", "licencePlate": "51H-123.45", "make": "Toyota", "model": "Vios", "year": 2021, "vin": "RL4BT3F3X0M001234" }
```

API-02.5 request: `{ "licencePlate": "51G-999.99", "make": "Honda", "model": "City", "year": 2022, "vin": null }`

| Field | Rule |
| --- | --- |
| `licencePlate` | Required, trimmed, 4–20 characters; after normalising (`plate_key`) 4–12 letters/digits |
| `make` | Required, 1–40 |
| `model` | Required, 1–60 |
| `year` | Optional, integer 1950..(current year + 1) |
| `vin` | Optional, 17 characters `[A-HJ-NPR-Z0-9]` (case-insensitive, stored upper case) |

## B3. Error table

[1-requirement-spec.md](1-requirement-spec.md) §B8. Extra members:

- `OUTSIDE_OPENING_HOURS`: `opensAt`, `closesAt` (`HH:mm` local for that day, `null` if closed), `latestStart` (ISO with offset or `null`), `durationMinutes`.
- `START_TOO_FAR_AHEAD`: `maxDate` (`YYYY-MM-DD` local).
- `NO_AVAILABILITY`, `VEHICLE_ALREADY_BOOKED`: as in F04 API §B3.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| 02.1 | `desiredStart` = `2026-11-02T09:00:00` (no offset) | 400 |
| 02.1 | `desiredStart` = `2026-11-02 09:00` | 400 |
| 02.1 | `desiredStart` = `2026-11-02T09:07:00+07:00` | 400 (15-minute step) |
| 02.1 | `desiredStart` = `2026-11-02T02:00:00Z` (= 09:00 +07) | Valid, same as 09:00 local |
| 02.1 | `desiredStart` = `2026-02-30T09:00:00+07:00` | 400 |
| 02.1 | `serviceTypeId` = `abc` | 400 |
| 02.1 | `serviceTypeId` of another dealership | 404 `SERVICE_TYPE_NOT_FOUND` |
| 02.1 | A customer sends B's `customerId` | 201 for A (AC-02.5) |
| 02.1 | A customer sends no `dealershipId` | 400 |
| 02.1 | An advisor sends another dealership's `dealershipId` | Ignored; the booking belongs to the advisor's dealership |
| 02.1 | `mode` = `HOLD` (before F10) | 400 |
| 02.1 | `reminderOptIn` = `"yes"` | 400 |
| 02.2 | `query` = `%` or `__` | 400 (< 3 characters) |
| 02.2 | `query` = `%%%` | 200, searches the literal `%%%` (does not match everything) |
| 02.2 | 61 requests in 1 minute | 429 |
| 02.3 | `{customer}` missing / not a UUID | 404 `CUSTOMER_NOT_FOUND` |
| 02.5 | `licencePlate` = `"!!!"` | 400 |
| 02.5 | `vin` of 16 characters, or containing `O`/`I`/`Q` | 400 |
| 02.5 | Same plate, different spelling | 409 `PLATE_ALREADY_REGISTERED` |
| 02.5 | No `Idempotency-Key` | 400 `IDEMPOTENCY_KEY_REQUIRED` |

## B5. Bounds

| Bound | Value |
| --- | --- |
| Booking horizon | 90 days |
| Time step | 15 minutes |
| Customer search results | 20; `query` ≥ 3 characters; 60/minute per advisor |
| Vehicles per customer | 10 |
| One customer's vehicle list | All (≤ 10) |

`GET /customers/{id}/vehicles` returns the same 404 for a missing or malformed id; advisors may see every customer anyway, so no extra limit against existence probing is needed.
