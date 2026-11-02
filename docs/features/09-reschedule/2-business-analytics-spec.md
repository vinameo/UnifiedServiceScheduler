# F09 · Reschedule · Business analysis (BA)

---

# PART A · For readers

## A1. Why

Without reschedule, a customer who wants another time must cancel and book again — and if the new time is full, they lose both. Advisors on the phone need a safe "move" in one step. The SDD names "customer moves while advisor cancels" as a classic lost update; F09 is where both protections (version check and EXCLUDE) run in one transaction.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-09.1 | Requirements F09 | FR-09.1–09.4, BR-09.1, 3 ACs. |
| E-09.2 | SDD §10 | Reschedule SQL example and "why this order". |
| E-09.3 | SDD §13 | Tests: move inside its own slot, move to a taken time, retry after success, two changes at once. |
| E-09.4 | Drafts | Staff "Reschedule appointment" (current booking card, "Prefer keeping technician", "Change to …"); customer "Change appointment time" ("Current time → New time"). |
| E-09.5 | F04 AC-04.6 | Proved at repository level; needs real cancel + reschedule endpoints. |

## A3. Priorities

Could (requirements). Within F09: the endpoint and the "nothing changes on failure" rule are the core; the customer screen is Should.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-09.1 | Customers move bookings at the last minute | Same 2-hour rule as cancel |
| RB-09.2 | The technician changes after a move and the customer is surprised | Prefer the same technician; the SMS says who (F11 template) |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-09.1 · Move in place, never cancel + create

- **Options:** (a) one transaction that moves the existing booking; (b) cancel the old booking and create a new one.
- **Chosen: (a).** Keeps id, reference and history; the old slot is never lost; one version check protects against concurrent changes. (b) needs compensation when the create fails.

### ADR-09.2 · Version first, then EXCLUDE (SDD §10)

- The versioned update locks the row so only one change at a time; deactivating old allocations before inserting new ones avoids self-clashes; a retry keeps the same expected version so a concurrent change still shows as 412.

### ADR-09.3 · Prefer the same technician and bay

- FR-09.3. The strategy already supports preferences (F03 D-03.8); preferring the bay too keeps small moves in the same bay.

## B2. Metrics

| ID | Metric | SQL / source | Threshold |
| --- | --- | --- | --- |
| M-09.1 | Reschedules per day | `SELECT date_trunc('day', occurred_at), count(*) FROM appointment_events WHERE type = 'RESCHEDULED' GROUP BY 1 ORDER BY 1 DESC;` | Watch |
| M-09.2 | Share of reschedules that kept the technician | `SELECT avg(((data->'from'->>'technicianId') = (data->'to'->>'technicianId'))::int) FROM appointment_events WHERE type = 'RESCHEDULED' AND occurred_at > now() - interval '30 days';` | ≥ 80% |
| M-09.3 | Failed reschedules by code | `SELECT code, count(*) FROM api_events WHERE route = 'POST api/v1/appointments/{appointment}/reschedule' AND status >= 400 GROUP BY 1;` | Watch |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-09.1 | Change the service type during a move | `RescheduleAppointment` takes a new duration; `moveTo` already takes any range |
| FC-09.2 | Release expired holds blocking the new time | F10 adds it to the booking flow used by `moveTo` |
| FC-09.3 | Per-dealership change window | `CustomerChangePolicy` (F07 FC-07.2) |
