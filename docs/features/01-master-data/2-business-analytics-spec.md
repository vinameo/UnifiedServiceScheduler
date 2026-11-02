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
| E-01.3 | SDD §4 | `service_type(required_skill, required_bay_type)`, `technician_skill` with many skills — replaced by levels (README D-G.16). |
| E-01.4 | User decision 2026-11-03 | Technicians have a level 1–7; a service needs one level; higher levels can do lower-level services; no shifts, attendance or leave; admins can disable technicians; disabling is refused while upcoming appointments exist. |
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

### ADR-01.1 · One ordered level instead of a set of skills

- **Options:** (a) a set of skills per technician, one required skill per service (SDD §4); (b) one level 1–7 per technician, one required level per service; (c) both.
- **Chosen: (b)** (user decision, README D-G.16). It matches how the workshop grades technicians (bậc nghề), needs no join table, and F03 compares two integers. Bay types stay an enum + CHECK.
- **Cost accepted:** a narrow certification (for example high-voltage work) can only be expressed as "level ≥ n"; a level-6 technician without that certificate would still qualify. Revisit when a service needs a certificate that does not follow the level (FC-01.5).
- **Constraint:** every level comparison goes through `Level::qualifiesFor` (or the same `>=` in SQL), never ad-hoc.

### ADR-01.5 · Refuse to disable a technician with upcoming appointments

- **Options:** (a) refuse while upcoming appointments exist; (b) disable and reassign them automatically; (c) disable and leave them assigned.
- **Chosen: (a)** (user decision). Nothing changes for customers behind the advisor's back; (c) would leave appointments with a technician who is gone.
- **Cost accepted:** before disabling, an advisor must reschedule or cancel each listed appointment (no same-time swap yet, Q-01.c). Race-safe through the technician row lock (D-01.13).

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
| M-01.1 | Services with no active qualified technician | `SELECT st.code FROM service_types st WHERE st.active AND NOT EXISTS (SELECT 1 FROM technicians t WHERE t.dealership_id = st.dealership_id AND t.active AND t.level >= st.required_level);` | 0 rows |
| M-01.2 | Services with no active matching bay | `SELECT st.code FROM service_types st WHERE st.active AND NOT EXISTS (SELECT 1 FROM bays b WHERE b.dealership_id = st.dealership_id AND b.active AND b.bay_type = st.required_bay_type);` | 0 rows |
| M-01.3 | Disable attempts refused because of upcoming appointments (per week) | `SELECT count(*) FROM api_events WHERE code = 'TECHNICIAN_HAS_BOOKINGS' AND occurred_at > now() - interval '7 days';` (F08) | Watch; many → consider FC-01.4 (same-time swap) |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-01.1 | Edit forms for master data | Models + CHECKs are ready; add a FormRequest + `admin` policy |
| FC-01.2 | Public holidays | `OpeningHours` (F03) takes closed dates |
| FC-01.3 | Many dealerships | Every table has `dealership_id`; route binding in one place |
| FC-01.4 | Swap a booking's technician at the same time (to empty a technician before disabling) | F09 `reschedule` with an `avoidTechnicianId`; deferred (Q-01.c) |
| FC-01.5 | Certifications that do not follow the level | A `technician_certifications` table + `AllocationRequest.requiredCertification`; F03 filters in `CandidateFinder` step 1 |
| FC-01.6 | Shifts, attendance, leave | An extra `BusyIntervalReader` source (F03 ADR-03.3); `CandidateFinder` unchanged |
