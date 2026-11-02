# F12 · Day-of-service operations · Web spec (WEB)

---

# PART A · For readers

| Screen | Path | Draft |
| --- | --- | --- |
| Workshop board | `/staff/workshop` | `Admin/07-workshop-board.png` |
| Appointment details actions | `/staff/appointments/:id` | `Admin/05-appointment-details.png` |

```
Workshop board
Monday, 2 Nov 2026 · updated 10:50
┌ (!) Bay 3 is running 20 minutes over ─────────────────────────────── [Extend 30 min] ┐
│ Phạm Quốc Bảo · 20,000 km service · Võ Thị Chi. The next booking for this bay and   │
│ technician at 14:00 was cancelled, so nothing is affected yet.                      │
└──────────────────────────────────────────────────────────────────────────────────────┘
Schedule by bay        08   09   10   11 | 12   13   14   15   16
Bay 1 Quick service    [██ An ][██ Dũng ]|
Bay 3 Standard         [███████ Chi ████▓▓]  (red line = now)
Legend: Completed · In progress · Checked in · Confirmed · On hold · Overrun
Technicians today
Technician      Level    Booked   Status
Trần Minh An    Level 2  120 min  Working
Võ Thị Chi      Level 4  180 min  Overrun
```

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Routes

| Path | Element | Guard |
| --- | --- | --- |
| `/staff/workshop` | `staff/workshop/WorkshopBoardPage` | every staff role; foreman sees the action buttons |

## B2. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-12.1 | `useWorkshopBoard(date)` | `web/src/shared/api/workshop.ts` | `refetchInterval` 30 s; "updated {HH:mm}" from `data.now`. |
| WEB-12.2 | `useAppointmentAction(id, action)` | same | `check-in`, `start`, `complete`, `no-show`: `If-Match` + key (`useIdempotencyKey({id, action, etag})`, settled after every answer, README D-G.15); 200 → `setAppointment`, invalidate board and lists. |
| WEB-12.3 | `useExtend(id)` | same | Body `{minutes}`; shows `affected` in a notification ("Next booking moved to Bay 2" / "1 booking needs customer contact"). |
| WEB-12.5 | `BayTimeline` | `web/src/staff/workshop/BayTimeline.tsx` | Columns per hour from `opensAt` to `closesAt`; blocks positioned by minutes in the dealership zone; status colours as `AppointmentStatusBadge`; overrun part hatched red; a red line at `now`. Blocks link to details. |
| WEB-12.6 | `AppointmentActions` | `web/src/staff/appointments/AppointmentActions.tsx` | Buttons by status and role (B3); plugs into `AppointmentDetailsLayout.actions` with F07/F09 buttons. |

## B3. Behaviour

**Appointment details actions** (role / status):

| Status | Advisor | Foreman |
| --- | --- | --- |
| CONFIRMED | Check in · Reschedule (F09) · Cancel (F07) · Mark no-show (after start) | Check in · Mark no-show (after start) |
| CHECKED_IN | — | Start work · Extend time |
| IN_PROGRESS | — | Mark completed · Extend time |

"Mark no-show" before the start shows disabled with the tooltip "Available after {time}". "Extend time" opens a menu: 15, 30, 45, 60, 90, 120 min. A `contactRequiredReason` shows a yellow banner "Customer contact needed: {reason}" with the phone and Reschedule/Cancel buttons (advisor).

**Workshop board**: overrun alert cards (one per alert) with "Extend 30 min" (foreman), which sends `If-Match` from the alert's `version` (RQ D-12.15); a 412 shows `AppointmentModifiedAlert` and refetches the board; the technicians table shows Technician · Level · Booked · Status (RQ D-12.16), no actions; displaced bookings listed under the timeline as "Needs customer contact".

## B4. Copy

| Key | Copy |
| --- | --- |
| `board.title` | Workshop board |
| `board.updated` | {dayLong} · updated {time} |
| `board.overrunTitle` | {bay} is running {minutes} minutes over |
| `board.overrunBodyFree` | {customer} · {service} · {technician}. The next booking for this bay and technician at {time} was cancelled, so nothing is affected yet. |
| `board.overrunBodyNext` | {customer} · {service} · {technician}. The next booking at {time} will be moved or flagged if you extend. |
| `board.overrunBodyNone` | {customer} · {service} · {technician}. No other booking follows today. |
| `board.extend30` | Extend 30 min |
| `board.byBay` | Schedule by bay |
| `board.legend` | Completed · In progress · Checked in · Confirmed · On hold · Overrun |
| `board.caption` | The red line is the current time. Each block is an appointment holding a bay; cancelled and no-show appointments are not shown. |
| `board.technicians` | Technicians today |
| `board.columns` | Technician · Level · Booked · Status |
| `board.working` / `overrun` | Working / Overrun |
| `board.level` | Level {n} |
| `board.displaced` | Needs customer contact |
| `actions.checkIn` | Check in |
| `actions.start` | Start work |
| `actions.complete` | Mark completed |
| `actions.noShow` | Mark no-show |
| `actions.noShowLater` | Available after {time} |
| `actions.extend` | Extend time |
| `actions.extendBy` | {minutes} min |
| `actions.extended` | Time extended to {time} |
| `actions.movedNext` | Next booking {reference} moved to {technician}, {bay} |
| `actions.flaggedNext` | Booking {reference} needs customer contact |
| `contact.banner` | Customer contact needed: {reason} |
| `contact.displacedNote` | This booking no longer has a bay or technician. Reschedule it before checking in. |
| `contact.reason.OVERRUN` | the previous job ran late |
