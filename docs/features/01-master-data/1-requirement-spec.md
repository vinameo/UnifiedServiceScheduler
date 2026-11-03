# F01 · Master data · Requirement spec (RQ)

Branch: `feature/01-master-data` · Priority: Must · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** The system can only allocate correctly if it knows what the dealership has: which level and bay type each service needs, which bays are usable, which technicians take bookings and at what level, and when the dealership is open.

**The problem.** None of these tables exist yet.

**What it does.**
- Creates tables for opening hours, service types, bays, technicians (one level each) and vehicles.
- Loads demo data for "Saigon Auto · District 7" (3 service types, 4 bays, 5 technicians, 3 vehicles).
- Read APIs for staff (their own dealership) and for customers (dealerships and service types).
- A "Master data" screen for admins, with 4 tabs. Everything is read-only except one action: an admin can **disable or enable a technician**.

**Who uses it, where.** Admin: staff web → Administration → Master data. Other staff and customers use it through the API (the booking screens read from it).

**Inputs.** Seed files; the admin's disable/enable click.

**Outcome.** The data F03 allocates from and the booking screens show. A disabled technician gets no new appointments.

**What it does not do.** Screens to add or change other master data, shifts, attendance, leave, public holidays, lunch breaks (README D-G.16).

## A2. Usage steps

1. Admin signs in → Master data.
2. Views the tabs Service types (default), Bays, Technicians, Opening hours. Each tab shows a count.
3. On the Technicians tab, taps "Disable" on a technician → confirms. If the technician still has upcoming appointments, the screen lists them and nothing changes; an advisor moves or cancels them first. "Enable" puts a disabled technician back.
4. To change other data: edit the seed file and run the seeder again (the screen says so).

## A3. Screens and deviations from the drafts

Draft: `Admin/screenshots/09-master-data.png`. Deviations:

| Draft | Spec | Reason |
| --- | --- | --- |
| "Next holiday · 01/01/2027" box | Removed | Public holidays are out of scope (requirements) |
| "Version 1 loads this data through migrations" | "This version loads this data from seed files" | D-01.1 |
| Menu item visible to an advisor | Only admins see Master data | FR / README §4 |
| Service types: "Skill required" column | "Level required" ("Level 3") | D-01.3 |
| Technicians: "Skills" and "Shift" columns | "Level" column; no Shift column; a "Disable"/"Enable" action per row | D-01.10, D-01.11 |
| "Read only" badge | "Read only" badge kept, with the note "Only technician status can be changed here." | D-01.11 |

## A4. Key rules in plain words

- Each service type needs exactly **one** level (1–7) and **one** bay type. Duration must be > 0.
- A technician has one level (1–7). They can do every service whose required level is **at or below** their level: a level-6 technician can do a level-1 oil change.
- An active technician works whenever the dealership is open. There are no shifts or leave in this version.
- An admin can disable a technician only when they have no upcoming appointments; a disabled technician gets no new ones. Enabling is always allowed.
- Opening hours are in the dealership's local time.
- Licence plates and VINs are unique across vehicles.
- Staff read only their own dealership; asking for another returns "Dealership not found".

## A5. Out of scope

Editing master data in the UI (except technician status); shifts, attendance, leave; several opening intervals per day; public holidays; technicians working at several dealerships; moving a disabled technician's appointments automatically.

## A6. Success criteria

- After `migrate --seed` on an empty database, the API returns 3 service types, 4 bays, 5 technicians.
- F03 runs on this data alone (no hand-made data needed).
- A technician with upcoming appointments can never end up disabled (TC-01-18).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-01.1 | Demo data is loaded by **seeders** (safe to rerun), not by a migration as FR-01.1 says. Migrations hold schema only. `docker compose` runs the seeders when `RUN_SEEDERS=true` (existing entrypoint). |
| D-01.2 | Service types belong to a dealership (`service_types.dealership_id`), per NFR-03 and the path `/dealerships/{id}/service-types`. |
| D-01.3 | Levels: integers 1–7 (trade grade, README D-G.16), label "Level {n}". `technicians.level` and `service_types.required_level` with DB CHECKs. A technician qualifies for a service when `level ≥ required_level`. Bay type codes: `QUICK`, `STANDARD`, `EV` (labels "Quick service", "Standard service", "EV"), PHP backed enum + DB CHECK. |
| D-01.4 | Seed data follows the drafts (DB §B3) with levels instead of skills: 3 service types (levels 1, 3, 5), 4 bays, 5 technicians (levels 2, 3, 4, 6, and Huỳnh Tấn Phát level 1 disabled), opening hours Mon–Fri 08:00–17:00, Sat 08:00–12:00, Sun closed. More than the FR-01.1 minimum. |
| D-01.5 | The `vehicles` table is created in F01 (master data); the add-vehicle API is in F02. Seed 3 vehicles (DB §B3). |
| D-01.6 | Licence plate: store the display string (trimmed, upper case) and a unique `plate_key` (letters + digits only, upper case). `51H-123.45` and `51h 12345` are the same vehicle. VIN: optional, 17 characters `[A-HJ-NPR-Z0-9]`, upper case, unique when present. |
| D-01.7 | The 15-minute slot step is an app constant (`BookingTimeRules::STEP_MINUTES`, F02), returned by the dealership API as `slotStepMinutes` for display; not stored in the DB. |
| D-01.8 | Customers can read the dealership list and the **active** service types of any dealership; they cannot read bays or technicians. |
| D-01.9 | One opening interval per day (unique on `dealership_id, weekday`); a day without a row is closed. |
| D-01.10 | No shifts: an active technician is available for any range inside the dealership's opening hours (README D-G.16). The opening-hours check (F02 `BookingTimeRules`) is the only time rule (FR-03.1, BR-01.2). |
| D-01.11 | Admins disable/enable a technician with `PUT /dealerships/{dealership}/technicians/{technician}/status` `{ "active": false }`. Disabling is refused with 409 `TECHNICIAN_HAS_BOOKINGS` while the technician has an **upcoming appointment**: an active `TECHNICIAN` allocation whose period ends after now, except expired holds (README D-G.6 clock). The answer lists up to 20 of them (reference, time, customer name) with the real `count`. Enabling is always allowed. Setting the current value again → 200, nothing changes. |
| D-01.12 | `PUT` sets an absolute value, so repeating it is harmless: it needs neither `Idempotency-Key` (the rule covers writing `POST`s, README D-G.8) nor `If-Match` (technicians have no version; the last admin's value wins). |
| D-01.13 | Race between disabling and booking: the disable transaction locks the technician row (`FOR UPDATE`) before counting upcoming appointments; every allocation write (F04 `AppointmentWriter`) reads the technician with `FOR SHARE` in its own transaction and treats an inactive technician like a taken one (next pair). So a booking either commits first (and disabling is refused) or sees the technician disabled. |

## A8. Open questions (with defaults)

| ID | Question | Default |
| --- | --- | --- |
| Q-01.a | Do advisors need the Master data screen? | No; the API still serves them. |
| Q-01.b | Do service types need an `active` flag? | Yes, to hide a service without deleting history. |
| Q-01.c | Can an advisor swap the technician of a booking at the same time (to free a technician before disabling)? | No in this version: the advisor reschedules (F09) or cancels (F07). FC-01.4. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00 is merged: `dealerships`, `users.role`, `users.dealership_id`, `customers`, `Role`, Gates `staff`/`customer`, `AccountSeeder`. There is no `api/app/Domain/MasterData/` folder yet. The web `StaffLayout` has a static "Master data" link with no page.

The agent MUST check these paths at the branch HEAD before starting (B0).

## B2. Scope

| ID | In scope |
| --- | --- |
| S-01.1 | Migrations: `dealership_opening_hours`, `service_types`, `bays`, `technicians`, `vehicles` (DB §B1). |
| S-01.2 | Enum `BayType`, value object `Level` (1–7); models + read repository. |
| S-01.3 | Route binding `{dealership}` with the access check (INV-01.3). |
| S-01.4 | 5 read endpoints + 1 status endpoint (API §B1). |
| S-01.8 | Disable/enable a technician: `TechnicianService::setActive`, seam `UpcomingAssignmentsReader` (`NoUpcomingAssignments` until F04 binds the real one), D-01.11–01.13. |
| S-01.5 | `MasterDataSeeder`, `VehicleSeeder`, safe to rerun. |
| S-01.6 | Fixture `Tests\Support\World` (README §7). |
| S-01.7 | Master data screen (WEB). |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-01.1 | `service_types.duration_minutes > 0` and a multiple of 15; `required_level` and `technicians.level` are integers 1–7; `required_bay_type` is an enum value. | DB CHECKs; the seeder writes only through models. |
| INV-01.2 | Each dealership has at most one opening interval per weekday, with `closes_at > opens_at`. | CHECK + unique. |
| INV-01.3 | Staff read only their own dealership: any other `{dealership}` (missing, not a UUID, or another dealership's) → 404 `DEALERSHIP_NOT_FOUND`, all identical. Customers: an existing dealership → allowed; missing → 404. | The route binding reads `auth()->user()->dealership_id` of the current request. |
| INV-01.4 | Customers cannot read bays or technicians (403). Customers see only `active` service types. | Gate `staff` on 2 routes; the query filters `active` when the user is a customer. |
| INV-01.5 | `plate_key` is unique; `vin` is unique when not NULL. | Unique index; partial unique for VIN. |
| INV-01.6 | Rerunning the seeders creates no duplicates and changes no existing id. | `updateOrCreate` on natural keys (code, `plate_key`, name + dealership for technicians). The seeder never changes `technicians.active` of an existing row, so a rerun does not undo an admin's disable. |
| INV-01.7 | A disabled technician never holds an upcoming appointment, and no new allocation ever uses one (D-01.11, D-01.13). | Disable: one transaction — lock the technician row `FOR UPDATE`, count upcoming appointments through `UpcomingAssignmentsReader` with the app clock, refuse (409) or set `active = false`. Writers: `FOR SHARE` read of the technician inside the allocation transaction (F04 INV-04.15). |
| INV-01.8 | Only admins change technician status, and only for their own dealership; another dealership's technician (or a missing / malformed id) → 404 `TECHNICIAN_NOT_FOUND`. | Gate `admin` + the `{dealership}` binding + `{technician}` resolved inside that dealership in the same query. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| INV-01.3 | Another dealership; missing id; non-UUID id; customer asks any dealership | TC-01-02, TC-01-03, TC-01-04 |
| INV-01.4 | Customer calls bays/technicians; inactive service | TC-01-05, TC-01-06 |
| INV-01.5 | Same plate spelled differently; two vehicles with NULL VIN | TC-01-08 |
| INV-01.6 | Seeding twice; seeding after a disable | TC-01-09 |
| INV-01.7 | No upcoming appointment; one confirmed tomorrow; only an expired hold; a booking committed while disabling | TC-01-16, TC-01-17, TC-01-19 (F04), TC-01-18 (F04) |
| INV-01.8 | Advisor; another dealership's technician; `abc`; enable an active technician | TC-01-20 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/database/migrations/2026_11_04_*` | 5 migrations (DB §B2), plus the `users.dealership_id` FK if F00 did not add it. |
| `api/app/Domain/MasterData/BayType.php`, `Level.php` | Enum + `label()`; `Level` value object (1–7, `qualifiesFor(Level $required)`). |
| `api/app/Models/{ServiceType,Bay,Technician,DealershipOpeningHour,Vehicle}.php` | New. |
| `api/app/Domain/MasterData/Services/TechnicianService(.php, Interface.php)` | `setActive(User $admin, Technician $t, bool $active, CarbonImmutable $now)` (D-01.11–01.13); bound in `DomainServiceProvider`. |
| `api/app/Domain/MasterData/Services/UpcomingAssignmentsReader.php`, `NoUpcomingAssignments.php` | `count(string $technicianId, CarbonImmutable $now)`, `preview(…, int $limit)`; F01 binds the empty one, F04 the real one. |
| `api/app/Domain/MasterData/Exceptions/{TechnicianNotFound,TechnicianHasBookings}.php` | 404, 409 (README §5). |
| `api/app/Domain/MasterData/Repositories/MasterDataRepository(.php, Interface.php)` | Reads per dealership; binding in `DomainServiceProvider`. |
| `api/app/Domain/MasterData/DealershipAccess.php` | Seam for INV-01.3: `resolveFor(User $user, string $id): Dealership` throws `DealershipNotFound`. |
| `api/app/Domain/MasterData/Exceptions/DealershipNotFound.php` | 404 `DEALERSHIP_NOT_FOUND`. |
| `api/bootstrap/app.php` or `AppServiceProvider` | `Route::bind('dealership', …)` calls `DealershipAccess`. |
| `api/app/Http/Controllers/Api/V1/{Dealership,ServiceType,Bay,Technician}Controller.php` | Thin. `TechnicianController::updateStatus` calls `TechnicianServiceInterface`. |
| `api/app/Http/Requests/UpdateTechnicianStatusRequest.php` | `active`: required boolean. |
| `api/app/Providers/AppServiceProvider.php` (`boot()`) | Gate `admin` (if F00 did not add it); explicit binding `{technician}` scoped to the bound `{dealership}`. |
| `api/app/Http/Resources/*Resource.php` | API §B2. |
| `api/routes/api.php` | 6 routes under `auth:sanctum`. |
| `api/database/seeders/MasterDataSeeder.php`, `VehicleSeeder.php` | D-01.4, D-01.5. |
| `api/tests/Support/World.php` | Fixture. |
| `web/src/staff/master-data/*` | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

Read seam for F03 (_illustrative_, serves FR-03.2 and D-01.10):

```php
interface MasterDataRepositoryInterface {
    public function serviceTypeFor(string $dealershipId, string $serviceTypeId): ?ServiceType;
    /** @return list<Technician> (id, name, level, active) */
    public function technicians(string $dealershipId): array;
    /** @return list<Bay> */
    public function bays(string $dealershipId): array;
    public function openingHours(string $dealershipId): OpeningHoursData; // weekday => [opens, closes]
}
```

```php
// illustrative — INV-01.7, D-01.13
interface TechnicianServiceInterface {
    public function setActive(User $admin, Technician $technician, bool $active, CarbonImmutable $now): Technician; // throws TechnicianHasBookings
}
interface UpcomingAssignmentsReader {
    public function count(string $technicianId, CarbonImmutable $now): int;
    /** @return list<array{id, reference, start, end, customerName}> */
    public function preview(string $technicianId, CarbonImmutable $now, int $limit = 20): array;
}
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `DealershipAccess` + route binding `{dealership}` | F05, F06, F12 (every `/dealerships/{dealership}/…` route) |
| `MasterDataRepository` | F03 (snapshot), F02 (services), F06 |
| `World` fixture | Every later feature |
| `UpcomingAssignmentsReader` | F04 binds the real reader |
| `Level::qualifiesFor` | F03 (`CandidateFinder`) |

FCs: FC-01.1 (edit master data in the UI) → models already have DB CHECKs, a later form only needs a FormRequest; FC-01.2 (public holidays) → F03 `OpeningHours` takes a list of closed dates; deferred. FC-01.3 (shifts and leave later) → F03 `BusyIntervalReader` can add unavailability without touching `CandidateFinder` (F03 ADR-03.3). FC-01.4 (swap technician at the same time) → F09 `reschedule` could take `avoidTechnicianId`; deferred.

## B7. Algorithms

Reads: no algorithm. Sort order: service types by `name`; bays by `code`; technicians by `name`; opening hours by `weekday`.

**`setActive(admin, technician, active, now)`**
1. `active = true`: `UPDATE technicians SET active = true` → 200.
2. `active = false`, one transaction: `SELECT … FROM technicians WHERE id = ? AND dealership_id = ? FOR UPDATE` → already inactive → 200, nothing changes → `UpcomingAssignmentsReader::count(id, now)` > 0 → rollback, 409 `TECHNICIAN_HAS_BOOKINGS` with `count` and `preview(…, 20)` → else `UPDATE … SET active = false` → commit → 200.

A bay's `supportedServiceTypeIds` = the `active` services of the same dealership whose `required_bay_type = bay.bay_type`.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| No session | 401 | `UNAUTHENTICATED` |
| Customer calls bays/technicians | 403 | `FORBIDDEN` |
| Dealership missing, not a UUID, or not the staff member's | 404 | `DEALERSHIP_NOT_FOUND` |
| `active` missing or not boolean | 400 | `VALIDATION_ERROR` |
| Not an admin changes technician status | 403 | `FORBIDDEN` |
| Technician missing, malformed, or of another dealership | 404 | `TECHNICIAN_NOT_FOUND` |
| Disable a technician with upcoming appointments | 409 | `TECHNICIAN_HAS_BOOKINGS` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-01.1 | The app has just run `migrate --seed` on an empty database | Admin calls `GET /dealerships/{id}/service-types` | All 3 service types, each with duration and required level | TC-01-01 |
| AC-01.2 | Admin of D1 is signed in | Calls the same API for D2 | 404 `DEALERSHIP_NOT_FOUND` | TC-01-02 |
| AC-01.3 (new) | Signed-in customer | `GET /dealerships/{id}/bays` | 403 `FORBIDDEN` | TC-01-05 |
| AC-01.4 (new) | A service with `active = false` | Customer and admin call service-types | The customer does not see it; the admin does, with `active: false` | TC-01-06 |
| AC-01.5 (new) | Staff of D1 | `GET /dealerships` | Only D1 | TC-01-07 |
| AC-01.6 (new) | Admin opens Master data | Screen loads | 4 tabs with counts; "Read only" note; no "Next holiday"; Technicians show a Level column and no Shift column | TC-01-11, TC-01-12 |
| AC-01.7 | Technician Chi has no upcoming appointment | Admin disables Chi | 200, `active: false`; F03 never offers Chi | TC-01-16 |
| AC-01.8 | Chi has a confirmed booking tomorrow | Admin disables Chi | 409 `TECHNICIAN_HAS_BOOKINGS`, `count 1`, the booking listed; Chi stays active | TC-01-17 |
| AC-01.9 (new) | A booking for Chi and Chi's disable run at the same moment | Both finish | Either the booking exists and Chi is active (409), or Chi is disabled and the booking used someone else or got 409; never a disabled Chi with an upcoming booking | TC-01-18 |
| AC-01.10 (new) | Advisor; D2's technician; a disabled technician | Advisor disables; admin of D1 disables D2's technician; admin enables | 403; 404 `TECHNICIAN_NOT_FOUND`; 200 `active: true` | TC-01-20 |

## B10. Implementation order

1. Ticket 01 (prefactor): empty `World` fixture + enums; no behaviour change.
2. Migrations + seed + full `World`.
3. Tracer bullet: route binding + service-types + Service types tab, then `/code-review`.
4. Remaining endpoints + tabs.
5. Technician disable/enable (endpoint + Technicians tab action) with `NoUpcomingAssignments`.
6. Acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `migrate:fresh --seed` runs twice without errors.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-01.1 | The route binding runs before `auth:sanctum` → no user when resolving | Read the user inside the closure; check middleware order (`SubstituteBindings` after auth) with TC-01-02 |
| R-01.2 | The seeder makes new UUIDs each run → ids change, tests and bookmarks break | INV-01.6 |
| R-01.3 | F01 ships before bookings exist, so its disable check counts nothing | `UpcomingAssignmentsReader` seam; F04 binds the real reader and adds the `FOR SHARE` lock (F04 INV-04.15); TC-01-17/18/19 run in F04 |
