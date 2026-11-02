# F02 · Create a booking · Database spec (DB)

---

# PART A · For readers

F02 adds no tables. It writes to the tables F04 created (appointments, allocations, history, outbox) and to the vehicles table (F01) when a customer adds a vehicle. It adds indexes so advisors can search customers quickly by name, phone or email.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables.

| Writes to | By | Note |
| --- | --- | --- |
| `appointments`, `resource_allocations`, `appointment_events`, `outbox_messages` | `AppointmentWriter` (F04) | `source = STAFF` (advisor) or `CUSTOMER`; `created_by_user_id` = the signed-in user |
| `vehicles` | `AddVehicle` | `customer_id` from `CurrentCustomer`; normalised `plate_key` (D-01.6) |

## B2. Migration `add_customer_search_indexes`

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX customers_full_name_trgm_idx ON customers USING gin (lower(full_name) gin_trgm_ops);
CREATE INDEX customers_phone_prefix_idx ON customers (phone text_pattern_ops);
CREATE INDEX users_email_prefix_idx ON users (email text_pattern_ops) WHERE role = 'CUSTOMER';
```

| Pre-check | `down()` |
| --- | --- |
| `pg_trgm` ships with the `postgres:17-alpine` image (contrib) | Drop the 3 indexes; do **not** drop the extension |

No soft deletes, so no index needs a `deleted_at` condition.

## B3. Main queries

| Query | Index |
| --- | --- |
| Customer search: `lower(c.full_name) LIKE '%' || :q || '%' OR c.phone LIKE :digits || '%' OR u.email LIKE :q || '%'`, `LIMIT 20`, ordered by `full_name` | The 3 indexes above |
| A customer's vehicles | `vehicles (customer_id)` (F01) |
| Count a customer's vehicles before adding (D-02.9) | `vehicles (customer_id)` |
| Service by dealership + id | `service_types` PK |
| Snapshot (F03) + write (F04) | see F03, F04 |

`%` and `_` in `query` must be escaped before going into `LIKE` (TC-02-23).

Counting 10 vehicles (D-02.9) and inserting a vehicle are not under one lock: two parallel requests could create an 11th vehicle. Accepted: this is a soft anti-abuse limit, not a business rule, so no lock is needed.

## B4. DB tests

TC-02-09, TC-02-24 (unique plate/VIN through the API), TC-02-23 (`LIKE` escaping), TC-02-22 (outbox), TC-02-20 (concurrency through the endpoint).
