---
name: write-feature-spec
description: Write or update an OpenPlany feature spec set (docs/features/<n>-<feature>/: requirement, business analysis, database, API, web, test cases) from a draft UI or a request, then gate it with spec review and split it into tickets. Use when asked to write requirements, specs, BA/DB/API/web specs, test cases, or to turn a draft into tickets.
---

# Write a feature spec

Specs live in `docs/features/<n>-<feature-slug>/` next to the draft UI images. Write every file in English by default (headings, prose, tables, comments in sketches); use another language only when the user asks for it for that spec. The directory name is also the branch name (`feature/<n>-<feature-slug>`, CLAUDE.md §8).

## Steps

1. **Settle the decisions with the user.** Run a grilling session (`mattpocock-skills:grill-with-docs`) over the draft, the code and `CONTEXT.md` until no decision is left assumed. Facts come from the code and the DB (via MCP: schema and aggregate counts only, never personal data). Never name the reference product the drafts imitate; describe its ideas as OpenPlany's own. Done when the user confirms shared understanding.
2. **Write the six files** in the format below: the five spec files first, then `6-test-case.md` from them. Done when every decision from step 1 has a `D` row, every part B section exists, and every `AC-`, `INV-`, edge case and adversarial input row has a `TC-` row.
3. **Gate with spec review.** Dispatch the `spec-reviewer` subagent on the directory. Bring each blocker and major finding to the user with your recommended fix, apply the agreed fixes to every affected file, and keep IDs consistent across files. Each scenario that breaks a sketch (check P8) becomes an invariant or edge case plus a `TC-` row. Done when no blocker or major finding is open.
4. **Split into tickets** with `mattpocock-skills:to-tickets`, writing `tickets/README.md` plus one `NN-slug.md` per ticket, using the ticket rules below. That skill sets `disable-model-invocation`, so the Skill tool cannot load it: read its `SKILL.md` (`~/.claude/plugins/cache/*/mattpocock-skills/*/skills/engineering/to-tickets/SKILL.md`, newest version) and follow it in place, including its step that shows the proposed breakdown to the user and waits for approval before writing files. This skill overrides two of its defaults: tickets go in the spec directory's `tickets/`, not `.scratch/`, and they may cite spec IDs and section numbers. Done when the user has approved the breakdown and every ticket file exists.

## File set

| File                                                                    | Answers                                                                                   |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `1-requirement-spec.md` (RQ)                                            | **What** to build                                                                         |
| `2-business-analytics-spec.md` (BA)                                     | **Why**: evidence, options and trade-offs (ADRs), priorities, metrics, future constraints |
| `3-database-spec.md` (DB), `4-api-spec.md` (API), `5-web-spec.md` (WEB) | RQ part B refined for one layer; the layer file wins on its own detail                    |
| `6-test-case.md` (TC)                                                   | **How we prove it**: each requirement as a test case with level, data and expected result |

RQ and BA do not repeat each other. Test cases live only in TC; the other files cite `TC-` IDs instead of keeping their own test tables.

Every file has two parts:

- **Part A, for people** (product owner, designer, QA, new dev), readable in about 5 minutes: the story (why the feature exists, the problem, what it does, who uses it, where it opens, inputs, outcome, what it does not do), usage steps, screens and deviations from the draft, key rules in plain words, out of scope, success criteria, decisions, open questions each with a default.
- **Part B, for implementing agents**: reading rules (section B0 below, verbatim), current code state (with the commit it was checked at) with verified file paths, scope, invariants, file-by-file changes, contracts, algorithm steps, error table, Given/When/Then ACs each citing the `TC-` rows that prove it, implementation order, Definition of Done, technical risks.

**ID scheme**, shared across files and kept consistent when a later file changes an earlier one: `D` decisions, `Q-<x>` open questions, `S-` scope, `INV-` invariants, `AC-` acceptance criteria, `R-` technical risks, `BR` business risks, `E-`/`F` evidence and findings, `ADR-` decisions (with implementation constraints), `M-` metrics (with SQL and thresholds), `FC-` future constraints, `DB-`/`API-`/`WEB-` layer requirements, `TC-` test cases.

## Part B must carry

Implementing agents treat every sketch as a _hypothesis_ and build to the invariants (`implement-ticket` step 4). Part B therefore states each requirement in a form a sketch can be tested against:

- **B0, verbatim** (the reading rules):

  > - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
  > - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
  > - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

- **Decisions as invariants.** For every decision a write depends on (who may act, which role, which rows, what count), state:
  - what is decided, from which data;
  - where and when that data is read: a row locked in the same transaction, or a value the guard read earlier;
  - the edge cases: soft-deleted rows, deactivated accounts, ended memberships, the same write twice, a change committed between the read and the write, who is in or out of a count;
  - one `TC-` row per edge case.

  Each rule lives in one section; other sections and files point to it.

- **Sketches as shapes.** Code shows signatures, types and call shapes (`create({ …, creatorRoleFor })`), labelled _illustrative_ and tied to the `INV-` it serves. Lock modes, `where` clauses, queries, counts and the order of checks are written as invariants and steps in words.
- **Seams.** For every rule more than one endpoint or page will need, name the reusable seam (guard and decorator, path helper, shared hook or component, context API). Map every `FC-` constraint to a seam in this feature or defer it with a reason.
- **Adversarial input table** per endpoint, with the expected 4xx for each row.
- **Bounds**: list maximums, real counts beside previews, rate limits on lookups whose 403/404 reveal existence, one log line per person and window for warnings any member can trigger.
- **Migrations**: the pre-check of existing rows, the exact `down()`, and the soft-delete scope of every index.
- **Copy and codes** for every user-facing message, including errors, in English, following the glossary in `CONTEXT.md`. Add another language column only when the project ships that language. Each sentence the web must tell apart has its own `code` in the API error table.

## Test case file must carry

`6-test-case.md` turns the requirements of the other five files into test cases. It adds no new rule: a case that needs a rule the spec lacks means the spec has a gap, so fix the owning file first.

**Part A, for QA and the product owner:**

- the test strategy in plain words: what is proven at which level, and why;
- manual scenarios on the screens, as steps QA can follow, each with the expected screen and copy;
- test accounts and data, by role;
- what is not tested, and why;
- exit criteria: when the feature counts as tested.

**Part B, for implementing agents:**

- **B0, verbatim**, as in the other files.
- **Coverage matrix.** One row per `AC-`, `INV-`, `DB-`/`API-`/`WEB-` requirement, decision edge case and adversarial input row, with the `TC-` IDs that prove it. A requirement with no `TC-` is a gap. A `TC-` that proves nothing in the matrix is linked or removed.
- **Test case rows**, one per case:

  | Column   | Holds                                                                                                         |
  | -------- | ------------------------------------------------------------------------------------------------------------- |
  | ID       | `TC-<n>`                                                                                                       |
  | Proves   | the `AC-`/`INV-`/layer IDs it covers                                                                          |
  | Level    | unit, integration (HTTP + real database), concurrency, web component, e2e or manual                           |
  | Given    | data and state, built from named fixtures                                                                     |
  | When     | the one action under test                                                                                     |
  | Then     | what is observed at the boundary: status and `code`, rows in the DB, outbox or event rows, the copy shown |
  | Fails if | the code change that would make it fail, so a reviewer can see it is not vacuous                             |
  | Where    | test file and test name, named after the requirement it proves (`CLAUDE.md` naming rule)                      |

- **Level choice.** Use the lowest level that fails for the right reason. Pure logic is a unit test. Anything that relies on database constraints, locks or transactions runs on the real database, never an in-memory substitute. A race is a concurrency test: real parallel processes released together, followed by the data-integrity check `CLAUDE.md` requires. Screen behaviour is a component test; a full user flow is e2e or manual.
- **Edge cases and adversarial input.** Every edge case listed under a decision in part B (soft-deleted rows, deactivated accounts, the same write twice, a change committed between the read and the write) gets its own row. Every row of an API adversarial input table gets a row, or one parametrised case that names each input and its expected 4xx.
- **Copy.** Each user-facing message and error `code` the web must tell apart has a case that checks it.
- **Fixtures.** The data builders and seeders the cases share, named as seams, so tickets reuse them instead of rebuilding data.
- **Regression.** The existing tests and suites this feature touches, which must stay green unchanged.

## Ticket rules

- Checkboxes name the outcome and the `INV-`/`AC-` that proves it. A function name or step order copied from part B is a hypothesis, like the sketch it comes from.
- Each ticket lists the `TC-` rows it must make pass. A `TC-` belongs to the ticket that delivers its behaviour, and every `TC-` belongs to exactly one ticket.
- Ticket 01 is the prefactor: extract the shared seams the feature needs, with no change users can see; done when existing tests stay green unchanged.
- The tracer bullet comes early, and its acceptance list ends with "run `/code-review` on the branch and fix findings before later tickets build on it".
- The last ticket is acceptance: every `TC-` row automated and green, or run by hand with the result recorded in `6-test-case.md`; BA metrics measured; and the whole-branch self-review written to `.claude/reviews/<branch>.md` (the merge hook requires it).
