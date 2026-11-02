# F08 · Monitoring · Web spec (WEB)

---

# PART A · For readers

The "System monitoring" screen (`/staff/monitoring`, admin only), per the draft `Admin/11-system-monitoring.png`:

```
System monitoring                                 [1 hour | 24 hours | 7 days]
Service health, booking metrics and recent errors.
┌ Application ──────┐ ┌ Database ──────────────┐ ┌ Outbox pending ───────┐
│ Running · p95 184 │ │ PostgreSQL · 8/100 conn│ │ 2 events · oldest 7min│
│ ms (bookings)  UP │ │                     UP │ │                 [View]│
└───────────────────┘ └────────────────────────┘ └───────────────────────┘
Bookings in the last 24 hours
[Booking requests 1,248] [Confirmed 1,102 · 88.3%] [No availability (409) 131 · business outcome, not an outage]
[System errors (5xx) 15 · 1.2%] [p95 184 ms · Target under 300 ms] [Allocation retries 23] [Idempotent replays 41]
Recent errors                                  [Search by correlationId ___]
Time      Endpoint            Error code       HTTP  correlationId  Details
16:44:03  POST /appointments  NO_AVAILABILITY  409   7f3c9a1e       No Standard service bay free 10:00–12:00
```

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Route and data

| Path | Element | Guard |
| --- | --- | --- |
| `/staff/monitoring` | `staff/monitoring/MonitoringPage` | `RequireRole ['ADMIN']` |

| ID | Hook | API | Note |
| --- | --- | --- | --- |
| WEB-08.1 | `useMonitoringSummary(range)` | API-08.1 | `refetchInterval` 30 s; `range` in the URL `?range=24h` (default `1h`) |
| WEB-08.2 | `useMonitoringErrors(correlationId)` | API-08.2 | Runs when the search box has ≥ 8 characters (300 ms debounce) |

## B2. Display

- Health cards: `UP` badge green / `DOWN` red; Outbox "{n} events · oldest {age}" (`age` like "7 min", "2 h"); the "View" button → `/staff/notifications?status=PENDING` once F11 has the page.
- Figure cards: numbers formatted `en-GB` (1,248); percentages with 1 decimal against `total`; p95 red when ≥ 300.
- Error table: Time `HH:mm:ss` in the dealership's zone; correlationId shows the first 8 characters, click to copy the whole string (`CopyButton`); 5xx rows light red.
- While searching: the table shows the search results; clearing the box returns to "Recent errors".
- Load error: `Alert` + "Retry"; 403 cannot happen because of the guard.
- The existing `HealthBadge` (`web/src/shared/HealthBadge.tsx`) for `/health/ready` sits in the corner of the Application card.

## B3. Copy

| Key | Copy |
| --- | --- |
| `title` | System monitoring |
| `subtitle` | Service health, booking metrics and recent errors. |
| `range.1h` / `24h` / `7d` | 1 hour / 24 hours / 7 days |
| `health.app` | Application |
| `health.appBody` | Running · p95 {ms} ms (bookings) |
| `health.db` | Database |
| `health.dbBody` | PostgreSQL · {used}/{max} connections |
| `health.outbox` | Outbox pending |
| `health.outboxBody` | {n} events · oldest {age} |
| `health.view` | View |
| `bookings.title` | Bookings in the last {range} |
| `bookings.total` | Booking requests |
| `bookings.totalNote` | Total POST /appointments |
| `bookings.confirmed` | Confirmed |
| `bookings.ofRequests` | {pct}% of requests |
| `bookings.noAvailability` | No availability (409) |
| `bookings.noAvailabilityNote` | {pct}% · business outcome, not an outage |
| `bookings.errors` | System errors (5xx) |
| `bookings.p95` | p95 response time |
| `bookings.p95Note` | Target under 300 ms |
| `bookings.retries` | Allocation retries |
| `bookings.retriesNote` | Constraint conflicts, retried automatically |
| `bookings.replays` | Idempotent replays |
| `bookings.replaysNote` | Returned the original result, no duplicate |
| `errors.title` | Recent errors |
| `errors.search` | Search by correlationId |
| `errors.columns` | Time · Endpoint · Error code · HTTP · correlationId · Details |
| `errors.empty` | No errors in this period. |
| `errors.notFound` | No error with this correlation id. |
| `errors.copied` | Copied |
