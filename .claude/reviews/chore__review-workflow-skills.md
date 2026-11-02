# Self-review: chore/review-workflow-skills

Scope: `git diff develop...HEAD` (8765164). Tooling and docs only (`.claude/skills`, `.claude/agents`, `CLAUDE.md`); no app code.

## Findings

None open.

## Checked

- **Step references.** `implement-ticket` gained a skills step (3) and a review step (8), which renumbers the steps after them. Every reference matches the new numbering: `deep-coder` (steps 1–9, step 10, step 7), `implement-feature` (step 4.2), `CLAUDE.md` (step 8), and inside `implement-ticket` (steps 4 and 7).
- **One source for the checklists.** The per-ticket review and the feature review (spec, SOLID, OWASP A01–A10, extensibility) live only in `implement-ticket`. `deep-coder`, `implement-feature` and `CLAUDE.md` point to them; the copy that used to be in `deep-coder` is gone.
- **Skills by layer.** The postgres, backend and frontend skills are listed once, in `implement-ticket` step 3, and preloaded in `deep-coder`. `CLAUDE.md` overrides them (Mantine, not MUI).
- **Database safety.** The read-only `open-plany-postgres` MCP is used for inspection only. `deep-coder` keeps `disallowedTools` on the `postgres-mcp` write tools.
- **Antigravity.** `agy agents` still lists `deep-coder` and `spec-reviewer`.
- **Codex fallback.** `AGENTS.md` now points `/code-review` and `/security-review` to the same feature-review checklist.

## Not verified

The new flow has not run on a real ticket. The first `/implement-feature` run is its test.

## Checks

`pnpm lint`, `pnpm typecheck` and `pnpm test` pass; Prettier is clean on the touched skill and agent files.
