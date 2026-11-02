# CONTEXT · Unified Service Scheduler

Glossary for specs, code, UI copy and tests. One term means one thing everywhere. Specs, code, API and UI copy are written in English.

## Domain terms

| Term | Meaning |
| --- | --- |
| Dealership | One branch, for example "Saigon Auto · District 7". Has a time zone, opening hours and a phone number. Every operational row belongs to one dealership. |
| Opening hours | Per weekday, in the dealership's local time. A booking `[start, end)` must fit inside one day's opening hours. |
| Service type | What the customer books: name, duration in minutes, one required level, one required bay type. |
| Level | A technician's trade grade (bậc nghề), 1 (lowest) to 7 (highest). A service type requires one level; a technician can do every service whose required level is at or below their own. |
| Bay | A service bay of one bay type: `QUICK`, `STANDARD`, `EV`. |
| Technician | Has one level. Works whenever the dealership is open (no shifts, attendance or leave in this version). An admin can disable a technician ("Not taking bookings") when they have no upcoming appointments. |
| Customer | A person with a customer account (email + password). Owns vehicles. |
| Vehicle | Owned by one customer. Licence plate unique; VIN unique when given. |
| Appointment / booking | One service, for one vehicle, at one time, with one technician and one bay. "Booking" is the user word; `appointment` is the code word. |
| Reference | Human id shown in the UI, for example `APT-20261102-0042`. The API id is a UUID. |
| Resource | `TECHNICIAN`, `BAY` or `VEHICLE`: what an appointment holds for its time range. |
| Allocation | One row in `resource_allocations`: one resource held by one appointment for `[start, end)`. Active allocations cannot overlap (EXCLUDE). |
| Candidate pair | A (technician, bay) pair that F03 found free and qualified, ranked best first. |
| Slot / free time | A start time (15-minute step) at which F03 finds at least one candidate pair for the whole duration. |
| Alternatives | Up to 3 free start times nearest to the wanted time, returned with `409 NO_AVAILABILITY`. |
| Hold | A `HELD` appointment that keeps a slot for a customer for `HOLD_MINUTES` (5). |
| Confirm | Turn a hold into `CONFIRMED`. |
| Reschedule | Move a `CONFIRMED` appointment to a new start time. The old one stays if the new time is taken. |
| Cancel | Set `CANCELLED` and free the slot. |
| Check in | The car has arrived: `CHECKED_IN`. |
| Start work | `IN_PROGRESS`. |
| Complete | `COMPLETED`; frees the slot. |
| No-show | `NO_SHOW`; frees the slot; only after the start time. |
| Overrun | An `IN_PROGRESS` job whose end time has passed. |
| Extend | Push the end time of a running job later. |
| Reassign | Give an appointment another technician or bay at the same time (when an overrun takes its bay or technician). |
| Contact required | An appointment the system could not reassign after an overrun; staff must call the customer. Never cancelled automatically. |
| Version | `appointments.version`; sent as `ETag`, expected back in `If-Match`. |
| Idempotency key | UUID the client makes for one user action, sent as `Idempotency-Key`. |
| Outbox message | A row in `outbox_messages`, written in the same transaction as the change, sent later by the relay. |
| Relay | `php artisan outbox:relay`: sends outbox messages to SMS, email, DMS. |
| Correlation id | `X-Correlation-Id`: ties a request, its logs and its error together. |

## Status values

| Status | UI label | Holds the slot |
| --- | --- | --- |
| `HELD` | On hold | yes |
| `CONFIRMED` | Confirmed | yes |
| `CHECKED_IN` | Checked in | yes |
| `IN_PROGRESS` | In progress | yes |
| `COMPLETED` | Completed | no |
| `CANCELLED` | Cancelled | no |
| `NO_SHOW` | No-show | no |
| `EXPIRED` | Expired | no |

## Roles

| Role (code) | UI label |
| --- | --- |
| `CUSTOMER` | Customer |
| `SERVICE_ADVISOR` | Service advisor |
| `WORKSHOP_FOREMAN` | Workshop foreman |
| `ADMIN` | Admin |

## Words we do not use

- "Slot" in the API: the API says `start` / `availability`. "Slot" is fine in UI copy.
- "Lock" for the hold: a hold is a `HELD` appointment, not a lock.
- The name of any product the design drafts imitate.
