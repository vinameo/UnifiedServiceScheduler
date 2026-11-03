# 14: Outbox writer in the same transaction — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| Outbox writer transaction guard (RQ INV-04.12, TC-04-25) | INV-04.12 | `api/app/Domain/Notifications/Services/OutboxWriter.php:append` | asserts DB::transactionLevel() > 0; throws LogicException if called outside active transaction | no outbox rows created outside transaction boundary | `1-requirement-spec.md:122` | follow |
| Outbox message channels fanout per event (RQ D-04.4, AC-04.20) | AC-04.20, D-04.4 | `api/app/Domain/Notifications/Services/OutboxWriter.php:channelsFor` | Confirmed/Cancelled/Rescheduled fan out to SMS, EMAIL, DMS with shared event_id | pending outbox rows committed atomically with business entity; rollback leaves 0 rows | `3-database-spec.md:169` | follow |
| Privacy invariant no personal data in payload (RQ INV-11.5, D-04.4) | INV-11.5 | `api/app/Domain/Booking/Services/AppointmentWriter.php` | payload contains entity IDs and ISO 8601 timestamps only; customer phone and email excluded | recipient contact details resolved only at transmission time in F11 | `3-database-spec.md:159` | follow |
