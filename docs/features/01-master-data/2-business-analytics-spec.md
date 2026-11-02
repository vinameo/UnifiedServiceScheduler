# F01 · Master data · Business analysis (BA)

---

# PART A · For readers

## A1. Why

F03 (allocation) and every booking screen read master data. Wrong master data (say, the EV service mapped to the wrong bay type) makes every later booking wrong. So master data needs DB constraints, and admins need to be able to see it.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-01.1 | Requirements F01 | Entity list, FR-01.1 (minimums), BR-01.1, 2 ACs. |
| E-01.2 | Master data draft | 3 services, 4 bays (2 Quick, 1 Standard, 1 EV), 5 technicians, 3 opening-hour rows, time zone, 15-minute step. |
| E-01.3 | SDD §4 | `service_type(required_skill, required_bay_type)`, `technician_skill` with many skills. |
| E-01.4 | AC-03.8 | Skill code `EV_HIGH_VOLTAGE`. |
| E-01.5 | `docker/php/entrypoint.sh` | Seeders must be safe to rerun (`RUN_SEEDERS`). |

## A3. Priorities

Must: schema, seed, read API, route binding. Should: Master data screen. Could: none.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-01.1 | Admins want to edit data and have no UI | The screen explains how; FC-01.1 |
| RB-01.2 | No public holidays → customers can book on 1 January | Out of scope per requirements |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-01.1 · Skills and bay types are enums, not tables

- **Options:** (a) PHP backed enum + CHECK; (b) `skills`, `bay_types` tables.
- **Chosen: (a).** No edit screen; F03 compares codes; fewer joins. Moving to tables later touches only the enum and the CHECK.
- **Constraint:** every skill/bay-type comparison uses the enum, never free strings.

### ADR-01.2 · Seed with seeders, not migrations

- FR-01.1 says "loaded by a database migration". A migration that holds data cannot be rerun and mixes schema with demo data.
- **Chosen:** rerunnable seeders; the entrypoint already has `RUN_SEEDERS`.

### ADR-01.3 · One opening interval per day

- Lunch breaks are out of scope. Unique `(dealership_id, weekday)` keeps F03 simple.
- **Constraint:** F03 `OpeningHours` takes "one interval or closed" per day; supporting several intervals later changes the return type in one place.

### ADR-01.4 · Licence plates have a normalised key

- Users type `51H-123.45`, `51H 12345`, `51h12345`. A unique index on the raw string lets duplicates through. Store `plate_key` too.

## B2. Metrics

| ID | Metric | SQL | Threshold |
| --- | --- | --- | --- |
| M-01.1 | Services with no active qualified technician | `SELECT st.code FROM service_types st WHERE st.active AND NOT EXISTS (SELECT 1 FROM technicians t JOIN technician_skills s ON s.technician_id = t.id WHERE t.dealership_id = st.dealership_id AND t.active AND s.skill = st.required_skill);` | 0 rows |
| M-01.2 | Services with no active matching bay | `SELECT st.code FROM service_types st WHERE st.active AND NOT EXISTS (SELECT 1 FROM bays b WHERE b.dealership_id = st.dealership_id AND b.active AND b.bay_type = st.required_bay_type);` | 0 rows |
| M-01.3 | Open weekdays with no technician shift | Join `dealership_opening_hours` with `technician_shifts` on `weekday` | 0 rows |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-01.1 | Edit forms for master data | Models + CHECKs are ready; add a FormRequest + `admin` policy |
| FC-01.2 | Public holidays | `OpeningHours` (F03) takes closed dates |
| FC-01.3 | Many dealerships | Every table has `dealership_id`; route binding in one place |
