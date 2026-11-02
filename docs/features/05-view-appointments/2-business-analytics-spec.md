# F05 · View appointments · Business analysis (BA)

---

# PART A · For readers

## A1. Why

After booking, everyone asks "where is my booking?". Staff need the day list to run the workshop; customers need to be sure their booking is still there. The details page is also the "door" for every later change (cancel, reschedule, check-in), so it must carry the right `ETag`.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-05.1 | Requirements F05 | 3 endpoints, FR-05.1–05.4, 4 ACs. |
| E-05.2 | Appointments draft | Filters Date / Technician / Bay / Status; "Showing the first 8 appointments, sorted by start time"; "Load more". |
| E-05.3 | Appointment details draft | Info, customer and vehicle, history, closed banner, action buttons. |
| E-05.4 | Customer drafts | Upcoming/Past tabs; booking cards; details with location and phone. |
| E-05.5 | F00 AC-00.7, AC-00.8 | Two permission ACs that need F05's endpoints. |

## A3. Priorities

Must: the 3 requirement endpoints, scope, screens. Should: history, D-05.8 links. Could: 60-second auto refresh.

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-05.1 | A screen left open shows old data | `ETag` + 412 on every change (F04); refresh when returning to the tab |
| RB-05.2 | Customers confuse "Past" with "Cancelled" | A status badge on every card |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-05.1 · Scope in the query, 404 instead of 403

- Reading first and then calling `authorize` can reveal existence through timing or error codes. Put `customer_id`/`dealership_id` in the `WHERE`; not visible = does not exist (`CLAUDE.md` §7).

### ADR-05.2 · Cursor instead of offset

- FR-05.4. Offsets shift when a booking is inserted between two pages; a cursor on the sort key does not.

### ADR-05.3 · ETag in the web cache

- Every later change needs `If-Match`. Keep the `etag` next to the data in the TanStack Query cache (`CLAUDE.md` frontend), updated from every 200/201/412 response.

## B2. Metrics

| ID | Metric | SQL / source | Threshold |
| --- | --- | --- | --- |
| M-05.1 | Day list p95 | `api_events` route `GET api/v1/dealerships/{dealership}/appointments` (F08) | < 200 ms |
| M-05.2 | `APPOINTMENT_NOT_FOUND` 404s per user per hour | `SELECT user_id, count(*) FROM api_events WHERE code = 'APPOINTMENT_NOT_FOUND' AND occurred_at > now() - interval '1 hour' GROUP BY 1 HAVING count(*) > 50;` | 0 rows (many → possible id probing) |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-05.1 | Search by plate/name | Add a parameter to `DealershipAppointmentsRequest`; the trigram index exists (F02) |
| FC-05.2 | Customers see history | Change the history route gate; the data already has no PII |
| FC-05.3 | "Contact required" filter (F12) | Parameter `contactRequired=true` (added by F12) |
