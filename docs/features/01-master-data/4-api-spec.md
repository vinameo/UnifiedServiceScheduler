# F01 · Master data · API spec (API)

---

# PART A · For readers

Five read-only APIs. Customers can read the dealership list and the services on offer. Staff can also read their own dealership's bays and technicians. Asking for another dealership returns "Dealership not found" (without revealing whether it exists).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

All under `auth:sanctum`, method `GET`, no special headers.

| ID | Path | Who | Note |
| --- | --- | --- | --- |
| API-01.1 | `/api/v1/dealerships` | every role | Staff: only their dealership. Customers: every dealership. Sorted by `name`. |
| API-01.2 | `/api/v1/dealerships/{dealership}` | every role | Binding INV-01.3 |
| API-01.3 | `/api/v1/dealerships/{dealership}/service-types` | every role | Customers: `active` only |
| API-01.4 | `/api/v1/dealerships/{dealership}/bays` | `can:staff` | |
| API-01.5 | `/api/v1/dealerships/{dealership}/technicians` | `can:staff` | |

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

**ServiceType**: `{ "id", "code", "name", "description", "durationMinutes": 60, "requiredSkill": "BASIC", "requiredBayType": "QUICK", "active": true }`

**Bay**: `{ "id", "code": "K2", "name": "Bay 2", "bayType": "QUICK", "active": true, "supportedServiceTypeIds": ["…"] }`

**Technician**: `{ "id", "name": "Ngô Bảo Em", "active": true, "skills": ["BASIC","GENERAL_MAINTENANCE","EV_HIGH_VOLTAGE"], "shifts": [ { "weekday": 1, "startsAt": "08:00", "endsAt": "17:00" } ] }` — `skills` in enum order; `shifts` by `weekday`, `startsAt`.

## B3. Error table

| HTTP | `code` | When |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | No session |
| 403 | `FORBIDDEN` | Customer calls API-01.4, API-01.5 |
| 404 | `DEALERSHIP_NOT_FOUND` | INV-01.3 |

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| API-01.2–01.5 | `{dealership}` = `abc` (not a UUID) | 404 `DEALERSHIP_NOT_FOUND` (not 500, not 400) |
| API-01.2–01.5 | Valid UUID that does not exist | 404 `DEALERSHIP_NOT_FOUND` |
| API-01.2–01.5 | Another dealership's UUID, called by staff | 404 `DEALERSHIP_NOT_FOUND`, body identical to the row above except `correlationId` |
| API-01.3 | `?includeInactive=true` sent by a customer | Ignored; still `active` only |
| API-01.1 | `?dealershipId=<other>` | Unknown parameter ignored |

## B5. Bounds

- 200 rows per response (B1).
- No dedicated rate limit (catalogue data for signed-in users); a 404 for another dealership reveals nothing more, because dealerships and their names are public to customers anyway.
