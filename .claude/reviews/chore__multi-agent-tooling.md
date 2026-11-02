# Self-review: chore/multi-agent-tooling

Scope: `git diff develop...HEAD` (88ce681, b5174af). Tooling only (`.claude/`, `.agents/`, `CLAUDE.md`); no app code, so the OWASP list (§5) applies only to agent permissions and the hook.

## Findings fixed

1. **Subagents could write to the database.** Replacing `tools:` with `disallowedTools` let `spec-reviewer` (read-only) and `deep-coder` inherit the `postgres-mcp` write tools. Both now disallow `postgres_mcp_modify` and `postgres_mcp_bulk_load_csv` (b5174af).
2. **CLAUDE.md feature-workflow sentence** lost its separator between `write-feature-spec` and `implement-ticket`. Fixed (b5174af).

## Checked, no change

- **Merge-gate hook** (`requireBranchReview.mjs`): Claude Code and Antigravity payloads tested with fixture files (deny when the report is missing, silent when present, silent on non-merge commands). The gate logic is unchanged, and Claude Code still gets the same output.
- **Prettier reformatted the hook** (single → double quotes). The file already failed `prettier --check` on develop; it now passes. It is formatting churn only.
- **Antigravity discovery:** `agy agents` lists `deep-coder` and `spec-reviewer` through `.agents/agents`.
- **Duplication:** the skills and agents have one source (`.claude/`), and `.agents/` holds symlinks only. The hook is shared and not copied.

## Deferred

- **Antigravity hook "allow" output.** The hook prints nothing when it allows a command. Antigravity's docs do not say whether that is valid, and it could not be tested (headless mode denies shell commands; the service returned 503). If commands fail with a hook error in Antigravity, set `"enabled": false` in `.agents/hooks.json` and print `{"decision":"allow"}` or `{}` instead.
- **Codex:** it has no merge-gate hook and no project MCP config (`.codex/` dropped at the user's request). `AGENTS.md` documents the manual fallbacks.

## Checks

`pnpm lint`, `pnpm typecheck` and `pnpm test` pass. There are no API changes, so `test:e2e` was not run.
