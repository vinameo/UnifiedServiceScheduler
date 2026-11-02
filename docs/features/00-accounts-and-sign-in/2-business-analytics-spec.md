# F00 · Accounts and sign-in · Business analysis (BA)

---

# PART A · For readers

## A1. Why F00 comes first

Every permission rule in the requirements ("customers see only their own", "staff see only their dealership", AC-00.7, AC-00.8) needs to know who is calling and which dealership they belong to. F00 is the foundation; without it, F02 onward cannot test permissions.

## A2. Evidence

| ID | Source | Content |
| --- | --- | --- |
| E-00.1 | Requirements F00 | 4 roles; customers sign up; staff come from seed data; 9 ACs. |
| E-00.2 | `CLAUDE.md` §7 | Sanctum SPA cookie, `SESSION_DRIVER=database`, 5/15-minute rate limit, the error never says which part was wrong. |
| E-00.3 | Code | `users` exists (bigint id), `personal_access_tokens` exists, the renderer already maps 401/403/429. |
| E-00.4 | Designs | No sign-in screens; staff header shows dealership + name + role. |
| E-00.5 | NFR-05 | HTTPS, hashed passwords, rate limit, CSRF, no personal data in logs. |

## A3. Priorities

| Priority | Items |
| --- | --- |
| Must | Sign-up, sign-in on 2 portals, sign-out, `GET /me`, Gates, lock per email, seed |
| Should | Per-IP limit, `returnTo` redirect |
| Could | Tokens for external clients (deferred, D-00.7) |

## A4. Business risks

| ID | Risk | Handling |
| --- | --- | --- |
| RB-00.1 | Someone fails 5 times on purpose to lock a customer out | Accepted: the lock lasts 15 minutes; the 429 message gives the wait time. Owner: product owner. Revisit when M-00.3 > 30% for 1 hour, or support gets > 3 lock-out complaints a week. |
| RB-00.4 | Sign-up reveals which emails have an account (D-00.14) | Accepted (ADR-00.5). Owner: product owner. Revisit when M-00.5 alerts (> 50 in an hour). |
| RB-00.2 | A customer types a wrong phone number → no SMS | Phone verification is out of scope; the number is shown on the confirmation screen so the customer can check it. |
| RB-00.3 | One person is both staff and customer | BR-00.1: two emails. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. ADRs

### ADR-00.1 · Sanctum SPA cookie session, no token in localStorage

- **Context:** two web apps on one domain behind Nginx.
- **Options:** (a) HttpOnly cookie + CSRF (Sanctum SPA); (b) bearer token stored in the browser.
- **Chosen: (a).** JavaScript cannot read an HttpOnly cookie (no token theft by XSS); `CLAUDE.md` requires it.
- **Implementation constraints:** call `GET /sanctum/csrf-cookie` before sign-in/sign-up; every request uses `withCredentials`; `SESSION_DRIVER=database` so every PHP-FPM worker sees the session; regenerate the session id on sign-in.

### ADR-00.2 · Failed sign-in counter in the `database` cache

- **Options:** (a) `database` cache; (b) Redis; (c) a `failed_attempts` column on `users`.
- **Chosen: (a).** Compose has no Redis; (c) cannot count unknown emails and reveals existence through behaviour. Laravel `RateLimiter` on the `database` cache is shared by every worker.
- **Constraint:** key on `sha256(lower(trim(email)))`; never store the raw email in the cache table.

### ADR-00.3 · `customers` separate from `users`

- **Options:** (a) `full_name`, `phone` columns on `users`; (b) a 1–1 `customers` table.
- **Chosen: (b).** Appointments and vehicles reference `customers.id` (UUID), not `users.id`; later, walk-in customers without an account need no foreign-key change.
- **Constraint:** in this version every customer has an account (`user_id NOT NULL UNIQUE`).

### ADR-00.5 · Sign-up says "email already used"

- **Options:** (a) 409 `EMAIL_ALREADY_USED` with a "Sign in instead" link; (b) always answer "check your email" and send a mail (needs email verification, out of scope); (c) a generic error for every sign-up failure.
- **Chosen: (a).** Without email sending, (b) is impossible and (c) leaves real users stuck.
- **Cost accepted:** anyone can check whether an email has an account, at 30 requests/minute per IP. Sign-in still hides it (INV-00.4). Revisit with email verification (RB-00.4).

### ADR-00.4 · `dealerships` created in F00

- Staff need a dealership as soon as they are seeded, and F00 is built before F01. Create the minimal table in F00; F01 adds opening hours and the rest.

## B2. Metrics

| ID | Metric | SQL / source | Threshold |
| --- | --- | --- | --- |
| M-00.1 | New customer sign-ups per day | `SELECT date_trunc('day', created_at) d, count(*) FROM customers GROUP BY d ORDER BY d DESC LIMIT 14;` | Watch, no threshold |
| M-00.2 | Log lines containing an email or password | `grep -E '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}' storage/logs/*` on the logs after a full test run | **0** |
| M-00.3 | Failed sign-in rate | `sign_in_failures_total` (F08) / all sign-ins | Alert if > 30% in 1 hour |
| M-00.4 | Sign-in p95 | `durationMs` of the sign-in route in logs (F08) | < 500 ms (bcrypt dominates) |
| M-00.5 | `EMAIL_ALREADY_USED` per hour (email probing) | `SELECT date_trunc('hour', occurred_at), count(*) FROM api_events WHERE code = 'EMAIL_ALREADY_USED' AND occurred_at > now() - interval '1 day' GROUP BY 1;` (F08 stores every response ≥ 400) | Alert when > 50 in an hour |

## B3. Future constraints

| ID | Constraint | Seam |
| --- | --- | --- |
| FC-00.1 | A staff account management screen later | `CreateStaffAccount` action shared with the seeder |
| FC-00.2 | Personal tokens for external clients | `Portal` enum + Sanctum tokens; no endpoint yet |
| FC-00.3 | One staff member working for several dealerships | Today `users.dealership_id` holds one value; a join table would touch the `{dealership}` route binding (F01) — keep every dealership check in that seam |
| FC-00.4 | More UI languages | One `copy.ts` per area (README D-G.4) |
