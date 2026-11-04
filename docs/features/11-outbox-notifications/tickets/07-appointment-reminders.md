# 07: Appointment reminders

**What to build:** `outbox:queue-reminders` queues exactly one `AppointmentReminder` per booking, also after a reschedule for the new time (AC-11.8, INV-11.7, D-11.6).

**Blocked by:** [06-leases-and-parallel-relays](06-leases-and-parallel-relays.md)

**Status:** done

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-10, TC-11-11

- [x] TC-11-10 (AC-11.8, INV-11.7; Integration): given Confirmed booking 24 h ahead, opt-in; another with opt-out; another 30 h ahead; when `outbox:queue-reminders` twice; then Exactly 1 `AppointmentReminder` SMS row (the opted-in, 24 h one). Test: `QueueRemindersTest::test_AC_11_8_one_reminder_per_booking`.
- [x] TC-11-11 (D-11.6, D-11.7; Integration): given A reminder queued; the booking rescheduled to another day; when `runOnce`; `queue-reminders` later for the new time; then The old reminder `SKIPPED`; a new reminder row for the new start (new `dedupe_key`). Test: `…::test_rescheduled_booking_gets_a_fresh_reminder`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
