# 06: AppointmentWriter::create through a testing route (tracer bullet) — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| AppointmentWriter::create transaction (RQ §B7, CLAUDE.md core rule 1) | AC-04.1, INV-04.2 | `CLAUDE.md:120-141`, `docs/features/04-booking-integrity/1-requirement-spec.md:202-212` | atomic insert of appointment + 3 allocations in single DB::transaction; 23P01 triggers retry or failure | locks technician FOR SHARE; inactive technician treated as unavailable | `CLAUDE.md` Core rule 1 | follow |
| Reference format APT-YYYYMMDD-NNNN (RQ D-04.6) | INV-04.6 | `docs/features/04-booking-integrity/1-requirement-spec.md:56` | sequence nextval provides unique monotonic counter; local date derived from dealership timezone | dealerships table queried for timezone | `4-api-spec.md:46` | follow |
| 23P01 exclusion violation error mapping (RQ D-04.17) | AC-04.1, AC-04.2 | `docs/features/04-booking-integrity/1-requirement-spec.md:67` | reads resource_type from PostgreSQL error message detail; deadlocks on 40P01 retried with jitter | only active allocations are checked by EXCLUDE constraint WHERE (active); inactive rows ignored | `CLAUDE.md:135-139` | follow |
| Testing route POST /api/v1/_testing/appointments (API §B5) | AC-04.1, AC-04.2, INV-04.14 | `docs/features/04-booking-integrity/4-api-spec.md:104` | dispatches through CandidateFinder and AppointmentWriter; returns 201 with ETag and Location | route registered only in testing environment | `4-api-spec.md:104` | follow |

