# 07: Extend displaces the next booking

**What to build:** When the extension clashes with the next booking, that booking is reassigned to another free technician or bay, or flagged for contact (AC-12.4, D-12.9).

**Blocked by:** [06-extend-an-appointment](06-extend-an-appointment.md)

**Status:** done

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-04, TC-12-09

- [x] TC-12-04 (AC-12.4; Integration): given X IN_PROGRESS K1 09:00–10:00 (now 10:05); Y CONFIRMED K1 10:00–11:00 (Dũng); K2 free 10:00–11:30; when Extend X by 30; then 200; X ends 10:30 on K1; Y `REASSIGNED` to K2 same time; `affected` lists Y. Test: `ExtendTest::test_AC_12_4_extend_moves_the_next_booking`.
- [x] TC-12-09 (AC-12.4, D-12.9; Integration): given As TC-12-04 but no other QUICK bay free; when Extend X by 30; then 200; Y still `CONFIRMED`, `contact_required_reason OVERRUN`, 0 active allocations; X holds K1 to 10:30; M-04.2 (with exception) 0 rows. Test: `…::test_AC_12_4_unplaceable_neighbour_is_displaced_not_cancelled`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
