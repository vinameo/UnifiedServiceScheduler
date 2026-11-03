# 19: Acceptance — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| Full test suite coverage (6-test-case.md §B2) | TC-04-01…TC-04-42 | `api/tests/Feature/Booking/`, `web/src/shared/` | all test cases executed and verified against real PostgreSQL | temporary test artifacts cleaned up; truncation clears tables per run | `CLAUDE.md:162-171` | follow |
| Business analytics metrics validation (2-business-analytics-spec.md §B2) | M-04.1…M-04.5 | `api/` database queries | M-04.2 (active allocations count) = 0 rows; M-04.4 (overlaps) = 0 rows; M-04.5 (stale in-progress keys) = 0 rows | inactive allocations verified with `active = false` and `deactivated_at IS NOT NULL` | `2-business-analytics-spec.md:74-81` | follow |
| Feature self-review and quality gates (CLAUDE.md §Self-review) | SDD §5-10, OWASP Top 10 | `.claude/reviews/feature__04-booking-integrity.md` | all 19 tickets implemented; diff against develop reviewed for SOLID, bounds, permissions | no dangling testing routes in production environment | `CLAUDE.md:120-134` | follow |

