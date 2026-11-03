# 10: Disable and enable action on the Technicians tab

**What to build:** The admin disables a technician from the Technicians tab through a confirm dialog and enables a disabled one again (WEB-01.5); the 409 `TECHNICIAN_HAS_BOOKINGS` copy is shown when the API refuses.

**Blocked by:** [09-disable-and-enable-a-technician-api](09-disable-and-enable-a-technician-api.md)

**Status:** done

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-21

- [x] TC-01-21 (WEB-01.5; Web component): given Fake API: disable → 409 with `count 23` and 20 rows; then enable → 200; when Click Disable, confirm; close; click Enable on a disabled row; then Modal shows `disable.blocked` with 20 references and "and 3 more"; row unchanged; enable notification "… takes bookings again.". Test: `web/src/staff/master-data/MasterDataPage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
