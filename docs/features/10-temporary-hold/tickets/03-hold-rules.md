# 03: Hold rules

**What to build:** A held time is not free for other customers (AC-10.4); staff cannot hold (422 `HOLD_NOT_ALLOWED`, AC-10.11). The adversarial input table of API §B4 is covered.

**Blocked by:** [02-customer-holds-a-time-and-confirms-it](02-customer-holds-a-time-and-confirms-it.md)

**Status:** ready-for-agent

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-04, TC-10-15, TC-10-19

- [ ] TC-10-04 (AC-10.4; Integration): given Customer A holds 10:00 (An only); when Customer B `GET availability`; then 10:00 `available: false`; B's `POST` 10:00 → 409. Test: `HoldTest::test_AC_10_4_held_slot_is_not_free_for_others`.
- [ ] TC-10-15 (AC-10.11; Integration): given Advisor; when `POST /appointments` with `mode: HOLD`; then 422 `HOLD_NOT_ALLOWED`. Test: `HoldTest::test_AC_10_11_staff_cannot_hold`.
- [ ] TC-10-19 (API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `HoldAdversarialInputTest`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
