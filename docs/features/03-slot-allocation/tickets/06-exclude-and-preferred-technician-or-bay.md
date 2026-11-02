# 06: Exclude and preferred technician or bay

**What to build:** The search can exclude one appointment's own busy intervals and put a preferred technician or bay first when they qualify (AC-03.12, INV-03.6, D-03.8).

**Blocked by:** [05-candidate-ranking](05-candidate-ranking.md)

**Status:** ready-for-agent

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-17, TC-03-18

- [ ] TC-03-17 (AC-03.12, INV-03.6, D-03.8; Unit): given Appointment X: An + K2 10:00–11:00; Dũng free with fewer minutes than An; when Search 10:30–11:30, `exclude = X`, `preferTechnicianId = An`, `preferBayId = K2`; then First pair (An, K2). Test: `…::test_AC_03_12_reschedule_inside_own_slot_keeps_technician`.
- [ ] TC-03-18 (D-03.8; Unit): given `preferTechnicianId` = Phát (inactive); when Search; then Same result as without `prefer`, no error. Test: `…::test_D_03_8_unqualified_preference_is_ignored`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
