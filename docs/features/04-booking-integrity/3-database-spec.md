# F04 · No double booking, no duplicate requests · Database spec (DB)

---

# PART A · For readers

Five new tables:

- `appointments`: one row per booking, with a **version** that goes up on every change.
- `resource_allocations`: three rows per booking (technician, bay, vehicle), each holding one resource for a time range. The **EXCLUDE** constraint here makes the DB refuse any overlap.
- `idempotency_keys`: the idempotency keys and the saved answers (24 hours).
- `appointment_events`: the history of every change to a booking.
- `outbox_messages`: messages waiting to be sent (SMS, email, DMS), written together with the change.

Plus one trigger: every change to an appointment must raise its version by exactly 1.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

No F04 table uses soft deletes; history is kept through statuses and `active = false`.

### DB-04.1 · `appointments`

| Column | Type | Constraint / note |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `reference` | `varchar(32)` | NOT NULL UNIQUE (D-04.6) |
| `dealership_id` | `uuid` | NOT NULL FK `dealerships` |
| `customer_id` | `uuid` | NOT NULL FK `customers` |
| `vehicle_id` | `uuid` | NULL FK `vehicles` (NULL only while `HELD`, F10) |
| `service_type_id` | `uuid` | NOT NULL FK `service_types` |
| `technician_id` | `uuid` | NOT NULL FK `technicians` (current assignment) |
| `bay_id` | `uuid` | NOT NULL FK `bays` |
| `status` | `varchar(16)` | NOT NULL, CHECK in the 8 values (`CONTEXT.md`) |
| `start_at`, `end_at` | `timestamptz` | NOT NULL, `CHECK (end_at > start_at)` |
| `expires_at` | `timestamptz` | NULL; `CHECK (status <> 'HELD' OR expires_at IS NOT NULL)` |
| `version` | `integer` | NOT NULL DEFAULT 1, `CHECK (version >= 1)` |
| `source` | `varchar(16)` | NOT NULL, CHECK `STAFF`/`CUSTOMER` |
| `reminder_opt_in` | `boolean` | NOT NULL DEFAULT true |
| `created_by_user_id` | `bigint` | NOT NULL FK `users` |
| `cancel_reason` | `varchar(32)` | NULL (F07) |
| `cancel_note` | `varchar(500)` | NULL (F07) |
| `cancelled_at` | `timestamptz` | NULL |
| `cancelled_by_user_id` | `bigint` | NULL FK `users` |
| `contact_required_reason` | `varchar(32)` | NULL, CHECK `TECHNICIAN_ABSENT`/`BAY_OUT_OF_SERVICE`/`OVERRUN` (F12) |
| `checked_in_at`, `started_at`, `completed_at` | `timestamptz` | NULL (F12) |
| `created_at`, `updated_at` | `timestamptz` | NOT NULL |
| — | | `CHECK (status = 'HELD' OR vehicle_id IS NOT NULL)` |

Indexes:
- `appointments_dealership_start_idx (dealership_id, start_at, id)` — day list (F05).
- `appointments_customer_start_idx (customer_id, start_at, id)` — My bookings (F05).
- `appointments_held_expiry_idx (expires_at) WHERE status = 'HELD'` — expiry job (F10).
- `appointments_contact_required_idx (dealership_id) WHERE contact_required_reason IS NOT NULL` — contact list (F12).

Sequence: `CREATE SEQUENCE appointment_reference_seq START 1`.

Version trigger (D-04.7, INV-04.3), _illustrative_:

```sql
CREATE FUNCTION appointments_version_guard() RETURNS trigger AS $$
BEGIN
  IF NEW.version IS DISTINCT FROM OLD.version + 1 THEN
    RAISE EXCEPTION 'appointments.version must increase by exactly 1 (id %, % -> %)', OLD.id, OLD.version, NEW.version
      USING ERRCODE = 'P0001';
  END IF;
  RETURN NEW;
END $$ LANGUAGE plpgsql;
CREATE TRIGGER appointments_version_guard BEFORE UPDATE ON appointments
  FOR EACH ROW EXECUTE FUNCTION appointments_version_guard();
```

### DB-04.2 · `resource_allocations`

| Column | Type | Constraint |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `appointment_id` | `uuid` | NOT NULL FK `appointments` `ON DELETE RESTRICT` |
| `dealership_id` | `uuid` | NOT NULL FK |
| `resource_type` | `varchar(16)` | NOT NULL, CHECK `TECHNICIAN`/`BAY`/`VEHICLE` |
| `resource_id` | `uuid` | NOT NULL (no FK: polymorphic) |
| `period` | `tstzrange` | NOT NULL; `CHECK (NOT isempty(period) AND lower_inc(period) AND NOT upper_inc(period) AND NOT lower_inf(period) AND NOT upper_inf(period))` |
| `active` | `boolean` | NOT NULL DEFAULT true |
| `created_at` | `timestamptz` | NOT NULL |
| `deactivated_at` | `timestamptz` | NULL; `CHECK (active OR deactivated_at IS NOT NULL)` |

Constraint and indexes (raw SQL, per `CLAUDE.md`):

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
ALTER TABLE resource_allocations ADD CONSTRAINT resource_allocations_no_overlap
  EXCLUDE USING gist (resource_type WITH =, resource_id WITH =, period WITH &&) WHERE (active);
CREATE UNIQUE INDEX resource_allocations_one_active_per_type
  ON resource_allocations (appointment_id, resource_type) WHERE active;
CREATE INDEX resource_allocations_dealership_period_idx
  ON resource_allocations USING gist (dealership_id, period) WHERE active;
```

The third index serves the busy-intervals query for a whole dealership day (F03 query 4, F06).

### DB-04.3 · `idempotency_keys`

| Column | Type | Constraint |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `user_id` | `bigint` | NOT NULL FK `users` `ON DELETE CASCADE` |
| `key` | `uuid` | NOT NULL |
| `method` | `varchar(8)` | NOT NULL |
| `path` | `varchar(255)` | NOT NULL |
| `request_hash` | `char(64)` | NOT NULL |
| `status` | `varchar(16)` | NOT NULL, CHECK `IN_PROGRESS`/`COMPLETED` |
| `locked_until` | `timestamptz` | NOT NULL |
| `response_status` | `smallint` | NULL |
| `response_headers` | `jsonb` | NULL (only `Location`, `ETag`, `Content-Type`) |
| `response_body` | `text` | NULL, ≤ 64 KB (R-04.4) |
| `created_at` | `timestamptz` | NOT NULL |
| `expires_at` | `timestamptz` | NOT NULL |
| unique | | `(user_id, key)` |
| index | | `(expires_at)` for prune |

`CHECK (status <> 'COMPLETED' OR response_status IS NOT NULL)`.

### DB-04.4 · `appointment_events`

| Column | Type | Note |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `appointment_id` | `uuid` | NOT NULL FK; index `(appointment_id, occurred_at)` |
| `dealership_id` | `uuid` | NOT NULL |
| `type` | `varchar(32)` | NOT NULL, CHECK: `CREATED`, `HELD`, `CONFIRMED`, `CANCELLED`, `RESCHEDULED`, `EXPIRED`, `CHECKED_IN`, `STARTED`, `COMPLETED`, `NO_SHOW`, `EXTENDED`, `REASSIGNED`, `CONTACT_REQUIRED` |
| `from_status`, `to_status` | `varchar(16)` | NULL |
| `version` | `integer` | NOT NULL (version after the change) |
| `actor_user_id` | `bigint` | NULL (background job = NULL) |
| `actor_role` | `varchar(32)` | NULL |
| `data` | `jsonb` | NOT NULL DEFAULT `'{}'`; no personal data (ids, times, reason codes only) |
| `occurred_at` | `timestamptz` | NOT NULL |

### DB-04.5 · `outbox_messages`

| Column | Type | Note |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `event_id` | `uuid` | NOT NULL; unique `(event_id, channel)` |
| `dealership_id` | `uuid` | NOT NULL |
| `appointment_id` | `uuid` | NULL FK |
| `event_type` | `varchar(40)` | NOT NULL, CHECK `AppointmentConfirmed`, `AppointmentCancelled`, `AppointmentRescheduled`, `AppointmentReminder`, `AppointmentReassigned` |
| `channel` | `varchar(8)` | NOT NULL, CHECK `SMS`/`EMAIL`/`DMS` |
| `payload` | `jsonb` | NOT NULL; ids + times + codes, **no** phone number or email (F11 looks them up at send time) |
| `status` | `varchar(16)` | NOT NULL DEFAULT `PENDING`, CHECK `PENDING`/`SENT`/`FAILED`/`SKIPPED` |
| `attempts` | `smallint` | NOT NULL DEFAULT 0 |
| `next_attempt_at` | `timestamptz` | NOT NULL |
| `last_error` | `varchar(500)` | NULL |
| `sent_at` | `timestamptz` | NULL |
| `dedupe_key` | `varchar(120)` | NULL, UNIQUE (F11 uses it for reminders) |
| `created_at`, `updated_at` | `timestamptz` | NOT NULL |
| index | | `(next_attempt_at) WHERE status = 'PENDING'`; `(dealership_id, status, created_at)` |

Channels per event (D-04.4): Confirmed, Cancelled, Rescheduled → SMS, EMAIL, DMS; Reassigned → SMS, DMS; Reminder → SMS.

## B2. Migrations

| Migration | Pre-check | `down()` |
| --- | --- | --- |
| `create_appointments_table` (+ sequence, trigger, function) | — | drop trigger, function, table, sequence |
| `create_resource_allocations_table` (+ extension, EXCLUDE, indexes) | `btree_gist` ships with the `postgres:17-alpine` image (contrib) | drop the table; do **not** drop the extension (other tables may use it) |
| `create_idempotency_keys_table` | — | `dropIfExists` |
| `create_appointment_events_table` | — | `dropIfExists` |
| `create_outbox_messages_table` | — | `dropIfExists` |

`down()` runs in reverse `up()` order so no FK blocks it. All tables are new, so no existing rows need checking.

## B3. Main queries

| Query | Index |
| --- | --- |
| Insert allocations (EXCLUDE checks) | GiST of `resource_allocations_no_overlap` |
| Busy intervals per dealership + day (F03) | `resource_allocations_dealership_period_idx` |
| `UPDATE appointments … WHERE id AND version` | PK |
| Deactivate an appointment's allocations | `resource_allocations_one_active_per_type` (prefix `appointment_id`) |
| Look up a key | unique `(user_id, key)` |
| Prune keys | `(expires_at)` |
| Relay picks messages (F11) | `(next_attempt_at) WHERE status = 'PENDING'` |

Finding the resource type from a 23P01 error (D-04.17): the detail looks like `Key (resource_type, resource_id, period)=(VEHICLE, 0193…, ["…","…")) conflicts with existing key (…)`. The parser reads the first value inside the parentheses after `=(`.

## B4. DB tests

TC-04-01, 04-02 (concurrency), TC-04-09 (one transaction), TC-04-13 (trigger), TC-04-30 (CHECKs on `period`, `vehicle_id`, `expires_at`), TC-04-29 (23P01 parser with a real error).
