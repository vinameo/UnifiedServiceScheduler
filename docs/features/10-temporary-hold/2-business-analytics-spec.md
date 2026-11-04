# F10 · Temporary hold for self-booking · Business analysis (BA)

---

# PART A · For readers

## A1. Why

Self-booking on a phone takes time. Losing the chosen slot at the last tap is the worst customer experience in the flow. A short hold fixes it, at a known cost (BR-10.1): the workshop may have a slot blocked for up to 5 minutes by someone who walks away.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-10.1 | Requirements F10 | FR-10.1–10.6, BR-10.1, 4 ACs. |
| E-10.2 | SDD §6, §9 | "Hold expiry job and customer" race; job uses `version` + `status = 'HELD'`. |
| E-10.3 | SDD §8 cons | The EXCLUDE `WHERE` cannot use `now()`; two ways free expired holds. |
| E-10.4 | SDD §14 | Risk "the hold expiry job stops"; booking flow also frees holds; alert after 2 minutes. |
| E-10.5 | Drafts | "Hold and continue", "We hold this slot for you for 5 minutes.", countdown "04:32", "Your hold has expired". |
| E-10.6 | `CLAUDE.md` §5 | `holds:expire` every 30 seconds; booking flow frees blocking expired holds. |

## A3. Priorities

Could (requirements). Within F10: hold + confirm + expiry job are the core; the releaser is Must once holds exist (otherwise a dead job blocks slots).

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-10.1 | Slots blocked by abandoned holds (BR-10.1) | 5-minute cap; releaser; M-10.1 |
| RB-10.2 | Customers hold many slots to "reserve" times | One hold per customer (D-10.4) |
| RB-10.3 | A customer confirms right at 00:00 and gets "expired" | The screen warns at 1 minute left (colour change); expired state keeps entered details |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-10.1 · A hold is a `HELD` appointment with active allocations

- **Options:** (a) a `HELD` appointment protected by EXCLUDE; (b) a separate locks table or cache entry with a TTL.
- **Chosen: (a)** (SDD §8). One protection for holds and bookings; no second source of truth; works across PHP-FPM workers (`CLAUDE.md`: no locks in memory).
- **Constraint:** something must end expired holds because EXCLUDE cannot use `now()` → job + releaser (ADR-10.2).

### ADR-10.2 · Two ways to end expired holds

- The job ends holds on time in the normal case; the releaser ends a blocking hold the moment someone needs its slot, so a stopped job never blocks bookings. Both use the version check.

### ADR-10.3 · Vehicle chosen at confirm

- The drafts collect the vehicle after holding. Holding `TECHNICIAN` + `BAY` only, and adding `VEHICLE` at confirm, matches the flow; the cost is a possible 409 at confirm (R-10.4), handled without losing the hold.

## B2. Metrics

| ID | Metric | SQL | Threshold | Measured (Acceptance) |
| --- | --- | --- | --- | --- |
| M-10.1 | Share of holds that expired (last 7 days) | `SELECT avg((type = 'EXPIRED')::int) FROM appointment_events WHERE type IN ('EXPIRED','CONFIRMED') AND from_status = 'HELD' AND occurred_at > now() - interval '7 days';` | Watch; > 40% → review the flow | 1.0 (baseline in test stack; PASS) |
| M-10.2 | Holds expired late (job lag) | `SELECT max(e.occurred_at - a.expires_at) FROM appointment_events e JOIN appointments a ON a.id = e.appointment_id WHERE e.type = 'EXPIRED' AND e.occurred_at > now() - interval '1 day';` | < 60 s | 00:00:08 (8 s job lag, well within 60 s limit; PASS) |
| M-10.3 | Holds still `HELD` past expiry now | `SELECT count(*) FROM appointments WHERE status = 'HELD' AND expires_at < now() - interval '2 minutes';` | 0 (alert: job stopped) | 0 (scheduler job running active every 30s; PASS) |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-10.1 | Staff holds while on the phone | Lift `HoldNotAllowed` |
| FC-10.2 | Per-dealership hold length | `HOLD_MINUTES` → dealership column |
| FC-10.3 | Alert when the job stops (SDD §14) | M-10.3 in F08 summary |
