# F11 · Notifications through the outbox · Business analysis (BA)

---

# PART A · For readers

## A1. Why

Confirmations and reminders reduce no-shows; the DMS needs every change to stay in sync. But messaging providers fail often. BR-11.1 states the trade-off: a message may arrive a few seconds late, but a booking never fails because the SMS provider is down.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-11.1 | Requirements F11 | FR-11.1–11.5, BR-11.1, 4 ACs. |
| E-11.2 | SDD §12 | Transactional outbox; relay after commit; up to 5 retries with growing waits; at least once. |
| E-11.3 | `CLAUDE.md` rule 4 | Never call SMS/email/DMS inside a request; relay every 10 seconds `withoutOverlapping()`. |
| E-11.4 | Draft "Outgoing notifications" | Counts Pending / Retrying / Failed, needs action / Sent today; filters; Resend. |
| E-11.5 | F04 | Outbox table and writer exist; rows pile up as `PENDING`. |

## A3. Priorities

Could (requirements). Within F11: the relay with retries is the core; reminders and the admin screen are Should.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-11.1 | A customer gets a confirmation for a booking already cancelled | Relevance check (D-11.7) |
| RB-11.2 | Duplicate SMS after a crash | At least once by design; receivers dedupe by `eventId`. Owner: product owner. Revisit when M-11.2 or complaints show duplicates more than once a week. |
| RB-11.3 | Failed messages go unnoticed | "Failed, needs action" card; `DELIVERY_FAILED` in the monitoring screen (F08) |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-11.1 · Polling relay with `SKIP LOCKED`

- **Options:** (a) a scheduled relay polling the table; (b) Laravel queue jobs dispatched after commit; (c) logical decoding / CDC.
- **Chosen: (a).** `CLAUDE.md` prescribes it; no queue infrastructure needed; `SKIP LOCKED` + leases make parallel runs safe if `withoutOverlapping` ever fails.

### ADR-11.2 · Resolve recipients at send time

- Keeps personal data out of the outbox (NFR-05) and sends to the current phone/email.

### ADR-11.3 · Log drivers by default

- No provider is chosen (Q-11.a). Log drivers prove the pipeline end to end; a failing driver demonstrates retries and the admin screen.

## B2. Metrics

| ID | Metric | SQL | Threshold |
| --- | --- | --- | --- |
| M-11.1 | Pending older than 2 minutes | `SELECT count(*) FROM outbox_messages WHERE status = 'PENDING' AND created_at < now() - interval '2 minutes';` | 0 normally; alert if > 50 |
| M-11.2 | Failed per channel (7 days) | `SELECT channel, count(*) FROM outbox_messages WHERE status = 'FAILED' AND updated_at > now() - interval '7 days' GROUP BY 1;` | Watch |
| M-11.3 | Send delay p95 | `SELECT percentile_cont(0.95) WITHIN GROUP (ORDER BY sent_at - created_at) FROM outbox_messages WHERE status = 'SENT' AND sent_at > now() - interval '1 day';` | < 30 s |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-11.1 | Real SMS/email/DMS providers | `NotificationChannel` implementations |
| FC-11.2 | Message templates per dealership/language | `AppointmentNotification` |
| FC-11.3 | High volume | Batch size and relay frequency in `config/outbox.php` |
