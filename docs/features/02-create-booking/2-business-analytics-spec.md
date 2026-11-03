# F02 · Create a booking · Business analysis (BA)

---

# PART A · For readers

## A1. Why

Booking is the main value the dealership sees. Two channels (desk, app) book into limited capacity. Users need three things: a firm result at once (no "pending confirmation"), a clear reason when it fails, and never being booked twice because they tapped again.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-02.1 | Requirements F02 | FR-02.1–02.6, BR-02.1–02.3, 8 ACs; endpoint `POST /appointments` + `Idempotency-Key`. |
| E-02.2 | New booking draft | 3 sections: Customer and vehicle, Service, Date and time; Summary; "Assigned automatically on confirm"; request-key note. |
| E-02.3 | Booking error states draft | 5 error states + "Processing / Double-submit guard". |
| E-02.4 | Customer drafts 1–4 | 3-step flow, dealership phone "028 3812 3456", "Please arrive 10 minutes early". |
| E-02.5 | SDD §8 | Steps to save a new booking. |
| E-02.6 | NFR-02 | p95 < 300 ms with 20 bays, 30 technicians. |

## A3. Priorities

| Priority | Items |
| --- | --- |
| Must | Booking endpoint; time rules; permissions; New booking + Confirmed; 3-step customer flow |
| Should | Multi-field customer search; adding a vehicle in the flow; keeping the draft on reload |
| Could | "Show number" |

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-02.1 | Before F06, advisors guess free times → many 409s | The error card says why; F06 comes right after F05 |
| RB-02.2 | A customer enters someone else's plate → 409 | The message tells them to call the dealership; the owner is never revealed |
| RB-02.3 | Advisors searching customers see every customer's personal data | Accepted for staff (needed to book, D-02.15); never in logs; 20 results max, ≥ 3 characters, 60/minute. Owner: product owner. Revisit when one advisor hits the 60/minute limit more than 3 times a day (429 rows in `api_events`, F08). |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-02.1 · Business checks in the app, the final word in the DB

- Time, permission and vehicle checks run in the app for clear error codes; slot and vehicle clashes are decided by the DB (F04). No `SELECT` pre-check for vehicle clashes: EXCLUDE returns `VEHICLE_ALREADY_BOOKED`.
- **Constraint:** the D-02.7 check order is fixed so it can be tested.

### ADR-02.2 · One endpoint for both channels

- **Options:** (a) one `POST /appointments` with role-based rules; (b) separate `/staff/appointments` and `/me/appointments`.
- **Chosen: (a)** per the requirements and SDD §11. The differences (who the customer is, which dealership) live in `CreateBooking` steps 1 and 3.

### ADR-02.3 · 15-minute step required

- The time grid (F06) uses 15 minutes; odd start times fragment the schedule and make suggestions hard. Enforced at the API so other clients follow it too.

### ADR-02.4 · No free-time grid in F02

- F06 owns the free-time grid. F02 delivers a simple grid (every time within opening hours) so the flow works; F06 swaps the component (`TimeGrid` → `SlotGrid`) without changing the flow.

## B2. Metrics

| ID | Metric | SQL / source | Threshold | Status / Value |
| --- | --- | --- | --- | --- |
| M-02.1 | `POST /appointments` p95 | `SELECT percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms) FROM api_events WHERE route = 'POST api/v1/appointments' AND occurred_at > now() - interval '1 day';` (F08); before F08: TC-02-30 | < 300 ms | Measured < 300 ms (TC-02-30 `CreateBookingPerformanceTest` passes) |
| M-02.2 | Booking success rate | `booking_requests_total{outcome="confirmed"} / booking_requests_total` (F08) | Watch | Instrumented in F08 |
| M-02.3 | Bookings per channel per day | `SELECT date_trunc('day', created_at), source, count(*) FROM appointments GROUP BY 1, 2 ORDER BY 1 DESC;` | Watch | Verified via `appointments.source` (`STAFF`, `CUSTOMER`) |
| M-02.4 | Bookings not on a 15-minute step | `SELECT count(*) FROM appointments a JOIN dealerships d ON d.id = a.dealership_id WHERE extract(minute FROM a.start_at AT TIME ZONE d.timezone)::int % 15 <> 0;` | 0 | 0 violations (guaranteed by `BookingTimeRules` and TC-02-12, TC-02-31) |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-02.1 | Several services in one booking | `CreateBookingInput` |
| FC-02.2 | Advisors create walk-in customers | `CustomerSearch` + `customers.user_id` may become nullable later (ADR-00.3) |
| FC-02.3 | Pricing, deposits | Out of scope; no columns |
| FC-02.4 | More UI languages | `copy.ts` (README D-G.4) |
