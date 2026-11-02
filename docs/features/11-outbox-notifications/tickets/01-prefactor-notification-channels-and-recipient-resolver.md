# 01: Prefactor: notification channels and recipient resolver

**What to build:** Add the `NotificationChannel` interface with `log` and `failing` drivers for SMS and DMS, the Mail-based EMAIL channel and `RecipientResolver`; no relay yet. A scan keeps channel calls out of `app/Http` and `app/Domain/Booking` (INV-11.1).

**Blocked by:** F10 [11-acceptance](../../10-temporary-hold/tickets/11-acceptance.md) (the whole F10 branch merged to `develop`).

**Status:** ready-for-agent

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-03

- [ ] TC-11-03 (INV-11.1; Unit): when Scan `app/Http/**` and `app/Domain/Booking/**`; then No reference to `NotificationChannel` or `Mail::`. Test: `Unit/Architecture/NoChannelCallsInRequestsTest`.
- [ ] No change users can see; existing tests stay green unchanged
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
