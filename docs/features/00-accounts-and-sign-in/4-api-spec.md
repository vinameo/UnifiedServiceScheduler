# F00 · Accounts and sign-in · API spec (API)

---

# PART A · For readers

Five endpoints under `/api/v1`: customer sign-up, customer sign-in, staff sign-in, sign-out, and "who am I" (`GET /me`). Sign-in returns the user and sets the session cookie. Every other API (except sign-up, sign-in and health) needs that cookie.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Endpoints

| ID | Method + path | Auth | Middleware | Success |
| --- | --- | --- | --- | --- |
| API-00.1 | `POST /api/v1/auth/customer/sign-up` | none | `throttle:auth-ip` | 201 `Me` |
| API-00.2 | `POST /api/v1/auth/customer/sign-in` | none | `throttle:auth-ip` | 200 `Me` |
| API-00.3 | `POST /api/v1/auth/staff/sign-in` | none | `throttle:auth-ip` | 200 `Me` |
| API-00.4 | `POST /api/v1/auth/sign-out` | `auth:sanctum` | — | 204 |
| API-00.5 | `GET /api/v1/me` | `auth:sanctum` | — | 200 `Me` |

Every SPA request carries the `XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header (axios does this). Rate limiter `auth-ip`: 30 requests/minute per IP (D-00.6).

## B2. Contracts

### Sign-up (API-00.1)

```json
{ "email": "Hung@Mail.com ", "password": "secret123", "passwordConfirmation": "secret123",
  "fullName": "Nguyễn Văn Hùng", "phone": "0903 123 218" }
```

| Field | Rule | Error |
| --- | --- | --- |
| `email` | required, string, trim + lower before validation, `email:rfc`, ASCII only (D-00.13), ≤ 254 | 400 |
| `password` | required, string, at least 1 character, ≤ 72 **bytes** (`strlen`, D-00.4) | 400 |
| `password` strength | ≥ 8 characters, has `[A-Za-z]`, has `[0-9]` | 422 `WEAK_PASSWORD` |
| `passwordConfirmation` | required, equals `password` | 400 (field `passwordConfirmation`) |
| `fullName` | required, trim, 2–100 characters | 400 |
| `phone` | required, allowed characters per D-00.5, 9–15 digits after normalising | 400 |
| other fields (`role`, `dealershipId`, `customerId`…) | **ignored** | — |

Check order: format (400) → strength (422) → duplicate email (409).

### Sign-in (API-00.2, API-00.3)

```json
{ "email": "minhanh@saigonauto.test", "password": "…" }
```

`email`, `password` required, strings, ≤ 254 / ≤ 72. Otherwise 400. The rest follows RQ §B7.

### `Me` (response of API-00.1, 00.2, 00.3, 00.5)

```json
{
  "id": 12,
  "email": "minhanh@saigonauto.test",
  "name": "Minh Anh",
  "role": "SERVICE_ADVISOR",
  "dealership": { "id": "0193…", "name": "Saigon Auto", "branchName": "District 7",
                  "timezone": "Asia/Ho_Chi_Minh", "phone": "028 3812 3456" },
  "customer": null
}
```

Customer: `dealership: null`, `customer: { "id": "0193…", "fullName": "Nguyễn Văn Hùng", "phone": "0903123218" }`. `phone` is the stored normalised string (D-00.5). The response never contains `password` or `rememberToken`.

### 429

Header `Retry-After: <seconds>`; body `TOO_MANY_ATTEMPTS` with `retryAfterSeconds`.

## B3. Error table

| HTTP | `code` | Endpoint | When |
| --- | --- | --- | --- |
| 400 | `VALIDATION_ERROR` | 00.1–00.3 | RQ §B8 |
| 401 | `INVALID_CREDENTIALS` | 00.2, 00.3 | Wrong email/password/portal |
| 401 | `UNAUTHENTICATED` | 00.4, 00.5, every authenticated route | No session |
| 403 | `FORBIDDEN` | routes with a Gate | Wrong role |
| 409 | `EMAIL_ALREADY_USED` | 00.1 | Duplicate email |
| 419 | `CSRF_TOKEN_MISMATCH` | every stateful POST | Missing/wrong XSRF |
| 422 | `WEAK_PASSWORD` | 00.1 | Weak password |
| 429 | `TOO_MANY_ATTEMPTS` | 00.1–00.3 | Locked by email (00.2, 00.3) or by IP |

Copy: README §5.

## B4. Adversarial input table

| Endpoint | Input | Expected |
| --- | --- | --- |
| sign-up | `email` is an array `["a@b.c"]` | 400 |
| sign-up | `email` 300 characters | 400 |
| sign-up | `password` 73 characters | 400 |
| sign-up | `password` = `12345678` (no letter) | 422 `WEAK_PASSWORD` |
| sign-up | `password` = `abcdefgh` (no digit) | 422 `WEAK_PASSWORD` |
| sign-up | `phone` = `abc123` | 400 |
| sign-up | `phone` = `+84 90 312 3218 999 999` (> 15 digits) | 400 |
| sign-up | `fullName` = `"   "` | 400 |
| sign-up | body has `"role": "ADMIN"` | 201, the account is `CUSTOMER` |
| sign-up | body is not JSON | 400 |
| sign-in | `email` = `null` | 400 |
| sign-in | `password` 10 000 characters | 400 (bcrypt never runs) |
| sign-up | `email` = `ánh@mail.com` (non-ASCII) | 400 (never 500 from the lower-case CHECK) |
| sign-up | `password` = 30 Vietnamese characters with letters and a digit (> 72 bytes) | 400 |
| sign-in | email with surrounding spaces and upper case | Normalised, signs in normally |
| sign-in | no XSRF header | 419 |

## B5. Bounds

| Bound | Value |
| --- | --- |
| Failures per email | 5 / 15 minutes, locked 15 minutes |
| Requests per IP on the 3 auth routes | 30 / minute |
| Length of email / password / name / phone | 254 characters / 72 bytes / 100 characters / 15 digits |
| Idle session | 120 minutes (`SESSION_LIFETIME`) |

Logs: one line per failed sign-in (`outcome=INVALID_CREDENTIALS` or `TOO_MANY_ATTEMPTS`), without the email; F08 adds a counter.
