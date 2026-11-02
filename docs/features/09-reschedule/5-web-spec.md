# F09 · Reschedule · Web spec (WEB)

---

# PART A · For readers

**Staff · Reschedule appointment** (`/staff/appointments/:id/reschedule`, draft `Admin/06-reschedule.png`):

```
Appointments / APT-20261102-0042 / Reschedule
Reschedule appointment
Nguyễn Văn Hùng · Toyota Vios · Oil change (60 min)
(i) The current booking stays in place until the new time is confirmed. If the new time
    has just filled up, nothing changes and the customer keeps the original booking.
┌ Current booking ─────────────┐ ┌ Choose a new time ───────────────────────────┐
│ Time  Mon 2 Nov, 09:00–10:00 │ │ Date [Monday, 2 Nov 2026]                     │
│ Technician  Trần Minh An     │ │ [x] Prefer keeping technician Trần Minh An    │
│ Bay  Bay 2                   │ │ Afternoon [13:00] [1̶3̶:̶1̶5̶] [14:00] …           │
└──────────────────────────────┘ │ Change to Mon 2 Nov, 14:00–15:00              │
                                 │ [Back]  [Confirm reschedule]                  │
                                 └───────────────────────────────────────────────┘
```

**Customer · Change appointment time** (`/my-bookings/:id/change`, draft `Customer/07-change-time.png`):

```
Change appointment time
Oil change · 60 min
Current time  Mon 2 Nov · 10:00   →   New time  Tue 3 Nov · 09:00
Choose a day   [Mon 2] [Tue 3] [Wed 4] …
Choose a time  12 free slots
[08:00] [0̶8̶:̶1̶5̶] [09:00] …
(i) Your current booking is kept until the new time is secured.
    If someone takes the new time first, nothing changes.
[ Confirm new time ]
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
| `/staff/appointments/:id/reschedule` | `staff/appointments/ReschedulePage` | `RequireRole ['SERVICE_ADVISOR']`; booking not `CONFIRMED` → back to details with an `Alert` |
| `/my-bookings/:id/change` | `customer/bookings/ChangeTimePage` | `RequireRole ['CUSTOMER']`; not `CONFIRMED` or `!canCustomerChange` → back to details |

## B2. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-09.1 | `useRescheduleAppointment(id)` | `web/src/shared/api/appointments.ts` | Mutation: `If-Match` from `appointmentQuery`, key from `useIdempotencyKey({id, etag, newStart, keepTechnician})`, settled after every answer (README D-G.15). 200 → `setAppointment`, invalidate lists + availability, navigate back to details with notification "Appointment moved to {dayShort}, {range}". 409 → `BookingErrorCard` with `AlternativeChips`. 412 → `AppointmentModifiedAlert`. |
| WEB-09.2 | `SlotGrid` with `excludeAppointmentId` | F06 | The booking's own current slot shows as free and marked "Current" (outlined chip, not selectable as new time when it equals the current start). |

## B3. Behaviour

**Staff**
- "Reschedule" button on Appointment details (advisor, `CONFIRMED`) → this page.
- Current booking card from the cached appointment.
- Date input (as F02) defaulting to the booking's date; `SlotGrid` with `excludeAppointmentId`.
- "Prefer keeping technician {name}" checkbox, default checked → `keepTechnician`.
- Summary line "Change to {dayShort}, {range}" once a time is picked; "Confirm reschedule" (`SubmitButton`, "Moving…") disabled until then.
- "Back" returns to details without changes.
- F02's `VEHICLE_ALREADY_BOOKED` card gets "Reschedule existing booking" → this page for `existingAppointment.id` (advisor, existing booking `CONFIRMED`).

**Customer**
- "Change time" button on booking details when `status = CONFIRMED` and `canCustomerChange`; otherwise the "less than 2 hours… call {phone} to change it" sentence (shared with F07).
- Header "Current time → New time" updates live.
- Day chips (14 days) + `SlotGrid` (customer variant with counts).
- "Confirm new time" → mutation; success → details with notification.
- 422 `TOO_LATE_TO_RESCHEDULE` → `Alert` with the dealership phone.

## B4. Copy

| Key | Copy |
| --- | --- |
| `staff.button` | Reschedule |
| `staff.title` | Reschedule appointment |
| `staff.subtitle` | {customer} · {vehicle} · {service} ({duration} min) |
| `staff.info` | The current booking stays in place until the new time is confirmed. If the new time has just filled up, nothing changes and the customer keeps the original booking. |
| `staff.current` | Current booking |
| `staff.choose` | Choose a new time |
| `staff.keepTech` | Prefer keeping technician {name} |
| `staff.changeTo` | Change to {when} |
| `staff.back` | Back |
| `staff.confirm` | Confirm reschedule |
| `submitting` | Moving… |
| `done` | Appointment moved to {when} |
| `currentChip` | Current |
| `rescheduleExisting` | Reschedule existing booking |
| `cust.button` | Change time |
| `cust.title` | Change appointment time |
| `cust.current` | Current time |
| `cust.new` | New time |
| `cust.chooseDay` | Choose a day |
| `cust.chooseTime` | Choose a time |
| `cust.freeSlots` | {n} free slots |
| `cust.info` | Your current booking is kept until the new time is secured. If someone takes the new time first, nothing changes. |
| `cust.confirm` | Confirm new time |
| `cust.tooLate` | It's less than 2 hours before your appointment. Please call {phone} to change it. |
