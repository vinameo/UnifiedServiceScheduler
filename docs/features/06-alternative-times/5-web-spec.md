# F06 · Suggest other free times · Web spec (WEB)

---

# PART A · For readers

F06 upgrades the time grid on two existing screens and the 409 error card:

- **New booking (staff)**: the grid strikes through taken times; a line "{n} free start times"; legend "Struck-through slots have no free bay or technician."
- **Choose date and time (customer)**: "Morning {n} free slots", "Afternoon {n} free slots"; a full day → card "This day is fully booked" + "Next available slot for {service}: {Fri 6 Nov · 08:00}".
- **409 error card**: "Nearest available slots" with chips "10:00 today", "11:15 today", "08:00 Tue 3 Nov"; tapping a chip selects that time.

```
Morning  6 free slots
[08:00] [0̶8̶:̶1̶5̶] [0̶8̶:̶3̶0̶] … [10:00] [10:15]
┌ This day is fully booked ─────────────┐
│ Next available slot for Oil change:    │
│ [Fri 6 Nov · 08:00]                    │
└────────────────────────────────────────┘
```

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-06.1 | `useAvailability({ dealershipId, serviceTypeId, date, excludeAppointmentId? })` | `web/src/shared/api/availability.ts` | `queryKey ['availability', …]`, `staleTime` 15 s; `invalidateAvailability()` is called after every 409 and after every successful book/reschedule/cancel. |
| WEB-06.2 | `useNextAvailable({ …, after })` | same | Only called when `freeCount === 0`. |
| WEB-06.3 | `SlotGrid` | `web/src/shared/booking/SlotGrid.tsx` | Replaces `TimeGrid`. A taken chip: `disabled`, `td="line-through"`, `aria-disabled`, `title` from `reason` (B3). Morning/Afternoon groups with counts (customer variant). A `closed` day → "The dealership is closed on this day." |
| WEB-06.4 | `AlternativeChips` | `web/src/shared/booking/AlternativeChips.tsx` | Takes `alternatives`, `timeZone`, `onPick(start)`; labels: same day as the wanted time → "{HH:mm} today" (when that day is today) or "{HH:mm}"; another day → "{HH:mm} {Ddd D Mmm}". |
| WEB-06.5 | `FullyBookedCard` | `web/src/customer/booking/FullyBookedCard.tsx` | Customer; uses WEB-06.2. |

## B2. Behaviour

- Changing the service or date drops the selected time if it is no longer `available`.
- `onPick(start)` from a suggestion chip: switch the date if needed, select the time, hide the error card, new idempotency key (the input changed → `useIdempotencyKey` makes a new one).
- After a 409: call `invalidateAvailability()` so the grid shows the time that was just taken.
- Customer step 3 gets a 409: go back to step 2 with an `Alert` holding `AlternativeChips`.
- Under the customer grid: "Times can be taken by someone else until you confirm." (RB-06.1).
- Loading the grid: `Skeleton` chips; error: `Alert` + "Retry".

## B3. Copy

| Key | Copy |
| --- | --- |
| `staff.freeCount` | {n} free start times |
| `staff.legend` | Struck-through slots have no free bay or technician. |
| `cust.freeSlots` | {n} free slots |
| `cust.fullyBooked` | This day is fully booked |
| `cust.nextFor` | Next available slot for {service}: |
| `cust.noneSoon` | No free time in the next 7 days. Call {phone}. |
| `cust.hint` | Times can be taken by someone else until you confirm. |
| `nearest` | Nearest available slots |
| `today` | {time} today |
| `noAlternatives` | No free time nearby. Pick another day. |
| `reason.PAST` (tooltip) | This time has passed |
| `reason.NO_TECHNICIAN_AVAILABLE` | No qualified technician free |
| `reason.NO_BAY_AVAILABLE` | No bay free |
| `reason.NO_QUALIFIED_TECHNICIAN` | No technician for this service |
