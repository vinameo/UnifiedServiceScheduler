# F03 · Free slots and resource allocation · API spec (API)

---

# PART A · For readers

F03 has no HTTP endpoint (requirements: "no HTTP endpoint of its own"). Its "API" is the PHP classes F02, F06, F09 and F12 call. This file describes that PHP contract and how the "no slot" reasons show up in other features' HTTP errors.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. PHP contract (_illustrative_, serves INV-03.1–03.8)

```php
namespace App\Domain\Allocation;

final readonly class AllocationRequest {
    public function __construct(
        public string $dealershipId,
        public Skill $requiredSkill,
        public BayType $requiredBayType,
        public TimeRange $range,                 // UTC, [start, end)
        public ?string $preferTechnicianId = null,
        public ?string $preferBayId = null,
        public ?string $excludeAppointmentId = null,
        public int $maxPairs = 5,
    ) {}
}

final class CandidateFinder {
    public function __construct(private AllocationStrategy $strategy) {}
    public function find(AllocationRequest $request, ResourceSnapshot $snapshot): AllocationResult;
}

interface AllocationStrategy {
    /** @param list<TechnicianState> $free @return list<TechnicianState> */
    public function rankTechnicians(array $free, AllocationRequest $request, ResourceSnapshot $snapshot): array;
    /** @param list<BayState> $free @return list<BayState> */
    public function rankBays(array $free, AllocationRequest $request): array;
}

final readonly class AllocationResult {
    /** @param list<CandidatePair> $pairs */
    public function __construct(public array $pairs, public ?AllocationFailure $failure) {}
    public function found(): bool;
}
```

`ResourceSnapshot` holds `dealershipId`, `timezone`, `localDate`, `technicians` (id, name, active, skills, UTC shifts, busy), `bays` (id, code, bayType, active, busy). One day's snapshot serves every `find()` for that day.

## B2. Mapping to HTTP (done by F04)

| `AllocationFailure` | HTTP | `code` | `reason` member |
| --- | --- | --- | --- |
| `NO_QUALIFIED_TECHNICIAN` | 409 | `NO_AVAILABILITY` | `"NO_QUALIFIED_TECHNICIAN"` |
| `NO_TECHNICIAN_AVAILABLE` | 409 | `NO_AVAILABILITY` | `"NO_TECHNICIAN_AVAILABLE"` |
| `NO_BAY_AVAILABLE` | 409 | `NO_AVAILABILITY` | `"NO_BAY_AVAILABLE"` |
| 3 tries used up on 23P01 | 409 | `NO_AVAILABILITY` | `"SLOT_TAKEN"` (F04) |

## B3. Adversarial input (wrong calls from code)

| Input | Expected |
| --- | --- |
| `TimeRange` with `start >= end` | `InvalidArgumentException` |
| `maxPairs = 0` | `InvalidArgumentException` |
| A snapshot of another dealership than the request | `InvalidArgumentException` (never returns pairs of another dealership — INV-03.4) |
| A snapshot of another day than the local date of `range.start` | `InvalidArgumentException` |

## B4. Bounds

`maxPairs` ≤ 20. One snapshot per day; multi-day searches (F06 alternatives) load several snapshots, at most 8.
