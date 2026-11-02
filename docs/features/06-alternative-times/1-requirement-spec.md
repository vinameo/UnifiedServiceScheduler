# F06 · Suggest other free times · Requirement spec (RQ)

Branch: `feature/06-alternative-times` · Priority: Should · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** Customers should not have to guess one time after another. An advisor on the phone needs to see at once which times are still free.

**What it does.**
- A time grid for one day (15-minute steps within opening hours) that shows which times **really** have a technician and a bay free for the **whole** service. Taken times are struck through (not hidden) so the grid keeps its shape.
- When the chosen time was just taken (`409`), the answer carries up to 3 nearest free times: first on the same day, then up to 7 days ahead.
- When the whole day is full (customer app), it shows "This day is fully booked" and the next free time.

**Who uses it.** Advisors (New booking), customers (Choose date and time); reused by F09 (reschedule) and F10 (hold).

**What it does not do.** Holds (F10). The list is **a hint, not a hold**: someone else may take a time first; then a 409 is correct (BR-06.1).

## A2. Usage steps

1. Pick a service and a date → the grid shows free times (normal) and taken times (struck through).
2. Pick a free time → book (F02).
3. On a 409 → the error card has "Nearest available slots" → tap one → it is selected → confirm again.

## A3. Screens and deviations from the drafts

Drafts: `Admin/01-new-booking.png` (struck-through grid), `Admin/03-booking-error-states.png` (suggestions), `Customer/02-choose-date-time.png` ("{n} free slots", "This day is fully booked", "Next available slot"). F06 matches the drafts; no deviations.

## A4. Key rules in plain words

- A time is free only when F03 finds at least one technician + bay pair for the whole service duration.
- The last time of the day = closing time − duration (60-minute service, closing 17:00 → last start 16:00).
- Past times today are not free.
- A hold (by another customer) makes that time taken.

## A5. Out of scope

Suggestions by favourite technician, other dealerships, notifications when a time frees up.

## A6. Success criteria

- NFR-02: free times for one day p95 < 300 ms with 20 bays, 30 technicians.
- The number of DB queries for one day does not depend on the number of slots (FR-06.4).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-06.1 | `GET /dealerships/{d}/availability?serviceTypeId=&date=` returns **every** slot (free or not) with `available` and `reason`, so the web can strike them through (FR-06.5). |
| D-06.2 | The `reason` of a taken slot: `PAST`, or an F03 reason (`NO_QUALIFIED_TECHNICIAN`, `NO_TECHNICIAN_AVAILABLE`, `NO_BAY_AVAILABLE`). |
| D-06.3 | `date` before today → 422 `START_IN_PAST`; after today + 90 → 422 `START_TOO_FAR_AHEAD` (F02 rules). A closed day → 200, `closed: true`, `slots: []`. |
| D-06.4 | Suggestion order (FR-06.3): free slots of the same day by distance from the wanted time (absolute; ties → earlier first), skipping past times and the wanted time itself; if fewer than 3, add days +1…+7 in time order (earliest first). At most 3 in total. Never a slot after the booking horizon (today + 90 days, `BookingTimeRules::MAX_DAYS_AHEAD`), so every suggestion can be booked. |
| D-06.5 | Suggestions are computed **after** the failed transaction rolled back, outside it, on a fresh snapshot. |
| D-06.6 | `excludeAppointmentId` (optional) ignores that booking when computing free times, so F09's reschedule sees its own slot as free. The caller must be able to see that booking (`findVisibleTo`), otherwise 404 `APPOINTMENT_NOT_FOUND`. |
| D-06.7 | `GET /dealerships/{d}/availability/next?serviceTypeId=&after=` returns the first free slot after `after` within 7 days and within the booking horizon (today + 90) (for the "Next available slot" card), or `null`. |
| D-06.8 | 120 requests/minute per user on both availability endpoints. |
| D-06.9 | The web replaces `TimeGrid` (F02) with `SlotGrid`; the F02 flow does not change. |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-06.a | Do suggestions include times **before** the wanted time on the same day? | Yes (absolute distance). |
| Q-06.b | Does the grid refresh itself? | Yes: on date/service change, when returning to the tab, and right after a 409. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00–F05 are merged: `CandidateFinder`, `OpeningHours`, `AllocationSnapshotRepository::forDay`, `BookingTimeRules`, `CreateBooking` (throws `NoAvailability`), `findVisibleTo`, web `TimeGrid`, `BookingErrorCard` (`actions` slot), `BookingDraft`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-06.1 | `AvailabilityCalculator::forDay(dealershipId, serviceType, localDate, now, ?excludeAppointmentId)`. |
| S-06.2 | `AlternativeTimesFinder::near(…)`, `::next(…)`. |
| S-06.3 | 2 availability endpoints. |
| S-06.4 | `alternatives` attached to every `409 NO_AVAILABILITY` (F02 today; F09 reuses it). |
| S-06.5 | Web: `SlotGrid`, the "fully booked" card, suggestion chips in the error card. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-06.1 | Slot `t` has `available: true` if and only if `t > now`, `[t, t+duration)` fits that day's opening hours, and `CandidateFinder::find` returns at least one pair on the day's snapshot (FR-06.2). | `AvailabilityCalculator`, one snapshot for the whole day. |
| INV-06.2 | First slot = opening time; last slot = closing − duration; 15-minute step (FR-06.1). | `OpeningHours` + `BookingTimeRules::STEP_MINUTES`. |
| INV-06.3 | The DB query count for one day is fixed, whatever the number of slots (FR-06.4). | `forDay` calls `AllocationSnapshotRepository::forDay` **once**. |
| INV-06.4 | No cache between requests (SDD §12). | — |
| INV-06.5 | Suggestions hold only slots that were `available: true` when computed, ≤ 3, per D-06.4, no duplicates, never the wanted time, never after today + 90 days. | `AvailabilityServiceInterface::near()` (`AlternativeTimesFinder`). |
| INV-06.6 | Staff only ask about their own dealership (404 `DEALERSHIP_NOT_FOUND`); customers ask about any dealership but only `active` services (404 `SERVICE_TYPE_NOT_FOUND`). | Route binding `{dealership}` (F01) + the service read. |
| INV-06.7 | `excludeAppointmentId` takes effect only if the caller may see that booking; otherwise 404, never silently ignored. | `findVisibleTo` before computing. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| INV-06.1 | The only technician busy 09–10; past times; slot touching closing; a valid / expired hold | TC-06-01, 06-04, 06-02, TC-10-xx (F10) |
| INV-06.5 | Same day has 3; same day short; 7 days all full; two slots equally far from the wanted time; wanted day near the 90-day horizon | TC-06-03, 06-07, 06-08, 06-09, 06-21 |
| INV-06.6 | Staff of another dealership; customer with an inactive service | TC-06-06 |
| INV-06.7 | Customer excludes someone else's booking | TC-06-11 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Booking/Services/AvailabilityService(.php, Interface.php)` | `forDay` = S-06.1 (`AvailabilityCalculator`), `near`/`next` = S-06.2 (`AlternativeTimesFinder`); README §6.3; bound in `DomainServiceProvider`. |
| `api/app/Domain/Booking/Exceptions/NoAvailability.php` | Adds `alternatives` (array of `{start, end}`), default `[]`. |
| `api/app/Domain/Booking/Services/BookingService.php` (`create`) | Catches `NoAvailability`, computes suggestions (D-06.5), rethrows with `alternatives`. |
| `api/app/Http/Controllers/Api/V1/AvailabilityController.php`, `Requests/AvailabilityRequest.php`, `Resources/AvailabilityResource.php` | API. |
| `api/routes/api.php` | 2 routes + `throttle:120,1`. |
| `web/src/shared/booking/SlotGrid.tsx`, `web/src/shared/api/availability.ts` | WEB. |
| `web/src/staff/booking/NewBookingPage.tsx`, `web/src/customer/booking/ChooseTimePage.tsx`, `web/src/shared/booking/BookingErrorCard.tsx` | Use `SlotGrid`, suggestion chips. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [5-web-spec.md](5-web-spec.md). No new tables.

```php
// illustrative — INV-06.1, 06.3
interface AvailabilityServiceInterface {
    /** `AvailabilityCalculator`. @return DayAvailability with list<SlotState{start, available, reason}> */
    public function forDay(string $dealershipId, ServiceType $type, string $localDate, CarbonImmutable $now, ?string $excludeAppointmentId = null): DayAvailability;
    // `AlternativeTimesFinder`:
    /** @return list<TimeRange> ≤ $limit — INV-06.5 */
    public function near(string $dealershipId, ServiceType $type, CarbonImmutable $wanted, CarbonImmutable $now, ?string $excludeAppointmentId = null, int $limit = 3): array;
    public function next(string $dealershipId, ServiceType $type, CarbonImmutable $after, CarbonImmutable $now): ?TimeRange;
}
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `AvailabilityCalculator` | F09 (reschedule grid with `excludeAppointmentId`), F10 |
| `AlternativeTimesFinder::near` | F09 (409 on reschedule) |
| Web `SlotGrid`, `AlternativeChips` | F09 (staff, customer), F10 |

FCs: FC-06.1 (suggest a familiar technician) → `AllocationRequest.preferTechnicianId` exists; FC-06.2 (short cache under high load) → forbidden today (INV-06.4), consider in SDD phase 2.

## B7. Algorithms

**`forDay`**: get the day's opening window (closed → `closed`); load the snapshot once; for each `t` from `opens` to `closes − duration` in 15-minute steps: `t <= now` → `PAST`; otherwise `find(AllocationRequest(range = [t, t+duration), excludeAppointmentId))` → `available` or `reason`.

**`near`**: `forDay(wanted's day)` → keep `available`, drop `wanted` → sort by `(|t − wanted|, t)` → take up to 3. If < 3: for days +1…+7: `forDay` → take `available` slots in time order until there are 3. Stop early when full.

**`next`**: like the "later days" part of `near`, starting from `after` (same day, slots > `after`), limit 1.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| Missing/bad `serviceTypeId`, `date`, `after`; malformed `excludeAppointmentId` | 400 | `VALIDATION_ERROR` |
| Over 120/minute | 429 | `TOO_MANY_ATTEMPTS` |
| Another dealership (staff) | 404 | `DEALERSHIP_NOT_FOUND` |
| Service missing/inactive/another dealership | 404 | `SERVICE_TYPE_NOT_FOUND` |
| `excludeAppointmentId` not visible | 404 | `APPOINTMENT_NOT_FOUND` |
| `date` in the past | 422 | `START_IN_PAST` |
| `date` more than 90 days ahead | 422 | `START_TOO_FAR_AHEAD` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-06.1 | The only qualified technician is busy 09:00–10:00 | Get free times for a 60-minute service | 08:15 to 09:45 are not free; 08:00 and 10:00 are free | TC-06-01 |
| AC-06.2 | The dealership closes at 17:00 | Get free times for a 60-minute service | The last slot is 16:00 | TC-06-02 |
| AC-06.3 | 09:00 is full, 10:00 and 11:00 are free | Book 09:00 | 409 with suggestions starting at 10:00 | TC-06-03 (through `POST /appointments`) |
| AC-06.4 (new) | Today, now 10:05 | Get today's free times | Slots ≤ 10:00 have `available: false`, `reason: PAST` | TC-06-04 |
| AC-06.5 (new) | Closed on Sunday | Get Sunday's free times | 200, `closed: true`, `slots: []` | TC-06-05 |
| AC-06.6 (new) | D2 staff; a customer | Staff ask about D1; the customer asks D1 with an inactive service | 404 `DEALERSHIP_NOT_FOUND`; 404 `SERVICE_TYPE_NOT_FOUND` | TC-06-06 |
| AC-06.7 (new) | Today only 15:00 is free; tomorrow is free from 08:00 | Booking 09:00 gets 409 | Suggestions: 15:00 today, 08:00 and 08:15 tomorrow | TC-06-07 |
| AC-06.8 (new) | The next 8 days are full | Booking gets 409 | `alternatives: []` | TC-06-08 |
| AC-06.9 (new) | 10:00 and 08:00 are both one hour from 09:00 | Suggestions for 09:00 | 08:00 before 10:00 | TC-06-09 |
| AC-06.10 (new) | 36 slots vs 4 slots (long service) | Count queries | Equal | TC-06-10 |
| AC-06.11 (new) | Customer A's booking X holds An 10–11 (An is the only one) | A asks for 10:00 with `excludeAppointmentId=X`; B asks with X | A: 10:00 free; B: 404 | TC-06-11 |
| AC-06.12 (new) | The day is full, Friday 08:00 is free | `GET …/availability/next` | `nextAvailable` = Friday 08:00 | TC-06-12 |
| AC-06.13 (new) | The grid has taken times | Screen | Taken times are struck through, not clickable, still shown | TC-06-14 |
| AC-06.14 (new) | Customer app, a full day | Screen | "This day is fully booked" + "Next available slot for Oil change: Fri 6 Nov · 08:00"; tapping it selects that day and time | TC-06-15 |
| AC-06.15 (new) | New booking gets a 409 with suggestions | Tap the "10:00 today" chip | 10:00 is selected; the error card disappears; a new key for the next confirm | TC-06-16 |
| AC-06.16 (new) | 20 bays, 30 technicians, 200 bookings | Call availability | p95 < 300 ms | TC-06-13 |

## B10. Implementation order

1. Ticket 01 (prefactor): `TimeGrid` takes per-chip state from props; no behaviour change.
2. Tracer bullet: `AvailabilityCalculator` + endpoint + `SlotGrid` on New booking, then `/code-review`.
3. `AlternativeTimesFinder` + attach to 409 + suggestion chips.
4. Customer app: "free slots" counts, "fully booked", `next`.
5. `excludeAppointmentId`, performance, acceptance.

## B11. Definition of Done

Every TC green; README §8 green; TC-06-13 meets its threshold.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-06.1 | Suggestions over 8 days = 8 snapshots → slow 409 | Stop early at 3; each snapshot ≤ 6 queries; measured by TC-06-13 |
| R-06.2 | A stale grid (someone just booked) → the user picks a taken time | BR-06.1: accepted; 409 + refresh the grid at once |
