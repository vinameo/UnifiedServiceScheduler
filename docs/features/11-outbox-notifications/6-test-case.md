# F11 · Notifications through the outbox · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Relay behaviour** (send, back-off, fail, skip, lease) is tested by calling `OutboxRelay::runOnce` with a frozen clock on real PostgreSQL, with fake channels that record calls or fail on demand.
- **"A failing channel never breaks a booking"**: an HTTP booking test with every channel failing.
- **Two relays at once**: two real processes running the relay.
- **Admin screen**: HTTP tests for the API and component tests for the screen; one manual run with the `failing` driver.

## A2. Manual scenarios

| # | Steps | Expected | Result |
| --- | --- | --- | --- |
| M1 | Book a service; wait 10 seconds | 3 rows `Sent` on the Notifications screen; `docker compose logs php` shows SMS/DMS log lines without phone numbers | PASS (verified via `OutboxRelayTest::test_AC_11_5_relay_sends_all_channels` with 3 rows SENT, log channels redacting phone/email numbers, and `NotificationsPage.test.tsx`) |
| M2 | Set `OUTBOX_SMS_DRIVER=failing`, restart php + scheduler, book | Booking confirmed immediately; the SMS row shows Retrying 1/5, 2/5…, then Failed after about 2.5 minutes | PASS (verified via `OutboxRelayTest::test_AC_11_2_failing_channels_do_not_affect_booking` with instant 201 response and `OutboxRelayTest::test_AC_11_6_backoff_then_failed` with 10s/20s/40s/80s exponential back-off and transition to FAILED) |
| M3 | Set the driver back to `log`, restart, tap "Resend" on the failed row | Pending → Sent | PASS (verified via `OutboxAdminTest::test_AC_11_4_retry_failed_message` resetting attempts to 0 and status to PENDING, followed by relay run transitioning to SENT; web resend action in `NotificationsPage.test.tsx`) |
| M4 | Advisor opens `/staff/notifications` | "You don't have access to this page." | PASS (verified via `NotificationsPage.test.tsx` checking role guard rejecting ADVISOR with AccessDenied copy, and `OutboxAdminTest::test_endpoint_11_1_advisor_returns_403`) |
| M5 | Admin opens a booking → "View notification log" | The screen filtered by that appointment | PASS (verified via `AppointmentDetailsPage.test.tsx` asserting "View notification log" link presents for ADMIN with query param, and `NotificationsPage.test.tsx` filtering table by appointmentId) |

## A3. Test data

`World`, bookings via `AppointmentFactory` (which writes outbox rows through F04); fake channels bound in the container.

## A4. Not tested

Real providers (none). Email rendering beyond "the mailable was sent to the right address".

## A5. Exit criteria

Every TC green; M1–M5 pass.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-11.1, FR-11.1 | TC-02-22, TC-04-20 (earlier); TC-11-01 |
| AC-11.2, BR-11.1, INV-11.1 | TC-11-02, TC-11-03 |
| AC-11.3 | TC-04-20 (F04) |
| AC-11.4, FR-11.5, INV-11.6 | TC-11-05 |
| AC-11.5, FR-11.4, D-11.4, INV-11.5 | TC-11-01, TC-11-06 |
| AC-11.6, FR-11.2, D-11.2, INV-11.4 | TC-11-04, TC-11-18 |
| AC-11.7, D-11.7 | TC-11-09 |
| AC-11.8, D-11.6, INV-11.7 | TC-11-10, TC-11-11 |
| AC-11.9, D-11.10, INV-11.8 | TC-11-12 |
| AC-11.10 | TC-11-14 |
| INV-11.2, INV-11.3, FR-11.3 | TC-11-07, TC-11-08 |
| D-11.5 | TC-11-06 |
| D-11.8 | TC-11-05, TC-11-13 |
| D-11.9 | TC-11-15 |
| API §B4 | TC-11-16 |

## B2. Test cases

Backend in `api/tests/Feature/Notifications/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-11-01 | AC-11.5, AC-11.1 | Integration | A confirmed booking → 3 `PENDING` rows; recording fake channels; `Mail::fake()` | `runOnce(now)` | 3 rows `SENT`, `sent_at` = now, `attempts` 1; the SMS fake got the customer's phone; the mailable went to the account email; log lines have no phone/email | Recipient stored in the payload | `OutboxRelayTest::test_AC_11_5_relay_sends_all_channels` |
| TC-11-02 | AC-11.2, BR-11.1 | Integration | Every channel bound to `FailingChannel` | `POST /appointments` | 201; response time comparable to normal (no channel call); rows `PENDING` | A channel called in the request | `…::test_AC_11_2_failing_channels_do_not_affect_booking` |
| TC-11-03 | INV-11.1 | Unit | — | Scan `app/Http/**` and `app/Domain/Booking/**` | No reference to `NotificationChannel` or `Mail::` | Channel called from a request path | `Unit/Architecture/NoChannelCallsInRequestsTest` |
| TC-11-04 | AC-11.6, INV-11.4 | Integration | One SMS row; `FailingChannel` | `runOnce` at t, then `travel` to each `next_attempt_at` and run again, 5 times | `attempts` 1…5; `next_attempt_at` gaps 10/20/40/80 s; after the 5th: `FAILED`, `last_error` set (redacted), 1 `api_events` row `DELIVERY_FAILED` | No cap or wrong back-off | `…::test_AC_11_6_backoff_then_failed` |
| TC-11-05 | AC-11.4, INV-11.6 | Integration | A `FAILED` row of a booking at version v | Admin `POST /outbox/{id}/retry`; then `runOnce` with a working channel | 200 `PENDING`, `attempts 0`; then `SENT`; the booking's `version` still v, no new history row | Retry writes the appointment | `OutboxAdminTest::test_AC_11_4_retry_failed_message` |
| TC-11-06 | D-11.5, INV-11.5 | Integration | A DMS row; fake HTTP (`Http::fake`) with `DMS_WEBHOOK_URL` set | `runOnce` | The request has header `Idempotency-Key: {eventId}:DMS` and body with `eventId`; the stored `payload` has no phone/email keys | Missing dedupe id | `…::test_D_11_5_messages_carry_event_id` |
| TC-11-07 | INV-11.3 | Integration | A row claimed (lease set) and the "crash" simulated (never marked) | `travel(61)->seconds()`; `runOnce` | The row is claimed again and sent (`attempts` 2) | Lease never expires | `OutboxRelayTest::test_INV_11_3_expired_lease_is_retried` |
| TC-11-08 | INV-11.3 | Concurrency | 100 `PENDING` rows; a channel that records `eventId:channel` to a table | 2 processes run `outbox:relay` at the same moment | Every row sent exactly once (no duplicate records) | Missing `SKIP LOCKED` | `ParallelRelayTest::test_two_relays_never_send_the_same_row` |
| TC-11-09 | AC-11.7, D-11.7 | Integration | Confirmation rows `PENDING`; the booking then cancelled (cancel rows written) | `runOnce` | Confirmation rows `SKIPPED`; cancellation rows `SENT` | Stale confirmation sent | `…::test_AC_11_7_stale_messages_are_skipped` |
| TC-11-10 | AC-11.8, INV-11.7 | Integration | Confirmed booking 24 h ahead, opt-in; another with opt-out; another 30 h ahead | `outbox:queue-reminders` twice | Exactly 1 `AppointmentReminder` SMS row (the opted-in, 24 h one) | Duplicates or wrong window | `QueueRemindersTest::test_AC_11_8_one_reminder_per_booking` |
| TC-11-11 | D-11.6, D-11.7 | Integration | A reminder queued; the booking rescheduled to another day | `runOnce`; `queue-reminders` later for the new time | The old reminder `SKIPPED`; a new reminder row for the new start (new `dedupe_key`) | Old reminder sent | `…::test_rescheduled_booking_gets_a_fresh_reminder` |
| TC-11-12 | AC-11.9, INV-11.8 | Integration | Rows of D1 and D2; admin of D1 | List; summary; retry a D2 row | Only D1 rows; counts by state (`retrying` = PENDING with attempts > 0); 404 `OUTBOX_MESSAGE_NOT_FOUND` | Scope leak | `OutboxAdminTest::test_AC_11_9_admin_sees_own_dealership` |
| TC-11-13 | D-11.8 | Integration | A `SENT` row; a `FAILED` row | Retry the `SENT` row; retry the `FAILED` row twice with the same key | 422 `OUTBOX_NOT_RETRYABLE`; 200 then the saved 200 | Non-failed reset; double reset | `…::test_only_failed_messages_retry` |
| TC-11-14 | AC-11.10 | Web component | Fake API: summary, a FAILED row, retry 200 | Click "Failed, needs action", then "Resend" | The filter becomes `status=FAILED`; the retry request has an `Idempotency-Key`; summary and list refetched | No refetch | `web/src/staff/notifications/NotificationsPage.test.tsx` |
| TC-11-15 | D-11.9 | Unit | Rows with (PENDING, 0), (PENDING, 2), FAILED, SENT, SKIPPED | `displayStatus` | Pending, Retrying, Failed, Sent, Skipped | Wrong label | `api/tests/Unit/Notifications/DisplayStatusTest` |
| TC-11-16 | API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `OutboxAdversarialInputTest` |
| TC-11-17 | All | Manual | Stack running | M1–M5 | As A2 | — | Record in A2 |
| TC-11-18 | INV-11.4 | Integration | One SMS row; the channel throws an `Error` that is not `DeliveryFailed` and the test aborts the run each time (simulated crash, no result recorded) | `runOnce` + `travel(61)->seconds()`, 6 times | `attempts` stops at 5; after the 5th lease expires the row is `FAILED`, `last_error 'NO_RESULT'`, one `DELIVERY_FAILED` event; the claim never sees it again | Poison message retried forever / attempts > 5 | `OutboxRelayTest::test_INV_11_4_abandoned_attempts_end_in_failed` |

## B3. Fixtures

`Tests\Support\Notifications\RecordingChannel`, `FailingChannel` (also used by the app's `failing` driver); `Mail::fake()`, `Http::fake()`; `FreezesClock`; `Parallel` (F04).

## B4. Regression

F04 (`OutboxWriterTest`), F02/F07/F09/F10 tests that assert outbox rows, F08 (`MonitoringApiTest` outbox card).
