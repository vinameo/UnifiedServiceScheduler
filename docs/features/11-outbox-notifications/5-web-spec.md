# F11 · Notifications through the outbox · Web spec (WEB)

---

# PART A · For readers

"Outgoing notifications" (`/staff/notifications`, admin only), per the draft `Admin/10-notifications-outbox.png`:

```
Outgoing notifications
Event log from the outbox: customer SMS and email, plus DMS sync.
┌ (i) A failed send never breaks a booking ─────────────────────────────────┐
│ Events are written in the same transaction as the appointment and sent    │
│ afterwards. If an external channel fails, the system retries up to 5 times │
│ with growing back-off. Only then does the event move to Failed for manual │
│ handling.                                                                  │
└────────────────────────────────────────────────────────────────────────────┘
[Pending 1] [Retrying 1] [Failed, needs action 2] [Sent today 214]
Status [All ▾]  Channel [All channels ▾]  Event type [All types ▾]  Appointment ID [____]
Time      Event                 Appointment                      Channel  Attempts  Status          Actions
16:20:41  Appointment cancelled APT-20261102-0031 · Nguyễn Văn Hùng  Email    5/5      Failed · SMTP…  [Resend]
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
| `/staff/notifications` | `staff/notifications/NotificationsPage` | `RequireRole ['ADMIN']` |

| ID | Hook | API | Note |
| --- | --- | --- | --- |
| WEB-11.1 | `useOutboxSummary()` | API-11.2 | `refetchInterval` 15 s |
| WEB-11.2 | `useOutboxMessages(filters)` | API-11.1 | `useInfiniteQuery`; filters in the URL |
| WEB-11.3 | `useRetryOutboxMessage()` | API-11.3 | One key per click (`useIdempotencyKey({id})`); success → invalidate WEB-11.1, WEB-11.2 |

## B2. Behaviour

- Stat cards are clickable and set the Status filter.
- Table: Time `HH:mm:ss` (dealership zone); Event label (B3); Appointment "{reference} · {customerName}" linking to `/staff/appointments/{id}`; Channel; Attempts "{n}/5"; Status badge (Pending grey, Retrying yellow, Failed red, Sent green, Skipped grey) with `lastError` as a note under Failed/Retrying; "Resend" only for Failed (`SubmitButton`, "Resending…").
- Empty: "No events in this status."
- 422 `OUTBOX_NOT_RETRYABLE` (another admin already resent it) → `Alert` with the README §5 copy and a refresh.
- Links: F08 Outbox card "View" → `/staff/notifications?status=PENDING`; staff Appointment details "View notification log" (admins only) → `/staff/notifications?appointmentId={id}`; menu item "Notifications" (admin).

## B3. Copy

| Key | Copy |
| --- | --- |
| `title` | Outgoing notifications |
| `subtitle` | Event log from the outbox: customer SMS and email, plus DMS sync. |
| `infoTitle` | A failed send never breaks a booking |
| `infoBody` | Events are written in the same transaction as the appointment and sent afterwards. If an external channel fails, the system retries up to 5 times with growing back-off. Only then does the event move to Failed for manual handling. |
| `stats.pending` / `retrying` / `failed` / `sentToday` | Pending / Retrying / Failed, needs action / Sent today |
| `filters.*` | Status · Channel · Event type · Appointment ID · All · All channels · All types |
| `status.*` | Pending · Retrying · Failed · Sent · Skipped |
| `columns.*` | Time · Event · Appointment · Channel · Attempts · Status · Actions |
| `event.AppointmentConfirmed` | Appointment confirmed |
| `event.AppointmentCancelled` | Appointment cancelled |
| `event.AppointmentRescheduled` | Appointment rescheduled |
| `event.AppointmentReminder` | Appointment reminder |
| `event.AppointmentReassigned` | Technician or bay changed |
| `channel.*` | SMS · Email · DMS |
| `resend` | Resend |
| `resending` | Resending… |
| `resent` | Message queued to send again |
| `empty` | No events in this status. |
| `viewLog` | View notification log |
