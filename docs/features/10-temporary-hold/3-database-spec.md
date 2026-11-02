# F10 · Temporary hold for self-booking · Database spec (DB)

---

# PART A · For readers

F10 adds one index: a customer can have at most one hold at a time. Everything else uses the tables of F04 (`appointments` already has `expires_at` and allows a missing vehicle while on hold).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No new tables.

### DB-10.1 · Index `appointments_one_hold_per_customer`

```sql
CREATE UNIQUE INDEX appointments_one_hold_per_customer ON appointments (customer_id) WHERE status = 'HELD';
```

## B2. Migration `add_one_hold_per_customer_index`

| Pre-check | `up()` | `down()` |
| --- | --- | --- |
| `SELECT customer_id, count(*) FROM appointments WHERE status = 'HELD' GROUP BY 1 HAVING count(*) > 1` must return 0 rows. If not, the migration stops with a message (run `holds:expire` first). | Create DB-10.1 | `DROP INDEX IF EXISTS appointments_one_hold_per_customer` |

No soft deletes; the index scope is `status = 'HELD'` only.

## B3. Main queries

| Query | Index |
| --- | --- |
| Job: expired holds `WHERE status = 'HELD' AND expires_at <= :now ORDER BY expires_at LIMIT 500` | `appointments_held_expiry_idx` (F04) |
| The customer's current hold | `appointments_one_hold_per_customer` |
| Releaser: expired holds whose active allocations overlap a range in a dealership: `resource_allocations ra JOIN appointments a … WHERE ra.dealership_id = :d AND ra.active AND ra.period && :range AND a.status = 'HELD' AND a.expires_at <= :now` (+ `OR (ra.resource_type = 'VEHICLE' AND ra.resource_id = :vehicle)`) | `resource_allocations_dealership_period_idx` (F04) |
| Confirm: versioned update + vehicle allocation swap | PK, `resource_allocations_one_active_per_type` |

`:now` always comes from the app clock (README D-G.6).

## B4. DB tests

TC-10-07 (two holds in parallel → index), TC-10-03 (race), TC-10-13 (job), TC-10-17 (migration pre-check).
