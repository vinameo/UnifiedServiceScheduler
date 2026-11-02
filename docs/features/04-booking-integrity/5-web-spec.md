# F04 · No double booking, no duplicate requests · Web spec (WEB)

---

# PART A · For readers

F04 has no screen of its own. It delivers three shared pieces that every writing screen plugs in (draft `docs/designs/Admin/screenshots/03-booking-error-states.png`):

1. **A locked button while sending** ("Confirming…") so two different requests cannot be sent.
2. **"No response · safe retry"**: when the network does not answer, "Retry" is safe because the request reuses the same key.
3. **"Changed by someone else"** (412): shows the latest version, says what changed, lets the user try again.

```
┌ (!) No response from the server yet ───────────────────────────────┐
│ It is safe to retry. The request reuses key 7f3c…e21a, so if the  │
│ first attempt succeeded the system returns that same appointment  │
│ instead of creating a second one.                                  │
│ [Retry]  [Check appointment list]                                  │
└────────────────────────────────────────────────────────────────────┘
┌ (!) This appointment was changed by someone else ──────────────────┐
│ Status: Confirmed → Cancelled                                      │
│ Review the latest details and try again.        [Review and retry] │
└────────────────────────────────────────────────────────────────────┘
```

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-04.1 | `useIdempotencyKey(actionInput)` | `web/src/shared/idempotency/useIdempotencyKey.ts` | Returns `{ key, reset }`. A new key (`crypto.randomUUID()`) when `actionInput` changes (deep compare with stable JSON); the same key when the same input is resent after a network error, 409 `REQUEST_IN_PROGRESS`, or 5xx; `settle(result)` resets the key after every other answer (2xx and every other 4xx, including 409 `NO_AVAILABILITY`, 412 and 422), so trying again never replays a saved answer (README D-G.15). Change mutations put the `etag` they send into `actionInput`. `reset()` is also called when the user starts a new action. The key lives only in component state (never localStorage). |
| WEB-04.2 | `idempotentPost(url, body, key, options)` | `web/src/shared/idempotency/idempotentPost.ts` | Adds `Idempotency-Key` and `If-Match` (when given); 15 s timeout; returns `{ kind: 'ok' | 'problem' | 'no-response', … }`. "No response" = an axios error with no `response` (timeout, network). |
| WEB-04.3 | `SubmitButton` | `web/src/shared/idempotency/SubmitButton.tsx` | Mantine `Button`, `loading` + `disabled` while pending; a pending label (for example "Confirming…"). A second click while pending sends nothing. |
| WEB-04.4 | `IdempotentRetryPanel` | `web/src/shared/idempotency/IdempotentRetryPanel.tsx` | Shown on `no-response` or 409 `REQUEST_IN_PROGRESS`. "Retry" resends the **same key and body**; a secondary button comes from the screen (for example "Check appointment list"). The `staff` variant shows the shortened key (first 4 + "…" + last 4 characters); the `customer` variant hides it. |
| WEB-04.5 | `AppointmentModifiedAlert` | `web/src/shared/appointments/AppointmentModifiedAlert.tsx` | Takes `before` (what is on screen) and `latest` (from the 412 body). Lists changes in order: status, time, technician, bay, vehicle ("Status: Confirmed → Cancelled"). "Review and retry" calls `onReview(latest, etag)`; the screen updates the cache (F05 `appointmentQuery`) and lets the user decide again. Never resends by itself. |
| WEB-04.6 | `diffAppointment(before, latest)` | `web/src/shared/appointments/diffAppointment.ts` | Pure function for WEB-04.5. |

## B2. Rules

- One user action = one key (`CLAUDE.md`). Changing any field that affects the body → a new key.
- A key is reused only to resend after no response, a 5xx or 409 `REQUEST_IN_PROGRESS`. Any other answer (2xx, 409 `NO_AVAILABILITY`, 412, 422…) is final for that key: the hook resets, so the next try gets a new key (README D-G.15). Otherwise the server replays the saved answer (D-04.12).
- Never resend a mutation automatically (`AppProviders` already sets `mutations.retry: false`). Resend only when the user taps "Retry".
- Never retry a 412 automatically (BR-04.2).
- A 428 is a programming error: show the `PRECONDITION_REQUIRED` copy and `console.error` with the `correlationId`.

## B3. Copy

| Key | Copy |
| --- | --- |
| `retry.title` | No response from the server yet |
| `retry.bodyStaff` | It is safe to retry. The request reuses key {key}, so if the first attempt succeeded the system returns that same appointment instead of creating a second one. |
| `retry.bodyCustomer` | No response yet. It's safe to try again: you won't be booked twice. |
| `retry.button` | Retry |
| `inProgress.body` | Your request is still being processed. Wait a moment, then check again. |
| `submitting.confirm` | Confirming… |
| `modified.title` | This appointment was changed by someone else |
| `modified.body` | Review the latest details and try again. |
| `modified.button` | Review and retry |
| `modified.field.status` | Status: {from} → {to} |
| `modified.field.time` | Time: {from} → {to} |
| `modified.field.technician` | Technician: {from} → {to} |
| `modified.field.bay` | Bay: {from} → {to} |
| `doubleSubmitNote` | Each booking carries its own request key ({key}). On a flaky network, pressing again is safe and never creates a duplicate. |

`doubleSubmitNote` appears only on the staff New booking screen (draft Main), used by F02.
