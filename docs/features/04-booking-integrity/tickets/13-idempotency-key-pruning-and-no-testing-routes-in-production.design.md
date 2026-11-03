# 13: Idempotency key pruning and no testing routes in production — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| Daily idempotency prune command (RQ D-04.12, AC-04.19) | AC-04.19 | `api/app/Console/Commands/PruneIdempotencyKeys.php` | deletes idempotency_keys where expires_at <= now; runs daily via console schedule | expired keys physically removed from database | `CLAUDE.md:164` | follow |
| Testing routes restricted to testing environment (RQ INV-04.14, AC-04.23) | AC-04.23, INV-04.14 | `api/app/Providers/TestingRoutesServiceProvider.php:16,32` | checks app()->environment('testing'); if false, route prefix api/v1/_testing and testing:request command are not registered | zero testing routes or commands exposed in non-testing environments | `4-api-spec.md:100` | follow |
