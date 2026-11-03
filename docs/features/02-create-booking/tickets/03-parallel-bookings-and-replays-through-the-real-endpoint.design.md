# 03: Parallel bookings and replays through the real endpoint — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| 20 parallel `POST /appointments` for one slot (AC-02.21, AC-04.1) | AC-02.21, AC-04.1, INV-04.1 | `api/tests/Feature/Booking/Integrity/ParallelBookingTest.php:20-93` | 20 parallel PHP processes race for the same candidate pair; EXCLUDE constraint `resource_allocations_no_overlap` in PostgreSQL guarantees exactly 1 transaction wins and 19 catch `23P01` and return 409. | Inactive technicians and bays excluded by `AllocationSnapshotRepository::forDay` (active only). | Reused in F04 `ParallelBookingTest` for testing endpoint; this ticket tests through the real `/api/v1/appointments`. | follow |
| Idempotency replay through real endpoint (AC-04.3) | AC-04.3, INV-04.2 | `api/app/Http/Middleware/Idempotency.php:38-120` | Same `Idempotency-Key` and identical request body returns cached 201 response with `Idempotent-Replayed: true` without creating duplicate appointments or outbox events. | Expired keys pruned after 24h by scheduled artisan command. | Replayed on all idempotent endpoints (`POST /appointments`, `POST /me/vehicles`). | follow |

