# F00 · Accounts and sign-in · Database spec (DB)

---

# PART A · For readers

F00 adds three things to the database: a **dealerships** table (minimal: name, time zone, phone), a **role and dealership** on every account, and a **customers** table linked to customer accounts. Emails are always stored in lower case. Passwords are stored only as a hash. Demo data (1 dealership, 3 staff, 2 customers) is loaded by a seeder that is safe to run again.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Tables

### DB-00.1 · `dealerships` (new)

| Column | Type | Constraint |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `code` | `varchar(32)` | NOT NULL, UNIQUE (for example `SGA-D7`) |
| `name` | `varchar(120)` | NOT NULL ("Saigon Auto") |
| `branch_name` | `varchar(120)` | NULL ("District 7") |
| `timezone` | `varchar(64)` | NOT NULL; IANA name, checked in the app with `timezone_identifiers_list()` |
| `phone` | `varchar(32)` | NOT NULL ("028 3812 3456", display form) |
| `created_at`, `updated_at` | `timestamptz` | |

### DB-00.2 · `users` (changed)

| Column | Type | Constraint |
| --- | --- | --- |
| `role` | `varchar(32)` | NOT NULL; `CHECK (role IN ('CUSTOMER','SERVICE_ADVISOR','WORKSHOP_FOREMAN','ADMIN'))` |
| `dealership_id` | `uuid` | NULL; FK `dealerships(id)` `ON DELETE RESTRICT` |
| — | — | `CHECK ((role = 'CUSTOMER') = (dealership_id IS NULL))` (INV-00.6) |
| — | — | `CHECK (email = lower(email))` (INV-00.1) |
| index | | `users_dealership_id_role_idx (dealership_id, role)` |

`email` keeps its existing unique index. `email_verified_at`, `remember_token` are unused but kept.

### DB-00.3 · `customers` (new)

| Column | Type | Constraint |
| --- | --- | --- |
| `id` | `uuid` | PK |
| `user_id` | `bigint` | NOT NULL, UNIQUE, FK `users(id)` `ON DELETE RESTRICT` |
| `full_name` | `varchar(100)` | NOT NULL |
| `phone` | `varchar(16)` | NOT NULL; normalised form D-00.5; `CHECK (phone ~ '^\+?[0-9]{9,15}$')` |
| `created_at`, `updated_at` | `timestamptz` | |

Indexes for the customer search are added in F02, not here.

## B2. Migrations

| Migration | Pre-check | `up()` | Exact `down()` |
| --- | --- | --- | --- |
| `create_dealerships_table` | — | Create DB-00.1 | `Schema::dropIfExists('dealerships')` |
| `add_role_and_dealership_to_users` | Count existing `users`: must be 0 on every environment (no real data yet). If > 0, the migration **stops** with a clear message instead of guessing roles. | Add `role` NOT NULL (no default), `dealership_id`, 2 CHECKs, index | Drop the 2 CHECKs, the index, the FK, then the 2 columns |
| `create_customers_table` | — | Create DB-00.3 | `Schema::dropIfExists('customers')` |

No F00 table uses soft deletes, so no index needs a `deleted_at` condition.

CHECKs are written with `DB::statement` (the schema builder has no named CHECK). Names: `users_role_check`, `users_staff_has_dealership_check`, `users_email_lowercase_check`, `customers_phone_format_check`.

## B3. Seed (D-00.8, D-00.9)

| Data | `updateOrCreate` key | Password |
| --- | --- | --- |
| Dealership `SGA-D7` "Saigon Auto", "District 7", `Asia/Ho_Chi_Minh`, "028 3812 3456" | `code` | — |
| Admin `admin@saigonauto.test` "Admin" | `email` | `SEED_ADMIN_PASSWORD` |
| Advisor `minhanh@saigonauto.test` "Minh Anh" | `email` | `SEED_ADVISOR_PASSWORD` |
| Foreman `quangtin@saigonauto.test` "Quang Tín" | `email` | `SEED_FOREMAN_PASSWORD` |
| Customer `hung@example.test` "Nguyễn Văn Hùng", `0903123218` | `email` | `SEED_CUSTOMER_PASSWORD` |
| Customer `khanh@example.test` "Lâm Khánh", `0912555019` | `email` | `SEED_CUSTOMER_PASSWORD` |

- Empty variable → skip that account, `$this->command->warn(...)` without printing any password.
- Rerun: no duplicates, and an existing password is **not** rehashed if the variable did not change (`Hash::check` before overwriting), so rerunning the seeder signs nobody out.
- `.test` email domains (RFC 2606) so no real mail is ever sent.

## B4. Main queries

| Query | Index |
| --- | --- |
| Find user by email at sign-in | unique `users_email_unique` |
| The user's customer | unique `customers_user_id_unique` |
| Staff of a dealership (seed, later F12) | `users_dealership_id_role_idx` |

## B5. DB tests

TC-00-03 (two sign-ups with one email → one row), TC-00-14 (CHECKs reject staff without a dealership and an upper-case email on direct insert), TC-00-15, TC-00-16 (seeder). Details in [6-test-case.md](6-test-case.md).
