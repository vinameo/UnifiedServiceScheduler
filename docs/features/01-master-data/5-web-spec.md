# F01 · Master data · Web spec (WEB)

---

# PART A · For readers

The admin "Master data" screen, per the draft `docs/designs/Admin/screenshots/09-master-data.png`. Title, subtitle, a "Read only" badge, an info box "This version loads this data from seed files", then 4 tabs with counts: Service types · Bays · Technicians · Opening hours. Each tab is a read-only table.

```
Master data                                                [Read only]
Services, bays, technicians and opening hours that allocation relies on.
Saigon Auto · District 7 branch.
┌ (i) This version loads this data from seed files ──────────────────┐
│ This screen is for viewing and checking only. To add or change data,│
│ edit the seed file and run the seeder. In-app editing is planned…   │
└─────────────────────────────────────────────────────────────────────┘
[Service types 3] [Bays 4] [Technicians 5] [Opening hours]
Code        Service name        Duration  Bay type required  Level required  Status
OIL_CHANGE  Oil change          60 min    Quick service      Level 1         Active

Technicians tab:
Technician       Level    Status                 Action
Ngô Bảo Em       Level 6  Working                [Disable]
Huỳnh Tấn Phát   Level 1  Not taking bookings    [Enable]
…
```

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Route and access

| Path | Element | Guard |
| --- | --- | --- |
| `/staff/master-data` | `staff/master-data/MasterDataPage` | `RequireRole roles={['ADMIN']}`; other roles → Forbidden page (F00) |

The "Master data" menu item shows only for admins (F00 WEB §B3).

## B2. Data

| ID | Hook | Query key | API |
| --- | --- | --- | --- |
| WEB-01.1 | `useDealership(id)` | `['dealership', id]` | API-01.2 |
| WEB-01.2 | `useServiceTypes(dealershipId)` | `['service-types', id]` | API-01.3 |
| WEB-01.3 | `useBays(id)`, `useTechnicians(id)` | `['bays', id]`, `['technicians', id]` | API-01.4, 01.5 |
| WEB-01.5 | `useSetTechnicianActive(id)` | invalidates `['technicians', id]` | API-01.6 |

File: `web/src/shared/api/masterData.ts` (types + hooks, reused in F02, F06, F12). `dealershipId` comes from `useMe().dealership.id`.

## B3. Display

| Tab | Columns | Format |
| --- | --- | --- |
| Service types | Code · Service name · Duration · Bay type required · Level required · Status | Duration "60 min"; Status `Badge` "Active"/"Inactive" |
| Bays | Code · Bay name · Bay type · Supported services · Status | Supported services = service names joined with ", " |
| Technicians | Technician · Level · Status · Action | Level "Level 6"; Status "Working" (green) / "Not taking bookings" (grey); Action "Disable" (active) or "Enable" (disabled), admins only |
| Opening hours | Date · Opening hours | Consecutive days with the same hours are merged: "Monday to Friday · 08:00–17:00", "Saturday · 08:00–12:00", "Sunday · Closed"; under the table: Time zone "Asia/Ho_Chi_Minh (UTC+7)", Slot step "15 min", the fit rule sentence |

`WEB-01.4`: pure helper `groupWeekdays(rows)` in `web/src/shared/format/weekdays.ts` (unit tested).

The "UTC+7" offset comes from `Intl.DateTimeFormat(…, { timeZone, timeZoneName: 'shortOffset' })` for today, never hard-coded.

Tab counts are the number of rows returned; each list is complete (F01 lists have no paging, API §B1). When a response says `truncated: true`, the count shows "200+". An empty tab shows "Nothing here yet."

**Disable / enable (WEB-01.5)**: `useSetTechnicianActive(dealershipId)` mutation (`PUT` API-01.6, no idempotency key, RQ D-01.12). "Disable" opens a confirm `Modal` ("Disable {name}?" + `disable.body`); confirm is a red `SubmitButton`. 200 → notification "{name} no longer takes bookings." and the row refreshes. 409 `TECHNICIAN_HAS_BOOKINGS` → the modal shows `disable.blocked` with the list from the error (reference, "{dayShort} · {range}", customer name; each reference links to `/staff/appointments/{id}`), "and {more} more" when `count` > 20, and only a "Close" button. "Enable" needs no confirm; 200 → notification "{name} takes bookings again."

States: loading → `Skeleton` table; error → `Alert` with `errorCopy(code)` and a "Retry" button.

## B4. Copy

| Key | Copy |
| --- | --- |
| `title` | Master data |
| `subtitle` | Services, bays, technicians and opening hours that allocation relies on. |
| `branch` | {name} · {branchName} branch. |
| `readOnly` | Read only |
| `seedTitle` | This version loads this data from seed files |
| `seedBody` | This screen is for viewing and checking only. To add or change data, edit the seed file and run the seeder. In-app editing is planned for a later phase. |
| `onlyStatus` | Only technician status can be changed here. |
| `level` | Level {n} |
| `columns.level` / `columns.levelRequired` / `columns.action` | Level / Level required / Action |
| `disable` / `enable` | Disable / Enable |
| `disable.title` | Disable {name}? |
| `disable.body` | {name} will stop getting new appointments. You can enable them again at any time. |
| `disable.confirm` | Disable technician |
| `disable.blocked` | {name} still has {count} upcoming appointments. Move or cancel them before disabling. |
| `disable.more` | and {more} more |
| `disable.done` | {name} no longer takes bookings. |
| `enable.done` | {name} takes bookings again. |
| `tabs.*` | Service types · Bays · Technicians · Opening hours |
| `status.working` | Working |
| `status.notTaking` | Not taking bookings |
| `status.active` / `inactive` | Active / Inactive |
| `closed` | Closed |
| `empty` | Nothing here yet. |
| `timezone` | Time zone |
| `slotStep` | Slot step |
| `fitRule` | Appointments must fit entirely within opening hours: the end time cannot pass closing time. |
| `bayType.*` | Quick service · Standard service · EV |

Enum labels live in `web/src/shared/copy/enums.ts` (reused in F02, F12).
