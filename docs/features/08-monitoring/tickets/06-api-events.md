# 06: API events

**What to build:** Create `api_events` and record each error with its correlation id, never with personal data (INV-08.7).

**Blocked by:** [05-readiness-check](05-readiness-check.md)

**Status:** ready-for-agent

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-16

- [ ] TC-08-16 (INV-08.7; Integration): given A test route throws an exception whose message holds an email; when Call it; then `api_events.detail` has no email. Test: `ApiEventsTest::test_INV_08_7_detail_is_redacted`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
