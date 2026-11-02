# F12 · Day-of-service operations · Business analysis (BA)

---

# PART A · For readers

## A1. Why

The schedule is only a plan. On the day, the foreman needs to record reality (arrived, started, finished, did not come), absorb overruns, and react to absences and broken bays. Done by hand, these moves are where double bookings and forgotten customers happen. FR-12.5 sets the tone: the system never cancels a customer on its own.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-12.1 | Requirements F12 | FR-12.1–12.6, BR-12.1, 4 ACs. |
| E-12.2 | SDD §6 | "Foreman extends a late job while the advisor marks it completed" — a lost-update case. |
| E-12.3 | SDD §10 | Extend: version + EXCLUDE; reassign: version per appointment, 412 only for that one. |
| E-12.4 | Workshop board draft | Timeline by bay, legend (Completed, In progress, Checked in, Confirmed, On hold, Overrun), technicians table, overrun alert with "Extend 30 min". |
| E-12.5 | Reassign draft | Absence form, "Find affected appointments", per-booking proposal, "Apply reassignment", SMS note. |

## A3. Priorities

Could (requirements). Within F12: status actions are Must-like (they free slots); absence/reassign is the core value; extend with displacement and the board are Should.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-12.1 | A displaced customer is not called and arrives anyway | Contact list (F05 filter) + banner on the booking |
| RB-12.2 | Customers dislike a technician change | `AppointmentReassigned` SMS (F11); keep the same bay when possible |
| RB-12.3 | Foremen extend "just in case" and block later customers | Extend only while checked in/in progress; history records who extended |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-12.1 · Absences as unavailability, not fake allocations

- **Options:** (a) separate `technician_absences`/`bay_outages` tables read by F03; (b) insert `resource_allocations` rows without an appointment.
- **Chosen: (a).** `resource_allocations.appointment_id` is NOT NULL and every allocation belongs to a booking (INV-04.6). F03's `BusyIntervalReader` was designed for extra sources (ADR-03.3).

### ADR-12.2 · Extend in one transaction, reassignments of an absence one by one

- Extend changes one job and its direct neighbours; doing it atomically avoids a state where the job is extended but the neighbour still holds the bay (which EXCLUDE would refuse anyway). An absence can touch many bookings across a day; FR-12.4 requires independence so one 412 does not block the rest.

### ADR-12.3 · Displace, never cancel

- When an overrun physically takes the bay and nothing else is free, the next booking cannot keep its slot, but cancelling is forbidden (FR-12.5). Displacement (flag + no allocations) is honest about reality and keeps the customer visible for a call.

### ADR-12.4 · Preview then apply

- The draft shows a proposal before "Apply". The preview is read-only and may be stale; apply recomputes (same reasoning as F06 BR-06.1).

## B2. Metrics

| ID | Metric | SQL | Threshold |
| --- | --- | --- | --- |
| M-12.1 | Bookings waiting for a call | `SELECT dealership_id, count(*) FROM appointments WHERE contact_required_reason IS NOT NULL AND status = 'CONFIRMED' GROUP BY 1;` | Watch; > 0 at closing time → follow up |
| M-12.2 | Reassignment success rate | `SELECT avg((type = 'REASSIGNED')::int) FROM appointment_events WHERE type IN ('REASSIGNED','CONTACT_REQUIRED') AND occurred_at > now() - interval '30 days';` | Watch |
| M-12.3 | No-show rate | `SELECT avg((status = 'NO_SHOW')::int) FROM appointments WHERE start_at > now() - interval '30 days' AND start_at < now() AND status IN ('NO_SHOW','COMPLETED');` | Watch (F11 reminders should lower it) |
| M-12.4 | Average overrun minutes | `SELECT avg((data->>'minutes')::int) FROM appointment_events WHERE type = 'EXTENDED' AND occurred_at > now() - interval '30 days';` | Watch (service durations may be too short) |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-12.1 | Manual technician choice | `Reassigner` forced pair |
| FC-12.2 | Planned leave entered ahead | Absences already support future ranges (≤ 14 days; raise the bound) |
| FC-12.3 | Notify displaced customers automatically | Outbox event on `CONTACT_REQUIRED` |
