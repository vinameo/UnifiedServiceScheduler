# F12 · Day-of-service operations · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Status actions**: HTTP tests per transition and per rule (service day, after start), frozen clock, real PostgreSQL.
- **Extend and reassignment**: HTTP tests with prepared days (neighbours, free/busy alternatives), each followed by the overlap check and the M-04.2 check.
- **Independence of reassignments** (FR-12.4): a test that changes one affected booking in parallel during apply.
- **Absences block future bookings**: an HTTP booking test after recording an absence.
- **Screens**: component tests for the board, the actions and the report flow; one manual run.

## A2. Manual scenarios

| # | Steps | Expected |
| --- | --- | --- |
| M1 | Advisor opens today's CONFIRMED booking → Check in | Status Checked in; foreman then sees Start work |
| M2 | Foreman → Start work → Mark completed | Completed; the board block turns green; the slot is bookable again |
| M3 | Foreman sets a job's end in the past (factory) → board | Alert "Bay 3 is running 20 minutes over" |
| M4 | Booking B follows in the same bay; another STANDARD bay is free → Extend 30 min | Notification "Next booking … moved to …, Bay …"; B's details show the new bay |
| M5 | No other bay free → Extend 30 min | B shows the "Customer contact needed: the previous job ran late" banner; B appears in the contact list |
| M6 | Report absence for Ngô Bảo Em tomorrow → Find affected | "3 affected appointments · 2 can be reassigned · 1 needs customer contact" (seed scenario) |
| M7 | Apply reassignment | Results as previewed; New booking for tomorrow never picks Em |
| M8 | Advisor tries to open `/staff/workshop/absence` | "You don't have access to this page." |

## A3. Test data

`World::saigonD7()`; bookings via `AppointmentFactory` (including `->inProgress()->endedMinutesAgo(20)`); clock frozen at 2026-11-02 10:50 +07 unless noted.

## A4. Not tested

The SMS text of `AppointmentReassigned` (F11 sends it); manual technician choice (out of scope).

## A5. Exit criteria

Every TC green; M1–M8 pass.

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
| AC-12.3, FR-12.3, FR-12.5, INV-12.6 | TC-12-03 |
| AC-12.4, FR-12.6, D-12.4 | TC-12-04, TC-12-09 |
| AC-12.5, D-12.2 | TC-12-05 |
| AC-12.6, BR-12.1, D-12.2 | TC-12-06 |
| AC-12.7, D-12.3 | TC-12-08 |
| AC-12.8, INV-12.4 | TC-12-10 |
| AC-12.9, INV-12.8, D-12.11 | TC-12-15 |
| AC-12.10, FR-12.4, INV-12.5 | TC-12-12 |
| AC-12.11 | TC-12-14 |
| AC-12.12, D-12.13 | TC-12-16 |
| AC-12.13, D-12.10 | TC-12-19 |
| INV-12.3 | `AssertsNoOverlap` after every test |
| INV-12.7 | TC-12-03, TC-12-04 |
| INV-12.9, D-12.8 | TC-12-20 |
| INV-12.10 | TC-12-17 |
| D-12.6 (`IN_PROGRESS`, `HELD`) | TC-12-13 |
| D-12.9 | TC-12-09 |
| Stale job version on extend | TC-12-11 |
| D-12.14 | TC-12-21 |
| Roles (README §4) | TC-12-22 |
| DB-12.1, DB-12.2 | TC-12-18 |
| API §B4 | TC-12-23 |
| WEB-12.5, WEB-12.6 | TC-12-24, TC-12-25 |

## B2. Test cases

Backend in `api/tests/Feature/Workshop/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-12-01 | AC-12.1 | Integration | Today's CONFIRMED booking v1 | Advisor check-in (`"1"`), foreman start (`"2"`), complete (`"3"`) | Status `COMPLETED`, `checked_in_at`, `started_at`, `completed_at` set; 0 active allocations; 3 history rows | Allocations still active | `StatusActionsTest::test_AC_12_1_check_in_start_complete` |
| TC-12-02 | AC-12.2 | Integration | COMPLETED booking | Check in | 422 `INVALID_STATUS_TRANSITION` | Allowed | `…::test_AC_12_2_completed_cannot_check_in` |
| TC-12-03 | AC-12.3, INV-12.6, INV-12.7 | Integration | An (BASIC) has 3 bookings tomorrow (OIL 08:00, 10:00, 14:00); Chi free at 10:00 and 14:00, everyone else busy at 08:00 | Foreman `POST /technicians/{An}/absences` tomorrow 08:00–17:00 | 201; results: 08:00 `CONTACT_REQUIRED`, 10:00 and 14:00 `REASSIGNED` to Chi with the same times and vehicles; none cancelled; 2 `AppointmentReassigned` events | A booking cancelled or time changed | `AbsenceTest::test_AC_12_3_absence_reassigns_two_and_flags_one` |
| TC-12-04 | AC-12.4 | Integration | X IN_PROGRESS K1 09:00–10:00 (now 10:05); Y CONFIRMED K1 10:00–11:00 (Dũng); K2 free 10:00–11:30 | Extend X by 30 | 200; X ends 10:30 on K1; Y `REASSIGNED` to K2 same time; `affected` lists Y | Extension refused by EXCLUDE | `ExtendTest::test_AC_12_4_extend_moves_the_next_booking` |
| TC-12-05 | AC-12.5 | Integration | Booking tomorrow | Check in today | 422 `NOT_SERVICE_DAY` | Any day allowed | `StatusActionsTest::test_AC_12_5_check_in_only_on_service_day` |
| TC-12-06 | AC-12.6 | Integration | Booking at 11:20 (now 10:50) | No-show; then `travel` to 11:20 and no-show | 422 `NO_SHOW_BEFORE_START`; then 200 `NO_SHOW` with inactive allocations | Early no-show allowed | `…::test_AC_12_6_no_show_only_after_start` |
| TC-12-07 | INV-12.1 | Integration | CHECKED_IN booking; CONFIRMED booking | Complete each | 422 both | Skipping steps allowed | `…::test_complete_requires_in_progress` |
| TC-12-08 | AC-12.7 | Integration | X IN_PROGRESS, nothing after it | Extend 30 | 200; `end` +30; X's 3 allocations have the new range; 1 `EXTENDED` history row with `minutes 30` | Allocations not updated | `ExtendTest::test_AC_12_7_extend_without_neighbours` |
| TC-12-09 | AC-12.4, D-12.9 | Integration | As TC-12-04 but no other QUICK bay free | Extend X by 30 | 200; Y still `CONFIRMED`, `contact_required_reason OVERRUN`, 0 active allocations; X holds K1 to 10:30; M-04.2 (with exception) 0 rows | Y cancelled or extension refused | `…::test_AC_12_4_unplaceable_neighbour_is_displaced_not_cancelled` |
| TC-12-10 | AC-12.8, INV-12.4 | Integration | As TC-12-04; a test hook changes Y (version bump) right after the neighbour read on the first attempt | Extend | Attempt 2 succeeds with fresh data (200); never a state where X is extended but Y still holds K1 | Partial commit | `…::test_AC_12_8_neighbour_change_triggers_one_retry` |
| TC-12-11 | Stale job version | Integration | X v3; someone completes X (v4) | Extend with `If-Match "3"` | 412 with `status COMPLETED`; neighbours untouched | Neighbours moved anyway | `…::test_stale_job_version_changes_nothing` |
| TC-12-12 | AC-12.10, INV-12.5 | Concurrency | 3 affected bookings; process 2 reschedules booking B (F09) at the moment process 1 applies the absence | Apply absence | A and C get outcomes; B ends either rescheduled away (not affected) or `MODIFIED`/handled; no cancellation; overlap 0 | One failure aborts all | `ParallelAbsenceTest::test_AC_12_10_one_conflict_does_not_stop_the_others` |
| TC-12-13 | D-12.6 | Integration | Absence range covers an IN_PROGRESS job and a HELD hold of the technician | Apply | IN_PROGRESS → `CONTACT_REQUIRED`; HELD not in results | Holds reassigned / running job moved | `AbsenceTest::test_D_12_6_in_progress_and_holds` |
| TC-12-14 | AC-12.11 | Integration | K3 (STANDARD) out of service 10:00–12:00 with booking Z (Chi) | Foreman `POST /bays/{K3}/out-of-service` | Z `REASSIGNED` to another STANDARD bay keeping Chi, or `CONTACT_REQUIRED` when none (two variants) | Technician changed when not needed | `OutageTest::test_AC_12_11_outage_keeps_technician` |
| TC-12-15 | AC-12.9, INV-12.8 | Integration | Absence An tomorrow 08:00–17:00; An is the only free BASIC technician otherwise | Book OIL tomorrow 10:00; availability for tomorrow | 409 (or another technician); An never assigned; availability shows the slots taken | Reader ignores absences | `CompositeBusyIntervalReaderTest::test_AC_12_9_absent_technician_is_never_offered` |
| TC-12-16 | AC-12.12 | Integration | Today: blocks in K1, K3; X IN_PROGRESS ended 20 min ago; a cancelled booking | `GET workshop-board?date=today` | Bays with blocks (cancelled hidden); X `overrunMinutes 20`; alert `OVERRUN` for X with `nextBooking`; technicians with `bookedMinutes` and `availability` | Wrong statuses or overrun | `WorkshopBoardTest::test_AC_12_12_board` |
| TC-12-17 | INV-12.10 | Integration | Foreman of D1; technician/bay of D2; a D2 booking | Absence for D2 tech; outage for D2 bay; check-in D2 booking | 404 `TECHNICIAN_NOT_FOUND`, 404 `BAY_NOT_FOUND`, 404 `APPOINTMENT_NOT_FOUND` | Scope leak | `WorkshopScopeTest` |
| TC-12-18 | DB-12.1, DB-12.2 | Integration | — | Insert an absence with an empty period; a bad reason | `23514` each | Missing CHECK | `UnavailabilitySchemaTest` |
| TC-12-19 | AC-12.13, D-12.10 | Integration + Web | Seed-like tomorrow (as M6) | `GET absence-impact`; then apply | Preview summary 3/2/1 with no writes (row counts unchanged); apply returns the same outcomes; web shows "3 affected appointments · 2 can be reassigned · 1 needs customer contact" | Preview writes | `AbsenceTest::test_AC_12_13_preview_has_no_side_effects`, `web/src/staff/workshop/ReportUnavailabilityPage.test.tsx` |
| TC-12-20 | INV-12.9, D-12.8 | Integration | A flagged booking (`TECHNICIAN_ABSENT`) | Advisor reschedules it (F09); another flagged booking cancelled (F07) | Flag cleared in the same versioned write; history rows present | Flag stays | `ContactFlagTest::test_flag_cleared_by_reschedule_and_cancel` |
| TC-12-21 | D-12.14 | Integration | 2 flagged, 3 normal bookings | `GET /dealerships/{d}/appointments?date=…&contactRequired=true` | Only the 2 flagged | Filter missing | `ContactFlagTest::test_contact_list_filter` |
| TC-12-22 | README §4 | Integration | Advisor | Start, complete, extend, absence, outage | 403 each; check-in and no-show 200 | Wrong Gate | `WorkshopRolesTest` (data provider) |
| TC-12-23 | API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `WorkshopAdversarialInputTest` |
| TC-12-24 | WEB-12.5 | Web component | Fake board with a block 09:00–10:00 and an overrun block; `now` 10:50 | Render `BayTimeline` | The block is placed at the 09:00 column; overrun part hatched; the now-line at 10:50; cancelled blocks absent | Positioned in the browser zone | `web/src/staff/workshop/BayTimeline.test.tsx` (run with `TZ=America/New_York`) |
| TC-12-25 | WEB-12.6 | Web component | Booking IN_PROGRESS; foreman vs advisor | Render actions | Foreman: "Mark completed", "Extend time"; advisor: none; extend menu sends `{minutes: 30}` with `If-Match` | Wrong buttons | `web/src/staff/appointments/AppointmentActions.test.tsx` |
| TC-12-26 | All | Manual | Stack running | M1–M8 | As A2 | — | Record in A2 |

## B3. Fixtures

`AppointmentFactory::inProgress()`, `->endedMinutesAgo(n)`, `->forTechnician()`, `->inBay()`; `World::onlyBays()`; a test hook in `ExtendAppointment` (bound only in tests) to inject a concurrent change for TC-12-10; `Parallel` (F04).

## B4. Regression

F03 (`CandidateFinderTest` — the composite reader replaces the binding), F04 (`TransitionTest`, `AppointmentWriterTest`), F05 (list filters), F06 (availability), F07 and F09 (flag clearing).
