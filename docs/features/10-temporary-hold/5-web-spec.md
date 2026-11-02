# F10 · Temporary hold for self-booking · Web spec (WEB)

---

# PART A · For readers

The customer flow returns to the drafts:

- **Step 2** (`/book/time`, draft `Customer/02-choose-date-time.png`): the button reads "Hold and continue" with "We hold this slot for you for 5 minutes." under it.
- **Step 3 · Temporary hold** (`/book/hold/:id`, draft `Customer/03-temporary-hold.png`):

```
Book a service
Step 3 of 3 · Contact details
┌ Slot held for you ─────────────── 04:32 ┐
│ Finish within this time to keep the slot.│
└──────────────────────────────────────────┘
Your selection
Service  Oil change · 60 min
Time     Mon 2 Nov, 10:00–11:00
Dealership Saigon Auto · District 7
Full name     Nguyễn Văn Hùng
Phone number  0903 *** 218
Vehicle (•) Toyota Vios · 51H-123.45  ( ) Add a new vehicle
[x] Text me a reminder 1 day before
[ Confirm appointment ]
```

Expired state:

```
┌ (!) Your hold has expired ───────────────────────────────┐
│ Someone may have booked the 10:00 slot. Pick a time again │
│ to continue; the details you entered are kept.            │
│ [ Pick a time again ]                                     │
└───────────────────────────────────────────────────────────┘
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
| `/book/time` | `ChooseTimePage` (changed) | `CUSTOMER` |
| `/book/hold/:id` | `customer/booking/HoldPage` (replaces F02 `/book/details`) | `CUSTOMER`; the hold in `appointmentQuery` must be `HELD`, otherwise → `/book/time` |
| `/book/details` | Redirect to `/book/time` | — |

## B2. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-10.1 | `useServerClock()` | `web/src/shared/time/useServerClock.ts` | Keeps `offsetMs = Date.parse(response Date header) − Date.now()` from the last API response; `now()` returns the corrected time. |
| WEB-10.2 | `useCountdown(expiresAt)` | `web/src/shared/time/useCountdown.ts` | Ticks every second with `useServerClock().now()`; returns `mm:ss` and `expired`. |
| WEB-10.3 | `useCreateHold()` | `web/src/shared/api/appointments.ts` | `idempotentPost` with `mode: HOLD`; stores the appointment + `ETag` in `appointmentQuery`; navigates to `/book/hold/:id`. |
| WEB-10.4 | `useConfirmHold(id)` | same | Sends `If-Match` from the hold's `ETag`; 200 → `/book/confirmed/:id`; 410, or 412 whose `appointment.status` is `EXPIRED` → expired state; 409 `VEHICLE_ALREADY_BOOKED` → `Alert`, stay. |

## B3. Behaviour

- Step 2: "Hold and continue" creates the hold. 409 → `BookingErrorCard` with `AlternativeChips` (F06), stay on step 2. Going back from step 3 and holding another time just creates a new hold (the server ends the old one).
- Step 3: countdown from `expiresAt` using the server clock; under 1 minute the timer turns red. At 00:00 the form is disabled and the expired state shows (no API call needed).
- The vehicle picker and "Add a new vehicle" work as in F02 (new vehicle saved first with its own key, then confirm).
- "Pick a time again" → `/book/time` with service, date and vehicle kept in `BookingDraft`; the old time is unselected.
- `REQUEST_IN_PROGRESS` / no response → `IdempotentRetryPanel` (customer variant).

## B4. Copy

| Key | Copy |
| --- | --- |
| `step2.hold` | Hold and continue |
| `step2.holdNote` | We hold this slot for you for {minutes} minutes. |
| `hold.title` | Slot held for you |
| `hold.body` | Finish within this time to keep the slot. |
| `hold.expiredTitle` | Your hold has expired |
| `hold.expiredBody` | Someone may have booked the {time} slot. Pick a time again to continue; the details you entered are kept. |
| `hold.pickAgain` | Pick a time again |
| `hold.selection` | Your selection |
| `hold.confirm` | Confirm appointment |
| `hold.confirming` | Confirming… |
| (other step 3 copy) | As F02 WEB §B5 (`cust.*`) |
