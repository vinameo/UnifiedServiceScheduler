# 06: Staff header and menu

**What to build:** `StaffLayout` shows the signed-in staff member, their dealership and the menu items for their role (WEB §B3 menu), matching the design PNG.

**Blocked by:** [05-requirerole-route-guard-and-safe-returnto](05-requirerole-route-guard-and-safe-returnto.md)

**Status:** ready-for-agent

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-25

- [ ] TC-00-25 (WEB §B3 menu; Web component): given `me` is an advisor, then an admin; when Render `StaffLayout`; then Advisor: no Monitoring; admin: Master data, Notifications, Monitoring. Test: `web/src/staff/StaffLayout.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
