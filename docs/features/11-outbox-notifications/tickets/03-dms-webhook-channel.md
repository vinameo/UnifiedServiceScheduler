# 03: DMS webhook channel

**What to build:** The DMS channel posts to `DMS_WEBHOOK_URL` with `Idempotency-Key: {eventId}:DMS` and the event id in the body (D-11.5, INV-11.5).

**Blocked by:** [02-outbox-relay-sends-pending-messages](02-outbox-relay-sends-pending-messages.md)

**Status:** done

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-06

- [x] TC-11-06 (D-11.5, INV-11.5; Integration): given A DMS row; fake HTTP (`Http::fake`) with `DMS_WEBHOOK_URL` set; when `runOnce`; then The request has header `Idempotency-Key: {eventId}:DMS` and body with `eventId`; the stored `payload` has no phone/email keys. Test: `…::test_D_11_5_messages_carry_event_id`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
