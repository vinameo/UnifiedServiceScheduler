# F01 · Master data · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- Read access by dealership and by role: HTTP tests on real PostgreSQL.
- Data rules (duration, opening hours, duplicate plates): direct DB inserts, because the rules live in the DB.
- Seeder: run twice and count.
- Weekday grouping ("Mon–Sat"): web unit test.
- Screen: component tests + one manual run.

## A2. Manual scenarios

| # | Steps | Expected |
| --- | --- | --- |
| M1 | Admin → Master data | 4 tabs: "Service types 3", "Bays 4", "Technicians 5", "Opening hours"; "Read only" badge |
| M2 | Technicians tab | Columns Technician · Level · Status · Action; Huỳnh Tấn Phát: "Level 1", "Not taking bookings", "Enable"; no Shift column |
| M3 | Opening hours tab | "Monday to Friday · 08:00–17:00", "Saturday · 08:00–12:00", "Sunday · Closed", "Asia/Ho_Chi_Minh (UTC+7)", "15 min"; no "Next holiday" |
| M4 | Advisor types `/staff/master-data` | "You don't have access to this page." |
| M5 | Admin disables Võ Thị Chi (no bookings), then enables her | "Võ Thị Chi no longer takes bookings."; status "Not taking bookings"; after Enable: "Working" |
| M6 | An advisor books a 20,000 km service that gets Dũng; admin tries to disable Dũng | Modal "Lý Hoàng Dũng still has 1 upcoming appointments. Move or cancel them before disabling." with the booking's reference; Dũng stays "Working" |

## A3. Test data

Seed (F00 + F01, DB §B3). Automated tests use `Tests\Support\World` (same data, built inside the test).

## A4. Not tested

Editing master data (does not exist). Public holidays (out of scope).

## A5. Exit criteria

Every TC green; M1–M6 pass.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-01.1 | TC-01-01 |
| AC-01.2, INV-01.3 | TC-01-02, TC-01-03 |
| AC-01.3, INV-01.4 | TC-01-05 |
| AC-01.4, INV-01.4 | TC-01-06 |
| AC-01.5 | TC-01-07 |
| AC-01.6 | TC-01-11, TC-01-12 |
| INV-01.1, INV-01.2, DB-01.1–01.4, D-01.3 | TC-01-10 |
| INV-01.5, DB-01.5 | TC-01-08 |
| INV-01.6 | TC-01-09 |
| INV-01.7, D-01.11, D-01.13 | TC-01-16, TC-01-17, TC-01-18, TC-01-19 |
| INV-01.8, D-01.12 | TC-01-20 |
| AC-01.7 | TC-01-16 |
| AC-01.8 | TC-01-17 |
| AC-01.9 | TC-01-18 |
| AC-01.10 | TC-01-20 |
| WEB-01.5 | TC-01-21 |
| Customer reads any dealership (INV-01.3) | TC-01-04 |
| API §B4 | TC-01-03, TC-01-13 |
| WEB-01.4 | TC-01-14 |
| BR-01.1 | Used by allocation in F03; here TC-01-01 checks each service has exactly one required level and one bay type |

## B2. Test cases

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-01-01 | AC-01.1, BR-01.1 | Integration | `migrate:fresh` + `db:seed` (password variables set) | Admin `GET /dealerships/{d}/service-types` | 200; 3 items; each has `durationMinutes > 0`, one `requiredLevel` (1–7), one `requiredBayType` | Seeder misses data | `api/tests/Feature/MasterData/ServiceTypesTest::test_AC_01_1_seeded_service_types_are_returned` |
| TC-01-02 | AC-01.2, INV-01.3 | Integration | Admin of D1; D2 has data | Admin calls the 4 routes of D2 | Each route 404 `DEALERSHIP_NOT_FOUND` | Binding does not check the dealership | `…/MasterData/DealershipScopeTest::test_AC_01_2_other_dealership_is_404` (data provider per route) |
| TC-01-03 | INV-01.3, API §B4 | Integration | Advisor of D1 | Calls with `{dealership}` = `abc`, a missing UUID, D2's UUID | All three 404 `DEALERSHIP_NOT_FOUND`; bodies identical except `correlationId` | "Missing" and "someone else's" can be told apart | `…DealershipScopeTest::test_INV_01_3_missing_malformed_and_foreign_look_the_same` |
| TC-01-04 | INV-01.3 | Integration | Customer; D1, D2 | Customer calls `GET /dealerships`, `/dealerships/{D2}/service-types` | List has D1, D2; service-types 200 | Customer blocked like staff | `…DealershipScopeTest::test_customer_reads_any_dealership_catalogue` |
| TC-01-05 | AC-01.3, INV-01.4 | Integration | Customer | `GET /dealerships/{d}/bays`, `/technicians` | 403 `FORBIDDEN` for both | Missing `can:staff` | `…/MasterData/BaysAndTechniciansTest::test_AC_01_3_customer_cannot_read_bays_or_technicians` |
| TC-01-06 | AC-01.4, INV-01.4 | Integration | `World` + one service with `active=false` | Customer and admin call service-types | Customer: 3; admin: 4, the new one `active:false` | Not filtered by role | `…ServiceTypesTest::test_AC_01_4_inactive_service_hidden_from_customers` |
| TC-01-07 | AC-01.5 | Integration | Advisor of D1; D1, D2 | `GET /dealerships` | Exactly 1 item, D1 | All dealerships returned to staff | `…DealershipScopeTest::test_AC_01_5_staff_list_only_their_dealership` |
| TC-01-08 | INV-01.5, DB-01.5 | Integration | Vehicle `51H-123.45` | Insert vehicle `51h 12345`; two vehicles with NULL VIN; two with the same VIN | First: `23505`; second: ok; third: `23505` | No unique `plate_key`, or VIN unique not partial | `…/MasterData/VehicleSchemaTest::test_INV_01_5_plate_key_and_vin_are_unique` |
| TC-01-09 | INV-01.6 | Integration | Empty DB | `db:seed` twice; record ids after run 1 | Row counts unchanged; ids unchanged; a technician disabled between the runs stays disabled | Seeder uses `create` instead of `updateOrCreate` | `…/MasterData/MasterDataSeederTest::test_INV_01_6_seeding_twice_changes_nothing` |
| TC-01-10 | INV-01.1, INV-01.2 | Integration | — | Insert: service of 0 minutes, 50 minutes, `required_level` 0 and 8; technician `level` 0 and 8; opening 17:00 closing 08:00; two rows for one weekday | Each insert fails (`23514` or `23505`) | Missing CHECK/unique | `…/MasterData/MasterDataSchemaTest::test_INV_01_1_and_01_2_checks_reject_bad_rows` (data provider) |
| TC-01-11 | AC-01.6 | Web component | Fake API returns the seed data | Render `MasterDataPage` | 4 tabs with counts 3/4/5; "Read only"; "Only technician status can be changed here."; "This version loads this data from seed files"; no "holiday" text | Wrong draft, holiday box still there | `web/src/staff/master-data/MasterDataPage.test.tsx` |
| TC-01-12 | AC-01.6 | Web component | Same | Open Technicians, Opening hours | "Level 6"; "Not taking bookings"; no "Shift" or "Skills" column; "Monday to Friday · 08:00–17:00"; "Sunday · Closed"; "UTC+7" | Wrong format | `…MasterDataPage.test.tsx` |
| TC-01-13 | API §B4 | Integration | Customer | `service-types?includeInactive=true` | Still only `active` | Unknown parameter used | `…ServiceTypesTest::test_customer_cannot_include_inactive` |
| TC-01-14 | WEB-01.4 | Unit (web) | Day sets | `groupWeekdays` | `[1..6 08–17]` → "Mon–Sat"; `[1,2,4]` → "Mon–Tue, Thu"; different hours split rows | Wrong grouping | `web/src/shared/format/weekdays.test.ts` |
| TC-01-15 | All | Manual | Stack running | M1–M6 | As A2 | — | Record in A2 |
| TC-01-16 | AC-01.7, INV-01.7 | Integration | `World`; Chi has no appointment | Admin `PUT …/technicians/{Chi}/status` `{active:false}` | 200 `active:false`; `AllocationSnapshotRepository` snapshot lists Chi inactive and F03 never pairs her | Disable ignored by allocation | `api/tests/Feature/MasterData/TechnicianStatusTest::test_AC_01_7_disable_without_bookings` |
| TC-01-17 | AC-01.8, D-01.11 | Integration (written in F04, when the real reader exists) | Chi has a `CONFIRMED` booking tomorrow and a `COMPLETED` one yesterday | Admin disables Chi | 409 `TECHNICIAN_HAS_BOOKINGS`, `count 1`, `appointments[0].reference` = tomorrow's; Chi still active | Past or closed bookings counted, or disable not refused | `…TechnicianStatusTest::test_AC_01_8_disable_refused_with_upcoming_bookings` |
| TC-01-18 | AC-01.9, INV-01.7, D-01.13 | Concurrency (in F04) | `onlyTechnicians(['Võ Thị Chi'])`; 20 runs | Process 1 books Chi's only free slot, process 2 disables Chi, released together | Each run ends either (booking 201, disable 409, Chi active) or (disable 200, booking 409 `NO_AVAILABILITY`); never Chi inactive with an active allocation | No `FOR UPDATE`/`FOR SHARE` pair | `ParallelTechnicianDisableTest::test_AC_01_9_disable_and_booking_never_both_win` |
| TC-01-19 | D-01.11 | Integration (in F04) | Chi's only appointment is a `HELD` with `expires_at` in the past | Admin disables Chi | 200; Chi inactive | Expired hold counted as upcoming | `…TechnicianStatusTest::test_expired_hold_does_not_block_disable` |
| TC-01-20 | AC-01.10, INV-01.8, D-01.12, API §B4 | Integration | Admin of D1; D2's technician; advisor; a disabled technician | Each row of API §B4 for API-01.6; admin enables the disabled one twice | Status and `code` as in the table; enable: 200 both times, `active:true` | Wrong gate or dealership scope | `…TechnicianStatusTest::test_API_01_6_adversarial_input` (data provider) |
| TC-01-21 | WEB-01.5 | Web component | Fake API: disable → 409 with `count 23` and 20 rows; then enable → 200 | Click Disable, confirm; close; click Enable on a disabled row | Modal shows `disable.blocked` with 20 references and "and 3 more"; row unchanged; enable notification "… takes bookings again." | Server `detail` shown, or count from the list length | `web/src/staff/master-data/MasterDataPage.test.tsx` |

## B3. Fixtures

`Tests\Support\World` (built here, used by every later feature):

```php
// illustrative
$world = World::saigonD7();          // dealership, opening hours, 3 services (levels 1, 3, 5), 4 bays, 5 technicians (levels 2, 3, 4, 6, 1-disabled)
$world->serviceType('OIL_CHANGE');   // ServiceType
$world->technician('Trần Minh An');  // Technician
$world->bay('K2');                   // Bay
$world->customerWithVehicle();       // [User, Customer, Vehicle]
$world->onlyTechnicians(['Trần Minh An']); // deactivate the others (for AC-04.1, AC-07.1)
```

Same data as DB §B3, with fresh ids per test.

## B4. Regression

All F00 tests (`Feature/Auth/*`), `Feature/Platform/*`, `web/src/router.test.tsx`.
