# 05: Alternatives on 409 NO_AVAILABILITY

**What to build:** Every `409 NO_AVAILABILITY` from `POST /appointments` carries `alternatives` (later today, then the next days), empty when there are none; an idempotent replay returns the saved alternatives (AC-06.3, AC-06.7, AC-06.8, D-06.5).

**Blocked by:** [04-alternativetimesfinder-near](04-alternativetimesfinder-near.md)

**Status:** ready-for-agent

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-03, TC-06-07, TC-06-08, TC-06-19

- [ ] TC-06-03 (AC-06.3, D-06.5; Integration): given 09:00 full (An is the only one); 10:00, 11:00 free; when `POST /appointments` 09:00; then 409 `NO_AVAILABILITY`; `alternatives[0].start` = 10:00. Test: `AlternativesOnConflictTest::test_AC_06_3_conflict_returns_alternatives`.
- [ ] TC-06-07 (AC-06.7; Integration): given Today only 15:00 is free (wanted 09:00); tomorrow free; when `POST` 09:00; then `alternatives` = [15:00 today, 08:00 tomorrow, 08:15 tomorrow]. Test: `AlternativesOnConflictTest::test_AC_06_7_same_day_first_then_next_days`.
- [ ] TC-06-08 (AC-06.8; Integration): given 8 days full (one person, busy all day); when `POST`; then `alternatives: []`. Test: `…::test_AC_06_8_no_alternatives_within_a_week`.
- [ ] TC-06-19 (F04 + F06; Integration): given `POST` 09:00 with K → 409 with suggestions; when Change data so suggestions would differ; resend K; then The same 409 and the old `alternatives`. Test: `AlternativesOnConflictTest::test_replayed_409_keeps_saved_alternatives`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
