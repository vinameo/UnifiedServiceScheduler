# F08 · Monitoring · Business analysis (BA)

---

# PART A · For readers

## A1. Why

Without figures we cannot know when SDD phase 2 is needed (retry rate > 5%, p95 > 300 ms), and every customer complaint becomes a blind investigation. The drafts stress separating "409 · business outcome, not an outage" from "System errors (5xx)".

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-08.1 | Requirements F08 | FR-08.1–08.5, 4 ACs; 6 named counters. |
| E-08.2 | SDD §12, §14 | Count 409, 412, 23P01 retries; p95 of `POST /appointments`; phase-2 thresholds. |
| E-08.3 | System monitoring draft | 3 health cards, 7 figure cards, recent errors table, search by correlationId, 1h/24h/7d ranges. |
| E-08.4 | `CLAUDE.md` | JSON logs with 4 fields; no state in PHP-FPM memory; `/metrics` not exposed. |
| E-08.5 | Existing code | `CorrelationId`, `HealthController`, Nginx blocks `/metrics`. |

## A3. Priorities

Should. Within F08: logs + counters + screen are the core; tracing is Could within Should (new packages, off by default).

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-08.1 | Admins read many 409s as an incident | Label "business outcome, not an outage" |
| RB-08.2 | Recent errors leak customer data | `detail` is redacted (INV-08.7); admin only |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-08.1 · Counters in PostgreSQL

- **Options:** (a) APCu/worker memory; (b) Redis; (c) a `metric_counters` table; (d) Prometheus pushgateway.
- **Chosen: (c).** (a) gives one number per worker, lost on restart, forbidden by `CLAUDE.md`; (b) is not in compose; (d) adds a service. (c) is simple, durable and fast enough for current traffic (R-08.2).

### ADR-08.2 · `api_events` for the screen, logs for deep investigation

- The screen needs queries (counts per range, search by id) → a table. JSON logs go to stderr for the platform's log tooling. Both share `correlationId`.

### ADR-08.3 · `/metrics` through an internal Nginx

- Prometheus does not speak FastCGI. An internal Nginx server (not published) + a `fastcgi_param` flag tells the app where the request came from; defence in depth together with the public 404.

### ADR-08.4 · OpenTelemetry behind a seam

- FR-08.4 asks for OTel. New packages (D-08.8). A `Tracer` seam + `NullTracer` to switch it off entirely and keep the domain free of the SDK.

## B2. Metrics

| ID | Metric | SQL / source | Threshold | Measured (Acceptance) |
| --- | --- | --- | --- | --- |
| M-08.1 | Log lines with personal data | `grep -E '@[A-Za-z0-9.-]+\.[a-z]{2,}|\b0[0-9]{9}\b|"password"'` on CI logs | 0 | 0 (PASS, 0 matches across php container logs) |
| M-08.2 | Retry rate (SDD phase 2) | `allocation_retries_total / booking_requests_total` | > 5%/hour → look at phase 2 | 0.00% (0 retries / 2 bookings, PASS) |
| M-08.3 | Booking p95 | `SELECT percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms) FROM api_events WHERE route = 'POST api/v1/appointments' AND occurred_at > now() - interval '1 hour';` | < 300 ms | 100.3 ms (PASS) |
| M-08.4 | 5xx per hour | `SELECT count(*) FROM api_events WHERE status >= 500 AND occurred_at > now() - interval '1 hour';` | Watch | 0 (PASS) |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-08.1 | Alerts | Read `metric_counters`/`api_events`; not in this version |
| FC-08.2 | Many dealerships, a system-wide admin | INV-08.6 scope in one repository |
| FC-08.3 | High traffic | Aggregate counters per minute before upserting |
