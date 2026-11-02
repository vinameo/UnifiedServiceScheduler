# F08 · Monitoring · Database spec (DB)

---

# PART A · For readers

Two new tables: `api_events` (bookings and errors, kept 7 days, for the monitoring screen) and `metric_counters` (monotonic counters for `/metrics`).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

### DB-08.1 · `api_events`

| Column | Type | Note |
| --- | --- | --- |
| `id` | `bigserial` | PK |
| `occurred_at` | `timestamptz` | NOT NULL |
| `correlation_id` | `varchar(128)` | NOT NULL; index |
| `dealership_id` | `uuid` | NULL |
| `user_id` | `bigint` | NULL (no FK: errors stay when a user is deleted) |
| `appointment_id` | `uuid` | NULL |
| `method` | `varchar(8)` | NOT NULL (`-` for jobs) |
| `route` | `varchar(160)` | NOT NULL (route pattern or `outbox-relay`) |
| `status` | `smallint` | NULL (jobs have none) |
| `code` | `varchar(48)` | NULL |
| `outcome` | `varchar(24)` | NULL (`confirmed`, `no_availability`, `validation_error`, `error`) |
| `duration_ms` | `integer` | NULL |
| `idempotent_replay` | `boolean` | NOT NULL DEFAULT false |
| `allocation_retries` | `smallint` | NOT NULL DEFAULT 0 |
| `detail` | `jsonb` | NOT NULL DEFAULT `'{}'` (personal data removed) |

Indexes: `(occurred_at)`; `(dealership_id, occurred_at)`; `(route, occurred_at) WHERE route = 'POST api/v1/appointments'`; `(correlation_id)`.

### DB-08.2 · `metric_counters`

| Column | Type | Note |
| --- | --- | --- |
| `name` | `varchar(80)` | NOT NULL |
| `labels` | `varchar(200)` | NOT NULL DEFAULT `''`; canonical string `k1="v1",k2="v2"` sorted by key |
| `value` | `bigint` | NOT NULL DEFAULT 0, `CHECK (value >= 0)` |
| `updated_at` | `timestamptz` | NOT NULL |
| PK | | `(name, labels)` |

Names: `booking_requests_total` (label `outcome`), `booking_duration_seconds_bucket` (label `le` ∈ 0.05, 0.1, 0.2, 0.3, 0.5, 1, 2, 5, +Inf), `booking_duration_seconds_sum_ms`, `booking_duration_seconds_count`, `allocation_retries_total`, `idempotent_replays_total`, `appointment_modified_total`, `sign_in_failures_total`. `/metrics` converts `…_sum_ms` to seconds on output.

## B2. Migrations

| Migration | `down()` |
| --- | --- |
| `create_api_events_table` | `dropIfExists` |
| `create_metric_counters_table` | `dropIfExists` |

New tables, so no existing rows to check. No soft deletes.

## B3. Main queries

| Query | Index |
| --- | --- |
| Counts by `outcome` in a range for one dealership | `(dealership_id, occurred_at)` |
| Booking p95 | `(route, occurred_at)` partial |
| The 20 most recent errors (`status >= 400`) | `(dealership_id, occurred_at)` |
| Search by correlation id | `(correlation_id)` |
| Prune `occurred_at < now - 7 days` | `(occurred_at)` |
| Increment a counter: `INSERT … ON CONFLICT (name, labels) DO UPDATE SET value = metric_counters.value + EXCLUDED.value, updated_at = EXCLUDED.updated_at` | PK |
| DB connections: `SELECT count(*) FROM pg_stat_activity WHERE datname = current_database()`; `SHOW max_connections` | — |
| Outbox: `SELECT count(*), min(created_at) FROM outbox_messages WHERE status = 'PENDING'` | `(next_attempt_at) WHERE status='PENDING'` (F04); MAY add an index if slow |

## B4. DB tests

TC-08-06 (counters), TC-08-12 (prune), TC-08-09 (scope).
