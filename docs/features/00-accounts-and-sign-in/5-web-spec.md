# F00 · Accounts and sign-in · Web spec (WEB)

---

# PART A · For readers

Three new screens (not in the drafts), in the drafts' style: `gray.0` background, one white Card in the middle, blue.8 primary button, Inter.

| Screen | Path | Width | Who |
| --- | --- | --- | --- |
| Customer · Sign-up | `/sign-up` | 390 px first | Customer |
| Customer · Sign-in | `/sign-in` | 390 px first | Customer |
| Staff · Sign-in | `/staff/sign-in` | 1440 px | Staff |

Opening a page that needs a session while signed out → redirect to the right sign-in screen for that area, then back to the page after sign-in. The staff web header shows the dealership name, the user's name, role and a "Sign out" button. The left menu only shows items that person may use.

```
Customer · Sign-up (390)             Staff · Sign-in (1440, 400 px card centred)
┌──────────────────────────┐         ┌──────────────────────────────┐
│ Saigon Auto              │         │ [S] Service Scheduler        │
│ Create your account      │         │ Staff sign-in                │
│ Book and manage your car │         │ For dealership staff.        │
│ service appointments.    │         │ Work email [             ]   │
│ Email            [     ] │         │ Password   [             ]   │
│ Password         [     ] │         │ [        Sign in         ]   │
│  At least 8 characters…  │         │ Staff accounts are created   │
│ Confirm password [     ] │         │ by the admin team.           │
│ Full name        [     ] │         └──────────────────────────────┘
│ Phone number     [     ] │
│ [    Create account    ] │
│ Already have an account? │
│ Sign in                  │
└──────────────────────────┘
```

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Routes

| Path | Element | Guard |
| --- | --- | --- |
| `/sign-up` | `customer/auth/SignUpPage` | Only when not signed in as a customer; already a customer → `/book` |
| `/sign-in` | `customer/auth/SignInPage` | Same |
| `/staff/sign-in` | `staff/auth/StaffSignInPage` | Already staff → role home (D-00.11) |
| `/` | redirect to `/book` | — |
| `/book`, `/my-bookings`, … (customer) | pages from F02, F05… | `RequireRole roles={['CUSTOMER']} signInPath="/sign-in"` |
| `/staff/*` (except sign-in) | `StaffLayout` | `RequireRole roles={STAFF_ROLES} signInPath="/staff/sign-in"` |

Until F02 adds `/book`, F00 keeps the existing `customer/CustomerHomePage` behind the guard.

## B2. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-00.1 | `useMe()` | `web/src/shared/auth/useMe.ts` | Query `['me']` → `GET /me`; 401 returns `null` (not an error); `staleTime` 5 minutes. |
| WEB-00.2 | `RequireRole` | `web/src/shared/auth/RequireRole.tsx` | Loading → `Loader`; `me = null` → `Navigate` to `signInPath?returnTo=<path+search>`; wrong role → `Forbidden` page (`FORBIDDEN` copy, "Sign out" button). |
| WEB-00.3 | 401 interceptor | `web/src/shared/api/client.ts` | A 401 `UNAUTHENTICATED` from any route other than `/me` and `/auth/*` → clear the query cache, go to the sign-in screen of the current area (`/staff…` → `/staff/sign-in`, else `/sign-in`) with `returnTo`. |
| WEB-00.4 | `safeReturnTo(value)` | `web/src/shared/auth/returnTo.ts` | Accepts only strings starting with `/` and not with `//` or `/\`; otherwise the role home. |
| WEB-00.5 | `errorCopy(code, vars)` | `web/src/shared/api/errorCopy.ts` | README §5 "UI copy". Unknown code → the `INTERNAL_ERROR` copy. |
| WEB-00.6 | `useAuthActions()` | `web/src/shared/auth/useAuthActions.ts` | `signUp`, `signIn(portal)`, `signOut`: call `ensureCsrfCookie()` before sign-in/sign-up; success → `setQueryData(['me'], me)`; sign-out → `queryClient.clear()`. |
| WEB-00.7 | `homePathFor(role)` | `web/src/shared/auth/homePath.ts` | D-00.11. |

## B3. Screen behaviour

**Sign-up**
- Client-side checks come from one module, `web/src/shared/auth/rules.ts` (email ASCII + format, password 8 characters / 72 bytes / letter + digit, phone 9–15 digits), which copies the numbers of API §B2; its unit test uses the same examples as the API adversarial table (TC-00-34). The server stays the source of truth.
- "Create account" is `loading` and disabled while sending (no double submit).
- 400 → attach `errors` to each field (`passwordConfirmation` → "Confirm password").
- 422 `WEAK_PASSWORD` → error on "Password".
- 409 `EMAIL_ALREADY_USED` → `Alert` above the form with the README §5 copy and a "Sign in" link (carrying the email in `?email=`).
- 201 → go to `returnTo` or `/book`.

**Sign-in (customer and staff)**
- 401 `INVALID_CREDENTIALS` → red `Alert` "Email or password is incorrect." Clear the password, keep the email.
- 429 → `Alert` "Too many attempts. Try again in {minutes} minutes." with `minutes = ceil(Retry-After / 60)`.
- 419 → call `ensureCsrfCookie()` once and resend; still 419 → the `CSRF_TOKEN_MISMATCH` copy.

**Staff header (`StaffLayout`)**: left "Service Scheduler" + "{dealership.name} · {dealership.branchName}"; right initials `Avatar` + name + role label + "Sign out" (default variant). `HealthBadge` leaves the header (moves to the Monitoring screen in F08).

**Left menu** (README §4): group "Operations": New booking (advisor), Appointments (all staff), Workshop board (all staff); group "Administration": Master data, Notifications, Monitoring (admin). Items of features not built yet stay hidden.

## B4. Copy

| Key | Copy |
| --- | --- |
| `signUp.title` | Create your account |
| `signUp.subtitle` | Book and manage your car service appointments. |
| `signUp.email` | Email |
| `signUp.password` | Password |
| `signUp.passwordHint` | At least 8 characters, with a letter and a number. |
| `signUp.confirm` | Confirm password |
| `signUp.fullName` | Full name |
| `signUp.phone` | Phone number |
| `signUp.submit` | Create account |
| `signUp.haveAccount` | Already have an account? Sign in |
| `signUp.mismatch` | Passwords do not match. |
| `signUp.badEmail` | Enter a valid email address. |
| `signUp.badPhone` | Enter a valid phone number. |
| `signIn.title` | Sign in |
| `signIn.submit` | Sign in |
| `signIn.newHere` | New here? Create an account |
| `staffSignIn.title` | Staff sign-in |
| `staffSignIn.subtitle` | For dealership staff. |
| `staffSignIn.email` | Work email |
| `staffSignIn.note` | Staff accounts are created by the admin team. |
| `header.signOut` | Sign out |
| `role.*` | Customer / Service advisor / Workshop foreman / Admin |
| errors | by `code`, README §5 |

## B5. Accessibility

- Every field has a `label`; errors go through Mantine `error` (sets `aria-invalid`, `aria-describedby`).
- Error `Alert`s have `role="alert"`.
- Password fields use `PasswordInput` (labelled show/hide button).
- Touch targets ≥ 44 px at 390 px (the drafts use 44 px).
