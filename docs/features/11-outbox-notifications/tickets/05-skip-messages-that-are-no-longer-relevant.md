# 05: Skip messages that are no longer relevant

**What to build:** A confirmation for a booking that was cancelled before sending is `SKIPPED`, while the cancellation is `SENT` (AC-11.7, D-11.7).

**Blocked by:** [04-back-off-and-failed](04-back-off-and-failed.md)

**Status:** done

**Branch:** `feature/11-outbox-notifications`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-11-09

- [x] TC-11-09 (AC-11.7, D-11.7; Integration): given Confirmation rows `PENDING`; the booking then cancelled (cancel rows written); when `runOnce`; then Confirmation rows `SKIPPED`; cancellation rows `SENT`. Test: `…::test_AC_11_7_stale_messages_are_skipped`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
