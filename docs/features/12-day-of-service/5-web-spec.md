# F12 · Day-of-service operations · Web spec (WEB)

---

# PART A · For readers

| Screen | Path | Draft |
| --- | --- | --- |
| Workshop board | `/staff/workshop` | `Admin/07-workshop-board.png` |
| Report absence and reassign | `/staff/workshop/absence` | `Admin/08-report-absence-reassign.png` |
| Report bay out of service | `/staff/workshop/outage` | same layout, Bay picker |
| Appointment details actions | `/staff/appointments/:id` | `Admin/05-appointment-details.png` |

```
Workshop board                         [Report bay out of service] [Report absence]
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
Technician      Skills                      Shift        Booked   Availability  Actions
Trần Minh An    Basic                       08:00–17:00  120 min  Present       Report absence
```

```
Report technician absence and reassign
The system reruns allocation for each affected appointment. Any it cannot reassign waits for
you to contact the customer; nothing is cancelled automatically.
Technician [Ngô Bảo Em ▾]  From [03/11 08:00]  To [03/11 17:00]  Reason [Sick leave ▾]
[Find affected appointments]
3 affected appointments · 2 can be reassigned · 1 needs customer contact
┌ 08:00–09:30 Tue 3 Nov · Lâm Khánh · VinFast VF e34 · EV battery check ─ Needs contact ┐
│ No other technician with High voltage skill is available that day.                   │
│ [Call customer] [Offer another time]                                                 │
└──────────────────────────────────────────────────────────────────────────────────────┘
┌ 10:00–12:00 Tue 3 Nov · Đặng Hữu Phúc · 20,000 km service ─ Reassignable ┐
│ Ngô Bảo Em → Lý Hoàng Dũng · keeps Bay 3                                 │
└──────────────────────────────────────────────────────────────────────────┘
Customers of reassigned appointments get an SMS about the technician change.
[Discard] [Apply reassignment]
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
| `/staff/workshop/absence` | `staff/workshop/ReportUnavailabilityPage` (`kind="technician"`) | `WORKSHOP_FOREMAN` |
| `/staff/workshop/outage` | same (`kind="bay"`) | `WORKSHOP_FOREMAN` |

## B2. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-12.1 | `useWorkshopBoard(date)` | `web/src/shared/api/workshop.ts` | `refetchInterval` 30 s; "updated {HH:mm}" from `data.now`. |
| WEB-12.2 | `useAppointmentAction(id, action)` | same | `check-in`, `start`, `complete`, `no-show`: `If-Match` + key; 200 → `setAppointment`, invalidate board and lists. |
| WEB-12.3 | `useExtend(id)` | same | Body `{minutes}`; shows `affected` in a notification ("Next booking moved to Bay 2" / "1 booking needs customer contact"). |
| WEB-12.4 | `useImpactPreview(kind, id, from, to)` / `useApplyUnavailability(kind, id)` | same | Preview is a query run on "Find affected appointments"; apply is a mutation with its own key. |
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

**Workshop board**: overrun alert cards (one per alert) with "Extend 30 min" (foreman); "Report absence" per technician row opens the absence screen with that technician preselected; displaced bookings listed under the timeline as "Needs customer contact".

**Report screen**: form validation mirrors API §B4; "Find affected appointments" → preview cards with outcome badges (Reassignable / Needs contact); a needs-contact card shows the explanation, "Call customer" (`tel:` link) and "Offer another time" (→ F09 Reschedule). "Apply reassignment" → results replace the preview (Reassigned / Needs contact / Changed by someone else); "Discard" clears the form. Empty preview: "No appointments are affected."

## B4. Copy

| Key | Copy |
| --- | --- |
| `board.title` | Workshop board |
| `board.updated` | {dayLong} · updated {time} |
| `board.reportOutage` | Report bay out of service |
| `board.reportAbsence` | Report absence |
| `board.overrunTitle` | {bay} is running {minutes} minutes over |
| `board.overrunBodyFree` | {customer} · {service} · {technician}. The next booking for this bay and technician at {time} was cancelled, so nothing is affected yet. |
| `board.overrunBodyNext` | {customer} · {service} · {technician}. The next booking at {time} will be moved or flagged if you extend. |
| `board.overrunBodyNone` | {customer} · {service} · {technician}. No other booking follows today. |
| `board.extend30` | Extend 30 min |
| `board.byBay` | Schedule by bay |
| `board.legend` | Completed · In progress · Checked in · Confirmed · On hold · Overrun |
| `board.caption` | The red line is the current time. Each block is an appointment holding a bay; cancelled and no-show appointments are not shown. |
| `board.technicians` | Technicians today |
| `board.columns` | Technician · Skills · Shift · Booked · Availability · Actions |
| `board.present` / `absent` / `overrun` | Present / Absent / Overrun |
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
| `contact.reason.TECHNICIAN_ABSENT` | the technician is absent |
| `contact.reason.BAY_OUT_OF_SERVICE` | the bay is out of service |
| `contact.reason.OVERRUN` | the previous job ran late |
| `absence.title` | Report technician absence and reassign |
| `outage.title` | Report bay out of service and reassign |
| `report.info` | The system reruns allocation for each affected appointment. Any it cannot reassign waits for you to contact the customer; nothing is cancelled automatically. |
| `report.period` | Absence period |
| `report.fields` | Technician · Bay · From · To · Reason |
| `reason.SICK_LEAVE` / `TRAINING` / `PERSONAL` / `OTHER` | Sick leave / Training / Personal / Other |
| `reason.EQUIPMENT_FAULT` / `MAINTENANCE` | Equipment fault / Maintenance |
| `report.find` | Find affected appointments |
| `report.summary` | {n} affected appointments · {a} can be reassigned · {c} needs customer contact |
| `report.none` | No appointments are affected. |
| `report.reassignable` | Reassignable |
| `report.needsContact` | Needs contact |
| `report.keepsBay` | {from} → {to} · keeps {bay} |
| `report.keepsTech` | {from} → {to} · keeps {technician} |
| `report.noTech` | No other technician with {skill} skill is available that day. |
| `report.noBay` | No other {bayType} bay is free at that time. |
| `report.call` | Call customer |
| `report.offer` | Offer another time |
| `report.smsNote` | Customers of reassigned appointments get an SMS about the technician change. |
| `report.discard` | Discard |
| `report.apply` | Apply reassignment |
| `report.applying` | Applying… |
| `report.reassigned` | Reassigned |
| `report.modified` | Changed by someone else — review it |
