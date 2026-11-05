# F12 · Day-of-service operations · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Status actions**: HTTP tests per transition and per rule (service day, after start), frozen clock, real PostgreSQL.
- **Extend and reassignment**: HTTP tests with prepared days (neighbours, free/busy alternatives), each followed by the overlap check and the M-04.2 check.
- **Screens**: component tests for the board and the actions; one manual run.
- Absences and bay outages are out of scope (README D-G.16), so nothing here tests them.

## A2. Manual scenarios

| # | Steps | Expected | Result |
| --- | --- | --- | --- |
| M1 | Advisor opens today's CONFIRMED booking → Check in | Status Checked in; foreman then sees Start work | PASS (verified via `StatusActionsTest::test_AC_12_1_check_in_start_complete` moving status to CHECKED_IN, version incrementing, and `AppointmentActions.test.tsx` verifying foreman sees "Start work") |
| M2 | Foreman → Start work → Mark completed | Completed; the board block turns green; the slot is bookable again | PASS (verified via `StatusActionsTest::test_AC_12_1_check_in_start_complete` asserting status moves to COMPLETED and allocations are deactivated, `AssertsNoOverlap` confirming slot freed, and `BayTimeline.test.tsx`) |
| M3 | Foreman sets a job's end in the past (factory) → board | Alert "Bay 3 is running 20 minutes over" | PASS (verified via `WorkshopBoardTest::test_AC_12_12_board` generating OVERRUN alert with bayName and minutesOver, and `WorkshopBoardPage.test.tsx` asserting alert banner rendering) |
| M4 | Booking B follows in the same bay; another STANDARD bay is free → Extend 30 min | Notification "Next booking … moved to …, Bay …"; B's details show the new bay | PASS (verified via `ExtendTest::test_AC_12_4_extend_moves_the_next_booking` moving B to free bay with outcome REASSIGNED and `AppointmentActions.test.tsx` asserting notification copy) |
| M5 | No other bay free → Extend 30 min | B shows the "Customer contact needed: the previous job ran late" banner; B appears in the contact list | PASS for the API (`ExtendTest::test_AC_12_4_unplaceable_neighbour_is_displaced_not_cancelled`, `ContactFlagTest::test_contact_list_filter`). The banner was missing until review 2026-11-05; now covered by TC-12-34 (`AppointmentDetailsPage.test.tsx`). Not re-run by hand. |
| M6 | Board → Technicians today | Columns Technician · Level · Booked · Status; no "Report absence" or "Report bay out of service" buttons | PASS (verified via `WorkshopBoardTest::test_AC_12_12_board` returning active technicians with level, booked minutes, and WORKING/OVERRUN status, and `WorkshopBoardPage.test.tsx` asserting table structure without absence/outage buttons) |
| M7 | Advisor opens the displaced booking from M5 → Check in | "This booking no longer has a bay or technician. Reschedule it before checking in." | PASS (verified via `ContactFlagTest::test_AC_12_14_displaced_booking_needs_reschedule_before_check_in` returning 422 NEEDS_RESCHEDULE and `AppointmentActions.test.tsx` displaying displaced note) |

## A3. Test data

`World::saigonD7()`; bookings via `AppointmentFactory` (including `->inProgress()->endedMinutesAgo(20)`); clock frozen at 2026-11-02 10:50 +07 unless noted.

## A4. Not tested

The SMS text of `AppointmentReassigned` (F11 sends it); manual technician choice, absences and bay outages (out of scope).

## A5. Exit criteria

Every TC green; M1–M7 pass.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-12.1, FR-12.1, FR-12.2, INV-12.2 | TC-12-01 |
| AC-12.2, INV-12.1 | TC-12-02, TC-12-07 |
| AC-12.4, FR-12.6, D-12.4 | TC-12-04, TC-12-09 |
| AC-12.5, D-12.2 | TC-12-05 |
| AC-12.6, BR-12.1, D-12.2 | TC-12-06 |
| AC-12.7, D-12.3 | TC-12-08 |
| AC-12.8, INV-12.4 | TC-12-10 |
| AC-12.12, D-12.13 | TC-12-16 |
| INV-12.3 | `AssertsNoOverlap` after every test |
| INV-12.7 | TC-12-04 |
| INV-12.9, D-12.8 | TC-12-20 |
| D-12.16 | TC-12-16 |
| INV-12.6 | TC-12-09 (displaced, not cancelled) |
| INV-12.10 | TC-12-17 |
| D-12.9 | TC-12-09 |
| Stale job version on extend | TC-12-11 |
| D-12.14 | TC-12-21 |
| AC-12.14, D-12.2 (displaced) | TC-12-27 |
| AC-12.15, D-12.15 | TC-12-28 |
| D-12.9 (only CONFIRMED displaced) | TC-12-29 |
| D-12.4 (expired hold released) | TC-12-30 |
| R-12.2 (deadlock retried) | TC-12-31 |
| D-12.13 (EXPIRED hidden, nextBooking) | TC-12-32, TC-12-33 |
| WEB-12.7, WEB §B3 banner | TC-12-34, TC-12-35 |
| WEB-12.3 (key per duration) | TC-12-36 |
| Roles (README §4) | TC-12-22 |
| API §B4 | TC-12-23 |
| WEB-12.5, WEB-12.6 | TC-12-24, TC-12-25 |

## B2. Test cases

Backend in `api/tests/Feature/Workshop/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-12-01 | AC-12.1 | Integration | Today's CONFIRMED booking v1 | Advisor check-in (`"1"`), foreman start (`"2"`), complete (`"3"`) | Status `COMPLETED`, `checked_in_at`, `started_at`, `completed_at` set; 0 active allocations; 3 history rows | Allocations still active | `StatusActionsTest::test_AC_12_1_check_in_start_complete` |
| TC-12-02 | AC-12.2 | Integration | COMPLETED booking | Check in | 422 `INVALID_STATUS_TRANSITION` | Allowed | `…::test_AC_12_2_completed_cannot_check_in` |
| TC-12-04 | AC-12.4 | Integration | X IN_PROGRESS K1 09:00–10:00 (now 10:05); Y CONFIRMED K1 10:00–11:00 (Dũng); K2 free 10:00–11:30 | Extend X by 30 | 200; X ends 10:30 on K1; Y `REASSIGNED` to K2 same time; `affected` lists Y | Extension refused by EXCLUDE | `ExtendTest::test_AC_12_4_extend_moves_the_next_booking` |
| TC-12-05 | AC-12.5 | Integration | Booking tomorrow | Check in today | 422 `NOT_SERVICE_DAY` | Any day allowed | `StatusActionsTest::test_AC_12_5_check_in_only_on_service_day` |
| TC-12-06 | AC-12.6 | Integration | Booking at 11:20 (now 10:50) | No-show; then `travel` to 11:20 and no-show | 422 `NO_SHOW_BEFORE_START`; then 200 `NO_SHOW` with inactive allocations | Early no-show allowed | `…::test_AC_12_6_no_show_only_after_start` |
| TC-12-07 | INV-12.1 | Integration | CHECKED_IN booking; CONFIRMED booking | Complete each | 422 both | Skipping steps allowed | `…::test_complete_requires_in_progress` |
| TC-12-08 | AC-12.7 | Integration | X IN_PROGRESS, nothing after it | Extend 30 | 200; `end` +30; X's 3 allocations have the new range; 1 `EXTENDED` history row with `minutes 30` | Allocations not updated | `ExtendTest::test_AC_12_7_extend_without_neighbours` |
| TC-12-09 | AC-12.4, D-12.9 | Integration | As TC-12-04 but no other QUICK bay free | Extend X by 30 | 200; Y still `CONFIRMED`, `contact_required_reason OVERRUN`, 0 active allocations; X holds K1 to 10:30; M-04.2 (with exception) 0 rows | Y cancelled or extension refused | `…::test_AC_12_4_unplaceable_neighbour_is_displaced_not_cancelled` |
| TC-12-10 | AC-12.8, INV-12.4 | Integration | As TC-12-04; a test hook changes Y (version bump) right after the neighbour read on the first attempt | Extend | Attempt 2 succeeds with fresh data (200); never a state where X is extended but Y still holds K1 | Partial commit | `…::test_AC_12_8_neighbour_change_triggers_one_retry` |
| TC-12-11 | Stale job version | Integration | X v3; someone completes X (v4) | Extend with `If-Match "3"` | 412 with `status COMPLETED`; neighbours untouched | Neighbours moved anyway | `…::test_stale_job_version_changes_nothing` |
| TC-12-16 | AC-12.12 | Integration | Today: blocks in K1, K3; X IN_PROGRESS ended 20 min ago; a cancelled booking | `GET workshop-board?date=today` | Bays with blocks (cancelled hidden); X `overrunMinutes 20`; alert `OVERRUN` for X with `nextBooking`; technicians: active only, with `level`, `bookedMinutes` and `status` (`OVERRUN` for X's technician) | Wrong statuses, overrun, or disabled technicians listed | `WorkshopBoardTest::test_AC_12_12_board` |
| TC-12-17 | INV-12.10 | Integration | Foreman of D1; a D2 booking; D2 | Check in, extend the D2 booking; open D2's board | 404 `APPOINTMENT_NOT_FOUND` twice; 404 `DEALERSHIP_NOT_FOUND` | Scope leak | `WorkshopScopeTest` |
| TC-12-20 | INV-12.9, D-12.8 | Integration | A booking flagged `OVERRUN` by an extend | Advisor reschedules it (F09); another flagged booking cancelled (F07) | Flag cleared in the same versioned write; history rows present | Flag stays | `ContactFlagTest::test_flag_cleared_by_reschedule_and_cancel` |
| TC-12-21 | D-12.14 | Integration | 2 flagged today, 1 flagged in 3 days, 1 flagged yesterday, 3 normal bookings | `GET /dealerships/{d}/appointments?contactRequired=true` (no `date`) | The 3 flagged from today on, by start; `meta.total` 3 | Filter missing, or only one day listed | `ContactFlagTest::test_contact_list_filter` |
| TC-12-22 | README §4 | Integration | Advisor | Start, complete, extend | 403 each; check-in and no-show 200 | Wrong Gate | `WorkshopRolesTest` (data provider) |
| TC-12-23 | API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `WorkshopAdversarialInputTest` |
| TC-12-24 | WEB-12.5 | Web component | Fake board with a block 09:00–10:00 and an overrun block; `now` 10:50 | Render `BayTimeline` | The block is placed at the 09:00 column; overrun part hatched; the now-line at 10:50; cancelled blocks absent | Positioned in the browser zone | `web/src/staff/workshop/BayTimeline.test.tsx` (run with `TZ=America/New_York`) |
| TC-12-25 | WEB-12.6 | Web component | Booking IN_PROGRESS; foreman vs advisor | Render actions | Foreman: "Mark completed", "Extend time"; advisor: none; extend menu sends `{minutes: 30}` with `If-Match` | Wrong buttons | `web/src/staff/appointments/AppointmentActions.test.tsx` |
| TC-12-26 | All | Manual | Stack running | M1–M7 | As A2 | — | Record in A2 |
| TC-12-27 | AC-12.14, D-12.2 | Integration | As TC-12-09: Y displaced (`OVERRUN`, 0 allocations) | Advisor checks Y in; then reschedules Y to the same time (a bay is free again) and checks in | 422 `NEEDS_RESCHEDULE`, Y unchanged; reschedule 200 with 3 active allocations and the flag cleared; check-in 200 | Displaced booking runs with no allocations | `ContactFlagTest::test_AC_12_14_displaced_booking_needs_reschedule_before_check_in` |
| TC-12-28 | AC-12.15, D-12.15 | Web component | Fake API: board with an alert for X `version 3`; extend → 412 with X `COMPLETED` (v4) | Tap "Extend 30 min" | The request has `If-Match: "3"`; the 412 alert shows "Status: In progress → Completed"; the board refetches | Board extends without `If-Match` or with a fetched-later version | `web/src/staff/workshop/WorkshopBoardPage.test.tsx` |
| TC-12-29 | D-12.9, D-12.2 | Integration | As TC-12-09 but Y `CHECKED_IN` | Extend X by 30 | 409 `NO_AVAILABILITY` `SLOT_TAKEN`; X and Y unchanged, Y keeps 3 active allocations | Y displaced while checked in | `ExtendTest::test_D_12_9_checked_in_neighbour_is_never_displaced` |
| TC-12-30 | D-12.4, core rule 5 | Integration | Y is a `HELD` hold on K1 10:00 that expired at 10:00, allocations still active | Extend X by 30 | 200, `affected` empty; Y `EXPIRED`, 0 active allocations, no `REASSIGNED`/`CONTACT_REQUIRED` row | Expired hold moved (SMS) or flagged | `ExtendTest::test_extend_expires_a_blocking_expired_hold_instead_of_moving_it` |
| TC-12-31 | R-12.2 | Integration | As TC-12-04; the reassigner throws `DeadlockException` once / twice | Extend X by 30 | Once: 200, Y `REASSIGNED`; twice: 409 `SLOT_TAKEN`, nothing changed | 500 on a deadlock | `ExtendTest::test_R_12_2_*` |
| TC-12-32 | D-12.13 | Integration | An `EXPIRED` appointment in K2 for An | GET board | K2 has no block; An `bookedMinutes 0` | Expired hold drawn or counted | `WorkshopBoardTest::test_D_12_13_board_hides_expired_appointments` |
| TC-12-33 | D-12.13 | Integration | X overruns on Chi/K3; cancelled booking in K3 at 11:00; `CONFIRMED` booking for Chi in K1 at 11:30 | GET board | `nextBooking` is the 11:30 `CONFIRMED` booking | Card says "was cancelled" while extend would move a live booking | `WorkshopBoardTest::test_next_booking_skips_cancelled_when_a_live_booking_follows` |
| TC-12-34 | WEB §B3, WEB-12.7, M5 | Web component | Advisor; booking `CONFIRMED` with `contactRequiredReason OVERRUN`; check-in answers 422 `NEEDS_RESCHEDULE` | Open details; tap Check in | Banner "Customer contact needed: the previous job ran late" with the note, the phone and Reschedule/Cancel; after check-in the `NEEDS_RESCHEDULE` sentence shows | Banner missing; error swallowed | `AppointmentDetailsPage.test.tsx` |
| TC-12-35 | WEB-12.7 | Web component | Board alert; extend answers 409 `NO_AVAILABILITY` / an action answers 412 | Tap "Extend 30 min" / "Mark completed" | `actions.extendFailed` shows on the card / `AppointmentModifiedAlert` with the status change | Silent failure | `WorkshopBoardPage.test.tsx`, `AppointmentActions.test.tsx` |
| TC-12-36 | WEB-12.3, D-G.15 | Web unit | `useIdempotencyKeys({id, action: 'extend', etag})` | Keys for 15 and 30; settle 15 with 503, 30 with 409; new ETag | 15 ≠ 30; 15 reused after 503; 30 renewed after 409; all renewed on a new ETag | One key shared by all durations (422 `IDEMPOTENCY_KEY_MISMATCH`) | `useIdempotencyKey.test.ts` |

## B3. Fixtures

`AppointmentFactory::inProgress()`, `->endedMinutesAgo(n)`, `->forTechnician()`, `->inBay()`; `World::onlyBays()`; a test hook in `ExtendAppointment` (bound only in tests) to inject a concurrent change for TC-12-10; `Parallel` (F04).

## B4. Regression

F03 (`CandidateFinderTest`), F04 (`TransitionTest`, `AppointmentWriterTest`), F05 (list filters), F06 (availability), F07 and F09 (flag clearing).
