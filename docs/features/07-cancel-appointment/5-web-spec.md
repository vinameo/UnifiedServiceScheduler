# F07 · Cancel an appointment · Web spec (WEB)

---

# PART A · For readers

**Staff** (draft `Admin/05-appointment-details.png`): a "Cancel appointment" button (red outline) on the details page → dialog:

```
Cancel this appointment?
Bay 2 and technician Trần Minh An will be released for 09:00–10:00 so other
customers can book it. This cannot be undone.
Reason  [Customer is busy ▾]
Note (optional) [Customer will call back to book next week.]
                         [Keep appointment] [Cancel appointment]
```

**Customer** (draft `Customer/06-details-and-cancel.png`): a "Cancel appointment" button → bottom sheet:

```
Cancel your 10:00 appointment on Mon 2 Nov?
The slot will be released for others to book. You cannot restore it after cancelling.
Reason (optional)
( ) Something came up   ( ) I had it done elsewhere   ( ) Other reason
[Keep appointment]  [Cancel appointment]
```

After cancelling: banner "You cancelled this appointment · The time slot has been released. We'll send you a cancellation SMS."

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-07.1 | `useCancelAppointment(id)` | `web/src/shared/api/appointments.ts` | Mutation: reads the `etag` from `appointmentQuery`, sends `If-Match` + key (`useIdempotencyKey({id, etag, reason, note})`, settled after every answer, README D-G.15). 200 → `setAppointment(…)` + invalidate the lists and availability. 412 → hands `latest` to `AppointmentModifiedAlert`. |
| WEB-07.2 | `CancelAppointmentModal` | `web/src/staff/appointments/CancelAppointmentModal.tsx` | Mantine `Modal`. The body uses `bay.name`, `technician.name`, `range` of the booking on screen. Confirm is a red `SubmitButton` ("Cancelling…"). |
| WEB-07.3 | `CancelBookingSheet` | `web/src/customer/bookings/CancelBookingSheet.tsx` | Mantine `Drawer position="bottom"`; a `Radio.Group` of reasons (none = null). "Other reason" opens a `Textarea` → `note`. |
| WEB-07.4 | `canCustomerChange(appointment, now)` | `web/src/shared/appointments/changeWindow.ts` | Mirrors `CustomerChangePolicy`; F09 reuses it. `now` comes from a clock corrected by the server `Date` header (F10 builds it fully; F07 uses `Date.now()`). |

## B2. Behaviour

**Staff**
- The "Cancel appointment" button shows only for advisors, when `status ∈ {CONFIRMED, HELD}`.
- No reason selected by default; the confirm button stays `disabled` until one is picked.
- 200 → close the dialog, notification "Appointment cancelled", the page shows the Cancelled badge + closed banner (F05), action buttons hidden.
- 412 → `AppointmentModifiedAlert` inside the dialog; "Review and retry" updates the page with the new version; if the new status cannot be cancelled, close the dialog.
- 422 `INVALID_STATUS_TRANSITION` → `Alert` with the README §5 copy and `{status}`, page updated.

**Customer**
- The "Cancel appointment" button shows when `canCustomerChange` is true and `status = CONFIRMED`. Less than 2 hours → instead of the button, "It's less than 2 hours before your appointment. Please call {phone} to cancel." (`tel:` link).
- 422 `TOO_LATE_TO_CANCEL` (clock skew) → the same sentence in an `Alert`.
- 200 → close the sheet; banner on the card: title "You cancelled this appointment", body "The time slot has been released. We'll send you a cancellation SMS."; hide Cancel/Change time.
- Cancelled by an advisor: banner "This appointment was cancelled by the dealership. Call {phone} if you have questions."

## B3. Copy

| Key | Copy |
| --- | --- |
| `cancel.button` | Cancel appointment |
| `cancel.staff.title` | Cancel this appointment? |
| `cancel.staff.body` | {bay} and technician {technician} will be released for {range} so other customers can book it. This cannot be undone. |
| `cancel.reason` | Reason |
| `cancel.note` | Note (optional) |
| `cancel.keep` | Keep appointment |
| `cancel.submitting` | Cancelling… |
| `cancel.done` | Appointment cancelled |
| `reason.CUSTOMER_REQUEST` | Customer asked to cancel |
| `reason.CUSTOMER_BUSY` | Customer is busy |
| `reason.DONE_ELSEWHERE` (staff) | Service done elsewhere |
| `reason.DEALERSHIP_ISSUE` | Dealership issue |
| `reason.OTHER` (staff) | Other |
| `cancel.cust.title` | Cancel your {time} appointment on {day}? |
| `cancel.cust.body` | The slot will be released for others to book. You cannot restore it after cancelling. |
| `cancel.cust.reason` | Reason (optional) |
| `reason.SOMETHING_CAME_UP` | Something came up |
| `reason.DONE_ELSEWHERE` (customer) | I had it done elsewhere |
| `reason.OTHER` (customer) | Other reason |
| `cancel.cust.doneTitle` | You cancelled this appointment |
| `cancel.cust.doneBody` | The time slot has been released. We'll send you a cancellation SMS. |
| `cancel.cust.byDealership` | This appointment was cancelled by the dealership. Call {phone} if you have questions. |
| `cancel.cust.tooLate` | It's less than 2 hours before your appointment. Please call {phone} to cancel. |
