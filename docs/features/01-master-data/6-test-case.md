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
| M2 | Technicians tab | Huỳnh Tấn Phát: "Mon–Fri · 08:00–12:00", "Not taking bookings" |
| M3 | Opening hours tab | "Monday to Friday · 08:00–17:00", "Saturday · 08:00–12:00", "Sunday · Closed", "Asia/Ho_Chi_Minh (UTC+7)", "15 min"; no "Next holiday" |
| M4 | Advisor types `/staff/master-data` | "You don't have access to this page." |

## A3. Test data

Seed (F00 + F01, DB §B3). Automated tests use `Tests\Support\World` (same data, built inside the test).

## A4. Not tested

Editing master data (does not exist). Public holidays (out of scope).

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
| AC-01.1 | TC-01-01 |
| AC-01.2, INV-01.3 | TC-01-02, TC-01-03 |
| AC-01.3, INV-01.4 | TC-01-05 |
| AC-01.4, INV-01.4 | TC-01-06 |
| AC-01.5 | TC-01-07 |
| AC-01.6 | TC-01-11, TC-01-12 |
| INV-01.1, INV-01.2, DB-01.1–01.4 | TC-01-10 |
| INV-01.5, DB-01.5 | TC-01-08 |
| INV-01.6 | TC-01-09 |
| Customer reads any dealership (INV-01.3) | TC-01-04 |
| API §B4 | TC-01-03, TC-01-13 |
| WEB-01.4 | TC-01-14 |
| BR-01.1 | Used by allocation in F03; here TC-01-01 checks each service has exactly one skill and one bay type |

## B2. Test cases

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-01-01 | AC-01.1, BR-01.1 | Integration | `migrate:fresh` + `db:seed` (password variables set) | Admin `GET /dealerships/{d}/service-types` | 200; 3 items; each has `durationMinutes > 0`, exactly one `requiredSkill`, one `requiredBayType` | Seeder misses data | `api/tests/Feature/MasterData/ServiceTypesTest::test_AC_01_1_seeded_service_types_are_returned` |
| TC-01-02 | AC-01.2, INV-01.3 | Integration | Admin of D1; D2 has data | Admin calls the 4 routes of D2 | Each route 404 `DEALERSHIP_NOT_FOUND` | Binding does not check the dealership | `…/MasterData/DealershipScopeTest::test_AC_01_2_other_dealership_is_404` (data provider per route) |
| TC-01-03 | INV-01.3, API §B4 | Integration | Advisor of D1 | Calls with `{dealership}` = `abc`, a missing UUID, D2's UUID | All three 404 `DEALERSHIP_NOT_FOUND`; bodies identical except `correlationId` | "Missing" and "someone else's" can be told apart | `…DealershipScopeTest::test_INV_01_3_missing_malformed_and_foreign_look_the_same` |
| TC-01-04 | INV-01.3 | Integration | Customer; D1, D2 | Customer calls `GET /dealerships`, `/dealerships/{D2}/service-types` | List has D1, D2; service-types 200 | Customer blocked like staff | `…DealershipScopeTest::test_customer_reads_any_dealership_catalogue` |
| TC-01-05 | AC-01.3, INV-01.4 | Integration | Customer | `GET /dealerships/{d}/bays`, `/technicians` | 403 `FORBIDDEN` for both | Missing `can:staff` | `…/MasterData/BaysAndTechniciansTest::test_AC_01_3_customer_cannot_read_bays_or_technicians` |
| TC-01-06 | AC-01.4, INV-01.4 | Integration | `World` + one service with `active=false` | Customer and admin call service-types | Customer: 3; admin: 4, the new one `active:false` | Not filtered by role | `…ServiceTypesTest::test_AC_01_4_inactive_service_hidden_from_customers` |
| TC-01-07 | AC-01.5 | Integration | Advisor of D1; D1, D2 | `GET /dealerships` | Exactly 1 item, D1 | All dealerships returned to staff | `…DealershipScopeTest::test_AC_01_5_staff_list_only_their_dealership` |
| TC-01-08 | INV-01.5, DB-01.5 | Integration | Vehicle `51H-123.45` | Insert vehicle `51h 12345`; two vehicles with NULL VIN; two with the same VIN | First: `23505`; second: ok; third: `23505` | No unique `plate_key`, or VIN unique not partial | `…/MasterData/VehicleSchemaTest::test_INV_01_5_plate_key_and_vin_are_unique` |
| TC-01-09 | INV-01.6 | Integration | Empty DB | `db:seed` twice; record ids after run 1 | Row counts unchanged; ids unchanged; skills and shifts not doubled | Seeder uses `create` instead of `updateOrCreate` | `…/MasterData/MasterDataSeederTest::test_INV_01_6_seeding_twice_changes_nothing` |
| TC-01-10 | INV-01.1, INV-01.2 | Integration | — | Insert: service of 0 minutes, 50 minutes, skill `FOO`; opening 17:00 closing 08:00; two rows for one weekday; shift `ends_at = starts_at` | Each insert fails (`23514` or `23505`) | Missing CHECK/unique | `…/MasterData/MasterDataSchemaTest::test_INV_01_1_and_01_2_checks_reject_bad_rows` (data provider) |
| TC-01-11 | AC-01.6 | Web component | Fake API returns the seed data | Render `MasterDataPage` | 4 tabs with counts 3/4/5; "Read only"; "This version loads this data from seed files"; no "holiday" text | Wrong draft, holiday box still there | `web/src/staff/master-data/MasterDataPage.test.tsx` |
| TC-01-12 | AC-01.6 | Web component | Same | Open Technicians, Opening hours | "Mon–Sat · 08:00–17:00"; "Not taking bookings"; "Sunday · Closed"; "UTC+7" | Wrong format | `…MasterDataPage.test.tsx` |
| TC-01-13 | API §B4 | Integration | Customer | `service-types?includeInactive=true` | Still only `active` | Unknown parameter used | `…ServiceTypesTest::test_customer_cannot_include_inactive` |
| TC-01-14 | WEB-01.4 | Unit (web) | Day sets | `groupWeekdays` | `[1..6 08–17]` → "Mon–Sat"; `[1,2,4]` → "Mon–Tue, Thu"; different hours split rows | Wrong grouping | `web/src/shared/format/weekdays.test.ts` |
| TC-01-15 | All | Manual | Stack running | M1–M4 | As A2 | — | Record in A2 |

## B3. Fixtures

`Tests\Support\World` (built here, used by every later feature):

```php
// illustrative
$world = World::saigonD7();          // dealership, opening hours, 3 services, 4 bays, 5 technicians, shifts
$world->serviceType('OIL_CHANGE');   // ServiceType
$world->technician('Trần Minh An');  // Technician
$world->bay('K2');                   // Bay
$world->customerWithVehicle();       // [User, Customer, Vehicle]
$world->onlyTechnicians(['Trần Minh An']); // deactivate the others (for AC-04.1, AC-07.1)
```

Same data as DB §B3, with fresh ids per test.

## B4. Regression

All F00 tests (`Feature/Auth/*`), `Feature/Platform/*`, `web/src/router.test.tsx`.
