# 01: Prefactor: BookingTimeRules and web time helpers

**What to build:** Split `BookingTimeRules` (15-minute step, past, 90 days, opening hours) out of F03 `OpeningHours`, and add the web seams `formatInZone`, `maskPhone`, `localSlotsForDay` and `AppointmentStatusBadge` (S-02.8). Nothing users can see changes.

**Blocked by:** F04 [19-acceptance](../../04-booking-integrity/tickets/19-acceptance.md) (the whole F04 branch merged to `develop`).

**Status:** done

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-29

- [x] TC-02-29 (WEB-02.1–02.3; Web unit): when `formatInZone('2026-11-01T23:30:00Z','Asia/Ho_Chi_Minh','dayLong')`; `localSlotsForDay` Saturday (08–12), 60 min; `maskPhone('0903123218')`; then "Monday, 2 Nov 2026"; 08:00…11:00 (13 times); "0903 *** 218". Test: `web/src/shared/format/*.test.ts`.
- [x] No change users can see; existing tests stay green unchanged
- [x] README §8 checks green; no existing test skipped, deleted or weakened
