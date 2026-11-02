# F11 · Notifications through the outbox · Requirement spec (RQ)

Branch: `feature/11-outbox-notifications` · Priority: Could · Shared conventions: [README](../README.md) · Read first: SDD §12 "Notifications", `CLAUDE.md` rule 4

---

# PART A · For readers

## A1. Story

**Why this feature exists.** Customers expect an SMS or email when a booking is confirmed, cancelled or moved, and a reminder the day before; the dealer's DMS needs the same events. But a slow or broken SMS provider must never slow down or break a booking.

**The problem.** Since F04, every booking change already writes messages to `outbox_messages` in the same transaction, but nothing sends them.

**What it does.**
- A background relay (`outbox:relay`, every 10 seconds) sends pending messages to SMS, email and DMS, after the booking was committed.
- A failed send is retried up to 5 times with a growing wait; then the message is marked **Failed** for an admin to handle.
- Delivery is "at least once": every message carries its event id so receivers can drop repeats.
- A reminder job queues an SMS the day before each confirmed booking whose customer opted in.
- An admin screen "Outgoing notifications" shows counts by state, filters by status, channel, event and appointment, and lets the admin resend a failed message.

**Who uses it.** The system sends; admins view and resend.

**What it does not do.** Real SMS/DMS provider integrations (this version ships log-based drivers and a failing driver for demos), message templates editable in the UI, customer notification preferences beyond the reminder opt-in.

## A2. Usage steps

1. A booking is confirmed → within ~10 seconds the relay sends the SMS, email and DMS messages.
2. The SMS provider is down → the booking still succeeds; the SMS is retried after 10 s, 20 s, 40 s, 80 s; after the 5th failure it shows as "Failed" with the error.
3. Admin → Administration → Notifications → filter "Failed" → "Resend" → the message goes back to "Pending" and is sent on the next relay run.

## A3. Screens and deviations from the drafts

Draft: `Admin/screenshots/10-notifications-outbox.png`.

| Draft | Spec | Reason |
| --- | --- | --- |
| Menu visible to an advisor | Admin only | Requirements "Admin views the outbox" |
| Appointment details "View notification log" link | Shown to admins only, opens the screen filtered by the appointment | Same |
| — | A "Skipped" status for messages no longer relevant (cancelled before a reminder was sent) | D-11.7 |

## A4. Key rules in plain words

- A message exists only if its booking change was saved; a rolled-back booking sends nothing.
- A failing channel never touches the booking.
- Up to 5 attempts, waiting longer each time; then "Failed".
- Resending a failed message does not change the booking.
- Messages never store phone numbers or emails; the relay looks them up from the customer's account when sending.

## A5. Out of scope

Real providers, delivery receipts, message templates in the UI, unsubscribe links.

## A6. Success criteria

- A booking's response time does not change when every channel is failing (AC-11.2).
- Pending messages older than 2 minutes: 0 in normal operation (M-11.1).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-11.1 | Relay `outbox:relay` scheduled `everyTenSeconds()->withoutOverlapping()` (`CLAUDE.md`). Each run claims up to 50 due rows with `FOR UPDATE SKIP LOCKED`, sets a 60-second lease (`next_attempt_at = now + 60s`) and `attempts + 1`, commits, then sends outside any transaction. |
| D-11.2 | Back-off after a failed attempt n (1–4): `next_attempt_at = now + 10 s × 2^(n−1)` (10, 20, 40, 80 s). After the 5th failure → `FAILED`, `last_error` (redacted, ≤ 500 characters), plus an `api_events` row `DELIVERY_FAILED` (F08). |
| D-11.3 | Channel drivers behind one interface `NotificationChannel::send(OutboxMessage, Recipient)`: `SMS` and `DMS` use a `log` driver by default (one JSON log line, no personal data); `EMAIL` uses Laravel Mail with the configured mailer (`MAIL_MAILER=log` locally). A `failing` driver (env `OUTBOX_SMS_DRIVER=failing` etc.) always throws, for demos and tests. |
| D-11.4 | The recipient (phone, email) is resolved at send time from `customers.phone` and `users.email` (FR-11.4); the payload holds only ids, reference, times and codes. |
| D-11.5 | Each sent message carries `eventId` and `channel` (DMS: header `Idempotency-Key: {eventId}:{channel}`) so receivers can drop duplicates (FR-11.3). |
| D-11.6 | Reminders: `outbox:queue-reminders` hourly inserts one `AppointmentReminder` SMS row for each `CONFIRMED` booking with `reminder_opt_in` whose start is in `[now + 23 h, now + 25 h)`, with `dedupe_key = reminder:{appointmentId}:{startAt}` and `ON CONFLICT DO NOTHING`. It is not a booking change, so it runs in its own small transaction. |
| D-11.7 | Before sending, the relay checks relevance: `AppointmentConfirmed`, `AppointmentRescheduled`, `AppointmentReminder` for a booking that is no longer `CONFIRMED`, or whose start differs from the payload, are marked `SKIPPED` (not sent). `AppointmentCancelled` and `AppointmentReassigned` are always sent. |
| D-11.8 | Admin API: list with filters and cursor paging, a summary of counts, and `POST /outbox/{id}/retry` (Idempotency-Key). Retry only for `FAILED` (else 422 `OUTBOX_NOT_RETRYABLE`, new code): sets `PENDING`, `attempts = 0`, `next_attempt_at = now`, `last_error = NULL`. No appointment write (FR-11.5). |
| D-11.9 | Status labels: "Pending" = `PENDING` with `attempts = 0`; "Retrying" = `PENDING` with `attempts > 0`; "Failed"; "Sent"; "Skipped". |
| D-11.10 | Admins see only their dealership's messages; another dealership's message id → 404 `OUTBOX_MESSAGE_NOT_FOUND` (new code). |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-11.a | Which real SMS/DMS providers? | None in this version; drivers are swappable (FC-11.1). |
| Q-11.b | Should advisors see the notification log? | No (admin only). |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00–F10 are merged: table `outbox_messages` and `OutboxWriter` (F04), rows written by F02, F07, F09, F10; `RedactPersonalData` and `ApiEventRepository` (F08); `CursorPaginator` (F05); the scheduler container; Laravel Mail (`config/mail.php`, `MAIL_MAILER`). No relay exists: rows stay `PENDING`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-11.1 | Command `outbox:relay` + `OutboxRelay` service. |
| S-11.2 | `NotificationChannel` interface + `log`, `failing` drivers for SMS/DMS, Mail-based EMAIL, `RecipientResolver`. |
| S-11.3 | Command `outbox:queue-reminders`. |
| S-11.4 | Admin API: list, summary, retry. |
| S-11.5 | Web: Outgoing notifications screen; links from F08 and appointment details. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-11.1 | No SMS/email/DMS call ever runs inside an HTTP request (`CLAUDE.md` rule 4). | Only `OutboxRelay` calls channels; architecture test. |
| INV-11.2 | A row is sent only after its booking transaction committed (it is not visible before) and is never lost: every `PENDING` row is eventually `SENT`, `FAILED` or `SKIPPED`. | F04 writes in the same transaction; the relay picks committed rows only. |
| INV-11.3 | Two relay runs never send the same row at the same time: rows are claimed with `FOR UPDATE SKIP LOCKED` and a 60-second lease. A crash during send → the lease expires and the row is retried (at least once, FR-11.3). | `OutboxRelay::claim`. |
| INV-11.4 | `attempts` never exceeds 5; the 5th failure sets `FAILED` (FR-11.2). | `OutboxRelay::recordFailure`. |
| INV-11.5 | `payload` and `last_error` hold no phone numbers or emails; recipients are resolved at send time. | `OutboxWriter` (F04) + `RedactPersonalData` on errors. |
| INV-11.6 | Retrying a failed message changes only that outbox row; no appointment write, no version change (FR-11.5). | `RetryOutboxMessage`. |
| INV-11.7 | One reminder per booking per start time, even if the reminder job runs several times. | Unique `dedupe_key`. |
| INV-11.8 | Admins act only on their dealership's messages (404 otherwise). | `WHERE dealership_id` in every query. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Delivery (INV-11.2–11.4) | Channel down; down 5 times; crash mid-send; two relays at once | TC-11-02, TC-11-04, TC-11-07, TC-11-08 |
| Relevance (D-11.7) | Booking cancelled before the confirmation was sent; rescheduled before the reminder | TC-11-09 |
| Reminders (INV-11.7) | Job runs twice; booking rescheduled after the reminder was queued; opt-out | TC-11-10, TC-11-11 |
| Retry (INV-11.6, D-11.8) | `FAILED`; `SENT`; another dealership; same key twice | TC-11-05, TC-11-12, TC-11-13 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Notifications/OutboxRelay.php` | Claim, send, record (B7). |
| `api/app/Domain/Notifications/Channels/{NotificationChannel,LogChannel,FailingChannel,MailChannel}.php`, `ChannelRegistry.php` | D-11.3; drivers chosen by `config/outbox.php` (`OUTBOX_SMS_DRIVER`, `OUTBOX_EMAIL_DRIVER`, `OUTBOX_DMS_DRIVER`). |
| `api/app/Domain/Notifications/RecipientResolver.php` | D-11.4. |
| `api/app/Domain/Notifications/Mail/AppointmentNotification.php` | Plain-text mailable per event type. |
| `api/app/Domain/Notifications/QueueReminders.php`, `api/app/Console/Commands/{RelayOutbox,QueueReminders}.php` | D-11.1, D-11.6. |
| `api/routes/console.php` | `outbox:relay` every 10 s `withoutOverlapping()`; `outbox:queue-reminders` hourly. |
| `api/app/Domain/Notifications/Actions/RetryOutboxMessage.php`, `Exceptions/{OutboxMessageNotFound,OutboxNotRetryable}.php` | D-11.8, D-11.10. |
| `api/app/Http/Controllers/Api/V1/OutboxController.php`, `Resources/OutboxMessageResource.php` | API. |
| `api/app/Providers/AuthServiceProvider.php` | Ability `admin` reused. |
| `api/config/outbox.php`, `api/.env.example` | Driver settings, `DMS_WEBHOOK_URL` (optional). |
| `web/src/staff/notifications/*`, `web/src/staff/monitoring/*` (View link), `web/src/staff/appointments/AppointmentDetailsPage.tsx` (link) | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

```php
// illustrative
interface NotificationChannel {
    /** @throws DeliveryFailed */
    public function send(OutboxMessage $message, Recipient $recipient): void;
}
final class OutboxRelay {
    /** INV-11.2–11.4. Returns counts per outcome. */
    public function runOnce(CarbonImmutable $now, int $batch = 50): RelayResult;
}
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `NotificationChannel` + `ChannelRegistry` | Real providers later (FC-11.1) |
| `OutboxRelay` | F12 `AppointmentReassigned` messages need nothing new |

FCs: FC-11.1 (real SMS/DMS providers) → a new `NotificationChannel`; FC-11.2 (templates per dealership) → `AppointmentNotification` takes a template key.

## B7. Algorithms

**`runOnce(now)`**
1. Transaction: `SELECT … FROM outbox_messages WHERE status = 'PENDING' AND next_attempt_at <= :now ORDER BY next_attempt_at LIMIT 50 FOR UPDATE SKIP LOCKED`; `UPDATE` those rows `attempts = attempts + 1, next_attempt_at = :now + 60s`; commit.
2. For each claimed row, outside any transaction:
   1. Relevance check (D-11.7) → `SKIPPED`.
   2. `RecipientResolver` → recipient; none (deleted account) → `SKIPPED` with `last_error = 'NO_RECIPIENT'`.
   3. `channel->send(…)`: success → `status SENT, sent_at = now`; `DeliveryFailed` → `attempts >= 5` ? `FAILED` + `api_events DELIVERY_FAILED` : `next_attempt_at = now + 10s × 2^(attempts−1)`; `last_error` redacted.
   4. Each result update is a single `UPDATE … WHERE id = ? AND status = 'PENDING'`.
3. One log line per run with the counts.

**Retry**: `UPDATE outbox_messages SET status='PENDING', attempts=0, next_attempt_at=:now, last_error=NULL WHERE id=:id AND dealership_id=:d AND status='FAILED'` → 0 rows: missing → 404, else 422.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| Bad filter values, `limit`, `cursor` | 400 | `VALIDATION_ERROR` |
| Missing `Idempotency-Key` on retry | 400 | `IDEMPOTENCY_KEY_REQUIRED` |
| Not an admin | 403 | `FORBIDDEN` |
| Message missing or another dealership's | 404 | `OUTBOX_MESSAGE_NOT_FOUND` |
| Retry of a non-`FAILED` message | 422 | `OUTBOX_NOT_RETRYABLE` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-11.1 | A new booking is saved | Look at the outbox | One `AppointmentConfirmed` event, written in the same transaction | TC-02-22, TC-04-20 (proved earlier); TC-11-01 sends it |
| AC-11.2 | The SMS provider is down | Book a service | 201 for the booking; the outbox row is retried later | TC-11-02 |
| AC-11.3 | A booking transaction is rolled back | Look at the outbox | No row for it, so no message is sent | TC-04-20 (proved in F04) |
| AC-11.4 | A row failed 5 times | Admin taps Retry | Status goes back to `PENDING` and the relay sends it again | TC-11-05 |
| AC-11.5 (new) | Pending rows for SMS, EMAIL, DMS | `outbox:relay` | All `SENT`; the email went to the customer's account email; the log line has no phone/email | TC-11-01 |
| AC-11.6 (new) | SMS driver failing | Relay runs at +0, +10, +30, +70, +150 s | `attempts` 1…5, waits 10/20/40/80 s, then `FAILED` with `last_error` and an `api_events` `DELIVERY_FAILED` row | TC-11-04 |
| AC-11.7 (new) | A confirmation not yet sent; the booking is cancelled | Relay runs | The confirmation is `SKIPPED`; the cancellation is `SENT` | TC-11-09 |
| AC-11.8 (new) | A confirmed booking 24 h ahead with reminder opt-in | `outbox:queue-reminders` twice | Exactly one `AppointmentReminder` row | TC-11-10 |
| AC-11.9 (new) | Admin of D1 | List, summary, retry a D2 message | Only D1 rows; counts by state; 404 | TC-11-12 |
| AC-11.10 (new) | Admin opens Outgoing notifications | Filter "Failed", tap "Resend" | The row moves to "Pending"; the counts update | TC-11-14 |

## B10. Implementation order

1. Ticket 01 (prefactor): `NotificationChannel` + `log`/`failing` drivers + `RecipientResolver`; no relay yet.
2. Tracer bullet: `outbox:relay` sending to log drivers + AC-11.5, then `/code-review`.
3. Back-off, `FAILED`, relevance, leases.
4. Reminders.
5. Admin API + screen + links.
6. Acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `php artisan schedule:list` shows `outbox:relay` and `outbox:queue-reminders`.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-11.1 | A slow channel makes a relay run exceed 10 s | `withoutOverlapping()`; 50-row batches; channel timeouts of 5 s |
| R-11.2 | A crash after sending but before marking `SENT` → duplicate send | Accepted (at least once, FR-11.3); receivers dedupe by `eventId` |
| R-11.3 | Back-off computed from the SQL clock | Use the app clock (README D-G.6) |
