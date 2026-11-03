# 15: AppointmentWriter::moveTo — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| moveTo time change transaction ordering (RQ INV-04.7) | INV-04.7 | `api/app/Domain/Booking/Services/AppointmentWriter.php:moveTo` | versioned update locks row -> old allocations deactivated -> new allocations inserted -> outbox appended -> commit | deactivating old allocations first prevents self-clash when shifting overlapping range | `CLAUDE.md:144-152` | follow |
| moveTo 23P01 retry preserving expected version (RQ INV-04.7, TC-04-32) | INV-04.7, AC-04.10 | `api/app/Domain/Booking/Services/AppointmentWriter.php:moveTo` | on 23P01 rollback, DB version is restored to expectedVersion; next pair retries with same expectedVersion | rolled back transaction preserves unincremented version; no false 412 on subsequent pair | `1-requirement-spec.md:209` | follow |
| moveTo clash mapping and pair filtering (RQ D-04.16, D-04.17) | D-04.16, D-04.17 | `api/app/Domain/Booking/Services/AppointmentWriter.php:moveTo` | vehicle clash throws VehicleAlreadyBooked; technician or bay clash drops resource pairs and retries | inactive/past allocations ignored; active allocations enforced by EXCLUDE constraint | `CLAUDE.md:135` | follow |

