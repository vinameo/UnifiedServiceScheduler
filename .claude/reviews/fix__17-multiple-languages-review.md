# Self-review: fix/17-multiple-languages-review

Scope: `git diff develop...fix/17-multiple-languages-review` (e9b58e4, 9301bac). Fixes for the 10 findings of the feature 17 code review, checked against the spec (RQ INV-03/06/08/10/15, API B5/B9/B11, WEB B7), SOLID, OWASP Top 10 and adding a language.

## Findings fixed

| # | Finding | Fix | Proof |
| - | ------- | --- | ----- |
| 1 | Two `@Matches` on workspace name decoded the first decorator's message | `validationMessage()` returns a token unique to key+params; the pipe decodes the failing constraint's own message | `common/validation.spec.ts`; e2e `i18n.e2e-spec.ts` (URL vs hidden chars, en + vi) |
| 2 | Module-level `translate()` constants frozen at import | Removed; keys translated where shown or when thrown; 13 global-`t` components → `useTranslation()`; lint rule against module-level `translate()` and `i18next` imports | `ProjectMemberCandidateSelect.test.tsx` (switch to vi while open), `lintRules.test.ts` |
| 3 | English contract constants rendered in vi | Role summaries, truncated note, search hint, permission reasons go through keys; English constants removed from contracts; lint bans `*_MESSAGES` imports in web | web tests, `lintRules.test.ts` |
| 4 | LoginPage showed raw `Error.message` | Generic `auth:errors.signInFailed` + `console.error`; same for role-permissions save | `LoginPage.test.tsx` |
| 5 | `ApiFieldError` accepted strings, translated by English text | `MessageRef \| { libraryText }` only; `memberRows` and `rolePermissionsPlan` return refs; both reverse maps removed | API unit + e2e |
| 6 | Filter hardcoded `'vi'` and Vietnamese literals | `lang === DEFAULT_LANGUAGE` branches; `errors:http.*` keys | `apiExceptionFilter.spec.ts` |
| 7 | Locale mapping in three places | `LANGUAGE_LOCALES` in contracts; `languageLocale()`/`formatNumber()` everywhere | web tests |
| 8 | Catalog types hardcoded to en/vi, no typed keys | `TranslatedCatalog` (any plural forms), `LanguageResources` derived from `SUPPORTED_LANGUAGES`, web `CustomTypeOptions` | `tsc -b`; type probe (stray key rejected, `_few`/`_many` accepted) |
| 9 | Missing API e2e cases; hand-kept DTO list | e2e for B9 rows (name URL/hidden, name too long, ROLE_NOT_ALLOWED workspace+project, extra property, undeclared-message constraint); DTO metadata test discovers every `*.dto.ts` (the list had missed `workspaces/members/dto/memberCandidateQuery.dto.ts`) | `i18n.e2e-spec.ts`, `projectMembers.e2e-spec.ts`, `dtoValidationMetadata.spec.ts` |
| 10 | Empty `catch {}` in validation | Metadata lookup and JSON parsing removed; no catch left | — |

Found while fixing: `adminRoles` validation fields (`Unknown role`, guardrail reasons) reached vi users in English through the string path; now `MessageRef`s.

## Review lists

- **SOLID:** one seam per decision — the validation pipe decodes, the filter is the only renderer of API text, `translateRef()` is the one place a runtime key meets typed `t`. No god-modules added.
- **OWASP:** no route, guard or permission changed (`architecture.spec.ts` unchanged and green). A04/A09: non-API errors no longer surface internal text; logs stay English, header never logged. A03: the `messageRef:` token is looked up in a map built from DTO declarations; user input (property names in "should not exist") can't match it.
- **Adding a language:** add it to `SUPPORTED_LANGUAGES`; the compiler then requires `NATIVE_LANGUAGE_NAMES`, `LANGUAGE_LOCALES` and a `TranslatedCatalog`. API and web code have no per-language branches left.
- **Design pass:** no new copy; every sentence is an existing catalog key.

## Not changed (recorded)

- Role *names* (`PROJECT_ROLE_LABELS`, `WORKSPACE_ROLE_*LABELS`) are still English constants rendered directly in ~11 files. They are identical in en and vi by product decision (AC-13), so nothing is wrong today; a language that translates role names would need them moved to the existing `common:roles.*` keys.
- Form validation messages computed in Mantine `validate` stay in the language of the last validation until the field is validated again. Pre-existing, outside the 10 findings.
- One root `pnpm test` run had two web tests fail under parallel load (`ProjectSettings.test.tsx`, `ProjectMembersSettingsPage.test.tsx`); they pass alone, in two full web runs and in a second root run, with and without this branch.

## Checks

- `pnpm lint`, `pnpm typecheck`: pass.
- `pnpm test`: contracts 144, ui 5, api 366, web 679+ tests pass.
- `pnpm --filter @repo/api test:e2e`: 15 files, 376 tests pass.
