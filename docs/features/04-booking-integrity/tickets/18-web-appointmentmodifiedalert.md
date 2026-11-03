# 18: Web: AppointmentModifiedAlert

**What to build:** `AppointmentModifiedAlert` shows what changed after a 412 and lets the user retry with a new key and the new `ETag` (WEB-04.5, WEB-04.6, D-04.12).

**Blocked by:** [17-web-submitbutton-and-idempotentretrypanel](17-web-submitbutton-and-idempotentretrypanel.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-38, TC-04-40

- [x] TC-04-38 (WEB-04.5, WEB-04.6; Web component): given `before` CONFIRMED 09:00 An; `latest` CANCELLED 09:00 An; when Render the alert; then "Status: Confirmed → Cancelled"; no Time/Technician rows; the button calls `onReview(latest, '"4"')`; no request sent. Test: `…/AppointmentModifiedAlert.test.tsx`, `diffAppointment.test.ts`.
- [x] TC-04-40 (WEB-04.1, README D-G.15, D-04.12; Web component): given Fake API: first cancel → 412 with `latest` (`ETag "4"`), second → 200; when Submit cancel (`If-Match "3"`), tap "Review and retry", submit again; then The second request has `If-Match "4"` and a **different** `Idempotency-Key`; 200 shown. Test: `web/src/shared/idempotency/useIdempotencyKey.test.ts` (with `AppointmentModifiedAlert`).
- [x] README §8 checks green; no existing test skipped, deleted or weakened
