# F07 · Cancel an appointment · Business analysis (BA)

---

# PART A · For readers

## A1. Why

A slot held by a customer who will not come is lost revenue. Cancelling must return the slot at once. The 2-hour limit for customers gives the workshop time to rearrange; advisors have no limit because they talk to the customer directly.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-07.1 | Requirements F07 | FR-07.1–07.4, BR-07.1–07.2, 4 ACs. |
| E-07.2 | SDD §6 | "Customer moves while advisor cancels → the cancelled booking comes back" — why `If-Match` is needed. |
| E-07.3 | SDD §10 | Cancel: version check, no EXCLUDE, error 412. |
| E-07.4 | Drafts | Staff dialog (Reason + Note), customer sheet (3 reasons), banner after cancelling, dealership number "028 3812 3456". |

## A3. Priorities

Should. Within F07: endpoint + rules are the core; the customer UI is Should.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-07.1 | Many last-minute customer cancellations | The 2-hour rule; M-07.2 |
| RB-07.2 | An advisor cancels by mistake | The confirmation dialog says "This cannot be undone."; history records who cancelled |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-07.1 · A duplicate cancel is a success

- **Options:** (a) 412/422 when already cancelled; (b) 200 with the current state.
- **Chosen: (b)** per FR-07.2, extended to concurrent clashes (D-07.5): the user's goal is already met; an error only confuses.
- **Constraint:** only when the fresh status is `CANCELLED`; nothing else is written.

### ADR-07.2 · Reasons are fixed codes

- Codes allow statistics (M-07.1) and translation; the free note is separate.

## B2. Metrics

| ID | Metric | SQL | Threshold |
| --- | --- | --- | --- |
| M-07.1 | Cancellations by reason and channel (30 days) | `SELECT cancel_reason, source, count(*) FROM appointments WHERE status = 'CANCELLED' AND cancelled_at > now() - interval '30 days' GROUP BY 1, 2;` | Watch |
| M-07.2 | Customers blocked for being too late | `SELECT count(*) FROM api_events WHERE code = 'TOO_LATE_TO_CANCEL' AND occurred_at > now() - interval '7 days';` | Watch |
| M-07.3 | Cancelled slots booked again | `SELECT count(DISTINCT c.id) FROM appointments c JOIN appointments n ON n.technician_id = c.technician_id AND tstzrange(n.start_at, n.end_at, '[)') && tstzrange(c.start_at, c.end_at, '[)') AND n.created_at > c.cancelled_at WHERE c.status = 'CANCELLED' AND c.cancelled_at > now() - interval '30 days';` divided by the cancellations of the same period | Watch |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-07.1 | Cancellation fees | `CancelAppointment` returns fee details; no columns today |
| FC-07.2 | Per-dealership time limits | `CustomerChangePolicy` takes the hours from the dealership |
