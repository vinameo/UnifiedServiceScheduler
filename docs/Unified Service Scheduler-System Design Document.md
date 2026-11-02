# Unified Service Scheduler · System Design Document

## 1. Summary

The Unified Service Scheduler lets customers and service advisors book a service appointment. Each appointment needs three things at the same time: a technician whose level is high enough for the service, a service bay of the right type, and the customer's vehicle.

The main risk is **double booking**. It can happen at two moments:

- **When a new appointment is created.** Two requests see the same free slot at the same time, and both save it.
- **When an existing appointment is changed.** Two people change the same appointment from old screens, and one change is lost without anyone knowing.

We use a different tool for each moment:

| Moment | Our choice | What it guarantees |
| --- | --- | --- |
| Create a new appointment | PostgreSQL **EXCLUDE constraint** on `resource_allocation` | The database never stores two active bookings that overlap for the same technician, bay or vehicle. |
| Change an appointment | **Optimistic locking** with `appointment.version` (ETag and If-Match in the API) | A change is saved only if it is based on the latest version. If not, the user gets 412 and reloads. |
| Change that moves the time | **Both**: version check first, then EXCLUDE | The change is based on the latest data, and the new time is really free. |

All checks run inside one database transaction. The system is one service with one PostgreSQL database, so it is simple to run and test. Section 14 shows how it can grow if traffic becomes very high.

## 2. Background and goals

A dealership has a fixed number of technicians and bays. Today, bookings come from the service desk and from the customer app at the same time, so the system must stay correct when many people book at once.

**Who uses the system**

| User | What they do |
| --- | --- |
| Customer | Books, moves or cancels their own appointment in the app. A chosen slot is held for 5 minutes while they confirm. |
| Service advisor | Creates and changes appointments at the desk, often while talking to the customer. |
| Workshop foreman | Runs the day: check-in, start, extend a job that runs late (the next booking is moved to another technician or bay, or flagged for a call). |
| Admin | Keeps master data: services, bays, technicians and their levels, opening hours. Disables a technician who no longer takes bookings. |

**Features in scope**

| ID | Feature | ID | Feature |
| --- | --- | --- | --- |
| F01 | Master data | F07 | Cancel an appointment |
| F02 | Create a booking | F08 | Monitoring |
| F03 | Check free slots and pick resources | F09 | Reschedule |
| F04 | Stop double booking and duplicate requests | F10 | Temporary hold for self-booking |
| F05 | View appointments | F11 | Notifications through an outbox |
| F06 | Suggest other free slots | F12 | Day-of-service operations |

**Goals**

1. No double booking, even under heavy load.
2. A clear and quick answer: the user always knows if the booking worked, and what to do if it did not.
3. No lost changes: a change based on old data is never saved silently.
4. A failed SMS or email never breaks or undoes a booking.
5. Simple to run, test and explain.

**Out of scope:** payments, parts stock and pricing.

**Assumptions:** the backend uses PostgreSQL 14 or later with the `btree_gist` extension. All times are stored in UTC and shown in the dealership's time zone. One database serves all dealerships at the start.

## 3. Architecture

The system is a **modular monolith**: one deployable service, split into clear modules, with one PostgreSQL database. The rule "no double booking" lives in the database, not only in the code.

&#91;embedded content: architecture · 2 apps, 1 service, 1 database\]

Both apps call one REST API. All modules write to the same PostgreSQL database in one transaction, and the outbox relay talks to outside systems only after the commit.

| Module | Main job | Owns these tables |
| --- | --- | --- |
| Booking | Create, move, cancel and confirm appointments. Applies the status rules. | `appointment`, `idempotency_key` |
| Allocation | Finds free slots. Picks a technician and a bay for a service. | `resource_allocation` |
| Workshop | Check-in, start, overruns, moving the next booking when a job runs late. | uses Booking and Allocation |
| Master data | Services, bays, technicians (with a level), opening hours. | `service_type`, `bay`, `technician` |
| Notifications | Sends SMS, email and DMS messages after a commit. | `outbox` |

Two background jobs run beside the API. The **hold expiry job** turns old HELD appointments into EXPIRED and frees their slot. The **outbox relay** sends messages to outside systems and retries up to 5 times.

**Why one service and one database?** A booking must reserve a technician, a bay and the vehicle together, or not at all. In one database this is one transaction. With separate services we would need a saga and undo steps, which adds a lot of work and new ways to fail.

## 4. Data model

Two tables carry the two protections: `appointment` holds the **version** for updates, and `resource_allocation` holds the **EXCLUDE constraint** for new bookings.

| Table | Key columns | Notes |
| --- | --- | --- |
| `appointment` | id, dealership\_id, customer\_id, vehicle\_id, service\_type\_id, status, start\_at, end\_at, expires\_at, **version**, updated\_at | One row per booking. `version` starts at 1 and goes up by 1 on every change. `expires_at` is used only for HELD. |
| `resource_allocation` | id, appointment\_id, resource\_type, resource\_id, period, active | Three rows per booking: TECHNICIAN, BAY, VEHICLE. `period` is a time range `[start, end)`. The EXCLUDE constraint lives here. |
| `service_type` | id, name, duration\_min, required\_level, required\_bay\_type | Example: Oil change, 60 min, level 1, bay type QUICK. |
| `technician` | id, dealership\_id, name, level, active | `level` is the trade grade, 1 (lowest) to 7 (highest). |
| `bay` | id, dealership\_id, name, bay\_type | Example: Bay 2, type LIFT. |
| `outbox` | id, event\_type, payload, status, attempts, next\_try\_at | Written in the same transaction as the booking. |
| `idempotency_key` | key, request\_hash, response, created\_at | Stops the same request from being saved twice. |

**Technicians**

- Each technician has one **level** from 1 to 7 (trade grade). Each service type needs one level. A technician can do a service when they are active and their level is **at or above** the service's level, so a level-6 technician can also do a level-1 oil change.
- When several technicians qualify, the system picks the **lowest** level first, then the one with the fewest booked minutes that day, then by id. This keeps high-level technicians free for the services only they can do.
- There are no shifts, attendance or leave in this version: an active technician works whenever the dealership is open. Absences and bay outages are out of scope.
- An admin can disable a technician (they get no new bookings) or enable them again. Disabling is refused (409 `TECHNICIAN_HAS_BOOKINGS`) while the technician still has upcoming appointments; an advisor must move or cancel those first. To stay correct when a booking and a disable happen at the same moment, the disable locks the technician row (`SELECT … FOR UPDATE`) before counting upcoming appointments, and every booking reads its technician with `FOR SHARE` inside its transaction and skips an inactive one like a taken one.

**Status of an appointment**

| Holds the slot | Frees the slot |
| --- | --- |
| HELD (5-minute hold), CONFIRMED, CHECKED\_IN, IN\_PROGRESS | COMPLETED, CANCELLED, NO\_SHOW, EXPIRED |

When an appointment moves to a status that frees the slot, its rows in `resource_allocation` are set to `active = false` in the same transaction. Old rows are kept for history.

**Time ranges** are half-open: `[10:00, 11:00)` includes 10:00 but not 11:00. So a job from 10:00 to 11:00 and the next job from 11:00 to 12:00 do not overlap.

## 5. Problem 1: creating a new appointment

Two requests can book the same bay, technician or vehicle for the same time, because both check before either one saves.

**The rule:** for one technician, one bay or one vehicle, two active bookings must never overlap in time.

The simple way to code this is "check, then insert":

```sql
SELECT count(*) FROM resource_allocation
WHERE resource_id = :bay2 AND active AND period && tstzrange('10:00', '11:00');
-- 0 rows, so the slot looks free
INSERT INTO resource_allocation (...) VALUES (...);
```

This works for one user. It fails when two requests run at the same moment:

&#91;embedded content: race on check, then insert · 2 requests, 6 steps\]

Step 3 happens before step 5 is saved, so B still sees the bay as free. Only a rule inside the database can stop step 6.

**Why this problem is hard**

1. **The row does not exist yet.** A normal row lock cannot lock a row that is not there. Request B has nothing to wait for, so it reads "free" too.
2. **It is about overlap, not equal values.** 10:00 to 11:00 and 10:30 to 11:30 are different values but they clash. A normal UNIQUE index cannot see this.
3. **Three resources must be booked together.** If the technician and bay are saved but the vehicle fails, we must undo all of them. Half a booking is not allowed.

Where it happens in real life: two advisors at the desk pick the last free slot of the morning; a customer books in the app while an advisor books the same bay; a customer taps "Book" twice on a slow network.

## 6. Problem 2: changing an existing appointment

Two people can change the same appointment at almost the same time, and the second save quietly wipes out the first. This is called a **lost update**.

Each person reads the appointment, decides what to do, and saves. The second save is based on data that is already old, but the database accepts it:

&#91;embedded content: lost update · 2 people, 6 steps\]

Both people started from version 3. Without a version check, the database cannot tell that step 6 is based on old data, so it saves it.

**Common cases in a dealership**

| Who | What happens | Result without protection |
| --- | --- | --- |
| Customer and advisor | The customer moves the booking to 14:00 while the advisor cancels it. | The cancelled booking comes back and blocks 14:00. |
| Hold expiry job and customer | The job expires a HELD booking at 5 minutes while the customer taps Confirm. | The customer sees "Confirmed" but the slot was already given away. |
| Foreman and advisor | The foreman extends a late job while the advisor marks it COMPLETED. | The completed job holds the bay for 30 more minutes. |

**Why the EXCLUDE constraint does not help here.** EXCLUDE only checks that two bookings do not overlap. In these cases there is only one booking, and the final data has no overlap. The problem is that the change was based on an old screen, and only a version number can show that.

**Why this happens often.** Screens stay open for a long time. An advisor may keep a booking open while on the phone for several minutes. The customer app may show data from the last refresh. So "old screen" is a normal case, not a rare one.

## 7. Options considered

We looked at four common ways to stop conflicts. No single option is the best for both problems, so we use EXCLUDE for Problem 1 and optimistic locking for Problem 2.

| Option | How it works | Good for | Main weakness |
| --- | --- | --- | --- |
| Pessimistic lock | `SELECT ... FOR UPDATE` locks rows before the change. Others wait. | Short, very busy updates on rows that already exist. | Cannot lock a row that does not exist yet. Users wait. Risk of deadlocks. Cannot see an old screen. |
| Optimistic lock | Each row has a `version`. Save only if the version is still the one you read. | Updates where conflicts are rare and screens can be old. | Does not protect a new booking, because there is no row to compare yet. |
| Queue | All bookings for a dealership go through one queue, one at a time. | Very high traffic on one dealership. | More parts to run. The answer is not instant. Still needs a database check as a last line. |
| PostgreSQL EXCLUDE constraint | The database refuses any new row whose time range overlaps an active row for the same resource. | New bookings and any change that takes a new time. | Only in PostgreSQL. Gives a database error that we must turn into a clear message. |

**Which option fits which problem**

|  | Problem 1: create | Problem 2: update |
| --- | --- | --- |
| Pessimistic lock | Weak: no row to lock, needs a big lock on the whole day | Partly: stops two saves at once, but not an old screen |
| Optimistic lock | Does not fit: no version to compare | **Best fit**: catches both two saves at once and old screens |
| Queue | Works, but heavy for normal traffic | Partly: same as pessimistic lock |
| EXCLUDE | **Best fit**: the database itself refuses the overlap | Does not fit: one booking, no overlap to find |

So the two chosen tools cover each other's gaps. Sections 8 and 9 explain each one, and Section 10 shows how they run together.

## 8. Solution for Problem 1: EXCLUDE constraint

We let PostgreSQL refuse any overlap. The rule is written once, in the table, so every code path that books a slot must follow it.

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE TABLE resource_allocation (
  id             uuid PRIMARY KEY,
  appointment_id uuid NOT NULL REFERENCES appointment(id),
  resource_type  text NOT NULL,      -- TECHNICIAN | BAY | VEHICLE
  resource_id    uuid NOT NULL,
  period         tstzrange NOT NULL, -- [start, end)
  active         boolean NOT NULL DEFAULT true,
  EXCLUDE USING gist (
    resource_type WITH =,
    resource_id   WITH =,
    period        WITH &&
  ) WHERE (active)
);
```

In plain words: "For the same resource type and the same resource, no two active rows may have time ranges that overlap (`&&`)." If a new row breaks this rule, PostgreSQL stops the insert with error **23P01**.

**How a new booking is saved**

1. Check the Idempotency-Key. If we have seen this key before, return the saved answer and stop.
2. Read free technicians (active, level high enough) and bays for the time, and make a short list of (technician, bay) pairs, best first.
3. Start a transaction. Read the pair's technician with `FOR SHARE`; if it was disabled meanwhile, roll back and try the next pair. Insert the appointment (version 1) and three `resource_allocation` rows: technician, bay and vehicle.
4. If the commit works, return **201 Created**.
5. If we get 23P01 on the technician or bay, roll back and try the next pair from the list.
6. If we get 23P01 on the vehicle, the car is already booked at that time. Return **409 VEHICLE\_ALREADY\_BOOKED** at once.
7. If no pairs are left, return **409 NO\_AVAILABILITY** with a list of other free times (F06).

The customer hold (F10) uses the same steps, with status HELD and `expires_at` set to now + 5 minutes.

**Pros**

| Pro | Why it matters |
| --- | --- |
| Always correct | The rule is in the database. A bug in one code path, a new endpoint or a manual script cannot break it. |
| Solves the "row does not exist yet" problem | PostgreSQL checks the index when the row is inserted, so two inserts at the same time cannot both pass. |
| Understands overlap | `&&` on time ranges catches 10:00 to 11:00 against 10:30 to 11:30. No extra code. |
| Works at READ COMMITTED | We do not need SERIALIZABLE or long locks. Bookings for other bays and other times never wait. |
| All or nothing | The three rows are in one transaction. If one fails, none is saved. |
| Little code | No locking code to write and keep correct. |

**Cons and how we handle them**

| Con | How we handle it |
| --- | --- |
| Only works on PostgreSQL, and needs `btree_gist` | We accept PostgreSQL as part of the design. We check this with the partner (Section 14). |
| Tests need a real PostgreSQL | We use Testcontainers. In-memory databases like H2 do not support EXCLUDE. |
| Rows must be set to `active = false` when a booking ends | Every status change that frees the slot does this in the same transaction. A test checks every such status. |
| The error 23P01 is a database error, not a business error | The Allocation module reads the constraint error, finds which resource failed, and turns it into a clear 409 code. |
| A very busy slot can cause many retries | The list of pairs is short (top 5). We measure the retry rate. If it grows, we add the steps in Section 14. |
| The WHERE part cannot use `now()` | So an expired HELD row stays active until something turns it off. The hold expiry job and the booking flow both do this (Section 12). |

## 9. Solution for Problem 2: optimistic locking

Every change must say which version it is based on. The database saves the change only if that version is still the latest one.

**How it looks in the API**

1. `GET /appointments/{id}` returns the appointment and the header `ETag: "3"` (the version).
2. The client sends the change with `If-Match: "3"`.
3. If the version in the database is still 3, the change is saved, the version becomes 4, and the answer has `ETag: "4"`.
4. If someone else changed it first, the answer is **412 APPOINTMENT\_MODIFIED** with the latest data. The user reloads and decides again.
5. If the client forgets `If-Match`, the answer is **428 PRECONDITION\_REQUIRED**. We never guess.

**How it looks in the database**

```sql
UPDATE appointment
SET    status = 'CANCELLED', version = version + 1, updated_at = now()
WHERE  id = :id AND version = :expectedVersion;
-- 1 row: success
-- 0 rows: someone changed it first, so ROLLBACK and return 412
```

This `UPDATE` also takes a short row lock. If two changes arrive at the same moment, the second one waits for the first. When it continues, PostgreSQL checks the `WHERE` again, sees the new version, and finds 0 rows. So the version check is safe even when two saves happen at exactly the same time.

**Key rules**

- Every write to `appointment` must add 1 to `version`. This includes background jobs. A database trigger can enforce this.
- We do **not** retry a 412 by ourselves. The data has changed, so a person (or the job's own logic) must look again.
- Background jobs use the same rule. The hold expiry job runs `UPDATE ... WHERE id = :id AND version = :v AND status = 'HELD'`. If the customer confirmed a moment earlier, the job finds 0 rows and skips that booking.
- The Idempotency-Key is checked **before** If-Match. If a client sends the same change twice, the second call gets the saved answer, not a 412.

**Pros**

| Pro | Why it matters |
| --- | --- |
| Catches old screens | It is the only option that knows the user saw old data. Pessimistic lock and queue cannot do this. |
| No waiting in the normal case | Most changes do not clash. They run at full speed with no lock held while the user thinks. |
| One pattern for every change | Cancel, confirm, check-in, reschedule, extend: all use the same `WHERE version = :v`. Easy to review and test. |
| Standard HTTP | ETag, If-Match, 412 and 428 are web standards. Partner developers already know them. |
| No deadlocks | We never hold a lock across user actions. |

**Cons and how we handle them**

| Con | How we handle it |
| --- | --- |
| Clients must keep the ETag and send it back | Our API returns it on every read and write. We return 428 if it is missing, so the mistake shows up early in testing. |
| The user who saves second must reload | The 412 answer includes the latest data, so the screen can show what changed and let the user try again in one tap. |
| Every write path must add 1 to `version` | One shared repository method for all updates, plus a database trigger as a safety net. |
| Does not protect new bookings | That is the job of the EXCLUDE constraint (Section 8). |
| Many 412 errors if one booking is changed very often | This is rare for one appointment. We watch the 412 rate on the monitoring screen (F08). |

## 10. How the two solutions work together

A new booking passes only the EXCLUDE check. A change passes the version check first. If the change moves the time, it then passes the EXCLUDE check too, all in one transaction.

&#91;embedded content: combined strategy · 2 ways in, 1 shared database rule\]

A change that does not move the time, such as check-in or start, stops after the version check. A cancel also frees the old slot, but takes no new one.

**Example: reschedule (F09)**

```sql
BEGIN;
-- 1. Check the version and lock the row
UPDATE appointment
SET    start_at = :newStart, end_at = :newEnd, version = version + 1, updated_at = now()
WHERE  id = :id AND version = :expectedVersion;
-- 0 rows: ROLLBACK, return 412

-- 2. Free the old slot
UPDATE resource_allocation SET active = false
WHERE  appointment_id = :id AND active;

-- 3. Take the new slot (EXCLUDE checks it)
INSERT INTO resource_allocation (appointment_id, resource_type, resource_id, period) VALUES
  (:id, 'TECHNICIAN', :techId, tstzrange(:newStart, :newEnd)),
  (:id, 'BAY',        :bayId,  tstzrange(:newStart, :newEnd)),
  (:id, 'VEHICLE',    :vehId,  tstzrange(:newStart, :newEnd));
-- 23P01: ROLLBACK, try the next pair; no pairs left: 409

-- 4. Message for SMS and email, sent after commit
INSERT INTO outbox (...) VALUES (...);
COMMIT;
```

**Why this order**

- **Version first.** The `UPDATE` locks the appointment row. Any other change to the same booking must wait, then fails with 412. So only one person can move it at a time.
- **Free the old slot before taking the new one.** If a booking moves from 10:00–11:00 to 10:30–11:30 on the same bay, the old row would clash with the new row. Turning off the old row first stops a booking from clashing with itself.
- **A retry after 23P01 runs the whole transaction again** with the same expected version. If the version changed in the meantime, the retry gets 412, which is correct.

**Every operation at a glance**

| Operation | Version check | EXCLUDE check | Possible errors |
| --- | --- | --- | --- |
| Create at the desk (F02) | No, new row | Yes | 409 NO\_AVAILABILITY, 409 VEHICLE\_ALREADY\_BOOKED |
| Customer hold (F10) | No, new row | Yes | 409 |
| Confirm a hold (F10) | Yes | No, the slot is already held | 412, 410 HOLD\_EXPIRED |
| Reschedule (F09) | Yes | Yes, for the new time | 412 or 409 |
| Extend a late job (F12) | Yes | Yes, for the extra time | 412 or 409 |
| Move the next booking when a job is extended (F12) | Yes, inside the extend transaction | Yes, for the new technician or bay | The whole extend is retried once, then 409 |
| Disable a technician (F01) | No, it locks the technician row | No | 409 TECHNICIAN\_HAS\_BOOKINGS |
| Cancel, no-show, complete (F07, F12) | Yes | No, it only frees the slot | 412 |
| Check-in, start work (F12) | Yes | No, the time does not change | 412 |
| Hold expiry job | Yes, plus `status = 'HELD'` | No | None: skips the booking if the customer just confirmed |

## 11. API contract and error codes

Every write needs an **Idempotency-Key**, and every change to an existing appointment also needs **If-Match**. Errors use the RFC 9457 "problem details" format with a fixed `code` field that clients can check.

**Main endpoints** (all under `/api/v1`)

| Endpoint | Purpose | Headers |
| --- | --- | --- |
| `GET /dealerships/{id}/availability?serviceTypeId=&date=` | Free start times, in 15-minute steps | None |
| `POST /appointments` | Create a booking at the desk, or a 5-minute hold with `mode: HOLD` | Idempotency-Key |
| `GET /appointments/{id}` | Read one appointment | Returns ETag |
| `GET /dealerships/{id}/appointments?date=` | List one day's appointments | None |
| `POST /appointments/{id}/confirm` | Confirm a hold | Idempotency-Key, If-Match |
| `POST /appointments/{id}/reschedule` | Move to a new time | Idempotency-Key, If-Match |
| `POST /appointments/{id}/cancel` | Cancel with a reason | Idempotency-Key, If-Match |
| `POST /appointments/{id}/check-in`, `/start`, `/extend`, `/complete`, `/no-show` | Day-of-service actions (F12). Path names are proposed. | Idempotency-Key, If-Match |

**The two headers in short**

- **Idempotency-Key** is a UUID the client makes for each user action. If the network fails and the client sends the same request again, the server returns the first answer and does not book twice.
- **ETag / If-Match** carry the appointment version. The server sends `ETag` on every read and successful write. The client sends it back in `If-Match` on the next change.

**Error codes**

| HTTP | Code | When | What the client should do |
| --- | --- | --- | --- |
| 409 | `NO_AVAILABILITY` | No free technician and bay pair for that time | Show the other free times included in the answer (F06). |
| 409 | `VEHICLE_ALREADY_BOOKED` | The car already has a booking that overlaps | Show the existing booking. |
| 412 | `APPOINTMENT_MODIFIED` | The version in If-Match is old | Reload, show what changed, let the user try again. |
| 428 | `PRECONDITION_REQUIRED` | If-Match is missing on a change | Fix the client. This is a bug, not a user error. |
| 410 | `HOLD_EXPIRED` | Confirm after the 5-minute hold ended | Ask the customer to choose a time again. |
| 422 | `IDEMPOTENCY_KEY_MISMATCH` | Same key, but a different request body | Make a new key for a new action. |
| 422 | `INVALID_STATUS_TRANSITION` | For example, cancel a job that is already IN\_PROGRESS | Show the current status. |
| 422 | `START_IN_PAST`, `OUTSIDE_OPENING_HOURS` | The time is not allowed | Pick another time. |
| 422 | `VEHICLE_NOT_OWNED_BY_CUSTOMER` | The vehicle belongs to someone else | Check the customer and vehicle. |
| 409 | `TECHNICIAN_HAS_BOOKINGS` | Disabling a technician who still has upcoming appointments | List those appointments; move or cancel them first. |

**409 or 412?** 409 means "the time is taken", so the user picks another time. 412 means "your screen is old", so the user reloads first. Keeping them apart lets the app show the right message.

## 12. Other design decisions

These choices support the two main solutions and keep the system safe when the network or outside systems fail.

| Topic | Decision | Why |
| --- | --- | --- |
| Duplicate requests | Table `idempotency_key` stores the key, a hash of the request body and the saved answer. Same key and same body: return the saved answer. Same key, different body: 422. Keys are kept for 24 hours (assumption). | A slow network or a double tap must not create two bookings. |
| Notifications (F11) | Transactional outbox. The message row is saved in the same transaction as the booking. A relay sends it after commit, with up to 5 retries and a longer wait each time. Delivery is "at least once", so receivers ignore repeats by event id. | A failed SMS never undoes a booking, and a rolled-back booking never sends a message. |
| Hold expiry (F10) | A HELD booking has `expires_at` = now + 5 minutes and its slot stays active. A job runs every 30 seconds (assumption) and sets EXPIRED with a version check. The booking flow also frees any expired hold it meets on the way. | The EXCLUDE rule cannot use `now()`, so something must turn old holds off. Two ways make sure a slot is never blocked for long. |
| Transaction level | READ COMMITTED for all transactions. | Both EXCLUDE and the version check are correct at this level. SERIALIZABLE would add retries to every request. |
| Free-slot search | Read straight from the database, no cache. | A cache would show more slots that are already taken. The EXCLUDE check still has the last word. |
| Time | Store `timestamptz` in UTC. Show and filter by the dealership's time zone. | Safe across summer time changes and dealerships in different zones. |
| Monitoring (F08) | Count 409, 412 and 23P01 retries. Track p95 time of `POST /appointments` and the outbox backlog. | Tells us early when we need the steps in Section 14. |

## 13. Testing plan

The key tests send many requests at the same moment against a real PostgreSQL, then check the data directly. We use Testcontainers, because in-memory databases do not support EXCLUDE. All threads wait at a start gate, so they really run together.

After every test, one SQL query looks for any overlap in active `resource_allocation` rows. It must always return 0 rows.

**Tests for creating (Problem 1)**

| Test | Setup | Expected result |
| --- | --- | --- |
| Last free slot | 50 requests at once for a time with only one free pair | Exactly 1 × 201 and 49 × 409. No overlap in the data. |
| Overlap, not equal | Bay 2 has 10:00–11:00. Book Bay 2 at 10:30–11:30. | Uses another free bay, or returns 409 if there is none. |
| Back to back | Book 10:00–11:00, then 11:00–12:00 on the same bay | Both succeed. |
| Same car twice | One vehicle, two overlapping bookings on different bays | Second one gets 409 VEHICLE\_ALREADY\_BOOKED. |
| All or nothing | Force the vehicle row to fail | No technician or bay row is left behind. |
| Same Idempotency-Key at once | 10 parallel calls with one key | One booking. All 10 calls get the same answer. |
| Freed slot | Cancel a booking, then book the same time | Succeeds. |

**Tests for changing (Problem 2)**

| Test | Setup | Expected result |
| --- | --- | --- |
| Two changes at once | Cancel and reschedule, both with If-Match 3, sent together | One 200 and one 412. Final version is 4. |
| Old screen | Read version 3. Another change makes it 4. Send a change with version 3. | 412 with the latest data. Nothing saved. |
| Missing header | Send a change with no If-Match | 428. Nothing saved. |
| Expiry job and confirm | Run both at the same moment, 100 times | Each booking ends either CONFIRMED or EXPIRED, never mixed. A late confirm gets 410 or 412. |
| Move inside its own slot | 10:00–11:00 to 10:30–11:30 on the same bay | Succeeds. The booking does not clash with itself. |
| Move to a taken time | Reschedule to a full time | 409. The old slot and the version stay the same. |
| Retry after success | Send the same reschedule twice with one Idempotency-Key | Second call gets the saved 200, not 412. |
| Statuses that free the slot | Run each of COMPLETED, CANCELLED, NO\_SHOW, EXPIRED | All allocation rows become `active = false`. |

## 14. Scaling plan, risks and questions for the partner

We start simple and add parts only when the numbers show we need them. The EXCLUDE constraint and the version check stay in every phase as the last line of protection.

**Scaling plan**

| Phase | What we add | When we move to the next phase |
| --- | --- | --- |
| 1. Start | EXCLUDE + version + retry of the next pair. One service, one database. | More than 5% of bookings need a retry (starting target, to agree), or p95 of `POST /appointments` goes over 300 ms. |
| 2. Busy dealerships | A short lock per dealership and day (`pg_advisory_xact_lock`) before picking a pair. Requests for the same day wait in line for a few milliseconds instead of retrying. | Waiting time on that lock grows, for example during a big online campaign. |
| 3. Very high traffic | A queue for customer self-booking, split by dealership. One worker per dealership saves bookings in order. The desk still books directly. | Not planned. Only if Phase 2 is not enough. |

**Risks**

| Risk | Effect | What we do |
| --- | --- | --- |
| A new code path changes `appointment` without adding 1 to `version` | Lost updates come back | One shared update method, a database trigger, and a test for every endpoint. |
| A status change forgets to set `active = false` | A slot stays blocked | A test for every status that frees a slot, and a daily check query with an alert. |
| The hold expiry job stops | Expired holds keep blocking slots | The booking flow also frees expired holds. An alert fires if the job has not run for 2 minutes. |
| A very popular slot | Many retries, slower answers | Retry rate on the monitoring screen; move to Phase 2. |
| Partner apps do not send If-Match | Their changes get 428 | Clear API guide, examples and a test environment before go-live. |
| SMS or DMS is down | Messages arrive late | The outbox keeps them and retries. Bookings are not affected. An alert fires on a large backlog. |
| We must leave PostgreSQL one day | The overlap rule must be rebuilt | The rule is used only inside the Allocation module, and the tests describe the expected behaviour, so a new version can be checked the same way. |
