# F06 · Suggest other free times · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **The definition of "free"** is F03's, so the tests are mostly HTTP on real PostgreSQL with prepared bookings (busy person, busy bay, past times).
- **Suggestion order** is tested with multi-day data.
- **Performance and query count** get their own tests (FR-06.4, NFR-02).
- **Screens**: component tests for strike-through, "fully booked", suggestion chips.

## A2. Manual scenarios

| # | Steps | Expected |
| --- | --- | --- |
| M1 | Advisor books an EV battery check at 09:00 tomorrow (Em is the only one) | Success |
| M2 | New booking again, EV battery check tomorrow | 08:00–10:15 struck through (every 90-minute start in that range overlaps 09:00–10:30); 10:30 free |
| M3 | Two tabs: tab 1 picks 10:30, tab 2 books 10:30 first; tab 1 Confirms | Card "The 10:30 slot was just taken" + suggestion chips; tapping a chip selects that time |
| M4 | Customer picks a day that M1–M3 filled (or use full-day data) | "This day is fully booked" + "Next available slot for …" |

## A3. Test data

`World::saigonD7()`; bookings via `AppointmentFactory`; clock frozen at 2026-11-02 08:00 +07 (unless noted).

## A4. Not tested

Cache (forbidden). Booking itself (F02).

## A5. Exit criteria

Every TC green; M1–M4 pass.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-06.1, FR-06.2, INV-06.1 | TC-06-01 |
| AC-06.2, FR-06.1, INV-06.2 | TC-06-02 |
| AC-06.3, FR-06.3 | TC-06-03 |
| AC-06.4, D-06.2 | TC-06-04 |
| AC-06.5, D-06.3 | TC-06-05 |
| AC-06.6, INV-06.6 | TC-06-06 |
| AC-06.7, D-06.4, INV-06.5 | TC-06-07 |
| AC-06.8 | TC-06-08 |
| AC-06.9 | TC-06-09 |
| AC-06.10, FR-06.4, INV-06.3 | TC-06-10 |
| AC-06.11, D-06.6, INV-06.7 | TC-06-11 |
| AC-06.12, D-06.7 | TC-06-12 |
| AC-06.13, FR-06.5 | TC-06-14 |
| AC-06.14 | TC-06-15 |
| AC-06.15 | TC-06-16 |
| INV-06.5 horizon | TC-06-21 |
| AC-06.16, NFR-02 | TC-06-13 |
| D-06.5 | TC-06-03 (a 409 with suggestions means they were computed outside the failed transaction) |
| D-06.8, API §B4 | TC-06-17 |
| BR-06.1 | TC-06-18 |
| INV-06.4 | TC-06-18 |
| Suggestions saved with the idempotency key | TC-06-19 |

## B2. Test cases

Backend in `api/tests/Feature/Booking/Availability/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-06-01 | AC-06.1 | Integration | `onlyTechnicians(['An'])`, An busy 09:00–10:00 tomorrow | `GET availability` OIL tomorrow | 08:00 `true`; 08:15…09:45 `false` (`NO_TECHNICIAN_AVAILABLE`); 10:00 `true` | Only start times checked | `AvailabilityTest::test_AC_06_1_busy_technician_blocks_overlapping_starts` |
| TC-06-02 | AC-06.2 | Integration | Closes 17:00 | OIL tomorrow | Last slot `start 16:00`; no 16:15 | Listed up to 16:45 | `…::test_AC_06_2_last_start_is_close_minus_duration` |
| TC-06-03 | AC-06.3, D-06.5 | Integration | 09:00 full (An is the only one); 10:00, 11:00 free | `POST /appointments` 09:00 | 409 `NO_AVAILABILITY`; `alternatives[0].start` = 10:00 | Suggestions not attached | `AlternativesOnConflictTest::test_AC_06_3_conflict_returns_alternatives` |
| TC-06-04 | AC-06.4 | Integration | Clock 10:05 today | Today | Slots ≤ 10:00 `PAST`; 10:15 per F03 | Compared with DB time | `AvailabilityTest::test_AC_06_4_past_slots_are_not_available` |
| TC-06-05 | AC-06.5 | Integration | — | Sunday | 200, `closed: true`, `slots: []` | 422 or a list of times | `…::test_AC_06_5_closed_day` |
| TC-06-06 | AC-06.6 | Integration | D2 advisor; inactive service | Advisor asks D1; customer asks the inactive service | 404 `DEALERSHIP_NOT_FOUND`; 404 `SERVICE_TYPE_NOT_FOUND` | Wrong scope | `…::test_AC_06_6_scope` |
| TC-06-07 | AC-06.7 | Integration | Today only 15:00 is free (wanted 09:00); tomorrow free | `POST` 09:00 | `alternatives` = [15:00 today, 08:00 tomorrow, 08:15 tomorrow] | D-06.4 order wrong | `AlternativesOnConflictTest::test_AC_06_7_same_day_first_then_next_days` |
| TC-06-08 | AC-06.8 | Integration | 8 days full (one person, busy all day) | `POST` | `alternatives: []` | Searches beyond 7 days | `…::test_AC_06_8_no_alternatives_within_a_week` |
| TC-06-09 | AC-06.9 | Unit | Free slots 08:00, 10:00; wanted 09:00 | `near` | [08:00, 10:00] | Ties → later first | `api/tests/Unit/Booking/AlternativeOrderTest::test_AC_06_9_ties_prefer_earlier` |
| TC-06-10 | AC-06.10, INV-06.3 | Integration | OIL (36 slots) and a fake 480-minute service (2 slots) | Count queries per call | Equal | One query per slot | `AvailabilityTest::test_AC_06_10_query_count_independent_of_slots` |
| TC-06-11 | AC-06.11, INV-06.7 | Integration | A's booking X holds An 10–11; An is the only one | A asks with `excludeAppointmentId=X`; B asks with X | A: 10:00 `true`; B: 404 `APPOINTMENT_NOT_FOUND` | Silently ignored | `…::test_AC_06_11_exclude_own_appointment_only` |
| TC-06-12 | AC-06.12 | Integration | Monday–Thursday full; Friday 08:00 free | `availability/next?after=Monday 09:00` | `nextAvailable.start` = Friday 08:00 | Wrong day | `NextAvailableTest::test_AC_06_12_next_free_slot` |
| TC-06-13 | AC-06.16, NFR-02 | Integration (`@group performance`) | 20 bays, 30 technicians, 200 bookings that day | 30 × `GET availability` | p95 < 300 ms | Slow | `AvailabilityPerformanceTest` |
| TC-06-14 | AC-06.13, WEB-06.3 | Web component | Fake API: 3 slots, the middle one `false` | Render New booking, click the middle slot | The middle slot is struck through, `aria-disabled`, not selectable; "2 free start times" shown | Slot hidden | `web/src/shared/booking/SlotGrid.test.tsx` |
| TC-06-15 | AC-06.14, WEB-06.5 | Web component | Fake API: `freeCount 0`; `next` = Fri 6 Nov 08:00 | Render step 2, tap the chip | "This day is fully booked"; "Next available slot for Oil change:"; "Fri 6 Nov · 08:00"; the chosen day becomes 6 Nov, time 08:00 | Day not switched | `web/src/customer/booking/ChooseTimePage.test.tsx` |
| TC-06-16 | AC-06.15, WEB-06.4 | Web component | Fake API: 409 with 3 suggestions, then 201 | Confirm; tap "10:00 today"; Confirm | The card disappears, 10:00 selected; the second request has `desiredStart` 10:00 and a different `Idempotency-Key` from the first | Old key reused for a new body | `web/src/staff/booking/NewBookingPage.test.tsx` |
| TC-06-17 | D-06.8, API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `AvailabilityAdversarialInputTest` |
| TC-06-18 | BR-06.1, INV-06.4 | Integration | Get availability (10:00 free); another user books 10:00 | Get availability again | 10:00 `false` at once (no cache) | Cached | `AvailabilityTest::test_list_is_a_hint_and_never_cached` |
| TC-06-19 | F04 + F06 | Integration | `POST` 09:00 with K → 409 with suggestions | Change data so suggestions would differ; resend K | The same 409 and the old `alternatives` | Suggestions recomputed on replay | `AlternativesOnConflictTest::test_replayed_409_keeps_saved_alternatives` |
| TC-06-20 | All | Manual | Stack running | M1–M4 | As A2 | — | Record in A2 |
| TC-06-21 | INV-06.5, D-06.4, D-06.7 | Integration | Clock 2026-11-02; day 2027-01-29 (88 days ahead) full; 2027-02-01 free | `POST` for 2027-01-29 09:00; `availability/next?after=2027-01-29T09:00+07:00` | `alternatives` hold nothing after 2027-01-31; `next` is `null` or ≤ 2027-01-31 | Suggests a slot the booking rejects with `START_TOO_FAR_AHEAD` | `AlternativesOnConflictTest::test_INV_06_5_suggestions_stay_within_the_horizon` |

## B3. Fixtures

`World` + `AppointmentFactory::busyAllDay($technician, $date)` (added in F06) to build full days.

## B4. Regression

F02 (`CreateBookingTest`, `NewBookingPage.test.tsx`, `BookingFlow.test.tsx` — `TimeGrid` is replaced), F03, F04.
