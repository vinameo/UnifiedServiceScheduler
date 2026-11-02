# 02: Free times for one day on New booking (tracer bullet)

**What to build:** `AvailabilityCalculator::forDay` and `GET /dealerships/{d}/availability?serviceTypeId=&date=` return every 15-minute start of the day with `available` (AC-06.1, AC-06.2). New booking shows them in `SlotGrid`: taken times struck through, still shown, not clickable (AC-06.13).

**Blocked by:** [01-prefactor-timegrid-takes-chip-state-from-props](01-prefactor-timegrid-takes-chip-state-from-props.md)

**Status:** ready-for-agent

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-01, TC-06-02, TC-06-14

- [ ] TC-06-01 (AC-06.1; Integration): given `onlyTechnicians(['An'])`, An busy 09:00–10:00 tomorrow; when `GET availability` OIL tomorrow; then 08:00 `true`; 08:15…09:45 `false` (`NO_TECHNICIAN_AVAILABLE`); 10:00 `true`. Test: `AvailabilityTest::test_AC_06_1_busy_technician_blocks_overlapping_starts`.
- [ ] TC-06-02 (AC-06.2; Integration): given Closes 17:00; when OIL tomorrow; then Last slot `start 16:00`; no 16:15. Test: `…::test_AC_06_2_last_start_is_close_minus_duration`.
- [ ] TC-06-14 (AC-06.13, WEB-06.3; Web component): given Fake API: 3 slots, the middle one `false`; when Render New booking, click the middle slot; then The middle slot is struck through, `aria-disabled`, not selectable; "2 free start times" shown. Test: `web/src/shared/booking/SlotGrid.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
- [ ] Run `/code-review` on the branch and fix findings before later tickets build on it
