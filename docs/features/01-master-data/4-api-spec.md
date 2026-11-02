# F01 · Master data · API spec (API)

---

# PART A · For readers

Five read APIs and one write. Customers can read the dealership list and the services on offer. Staff can also read their own dealership's bays and technicians. Admins can disable or enable a technician; disabling is refused while the technician has upcoming appointments. Asking for another dealership returns "Dealership not found" (without revealing whether it exists).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

All under `auth:sanctum`. API-01.1–01.5 are `GET` with no special headers; API-01.6 is a `PUT` (no `Idempotency-Key`, no `If-Match`, RQ D-01.12).

| ID | Path | Who | Note |
| --- | --- | --- | --- |
| API-01.1 | `/api/v1/dealerships` | every role | Staff: only their dealership. Customers: every dealership. Sorted by `name`. |
| API-01.2 | `/api/v1/dealerships/{dealership}` | every role | Binding INV-01.3 |
| API-01.3 | `/api/v1/dealerships/{dealership}/service-types` | every role | Customers: `active` only |
| API-01.4 | `/api/v1/dealerships/{dealership}/bays` | `can:staff` | |
| API-01.5 | `/api/v1/dealerships/{dealership}/technicians` | `can:staff` | |
| API-01.6 | `PUT /api/v1/dealerships/{dealership}/technicians/{technician}/status` | `can:admin` | Body `{ "active": false }`; 200 `Technician`; 409 `TECHNICIAN_HAS_BOOKINGS` (RQ D-01.11) |

No paging: each dealership has few rows (hard cap 200 rows per response; beyond that, cut and add `"truncated": true` — not expected to happen).

## B2. Contracts

Responses are wrapped in `{ "data": … }` (Laravel API Resource default). JSON is camelCase.

**Dealership** (API-01.1 returns a `data` array; API-01.2 one object):

```json
{ "id": "0193…", "code": "SGA-D7", "name": "Saigon Auto", "branchName": "District 7",
  "timezone": "Asia/Ho_Chi_Minh", "phone": "028 3812 3456", "slotStepMinutes": 15,
  "openingHours": [ { "weekday": 1, "opensAt": "08:00", "closesAt": "17:00" }, … ] }
```

`openingHours` lists open days only. `weekday` is ISO 1–7.

**ServiceType**: `{ "id", "code", "name", "description", "durationMinutes": 60, "requiredLevel": 1, "requiredBayType": "QUICK", "active": true }`

**Bay**: `{ "id", "code": "K2", "name": "Bay 2", "bayType": "QUICK", "active": true, "supportedServiceTypeIds": ["…"] }`

**Technician**: `{ "id", "name": "Ngô Bảo Em", "level": 6, "active": true }` — `level` 1–7.

**API-01.6 409** (`TECHNICIAN_HAS_BOOKINGS`):

```json
{ "code": "TECHNICIAN_HAS_BOOKINGS", "status": 409, "count": 3,
  "appointments": [ { "id": "…", "reference": "APT-20261103-0012", "start": "2026-11-03T09:00:00+07:00",
                     "end": "2026-11-03T10:00:00+07:00", "customerName": "Nguyễn Văn Hùng" } ] }
```

`count` is the real number; `appointments` holds the first 20 by start time.

## B3. Error table

| HTTP | `code` | When |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | No session |
| 403 | `FORBIDDEN` | Customer calls API-01.4, API-01.5 |
| 404 | `DEALERSHIP_NOT_FOUND` | INV-01.3 |
| 400 | `VALIDATION_ERROR` | API-01.6 body |
| 403 | `FORBIDDEN` | API-01.6 by a non-admin |
| 404 | `TECHNICIAN_NOT_FOUND` | API-01.6, INV-01.8 |
| 409 | `TECHNICIAN_HAS_BOOKINGS` | API-01.6, D-01.11 |

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| API-01.2–01.5 | `{dealership}` = `abc` (not a UUID) | 404 `DEALERSHIP_NOT_FOUND` (not 500, not 400) |
| API-01.2–01.5 | Valid UUID that does not exist | 404 `DEALERSHIP_NOT_FOUND` |
| API-01.2–01.5 | Another dealership's UUID, called by staff | 404 `DEALERSHIP_NOT_FOUND`, body identical to the row above except `correlationId` |
| API-01.3 | `?includeInactive=true` sent by a customer | Ignored; still `active` only |
| API-01.1 | `?dealershipId=<other>` | Unknown parameter ignored |
| API-01.6 | `active` missing, `"false"`, `0`, `null` | 400 `VALIDATION_ERROR` |
| API-01.6 | `{technician}` = `abc`, a missing UUID, another dealership's technician | 404 `TECHNICIAN_NOT_FOUND` (identical bodies) |
| API-01.6 | Advisor, foreman, customer | 403 `FORBIDDEN` |
| API-01.6 | Disable an already disabled technician; enable an active one | 200, nothing changes |
| API-01.6 | Body also has `level: 7` or `name` | Ignored; only `active` changes |

## B5. Bounds

- 200 rows per response (B1).
- No dedicated rate limit (catalogue data for signed-in users); a 404 for another dealership reveals nothing more, because dealerships and their names are public to customers anyway.
