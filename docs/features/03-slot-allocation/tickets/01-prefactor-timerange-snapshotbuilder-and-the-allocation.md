# 01: Prefactor: TimeRange, SnapshotBuilder and the Allocation purity scan

**What to build:** Add the `TimeRange` value object (half-open overlap, BR-03.1) and the in-memory `Tests\Support\Allocation\SnapshotBuilder` (README §7). A scan test keeps `app/Domain/Allocation` free of HTTP and Eloquent (INV-03.1).

**Blocked by:** F01 [11-acceptance](../../01-master-data/tickets/11-acceptance.md) (the whole F01 branch merged to `develop`).

**Status:** done

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-11, TC-03-22

- [x] TC-03-11 (INV-03.2, BR-03.1; Unit): given Range A = 10:00–11:00; when `overlaps` with: 09:00–10:00, 11:00–12:00, 10:15–10:45, 09:00–12:00, 10:59–11:30, 10:00–11:00; then false, false, true, true, true, true. Test: `TimeRangeTest::test_INV_03_2_half_open_overlap` (data provider).
- [x] TC-03-22 (INV-03.1; Unit): when Scan `app/Domain/Allocation/**/*.php`; then No `use Illuminate\Http`, `Illuminate\Database`, `Illuminate\Support\Facades`, `App\Models`. Test: `Unit/Architecture/AllocationIsPurePhpTest`.
- [x] No change users can see; existing tests stay green unchanged
- [x] README §8 checks green; no existing test skipped, deleted or weakened
