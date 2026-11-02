# F03 · Free slots and resource allocation · Web spec (WEB)

---

# PART A · For readers

F03 has no screen. Users see it indirectly in two places:

- The New booking screen: "Technician, bay — Assigned automatically on confirm".
- The `409 NO_AVAILABILITY` error: a sentence explaining why there is no slot, depending on F03's reason.

This file owns the copy for those reasons, shared by F02, F06 and F09.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Seam

| ID | Seam | File | Used in |
| --- | --- | --- | --- |
| WEB-03.1 | `noAvailabilityReasonCopy(reason, { start, end, bayTypeLabel })` | `web/src/shared/api/errorCopy.ts` (next to `errorCopy`) | F02 (staff + customer), F09 |

An unknown `reason` → the `SLOT_TAKEN` copy. It is built in the first F02 ticket with an error screen; F03 only defines the copy.

## B2. Copy

| `reason` | Heading | Copy |
| --- | --- | --- |
| `NO_QUALIFIED_TECHNICIAN` | This service can't be booked right now | No technician at this dealership can do this service right now. Call the dealership to arrange it. |
| `NO_TECHNICIAN_AVAILABLE` | The {time} slot was just taken | No qualified technician is free from {start} to {end}. Your appointment was not created. |
| `NO_BAY_AVAILABLE` | The {time} slot was just taken | No {bayType} bay is free from {start} to {end}. Your appointment was not created. |
| `SLOT_TAKEN` | The {time} slot was just taken | This time was just taken by another booking. Your appointment was not created. |

The heading follows the reason (README §5): "The {time} slot was just taken" (the draft's wording) whenever the time is the problem, and "This service can't be booked right now" when no technician has a high enough level, because then no other time helps either.

`{start}`, `{end}`, `{time}` are `HH:mm` in the dealership's time zone (`formatInZone`, F02).
