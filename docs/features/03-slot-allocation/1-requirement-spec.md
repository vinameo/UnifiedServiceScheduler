# F03 · Free slots and resource allocation · Requirement spec (RQ)

Branch: `feature/03-slot-allocation` · Priority: Must · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** An appointment needs, at the same time, a technician with a high enough level and a bay of the right type, both free for the **whole** service. This is the shared brain: booking (F02), free times (F06), rescheduling (F09) and reassignment after an overrun (F12) all ask it.

**The problem.** Without it, each feature would search its own way, with different results and hard tests.

**What it does.** For one time range and one service type, it returns (technician, bay) pairs ranked best first, or says clearly why there are none: nobody has a high enough level, everyone qualified is busy, or no bay is free.

**Who uses it.** Only the system. No screen and no API of its own. Plain PHP, testable without a web server or a database.

**Inputs.** Dealership, required level, required bay type, the range `[start, end)`, and optionally a technician/bay to prefer and an appointment to ignore (when rescheduling that same appointment).

**Outcome.** Up to 5 candidate pairs, or one reason.

**What it does not do.** No DB writes. No opening-hours check (F02 does it, using F03's `OpeningHours` helper). No holds. No splitting one service between technicians.

## A2. Key rules in plain words

- Two ranges `[s1, e1)` and `[s2, e2)` overlap when `s1 < e2` and `s2 < e1`. 09:00–10:00 and 10:00–11:00 do **not** overlap.
- A technician qualifies when: same dealership, active, level at or above the service's level, and no booking overlaps the range. There are no shifts: an active technician works whenever the dealership is open (README D-G.16); the caller has already checked opening hours.
- A bay qualifies when: same dealership, active, right type, and no booking overlaps.
- Technician ranking: lowest level first (keep high-level people free for hard jobs), then fewest booked minutes that day, then by id. Bays: by bay code, then id. The same input always gives the same result.
- An expired hold counts as free.

## A3. Screens

None. The copy for the 3 reasons (shown on F02 screens) is in [5-web-spec.md](5-web-spec.md).

## A4. Out of scope

Global optimisation (planning a whole day), splitting work, VIP priority, lunch breaks.

## A5. Success criteria

- The 8 requirement ACs run as unit tests, without a database.
- Finding free times for a whole day (36 slots) with 30 technicians and 20 bays takes under 50 ms in memory (part of NFR-02).

## A6. Decisions

| ID | Decision |
| --- | --- |
| D-03.1 | F03 is built before F04, so `resource_allocations` does not exist yet. F03 delivers the pure part (`App\Domain\Allocation`) and a snapshot loader that reads master data; busy intervals come through the `BusyIntervalReader` interface. F03 binds the empty `NoBusyIntervals`; F04 replaces it with a reader over `resource_allocations`. Later sources of unavailability (shifts, leave, bay outages — out of scope today, README D-G.16) would plug in through the same interface. |
| D-03.2 | Return at most **5** pairs (SDD §8 "top 5"); F04 tries at most 3 (FR-04.3). |
| D-03.3 | Pair order: by the sum of technician rank and bay rank (0-based), ties by technician rank. The best technician + best bay comes first, and the first 5 pairs spread over several technicians and bays, so dropping every pair of one taken resource (F04 D-04.16) still leaves pairs to try. Example with 2 technicians and 5 bays: (T1,B1), (T1,B2), (T2,B1), (T1,B3), (T2,B2). |
| D-03.4 | Bays are ranked by `code`, then `id` (the requirement says "by id"; ids are UUIDs and meaningless to readers, while `code` is just as deterministic and easier to test). |
| D-03.5 | Reason order: no active technician with `level ≥ required level` → `NO_QUALIFIED_TECHNICIAN`; qualified ones exist but all are busy → `NO_TECHNICIAN_AVAILABLE`; someone is free but no bay → `NO_BAY_AVAILABLE` (also when the dealership has no bay of that type). Both technicians and bays busy → `NO_TECHNICIAN_AVAILABLE`. |
| D-03.6 | "Booked minutes that day" = the sum of the technician's busy intervals within the local day of the searched range, ignoring the excluded appointment. |
| D-03.7 | A `HELD` appointment with `expires_at <= now` does not count as busy in the snapshot (FR-10.5). Deactivating its allocations in the DB is F10's job. |
| D-03.9 | No shifts (README D-G.16, FR-03.1): F03 does not model working hours; the range is already inside opening hours (F02 `BookingTimeRules`). |
| D-03.10 | Qualification is `technician.level >= request.requiredLevel` (`Level::qualifiesFor`, F01). Ranking prefers the **lowest** qualifying level (FR-03.4). |
| D-03.8 | Preferences: `preferTechnicianId` (F09 keeps the same person) and `preferBayId` (F12 keeps the bay) move to the front **if** they qualify; otherwise they are ignored, without error. |

## A7. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-03.a | Should a lower-level technician who is much busier still come before a higher-level one who is free all day? | Yes: level first, then booked minutes (D-03.10). Revisit with M-03.3. |
| Q-03.b | Balance load across bays? | No; by bay code. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00, F01 are merged: `MasterDataRepository`, enum `BayType`, value object `Level`, `World` fixture. No `api/app/Domain/Allocation/` yet. No `resource_allocations` (F04).

## B2. Scope

| ID | In scope |
| --- | --- |
| S-03.1 | Value objects `TimeRange`, `AllocationRequest`, `ResourceSnapshot`, `TechnicianState`, `BayState`, `BusyInterval`, `CandidatePair`, `AllocationResult`, enum `AllocationFailure`. |
| S-03.2 | `CandidateFinder`, interface `AllocationStrategy`, default `LowestLevelFirstStrategy`. |
| S-03.3 | `OpeningHours` (pure): the UTC opening window of a local day; checks that a `TimeRange` fits in it. |
| S-03.4 | `AllocationSnapshotRepository::forDay()` + interface `BusyIntervalReader` + `NoBusyIntervals`. |
| S-03.5 | `Tests\Support\Allocation\SnapshotBuilder`. |

## B3. Invariants

| ID | Invariant |
| --- | --- |
| INV-03.1 | `App\Domain\Allocation\**` imports no `Illuminate\Http`, `Illuminate\Database`, `Illuminate\Support\Facades`, `App\Models`. Only PHP, `Carbon\CarbonImmutable` and the `App\Domain\MasterData` enums. |
| INV-03.2 | Every range is half-open `[start, end)`, `start < end`, stored in UTC. Overlap if and only if `s1 < e2 && s2 < e1` (BR-03.1). |
| INV-03.3 | Same `AllocationRequest` + same `ResourceSnapshot` → same result, same order, every time (FR-03.4). No randomness, no dependence on input array order (the snapshot is sorted before ranking). |
| INV-03.4 | A candidate pair holds only a technician and a bay of the request's **dealership**, active, with `level ≥ required level` (technician) or the right type (bay), and free for the **whole** range (FR-03.2, 03.3, D-03.10). |
| INV-03.5 | The local day window (`[00:00, 24:00)` of the searched date) and opening hours convert from local time to UTC with `dealerships.timezone` for that **date** (Carbon, IANA name), never the server or browser zone; a DST day is 23 or 25 hours long. |
| INV-03.6 | Busy intervals with `appointmentId == excludeAppointmentId` are ignored for both technicians and bays. |
| INV-03.7 | An empty result always has exactly one reason (D-03.5); a result with pairs has none. |
| INV-03.8 | `AllocationStrategy` is the only place with ranking rules (FR-03.5); `CandidateFinder` only filters, then calls the strategy. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Overlap (INV-03.2) | Adjacent before/after; inside; enclosing; one minute of overlap | TC-03-01, TC-03-02, TC-03-03, TC-03-11 |
| Level (D-03.10) | Higher level does a lower-level service; lower level cannot do a higher one; equal level qualifies; only lower-level technicians free | TC-03-06, TC-03-14, TC-03-15 |
| Day window (INV-03.5) | DST day in the fixture and in the repository | TC-03-26, TC-03-29 |
| Reasons (INV-03.7) | Nobody has a high enough level; qualified but busy; free technician but no bay; both busy | TC-03-04, TC-03-05, TC-03-08, TC-03-16 |
| Active | Only qualified technician inactive; bay inactive | TC-03-12, TC-03-13 |
| Pair spread (D-03.3) | 2 technicians, 5 bays | TC-03-28 |
| Ignore the appointment itself (INV-03.6) | Reschedule inside its own old range | TC-03-17 |
| Expired hold (D-03.7) | An expired HELD blocks the range | TC-04-27 (the SQL reader lives in F04) |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Allocation/TimeRange.php` | `of(start, end)`, `overlaps()`, `contains()`, `minutes()`. |
| `api/app/Domain/Allocation/{AllocationRequest,ResourceSnapshot,TechnicianState,BayState,BusyInterval,CandidatePair,AllocationResult}.php` | `final readonly` value objects. |
| `api/app/Domain/Allocation/AllocationFailure.php` | Enum `NO_QUALIFIED_TECHNICIAN`, `NO_TECHNICIAN_AVAILABLE`, `NO_BAY_AVAILABLE`. |
| `api/app/Domain/Allocation/CandidateFinder.php` | B7. |
| `api/app/Domain/Allocation/AllocationStrategy.php`, `LowestLevelFirstStrategy.php` | B7 steps 4–5. Bound in `DomainServiceProvider`. |
| `api/app/Domain/Allocation/OpeningHours.php` | `fromWeekdayMap(array $map, string $timezone)`, `windowFor(string $localDate): ?TimeRange`, `fits(TimeRange): bool`, `latestStartFor(localDate, minutes): ?CarbonImmutable`. |
| `api/app/Domain/Allocation/Contracts/BusyIntervalReader.php` | `forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): list<BusyInterval>` (a BusyInterval has `resourceType`, `resourceId`, `range`, `appointmentId?`). |
| `api/app/Domain/Booking/Repositories/AllocationSnapshotRepository(.php, Interface.php)` | `forDay(string $dealershipId, string $localDate, CarbonImmutable $now): ResourceSnapshot` — reads master data through `MasterDataRepository`, busy intervals through `BusyIntervalReader`. |
| `api/app/Domain/Booking/Repositories/NoBusyIntervals.php` | Empty reader (D-03.1). |
| `api/tests/Support/Allocation/SnapshotBuilder.php` | Fixture. |

## B5. Contracts

The PHP contract is in [4-api-spec.md](4-api-spec.md) (F03 has no HTTP). No new tables ([3-database-spec.md](3-database-spec.md) lists the read queries).

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `CandidateFinder` + `AllocationStrategy` | F02/F04 (create), F06 (free times), F09 (reschedule), F12 (reassign) |
| `OpeningHours` | F02 (`OUTSIDE_OPENING_HOURS`), F06 (time grid), F09 |
| `AllocationSnapshotRepository::forDay` | As above; F06 uses **one** snapshot for a whole day (FR-06.4) |
| `BusyIntervalReader` | F04 (allocations); later unavailability sources (FC-03.4) |

FCs from BA: FC-03.1 (another ranking rule) → `AllocationStrategy`; FC-03.2 (per-day lock, SDD phase 2) → does not touch F03; FC-03.3 (lunch breaks) → `OpeningHours` returns several intervals; FC-03.4 (shifts, leave, bay outages) → extra `BusyIntervalReader` sources.

## B7. Algorithm (`CandidateFinder::find(request, snapshot)`)

1. Filter **qualified** technicians: active and `level >= requiredLevel`. Empty → `NO_QUALIFIED_TECHNICIAN`.
2. Filter **free** ones: no busy interval (except INV-03.6) overlapping `range`. Empty → `NO_TECHNICIAN_AVAILABLE`.
3. Filter bays: active, `bayType == requiredBayType`, no busy interval overlapping. Empty → `NO_BAY_AVAILABLE`.
4. `strategy->rankTechnicians(…)`: level ascending → booked minutes that day ascending (D-03.6) → `id` ascending (string compare). Then move `preferTechnicianId` to the front if present.
5. `strategy->rankBays(…)`: `code` ascending → `id`. Then move `preferBayId` to the front if present.
6. Pair them per D-03.3 and take the first `maxPairs` (default 5).

`OpeningHours::fits(range)`: the opening window of the local day containing `range.start` exists and contains the whole `range` (BR-02.2: one day).

## B8. Error table

F03 throws no business errors; it returns an `AllocationResult`. Programming errors (range with `start >= end`, `maxPairs < 1`) throw `InvalidArgumentException` (500 if they escape). Mapping reasons to HTTP is F04's job (`409 NO_AVAILABILITY` with `reason`).

## B9. Acceptance criteria

Every AC runs as a unit test with `SnapshotBuilder`, unless noted.

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-03.1 | An is booked 09:00–10:00 | Search 10:00–11:00 | An is a candidate | TC-03-01 |
| AC-03.2 | An is booked 09:00–10:00 | Search 09:30–10:30 | An is removed | TC-03-02 |
| AC-03.3 | An is booked 08:00–12:00 | Search 09:00–10:00 | An is removed | TC-03-03 |
| AC-03.4 | A bay is free, every qualified technician is busy | Search that range | No pairs, reason `NO_TECHNICIAN_AVAILABLE` | TC-03-04 |
| AC-03.5 | A technician is free, every matching bay is busy | Search | `NO_BAY_AVAILABLE` | TC-03-05 |
| AC-03.6 | An (level 2), Dũng (level 3) and Em (level 6) are free | Search the 20,000 km service (level 3) | Dũng and Em are candidates, An is not | TC-03-06 |
| AC-03.7 | An (level 2) and Em (level 6) both qualify for an oil change (level 1) | Search | An is ranked first (lowest level first, D-03.10) | TC-03-07 |
| AC-03.8 | No active technician has level ≥ 5 | Search for the EV battery check (level 5) | `NO_QUALIFIED_TECHNICIAN` | TC-03-08 |
| AC-03.9 (new) | Two technicians with the same level and different booked minutes; then equal minutes | Search twice, reversing the input order | Fewer minutes first; equal minutes by id; both runs give the same result | TC-03-09, TC-03-10 |
| AC-03.10 (new) | The only technician with a high enough level is inactive | Search | `NO_QUALIFIED_TECHNICIAN` | TC-03-12 |
| AC-03.11 (new) | The only bay of the type is inactive | Search | `NO_BAY_AVAILABLE` | TC-03-13 |
| AC-03.12 (new) | Appointment X holds An 10:00–11:00 | Search 10:30–11:30 with `excludeAppointmentId = X`, `preferTechnicianId = An` | An is first | TC-03-17 |
| AC-03.13 (new) | Snapshot from the DB for a dealership in `Asia/Ho_Chi_Minh` | `forDay('2026-11-02')` | Busy intervals are read for the UTC window `2026-11-01T17:00Z`–`2026-11-02T17:00Z`; technicians carry their level | TC-03-19 |
| AC-03.14 (new) | Opening hours Mon 08:00–17:00 | `fits` 16:00–17:00; 16:30–17:30; Sunday 09:00–10:00 | true; false; false | TC-03-20 |

## B10. Implementation order

1. Ticket 01 (prefactor): `TimeRange` + `SnapshotBuilder` + overlap tests.
2. Tracer bullet: `CandidateFinder` with AC-03.1–03.3, then `/code-review`.
3. Reasons + ranking + strategy (AC-03.4–03.12).
4. `OpeningHours` + snapshot repository (AC-03.13, 03.14).
5. Acceptance, performance (TC-03-21).

## B11. Definition of Done

Every TC green; README §8 green; TC-03-22 (architecture) green.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-03.1 | Comparing Carbon values in different zones gives wrong results | Every `TimeRange` normalises to UTC in its constructor |
| R-03.2 | Stale snapshot (read, then write) → the pair was taken by someone else | Accepted: F04's EXCLUDE is the final guard, the next pair is tried |
| R-03.3 | Comparing UUID ids with PHP `<` on objects | Compare strings with `strcmp` |
