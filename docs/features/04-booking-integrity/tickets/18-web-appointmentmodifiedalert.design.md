# 18: Web: AppointmentModifiedAlert — Design Notes

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| `diffAppointment(before, latest)` field ordering (WEB §4 WEB-04.6) | WEB-04.6, TC-04-38 | `web/src/shared/appointments/diffAppointment.ts` | computes differences strictly in order: status, time, technician, bay, vehicle | fields with identical before/latest values are omitted | `docs/features/04-booking-integrity/5-web-spec.md:44` | follow |
| `AppointmentModifiedAlert` review callback and non-autosend (WEB §4 WEB-04.5, WEB §B2) | WEB-04.5, BR-04.2 | `web/src/shared/appointments/AppointmentModifiedAlert.tsx` | renders diff list, title, body, and button; invokes `onReview(latest, etag)` on click | never triggers automatic resend; leaves decision to user/screen | `CLAUDE.md:144` | follow |
| 412 retry workflow generates new Idempotency-Key and uses new ETag (WEB §4 WEB-04.1, TC-04-40) | WEB-04.1, D-04.12, D-G.15 | `web/src/shared/appointments/AppointmentModifiedAlert.test.tsx` | first request fails with 412, review updates etag and resets key; second request uses new ETag and fresh UUID | prevents infinite replay of saved 412 response | `docs/features/README.md:66` (D-G.15) | follow |

