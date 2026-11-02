# F01 · Master data · Database spec (DB)

---

# PART A · For readers

Seven new tables: opening hours, service types, bays, technicians, technician skills, shifts, vehicles. The rules live in the DB (duration > 0, closing after opening, unique plates), so bad data cannot get in through any path. Demo data matches the drafts.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

Every table: `id uuid PK`, `created_at`, `updated_at timestamptz` (unless noted). No soft deletes; "no longer used" is the `active` flag.

### DB-01.1 · `dealership_opening_hours`

| Column | Type | Constraint |
| --- | --- | --- |
| `dealership_id` | `uuid` | NOT NULL FK `dealerships` `ON DELETE CASCADE` |
| `weekday` | `smallint` | NOT NULL, `CHECK (weekday BETWEEN 1 AND 7)` (ISO: 1 = Monday) |
| `opens_at`, `closes_at` | `time` | NOT NULL, `CHECK (closes_at > opens_at)` |
| unique | | `(dealership_id, weekday)` |

### DB-01.2 · `service_types`

| Column | Type | Constraint |
| --- | --- | --- |
| `dealership_id` | `uuid` | NOT NULL FK |
| `code` | `varchar(32)` | NOT NULL; unique `(dealership_id, code)` |
| `name` | `varchar(100)` | NOT NULL |
| `description` | `varchar(200)` | NULL ("oil and oil filter") |
| `duration_minutes` | `integer` | NOT NULL, `CHECK (duration_minutes > 0 AND duration_minutes % 15 = 0 AND duration_minutes <= 480)` |
| `required_skill` | `varchar(32)` | NOT NULL, CHECK in enum `Skill` |
| `required_bay_type` | `varchar(16)` | NOT NULL, CHECK in enum `BayType` |
| `active` | `boolean` | NOT NULL DEFAULT true |

### DB-01.3 · `bays`

| Column | Type | Constraint |
| --- | --- | --- |
| `dealership_id` | `uuid` | NOT NULL FK |
| `code` | `varchar(16)` | NOT NULL; unique `(dealership_id, code)` |
| `name` | `varchar(60)` | NOT NULL |
| `bay_type` | `varchar(16)` | NOT NULL, CHECK enum |
| `active` | `boolean` | NOT NULL DEFAULT true |
| index | | `(dealership_id, bay_type) WHERE active` |

### DB-01.4 · `technicians`, `technician_skills`, `technician_shifts`

`technicians`: `dealership_id` NOT NULL FK; `name varchar(100)` NOT NULL; `active boolean` NOT NULL DEFAULT true; unique `(dealership_id, name)` (natural key for the seeder; renaming a real person changes the seed key).

`technician_skills`: `technician_id uuid` FK `ON DELETE CASCADE`, `skill varchar(32)` CHECK enum; PK `(technician_id, skill)`; no `id`, no timestamps.

`technician_shifts`: `technician_id` FK `ON DELETE CASCADE`; `weekday smallint` CHECK 1..7; `starts_at`, `ends_at time` `CHECK (ends_at > starts_at)`; unique `(technician_id, weekday, starts_at)`.

### DB-01.5 · `vehicles`

| Column | Type | Constraint |
| --- | --- | --- |
| `customer_id` | `uuid` | NOT NULL FK `customers` `ON DELETE RESTRICT`; index |
| `licence_plate` | `varchar(20)` | NOT NULL (display form, upper case, trimmed) |
| `plate_key` | `varchar(20)` | NOT NULL, UNIQUE, `CHECK (plate_key ~ '^[A-Z0-9]{4,12}$')` |
| `vin` | `char(17)` | NULL, `CHECK (vin ~ '^[A-HJ-NPR-Z0-9]{17}$')`; partial unique `WHERE vin IS NOT NULL` |
| `make` | `varchar(40)` | NOT NULL |
| `model` | `varchar(60)` | NOT NULL |
| `year` | `smallint` | NULL, `CHECK (year BETWEEN 1950 AND 2100)` |

### Extra FK

`users.dealership_id` → `dealerships(id)` if F00 did not add it (skip if F00 DB-00.2 already did).

## B2. Migrations

| Migration | Pre-check | `down()` |
| --- | --- | --- |
| `create_dealership_opening_hours_table` | — | `dropIfExists` |
| `create_service_types_table` | — | `dropIfExists` |
| `create_bays_table` | — | `dropIfExists` |
| `create_technicians_tables` (3 tables) | — | drop `technician_shifts`, `technician_skills`, `technicians` in that order |
| `create_vehicles_table` | — | `dropIfExists` |

CHECKs use `DB::statement`, named `<table>_<column>_check`. All tables are new, so no existing rows need checking.

## B3. Seed (`MasterDataSeeder`, `VehicleSeeder`)

Dealership `SGA-D7` (F00). The `updateOrCreate` key is in the "Key" column.

**Opening hours** (key `dealership_id, weekday`): 1–5: 08:00–17:00; 6: 08:00–12:00; 7: no row.

**Service types** (key `dealership_id, code`):

| code | name | description | minutes | skill | bay type |
| --- | --- | --- | --- | --- | --- |
| `OIL_CHANGE` | Oil change | oil and oil filter | 60 | `BASIC` | `QUICK` |
| `MAINT_20K` | 20,000 km service | full manufacturer checklist | 120 | `GENERAL_MAINTENANCE` | `STANDARD` |
| `EV_BATTERY` | EV battery check | by a high-voltage certified technician | 90 | `EV_HIGH_VOLTAGE` | `EV` |

**Bays** (key `dealership_id, code`): `K1` Bay 1 `QUICK`; `K2` Bay 2 `QUICK`; `K3` Bay 3 `STANDARD`; `KEV` Bay EV `EV`. All active.

**Technicians** (key `dealership_id, name`):

| Name | Skills | Shifts | Active |
| --- | --- | --- | --- |
| Trần Minh An | BASIC | Mon–Sat 08:00–17:00 | ✓ |
| Lý Hoàng Dũng | BASIC, GENERAL_MAINTENANCE | Mon–Sat 08:00–17:00 | ✓ |
| Võ Thị Chi | BASIC, GENERAL_MAINTENANCE | Mon–Sat 08:00–17:00 | ✓ |
| Ngô Bảo Em | BASIC, GENERAL_MAINTENANCE, EV_HIGH_VOLTAGE | Mon–Sat 08:00–17:00 | ✓ |
| Huỳnh Tấn Phát | BASIC | Mon–Fri 08:00–12:00 | ✗ |

The seeder syncs skills and shifts: delete extra rows, add missing ones (never delete-all-and-insert, so a rerun changes nothing).

**Vehicles** (key `plate_key`):

| Owner | Plate | VIN | Make | Model | Year |
| --- | --- | --- | --- | --- | --- |
| Nguyễn Văn Hùng | 51H-123.45 | RL4BT3F3X0M001234 | Toyota | Vios | 2021 |
| Lâm Khánh | 59A-888.01 | — | Kia | Morning | 2019 |
| Lâm Khánh | 51K-246.80 | — | VinFast | VF e34 | 2023 |

If a seed customer is missing (F00 skipped it because its password variable was empty) → skip their vehicles with a warning.

## B4. Main queries

| Query | Index |
| --- | --- |
| A dealership's technicians with skills and shifts (F03 snapshot) | `technicians (dealership_id, name)` unique; `technician_skills` PK; `technician_shifts (technician_id, weekday, starts_at)` |
| Active bays by type | `bays (dealership_id, bay_type) WHERE active` |
| A customer's vehicles | `vehicles (customer_id)` |
| Vehicle by plate | `vehicles (plate_key)` unique |

## B5. DB tests

TC-01-08 (unique plate/VIN), TC-01-09 (seeding twice), TC-01-10 (CHECKs reject 0 minutes, 50 minutes, closing ≤ opening). Details in [6-test-case.md](6-test-case.md).
