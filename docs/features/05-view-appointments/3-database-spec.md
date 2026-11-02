# F05 · View appointments · Database spec (DB)

---

# PART A · For readers

F05 only reads. No new tables or migrations; the indexes it needs were created in F04.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables, no migrations.

## B2. Main queries

| Query | Index (F04) | Note |
| --- | --- | --- |
| One booking by id + scope | PK | `WHERE id = :id AND (customer_id = :c | dealership_id = :d)` |
| A dealership's day list | `appointments_dealership_start_idx (dealership_id, start_at, id)` | `start_at` range + cursor `(start_at, id) > (:s, :i)` |
| A customer's Upcoming / Past | `appointments_customer_start_idx (customer_id, start_at, id)` | Past sorts descending: the same index scanned backwards |
| History | `appointment_events (appointment_id, occurred_at)` | ≤ 200 rows per booking (in practice < 20) |

Eager loading avoids N+1 in lists: `serviceType`, `technician`, `bay`, `customer`, `vehicle` (one query per relation). TC-05-12 counts queries with 5 and 50 rows: equal.

Status and time comparisons use the `:now` parameter from the app clock (README D-G.6).

## B3. DB tests

TC-05-02 (time zone), TC-05-07 (cursor), TC-05-12 (query count).
