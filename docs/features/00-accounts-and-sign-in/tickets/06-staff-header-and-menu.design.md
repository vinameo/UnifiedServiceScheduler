# Design notes · 06: Staff header and menu

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| StaffLayout header showing dealership branch and user avatar with initials (WEB §B3, README §4) | WEB-00.1, D-00.9 | `web/src/shared/auth/useMe.ts:28` me data model with dealership and role | User details read from React Query me cache populated at sign-in; reactive update on auth changes | Unloaded or empty dealership name displays safe placeholder text; HealthBadge moved out of header | `docs/designs/Admin/html/Main.dc.html:53` layout header sketch; WEB §B3 staff header | follow |
| Role-based menu navigation filtering admin vs operations items (README §4, WEB §B3) | TC-00-25 | `web/src/staff/StaffLayout.tsx:5` previous link list without role checks | Menu links filtered dynamically against `me.role` according to permission matrix | Advisor role cannot see Monitoring or Admin items; Admin role sees Master data, Notifications, Monitoring | README §4 Permission matrix; WEB §B3 Left menu specification | follow |

