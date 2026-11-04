# 05: Permissions per workshop action

**What to build:** Advisors may check in and mark no-show; start, complete and extend are foreman only (README §4).

**Blocked by:** [04-no-show](04-no-show.md)

**Status:** done

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-22

- [x] TC-12-22 (README §4; Integration): given Advisor; when Start, complete, extend; then 403 each; check-in and no-show 200. Test: `WorkshopRolesTest` (data provider).
- [x] README §8 checks green; no existing test skipped, deleted or weakened
