# Feature Review · `feature/11-outbox-notifications`

Date: 2026-11-04  
Branch: `feature/11-outbox-notifications`  
Spec: `docs/features/11-outbox-notifications/`

---

## 1. Branch Review Overview

- **Diff Overview:** `git diff develop...HEAD` across 83 files (+6,526 / -64 lines).
- **Tickets Executed (01–11):**
  1. `01-prefactor-notification-channels-and-recipient-resolver`: Introduced `NotificationChannel` interface, `Recipient` DTO, `RecipientResolver` domain service, `LogChannel`, `FailingChannel`, `MailChannel`, `AppointmentNotification` mailable, and `ChannelRegistry`.
  2. `02-outbox-relay-sends-pending-messages`: Added `OutboxRelay` service and `outbox:relay` CLI command, processing pending outbox messages and recording sent status; unit and integration tests.
  3. `03-dms-webhook-channel`: Implemented `DmsWebhookChannel` with HTTP webhook dispatching, `Idempotency-Key: {eventId}:{channel}` header, timeout handling, and error wrapping.
  4. `04-back-off-and-failed`: Implemented exponential back-off (10s, 20s, 40s, 80s) on failed delivery attempts, capping at 5 attempts, transitioning to `FAILED` with redacted `last_error` and logging `DELIVERY_FAILED` `api_events`.
  5. `05-skip-messages-that-are-no-longer-relevant`: Added relevance checking before send (D-11.7) marking stale messages (`AppointmentConfirmed`, `AppointmentRescheduled`, `AppointmentReminder`) as `SKIPPED` when the appointment is cancelled or rescheduled.
  6. `06-leases-and-parallel-relays`: Added concurrency safety using `FOR UPDATE SKIP LOCKED` and 60-second lease (`next_attempt_at = now + 60s`), parallel relay tests with 2 processes over 100 rows, and abandoned lease recovery (`failAbandoned` on 5th attempt).
  7. `07-appointment-reminders`: Added command `outbox:queue-reminders` (`ReminderQueue`) querying confirmed bookings 24 hours ahead with reminder opt-in, inserting deduplicated reminder messages with unique `dedupe_key`.
  8. `08-outbox-list-and-summary-api`: Added admin endpoints `GET /api/v1/outbox` and `GET /api/v1/outbox/summary`, multi-tenant dealership scoping, filters (status, channel, eventType, appointmentId), cursor pagination, and stats summary.
  9. `09-resend-a-failed-message`: Added admin endpoint `POST /api/v1/outbox/{id}/retry` with idempotency key, resetting `FAILED` message back to `PENDING` with `attempts = 0`, rejecting non-failed messages with 422 `OUTBOX_NOT_RETRYABLE`.
  10. `10-outgoing-notifications-screen`: Added React frontend at `/staff/notifications` with stat cards (Pending, Retrying, Failed, needs action, Sent today), filter dropdowns, cursor pagination, badge indicators, Resend action button, access restriction for non-admins, and navigation links from appointment details and monitoring card.
  11. `11-acceptance`: Acceptance verification across all criteria (AC-11.1 to AC-11.10), manual scenarios (M1–M5), BA metrics (M-11.1 to M-11.3), whole-branch review report, and quality checks.

---

## 2. Spec Completeness

- **All Acceptance Criteria (AC-11.1 to AC-11.10):**
  - **AC-11.1 / FR-11.1:** New booking saves one `AppointmentConfirmed` outbox event in the same transaction (`OutboxWriterTest`, `OutboxRelayTest::test_AC_11_5_relay_sends_all_channels`).
  - **AC-11.2 / BR-11.1:** External channel outage never affects booking creation; response remains 201 with normal latency and outbox rows are queued for background processing (`OutboxRelayTest::test_AC_11_2_failing_channels_do_not_affect_booking`).
  - **AC-11.3:** Rolled-back booking transactions write no outbox messages (`OutboxWriterTest`).
  - **AC-11.4 / INV-11.6:** Admin resending a failed message resets status to `PENDING` with `attempts = 0` and leaves appointment version and history untouched (`OutboxAdminTest::test_AC_11_4_retry_failed_message`).
  - **AC-11.5 / D-11.4:** Pending rows for SMS, EMAIL, DMS sent by `outbox:relay`, mailable dispatched to account email, logs contain no phone/email numbers (`OutboxRelayTest::test_AC_11_5_relay_sends_all_channels`).
  - **AC-11.6 / INV-11.4 / D-11.2:** Exponential back-off across 5 attempts (10s, 20s, 40s, 80s), transitioning to `FAILED` with redacted `last_error` and logging `DELIVERY_FAILED` in `api_events` (`OutboxRelayTest::test_AC_11_6_backoff_then_failed`).
  - **AC-11.7 / D-11.7:** Stale messages skipped if appointment cancelled or rescheduled (`OutboxRelayTest::test_AC_11_7_stale_messages_are_skipped`).
  - **AC-11.8 / INV-11.7 / D-11.6:** Scheduled reminders queued for confirmed bookings 24h ahead with reminder opt-in; deduplicated by `dedupe_key = reminder:{appointmentId}:{startAt}` (`QueueRemindersTest::test_AC_11_8_one_reminder_per_booking`).
  - **AC-11.9 / INV-11.8 / D-11.10:** Admin sees only their dealership's outbox messages; foreign dealership message returns 404 `OUTBOX_MESSAGE_NOT_FOUND` (`OutboxAdminTest::test_AC_11_9_admin_sees_own_dealership`).
  - **AC-11.10:** Admin Outgoing notifications screen filters "Failed", taps "Resend", transitioning row to "Pending" and refreshing counters (`NotificationsPage.test.tsx`).

- **Invariants Enforced (INV-11.1 to INV-11.8):**
  - **INV-11.1:** No external channel calls executed inside HTTP request lifecycle; verified by `NoChannelCallsInRequestsTest`.
  - **INV-11.2:** Rows sent only after booking transaction commits; all `PENDING` rows eventually transition to `SENT`, `FAILED`, or `SKIPPED`.
  - **INV-11.3:** Concurrency locking via `FOR UPDATE SKIP LOCKED` and 60-second lease prevents duplicate concurrent sends; verified by `ParallelRelayTest` and `OutboxRelayTest::test_INV_11_3_expired_lease_is_retried`.
  - **INV-11.4:** Delivery attempts strictly capped at 5; abandoned crashed claims fail cleanly via `failAbandoned()` (`OutboxRelayTest::test_INV_11_4_abandoned_attempts_end_in_failed`).
  - **INV-11.5:** No phone numbers or emails in `payload` or `last_error`; resolved at send time from user accounts (`RecipientResolverTest`).
  - **INV-11.6:** Resending modifies only outbox row without touching appointment version or history (`OutboxAdminTest`).
  - **INV-11.7:** At most one reminder per booking per start time enforced via unique `dedupe_key` (`QueueRemindersTest`).
  - **INV-11.8:** Strict multi-tenant isolation by `dealership_id` enforced in all queries (`OutboxAdminTest`).

- **Business Analytics Metrics (§B2):**
  - **M-11.1:** Pending older than 2 minutes: measured 0 (threshold: 0 normally, alert if > 50).
  - **M-11.2:** Failed per channel (7 days): measured 0 rows (threshold: Watch).
  - **M-11.3:** Send delay p95: measured ~2 s within 10s relay interval (well below < 30 s threshold).

---

## 3. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `OutboxRelay`: Solely responsible for claiming, dispatching, and recording delivery outcomes.
  - `ReminderQueue`: Dedicated strictly to discovering reminder candidates and queueing reminder outbox records.
  - `RecipientResolver`: Dedicated strictly to resolving phone/email from active customer accounts.
  - `OutboxService`: Orchestrates admin listing, summary aggregation, and retries.
  - `NotificationChannel` implementations (`LogChannel`, `MailChannel`, `DmsWebhookChannel`, `FailingChannel`): Each encapsulate channel-specific delivery mechanisms.
- **Open/Closed Principle (OCP):**
  - `NotificationChannel` interface allows adding real providers (Twilio, SendGrid, DMS SOAP/REST) without modifying the relay core.
  - `ChannelRegistry` allows dynamic channel registration via configuration (`config/outbox.php`).
- **Liskov Substitution Principle (LSP):**
  - All channel implementations adhere to `NotificationChannel` contract, throwing `DeliveryFailed` on failure.
  - Domain exceptions extend `ProblemException` and render RFC 9457 problem details.
- **Interface Segregation Principle (ISP):**
  - `NotificationChannel` declares only `send(OutboxMessage, Recipient): void`.
  - `OutboxRelayInterface` declares only `runOnce()`.
  - `ReminderQueueInterface` declares only `queueDueReminders()`.
  - `OutboxServiceInterface` cleanly separates admin listing, summary, and retry methods.
- **Dependency Inversion Principle (DIP):**
  - Commands and controllers depend on domain interfaces (`OutboxRelayInterface`, `OutboxServiceInterface`, `ReminderQueueInterface`, `OutboxMessageRepositoryInterface`), bound in `DomainServiceProvider`.

---

## 4. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - All admin endpoints (`/api/v1/outbox/*`) protected by `auth:sanctum` and `can:admin`.
  - Non-admins (advisors, foremen, customers) rejected with 403 `FORBIDDEN`.
  - Multi-tenant scoping strictly enforces `dealership_id` on all queries; cross-dealership access returns 404 `OUTBOX_MESSAGE_NOT_FOUND`.
- **A02:2021 — Cryptographic Failures:**
  - Outbox payload contains only identifiers (UUIDs, references, dates); recipient phone and email are not persisted in database payloads or error logs (INV-11.5).
  - Delivery error strings are sanitized via `RedactPersonalData`.
- **A03:2021 — Injection:**
  - Eloquent ORM and parameterized queries used exclusively; no raw string concatenation.
- **A04:2021 — Insecure Design:**
  - Concurrency protected by `FOR UPDATE SKIP LOCKED` avoiding deadlocks and race conditions.
  - Idempotency enforced via `Idempotency-Key` header on retries (`Idempotency-Key: {eventId}:{channel}` for DMS webhooks).
  - Exponential back-off limits retries to 5 attempts, preventing infinite hammering on external systems.
- **A05:2021 — Security Misconfiguration:**
  - RFC 9457 Problem Details returned across all error responses without stack traces or schema leaks.
- **A06:2021 — Vulnerable and Outdated Components:**
  - Zero external packages added; built with native framework and standard library components.
- **A07:2021 — Identification and Authentication Failures:**
  - Dealership and user context derived strictly from authenticated session token (`auth()->user()`).
- **A08:2021 — Software and Data Integrity Failures:**
  - Outbox message state transitions strictly follow state machine (`PENDING` -> `SENT`/`FAILED`/`SKIPPED`).
  - State reset on retry enforces `status = 'FAILED'` check.
- **A09:2021 — Security Logging and Monitoring Failures:**
  - Exhaustive logging of relay operations; failed deliveries produce `DELIVERY_FAILED` `api_events` for monitoring (F08).
- **A10:2021 — Server-Side Request Forgery (SSRF):**
  - Webhook URL configured via environment `DMS_WEBHOOK_URL`, not user input.

---

## 5. Extensibility against Future Constraints

- **FC-11.1 (Real SMS/email/DMS providers):**
  - Abstracted behind `NotificationChannel` and `ChannelRegistry`; easily configured via `config/outbox.php`.
- **FC-11.2 (Message templates per dealership/language):**
  - `AppointmentNotification` can be extended with localized templates or template engines.
- **FC-11.3 (High volume batching):**
  - Batch size and relay intervals configured in `config/outbox.php` and schedule definition in `routes/console.php`.

---

## 6. Design Pass Checks

1. **One decision, one seam:** Channel abstractions separated behind `NotificationChannel`; recipient resolution separated into `RecipientResolver`; background relay separated into `OutboxRelay`.
2. **Read once, decide on that row:** `FOR UPDATE SKIP LOCKED` claims rows atomically; status check on retry prevents double reset.
3. **Bounded:** Relay batches bounded to 50 rows per run; retries capped at 5 attempts; admin query pagination capped at 200 rows.
4. **Adversarial inputs:** Fully covered in `OutboxAdversarialInputTest` (invalid status/channel, malformed appointment ID, cross-dealership access, non-admin roles, non-retryable status, missing idempotency key).
5. **Migrations meet real data:** Reused existing `outbox_messages` schema without destructive alterations.
6. **Extract before you copy:** Reused shared UI components (`DataTable`, `StatCard`, `SubmitButton`, `ProblemAlert`, `PageHeader`).
7. **Copy from one place:** All user-facing strings sourced from `staff/notifications/copy.ts` and `shared/api/errorCopy.ts`.

---

## 7. Findings & Verification Verdict

- **Automated Tests:**
  - Backend: 608 passed (7,230 assertions), 0 failures.
  - Frontend: 48 test files passed (219 tests), 0 failures.
  - Static Analysis: Pint clean (425 files); PHPStan clean (Level 6, 0 errors, 410 files); ESLint & TypeScript clean.
- **Manual Scenarios (M1–M5):** All verified PASS in `6-test-case.md` §A2.
- **BA Metrics (M-11.1 to M-11.3):** All within target thresholds.
- **Verdict:** **READY TO MERGE** into `develop`.
