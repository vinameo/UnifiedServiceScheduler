# F01 · Master data · Requirement spec (RQ)

Branch: `feature/01-master-data` · Priority: Must · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** The system can only allocate correctly if it knows what the dealership has: which service needs which skill and bay type, which bays are usable, who works which shift, and when the dealership is open.

**The problem.** None of these tables exist yet.

**What it does.**
- Creates tables for opening hours, service types, bays, technicians (skills, shifts) and vehicles.
- Loads demo data for "Saigon Auto · District 7" (3 service types, 4 bays, 5 technicians, 3 vehicles).
- Read-only APIs for staff (their own dealership) and for customers (dealerships and service types).
- A read-only "Master data" screen for admins, with 4 tabs.

**Who uses it, where.** Admin: staff web → Administration → Master data. Other staff and customers use it through the API (the booking screens read from it).

**Inputs.** Seed files. No edit forms in this version.

**Outcome.** The data F03 allocates from and the booking screens show.

**What it does not do.** Screens to add or change master data, public holidays, lunch breaks.

## A2. Usage steps

1. Admin signs in → Master data.
2. Views the tabs Service types (default), Bays, Technicians, Opening hours. Each tab shows a count.
3. To change data: edit the seed file and run the seeder again (the screen says so).

## A3. Screens and deviations from the drafts

Draft: `Admin/screenshots/09-master-data.png`. Deviations:

| Draft | Spec | Reason |
| --- | --- | --- |
| "Next holiday · 01/01/2027" box | Removed | Public holidays are out of scope (requirements) |
| "Version 1 loads this data through migrations" | "This version loads this data from seed files" | D-01.1 |
| Menu item visible to an advisor | Only admins see Master data | FR / README §4 |

## A4. Key rules in plain words

- Each service type needs exactly **one** skill and **one** bay type. Duration must be > 0.
- A technician qualifies when their skills include the skill the service needs.
- Opening hours and shifts are in the dealership's local time.
- Licence plates and VINs are unique across vehicles.
- Staff read only their own dealership; asking for another returns "Dealership not found".

## A5. Out of scope

Editing master data in the UI; several opening intervals per day; public holidays; technicians working at several dealerships.

## A6. Success criteria

- After `migrate --seed` on an empty database, the API returns 3 service types, 4 bays, 5 technicians.
- F03 runs on this data alone (no hand-made data needed).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-01.1 | Demo data is loaded by **seeders** (safe to rerun), not by a migration as FR-01.1 says. Migrations hold schema only. `docker compose` runs the seeders when `RUN_SEEDERS=true` (existing entrypoint). |
| D-01.2 | Service types belong to a dealership (`service_types.dealership_id`), per NFR-03 and the path `/dealerships/{id}/service-types`. |
| D-01.3 | Skill codes: `BASIC`, `GENERAL_MAINTENANCE`, `EV_HIGH_VOLTAGE` (labels "Basic", "General maint.", "High voltage"). Bay type codes: `QUICK`, `STANDARD`, `EV` (labels "Quick service", "Standard service", "EV"). PHP backed enums + DB CHECKs. |
| D-01.4 | Seed data follows the drafts (DB §B3): 3 service types, 4 bays, 5 technicians (Huỳnh Tấn Phát not taking bookings), opening hours Mon–Fri 08:00–17:00, Sat 08:00–12:00, Sun closed. More than the FR-01.1 minimum. |
| D-01.5 | The `vehicles` table is created in F01 (master data); the add-vehicle API is in F02. Seed 3 vehicles (DB §B3). |
| D-01.6 | Licence plate: store the display string (trimmed, upper case) and a unique `plate_key` (letters + digits only, upper case). `51H-123.45` and `51h 12345` are the same vehicle. VIN: optional, 17 characters `[A-HJ-NPR-Z0-9]`, upper case, unique when present. |
| D-01.7 | The 15-minute slot step is an app constant (`BookingTimeRules::STEP_MINUTES`, F02), returned by the dealership API as `slotStepMinutes` for display; not stored in the DB. |
| D-01.8 | Customers can read the dealership list and the **active** service types of any dealership; they cannot read bays or technicians. |
| D-01.9 | One opening interval per day (unique on `dealership_id, weekday`); a day without a row is closed. |
| D-01.10 | A technician can have several shifts a day. For F03, one shift must cover the whole `[start, end)` (FR-03.1); two back-to-back shifts do not add up. |

## A8. Open questions (with defaults)

| ID | Question | Default |
| --- | --- | --- |
| Q-01.a | Do advisors need the Master data screen? | No; the API still serves them. |
| Q-01.b | Do service types need an `active` flag? | Yes, to hide a service without deleting history. |

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
| S-01.1 | Migrations: `dealership_opening_hours`, `service_types`, `bays`, `technicians`, `technician_skills`, `technician_shifts`, `vehicles` (DB §B1). |
| S-01.2 | Enums `Skill`, `BayType`; models + read repository. |
| S-01.3 | Route binding `{dealership}` with the access check (INV-01.3). |
| S-01.4 | 5 read endpoints (API §B1). |
| S-01.5 | `MasterDataSeeder`, `VehicleSeeder`, safe to rerun. |
| S-01.6 | Fixture `Tests\Support\World` (README §7). |
| S-01.7 | Master data screen (WEB). |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-01.1 | `service_types.duration_minutes > 0` and a multiple of 15; `required_skill` and `required_bay_type` are enum values. | DB CHECKs; the seeder writes only through models. |
| INV-01.2 | Each dealership has at most one opening interval per weekday, with `closes_at > opens_at`. Shifts have `ends_at > starts_at`. | CHECK + unique. |
| INV-01.3 | Staff read only their own dealership: any other `{dealership}` (missing, not a UUID, or another dealership's) → 404 `DEALERSHIP_NOT_FOUND`, all identical. Customers: an existing dealership → allowed; missing → 404. | The route binding reads `auth()->user()->dealership_id` of the current request. |
| INV-01.4 | Customers cannot read bays or technicians (403). Customers see only `active` service types. | Gate `staff` on 2 routes; the query filters `active` when the user is a customer. |
| INV-01.5 | `plate_key` is unique; `vin` is unique when not NULL. | Unique index; partial unique for VIN. |
| INV-01.6 | Rerunning the seeders creates no duplicates and changes no existing id. | `updateOrCreate` on natural keys (code, `plate_key`, name + dealership for technicians). |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| INV-01.3 | Another dealership; missing id; non-UUID id; customer asks any dealership | TC-01-02, TC-01-03, TC-01-04 |
| INV-01.4 | Customer calls bays/technicians; inactive service | TC-01-05, TC-01-06 |
| INV-01.5 | Same plate spelled differently; two vehicles with NULL VIN | TC-01-08 |
| INV-01.6 | Seeding twice | TC-01-09 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/database/migrations/2026_11_04_*` | 7 migrations (DB §B2), plus the `users.dealership_id` FK if F00 did not add it. |
| `api/app/Domain/MasterData/Skill.php`, `BayType.php` | Enums + `label()`. |
| `api/app/Models/{ServiceType,Bay,Technician,TechnicianShift,DealershipOpeningHour,Vehicle}.php` | New. |
| `api/app/Domain/MasterData/Repositories/MasterDataRepository(.php, Interface.php)` | Reads per dealership; binding in `DomainServiceProvider`. |
| `api/app/Domain/MasterData/DealershipAccess.php` | Seam for INV-01.3: `resolveFor(User $user, string $id): Dealership` throws `DealershipNotFound`. |
| `api/app/Domain/MasterData/Exceptions/DealershipNotFound.php` | 404 `DEALERSHIP_NOT_FOUND`. |
| `api/bootstrap/app.php` or `AppServiceProvider` | `Route::bind('dealership', …)` calls `DealershipAccess`. |
| `api/app/Http/Controllers/Api/V1/{Dealership,ServiceType,Bay,Technician}Controller.php` | Thin. |
| `api/app/Http/Resources/*Resource.php` | API §B2. |
| `api/routes/api.php` | 5 routes under `auth:sanctum`. |
| `api/database/seeders/MasterDataSeeder.php`, `VehicleSeeder.php` | D-01.4, D-01.5. |
| `api/tests/Support/World.php` | Fixture. |
| `web/src/staff/master-data/*` | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

Read seam for F03 (_illustrative_, serves FR-03.1/03.2):

```php
interface MasterDataRepositoryInterface {
    public function serviceTypeFor(string $dealershipId, string $serviceTypeId): ?ServiceType;
    /** @return list<Technician> with skills + shifts loaded */
    public function technicians(string $dealershipId): array;
    /** @return list<Bay> */
    public function bays(string $dealershipId): array;
    public function openingHours(string $dealershipId): OpeningHoursData; // weekday => [opens, closes]
}
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `DealershipAccess` + route binding `{dealership}` | F05, F06, F12 (every `/dealerships/{dealership}/…` route) |
| `MasterDataRepository` | F03 (snapshot), F02 (services), F06 |
| `World` fixture | Every later feature |

FCs: FC-01.1 (edit master data in the UI) → models already have DB CHECKs, a later form only needs a FormRequest; FC-01.2 (public holidays) → F03 `OpeningHours` takes a list of closed dates; deferred.

## B7. Algorithms

No algorithm; read and return. Sort order: service types by `name`; bays by `code`; technicians by `name`; opening hours by `weekday`.

A bay's `supportedServiceTypeIds` = the `active` services of the same dealership whose `required_bay_type = bay.bay_type`.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| No session | 401 | `UNAUTHENTICATED` |
| Customer calls bays/technicians | 403 | `FORBIDDEN` |
| Dealership missing, not a UUID, or not the staff member's | 404 | `DEALERSHIP_NOT_FOUND` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-01.1 | The app has just run `migrate --seed` on an empty database | Admin calls `GET /dealerships/{id}/service-types` | All 3 service types, each with duration and required skill | TC-01-01 |
| AC-01.2 | Admin of D1 is signed in | Calls the same API for D2 | 404 `DEALERSHIP_NOT_FOUND` | TC-01-02 |
| AC-01.3 (new) | Signed-in customer | `GET /dealerships/{id}/bays` | 403 `FORBIDDEN` | TC-01-05 |
| AC-01.4 (new) | A service with `active = false` | Customer and admin call service-types | The customer does not see it; the admin does, with `active: false` | TC-01-06 |
| AC-01.5 (new) | Staff of D1 | `GET /dealerships` | Only D1 | TC-01-07 |
| AC-01.6 (new) | Admin opens Master data | Screen loads | 4 tabs with counts; "Read only" note; no "Next holiday" | TC-01-11, TC-01-12 |

## B10. Implementation order

1. Ticket 01 (prefactor): empty `World` fixture + enums; no behaviour change.
2. Migrations + seed + full `World`.
3. Tracer bullet: route binding + service-types + Service types tab, then `/code-review`.
4. Remaining endpoints + tabs.
5. Acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `migrate:fresh --seed` runs twice without errors.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-01.1 | The route binding runs before `auth:sanctum` → no user when resolving | Read the user inside the closure; check middleware order (`SubstituteBindings` after auth) with TC-01-02 |
| R-01.2 | The seeder makes new UUIDs each run → ids change, tests and bookmarks break | INV-01.6 |
