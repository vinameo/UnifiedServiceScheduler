# 01: Prefactor: personal-data redaction processor

**What to build:** Add the `RedactPersonalData` log processor that strips emails, phone numbers, passwords and tokens from log context and messages (D-08.2). No API change.

**Blocked by:** F07 [08-acceptance](../../07-cancel-appointment/tickets/08-acceptance.md) (the whole F07 branch merged to `develop`).

**Status:** ready-for-agent

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-14

- [ ] TC-08-14 (D-08.2, R-08.4; Unit): when `RedactPersonalData` on: `{"user":{"email":"a@b.c"}}`, message "call 0903 123 218"; message "call +84 903 123 218"; `{"appointmentId":"01935512-7712-7abc-9def-0123456789ab"}`; message "booked APT-20261102-0042 at 2026-11-02T09:00:00+07:00"; `{"Authorization":"x"}`; then email dropped; "call [phone]" twice; the UUID unchanged; the reference and the time unchanged; Authorization key dropped. Test: `api/tests/Unit/Support/RedactPersonalDataTest`.
- [ ] No change users can see; existing tests stay green unchanged
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
