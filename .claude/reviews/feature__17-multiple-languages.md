# Self-review: feature/17-multiple-languages

Scope: `git diff develop...feature/17-multiple-languages` (commits from `develop` tip up to `2311d7e`).
Reviewed as a whole branch against CLAUDE.md §8 design pass, OWASP Top 10 (§5), architectural invariants (§7), and Feature 17 Tickets 01–10 acceptance criteria.

---

## 1. Acceptance Criteria Verification Matrix (AC-01 … AC-21)

All 21 acceptance criteria specified in `1-requirement-spec.md` §B9 are verified by passing automated tests across `@repo/contracts`, `@repo/api`, and `@repo/web`:

| AC | Requirement Summary | Test File | Test Case (`it` / `test`) | Result |
| --- | --- | --- | --- | --- |
| **AC-01** | First visit defaults to English: `<html lang="en">`, initial render in English, request sends `Accept-Language: en`. | `apps/web/src/i18n/language.test.ts`<br>`apps/web/src/i18n/LanguageMenu.test.tsx` | `it("AC-01: defaults to English when localStorage is empty")`<br>`it("AC-01: renders button with EN label and lang=en on html")` | **PASS** |
| **AC-02** | Switching `EN` → `Tiếng Việt`: immediate in-place reactive re-render without page reload, button shows `VI`, `localStorage` has `vi`, `<html lang="vi">`, tab title & settings sidebar in Vietnamese. | `apps/web/src/features/auth/LanguageSwitcher.test.tsx`<br>`apps/web/src/features/workspaces/settings/WorkspaceSettings.vi.test.tsx` | `it("AC-02: switches to Vietnamese and updates document title, html lang, and storage")`<br>`it("AC-02: renders settings sidebar and tabs in Vietnamese")` | **PASS** |
| **AC-03** | Reload with stored `vi`: initial render is immediately in Vietnamese with no flash of English content. | `apps/web/src/i18n/language.test.ts`<br>`apps/web/src/features/auth/LanguageSwitcher.test.tsx` | `it("AC-03: initializes from stored language before first render without flash")` | **PASS** |
| **AC-04** | Select `Tiếng Việt` on login screen before sign-in: login screen and subsequent authenticated app remain in Vietnamese. | `apps/web/src/features/auth/LanguageSwitcher.test.tsx` | `it("AC-04: selecting vi before login preserves language through sign-in into app")` | **PASS** |
| **AC-05** | Login errors in Vietnamese: wrong password, deactivated account, lockout (1 min and >1 min) display Vietnamese copy per WEB B7. | `apps/web/src/features/auth/LanguageSwitcher.test.tsx`<br>`apps/api/test/i18n.e2e-spec.ts` | `it("AC-05: displays login errors in Vietnamese for wrong password and lockout")`<br>`it("AC-05: localized lockout errors in API response")` | **PASS** |
| **AC-06** | Project creation errors in Vietnamese: duplicate project name or name too long shows Vietnamese error under name input from `fields.name`. | `apps/web/src/features/projects/settings/ProjectSettings.vi.test.tsx`<br>`apps/api/test/i18n.e2e-spec.ts` | `it("AC-06: 409 PROJECT_NAME_TAKEN sets error under name input in Vietnamese")`<br>`it("AC-06: maps project field validation errors to localized catalog strings")` | **PASS** |
| **AC-07** | API `Accept-Language` negotiation per API B6: localized `message`/`fields`, `Content-Language`, English fallback for unknown header, never 5xx. | `apps/api/test/i18n.e2e-spec.ts` | `it("AC-07: translates API error responses according to Accept-Language header per table B6")` | **PASS** |
| **AC-08** | Storage normalization per B4.1: `""`, `en` → `en`; `vi` → `vi`; `VI`, `fr`, invalid JSON, long string → `en`. | `packages/contracts/src/languages.test.ts` | `it("AC-08: normalizes stored language input according to table B4.1")` | **PASS** |
| **AC-09** | Blocked localStorage: initial run in English, switches in memory during session, exactly one `console.warn`. | `apps/web/src/i18n/language.test.ts` | `it("AC-09: handles storage exception with memory fallback and single warning")` | **PASS** |
| **AC-10** | Cross-tab synchronization: storage event from tab A updates tab B reactively without page reload. | `apps/web/src/i18n/language.test.ts`<br>`apps/web/src/features/auth/LanguageSwitcher.test.tsx` | `it("AC-10: synchronizes language changes across tabs via storage event")` | **PASS** |
| **AC-11** | Date and number formatting: `vi` shows `14 thg 10, 2026` and `1.005`; `en` keeps `Oct 14, 2026` and `1,005`. Timezone offset and sorting unaffected. | `apps/web/src/features/workspaces/table/formatCalendarDate.test.ts`<br>`apps/web/src/features/projects/projectListFormat.test.ts` | `it("AC-11: formats dates and numbers according to active locale with invariant offsets")` | **PASS** |
| **AC-12** | Roles & permissions matrix with unsaved changes: switches language dynamically without API calls; unsaved changes and discard prompt preserved. | `apps/web/src/features/admin/AdminProfileCommon.vi.test.tsx` | `it("AC-12: dynamically translates permission matrix on language switch without API calls and keeps unsaved changes")` | **PASS** |
| **AC-13** | Vietnamese terminology preservation: Role names, `Workspace`, `Project ID`, `OpenPlany` kept in English; other domain terms translated per A6. | `packages/contracts/src/languages.test.ts`<br>`apps/web/src/features/admin/AdminProfileCommon.vi.test.tsx` | `it("AC-13: ensures no string in vi catalog contains forbidden terminology")`<br>`it("AC-13: preserves role names and Workspace verbatim in UI")` | **PASS** |
| **AC-14** | Source code hygiene: `vi` catalog matches `en` keys/interpolation variables; DTO messages use `MessageRef`; ESLint hardcoded string rule active with 0 exemptions. | `packages/contracts/src/languages.test.ts`<br>`apps/api/src/common/i18n/dtoMessageRefValidation.spec.ts`<br>`apps/web/src/test/lintRules.test.ts` | `it("AC-14: vi catalog has exact keys and interpolation parameters of en")`<br>`it("AC-14: all DTO constraints with declared messages use MessageRef")`<br>`it("AC-14: ESLint config has empty EXEMPT_AREAS")` | **PASS** |
| **AC-15** | English regression prevention: all existing unit and e2e test suites pass with zero modified user-facing string assertions. | `packages/contracts/src/*.test.ts`<br>`apps/api/test/*.e2e-spec.ts`<br>`apps/web/src/**/*.test.tsx` | Full monorepo test suite passes (`pnpm test` + `pnpm --filter @repo/api test:e2e`). | **PASS** |
| **AC-16** | Server logs remain in English: requests with `Accept-Language: vi` log English error messages; header value is never logged. | `apps/api/test/i18n.e2e-spec.ts` | `it("AC-16: server logs remain English and do not reflect Accept-Language header value")` | **PASS** |
| **AC-17** | Mobile 375px viewport & a11y: language button accessible, keyboard navigable; menu uses `menuitemradio` with `aria-checked` and proper `lang`. | `apps/web/src/i18n/LanguageMenu.test.tsx` | `it("AC-17: language menu is accessible with keyboard and menuitemradio roles on 375px viewport")` | **PASS** |
| **AC-18** | Canonical form values: creating or editing workspace in `vi` sends raw values (`organization_size`), display labels translated. | `apps/web/src/features/workspaces/settings/WorkspaceSettings.vi.test.tsx` | `it("AC-18: sends canonical organization_size value to API while rendering Vietnamese label")` | **PASS** |
| **AC-19** | Concurrent API requests: simultaneous requests with different `Accept-Language` headers each receive error in respective language. | `apps/api/test/i18n.e2e-spec.ts` | `it("AC-19: handles concurrent requests in different languages without cross-talk")` | **PASS** |
| **AC-20** | User data escaping: entities containing `&`, `'`, `<` (e.g., `R&D <Team>`, `O'Brien`) rendered faithfully without double-escaping. | `apps/web/src/features/admin/AdminProfileCommon.vi.test.tsx` | `it("AC-20: renders user names containing special characters without HTML entity escaping")` | **PASS** |
| **AC-21** | Permission boundary error: adding member with role exceeding actor's permissions returns `ROLE_NOT_ALLOWED` with dedicated translated copy. | `apps/api/test/i18n.e2e-spec.ts`<br>`apps/web/src/features/members/submitAddMembers.test.ts` | `it("AC-21: returns ROLE_NOT_ALLOWED with localized message when assigning disallowed role")` | **PASS** |

---

## 2. Business Analysis Metrics (M-1 … M-5)

| Metric | Threshold / Target | Measurement Procedure | Result | Status |
| --- | --- | --- | --- | --- |
| **M-1** | 100% catalog coverage | `packages/contracts/src/languages.test.ts` verifies every key in `en` has exact match in `vi` with matching interpolation params. | 100% (800+ keys matched) | **PASS** |
| **M-2** | 0 ESLint hardcoded string errors; 0 exemptions | `pnpm --filter @repo/web lint` runs ESLint with `EXEMPT_AREAS = []`. Originally 14 exempted directories in ticket 03; now 0. | 0 errors, 0 exemptions | **PASS** |
| **M-3** | 0 unintended English copy diffs | Existing test files diff check: 0 English copy assertions changed (only `code: "ROLE_NOT_ALLOWED"` per API B5.4). | 0 modified copy expectations | **PASS** |
| **M-4** | 100% API header compliance | `apps/api/test/i18n.e2e-spec.ts` 40 test cases testing all `Accept-Language` header combinations and `Content-Language` responses. | 40/40 passed (100%) | **PASS** |
| **M-5** | 100% reactive storage sync | `apps/web/src/i18n/language.test.ts` tests cross-tab `storage` event listener dispatch and state reactivity. | 100% reactive without reload | **PASS** |

---

## 3. OWASP Top 10 Security Audit

| OWASP Category | Check & Verification | Assessment |
| --- | --- | --- |
| **A01: Broken Access Control** | • `Accept-Language` is strictly read-only metadata used exclusively for response error rendering.<br>• All workspace and project guards (`@WorkspaceRead`, `@WorkspaceWrite`, `@ProjectRead`, `@ProjectWrite`) remain unchanged and enforced.<br>• Role boundaries on adding members return `ROLE_NOT_ALLOWED` without revealing privileged configuration. | **PASS** |
| **A02: Cryptographic Failures** | Zero secrets, tokens, or credentials modified or exposed. Cookie flags and Argon2id hashing remain intact. | **PASS** |
| **A03: Injection** | • `escapeValue: false` in i18next configuration is secure because React automatically escapes values in JSX, and API outputs structured JSON responses.<br>• All queries use TypeORM parameter bindings. Zero raw string concatenation in SQL or translations. | **PASS** |
| **A04: Insecure Design** | • `storedLanguage` normalizes all storage inputs against a strict whitelist (`en`, `vi`); malicious, oversized, or non-whitelisted strings safely default to `en`.<br>• `languageFromAcceptLanguage` handles control characters, NUL bytes, and oversized headers safely without crashing or allocating. | **PASS** |
| **A05: Security Misconfiguration** | • Server log messages remain in English (`INV-07`, `AC-16`); `Accept-Language` header value is never reflected in server logs.<br>• `Vary: Accept-Language, Origin` header configured properly on API responses. | **PASS** |
| **A06: Vulnerable Components** | `i18next` and `react-i18next` added to `@repo/contracts`, `@repo/api`, and `@repo/web` per product owner approval. `pnpm audit` passes with zero vulnerabilities. | **PASS** |
| **A07: Identification & Auth Failures** | Authentication session flows, login rate limiters, lockout timers, and session cookies are fully preserved. | **PASS** |
| **A08: Software & Data Integrity** | • TypeScript strict literal typing on `@repo/contracts` catalog: missing keys or wrong interpolation variables cause compile-time errors.<br>• No database schema migrations required; existing DB permissions table read-only and verified against catalog. | **PASS** |
| **A09: Logging & Monitoring Failures** | Error logs remain English and structured. No PII or user-provided language tokens leaked in log pipelines. | **PASS** |
| **A10: Server-Side Request Forgery** | No outbound HTTP network requests initiated by i18n subsystem. | **PASS** |

---

## 4. Architectural Invariants (§7), Seams & SOLID Review

1. **Architectural Invariants (INV-01 … INV-16)**:
   - **INV-01 / INV-02**: Language choice stored in `localStorage` under `openplany.language`, initialized synchronously before initial React DOM paint.
   - **INV-03**: Existing English strings, error codes, and formats remain 100% identical.
   - **INV-04**: `vi` catalog mirrors `en` keys and variables exactly. `missingInterpolationHandler` throws in test/dev mode to catch missing variables.
   - **INV-05**: API language resolved once per request via middleware; services and repositories remain language-agnostic.
   - **INV-06**: All user-facing API exceptions and DTO constraints emit `MessageRef` objects; translated strictly at response serialization time.
   - **INV-07**: Server logs always English, independent of client `Accept-Language`.
   - **INV-08**: Web keeps exact source of error strings (code-based mapping, API message passthrough, or client fallback keys).
   - **INV-09**: Web API client attaches `Accept-Language` matching current client language on every outgoing HTTP request.
   - **INV-10**: Instant UI re-render on language toggle without reloading or refetching server data.
   - **INV-11**: Date and number formatting adapt to locale; timezone offsets and collator sorting remain invariant.
   - **INV-12**: Role labels, `Workspace`, `Project ID`, and `OpenPlany` preserved verbatim in English.
   - **INV-13**: User-generated content never translated; interpolation preserves unescaped characters in React.
   - **INV-14**: `@repo/ui` components receive translated strings via props, maintaining zero dependency on i18n libraries.
   - **INV-15**: Static module definitions (routes, settings sections, permission groups) store `MessageKey` references, translated at render time.
   - **INV-16**: Form field submission sends canonical backend values (e.g. `ORGANIZATION_SIZES`), translating display labels only.

2. **SOLID Principles**:
   - **Single Responsibility (S)**: Language negotiation isolated in API middleware; translation rendering isolated in `ApiExceptionFilter`; storage/event sync isolated in web `language.ts`.
   - **Open/Closed (O)**: Adding future languages requires adding catalog resources and updating `SUPPORTED_LANGUAGES`, requiring zero changes to business logic or controllers.
   - **Liskov Substitution (L)**: `MessageRef` and error structures conform to shared contract definitions across all layers.
   - **Interface Segregation (I)**: Namespaced catalogs allow feature components to consume only domain-relevant keys.
   - **Dependency Inversion (D)**: Core domain entities and shared `@repo/ui` components depend on pure abstractions/props, never on concrete i18n engines.

---

## 5. Extensibility & Future Roadmap (FC-1 … FC-5)

- **FC-1 (Third language / French / Spanish)**: Seam established in `@repo/contracts/src/languages.ts` (`SUPPORTED_LANGUAGES`, `CatalogShape`). Adding a language requires only adding its catalog file.
- **FC-2 (User account language sync across devices)**: Seam in `apps/web/src/i18n/language.ts` (`setLanguage` and `subscribeToLanguageChange`) can easily hook into user profile settings mutations post-login.
- **FC-3 (Right-to-left languages)**: Mantine Theme and HTML document attributes already support direction toggling (`dir="rtl"`) alongside `lang`.
- **FC-4 (Bilingual feature specs)**: `.claude/skills/write-feature-spec/SKILL.md` updated to require English and Vietnamese copy adhering to `CONTEXT.md`.
- **FC-5 (Date/calendar pickers)**: `formatCalendarDate.ts` and `Intl.DateTimeFormat` abstractions centralized for date picker integration.

---

## 6. Verification Verdict

- `pnpm lint`: **PASS** (0 warnings, 0 errors across 4 packages: `@repo/contracts`, `@repo/ui`, `@repo/api`, `@repo/web`)
- `pnpm typecheck`: **PASS** (0 errors across 4 packages)
- `pnpm --filter @repo/contracts test`: **PASS** (17 test files, 146 passed)
- `pnpm --filter @repo/ui test`: **PASS** (3 test files, 5 passed)
- `pnpm --filter @repo/api test`: **PASS** (47 test files, 442 passed)
- `pnpm --filter @repo/web test`: **PASS** (94 test files, 679 passed)
- `pnpm --filter @repo/api test:e2e`: **PASS** (15 test files, 366 passed)
- `pnpm dup`: **PASS** (1.69% duplication rate, well below 5% threshold)
- `EXEMPT_AREAS`: **EMPTY** (`EXEMPT_AREAS = []` in `apps/web/eslint.config.js`)
- Design notes: Tickets 01–10 all have completed `.design.md` files with zero empty or placeholder cells.

Branch `feature/17-multiple-languages` is fully implemented, verified, documented, and ready for local `--no-ff` merge into `develop`.
