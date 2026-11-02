# Review: chore/hook-stdin-timeout

## Cause

`readHookInput` read the payload with `readFileSync(0)`, which blocks until stdin closes. Reproduced: with stdin held open for 4 s the hook waited 4 s; held open forever (hook run by hand, in the background, or by a host that never closes stdin) it waited forever, the stack sitting in `hookIo.mjs:8`. A `setTimeout` could not end it because the read blocked the event loop. The git calls had no timeout and inherited stdin.

## Changes

- `hookIo.mjs`: `readHookInput(hook, { stdin, timeoutMs })` reads asynchronously with a 2 s deadline, skips a terminal, destroys stdin on timeout, and returns "nothing to check" for empty or non-JSON input (logged to stderr; never throws). `runGit` (5 s timeout, `stdio: ["ignore", "pipe", "pipe"]`) and `startWatchdog` (8 s, `unref`, exit 0) are shared by both hooks; the duplicate `git()` helper in `requireTicketDesignNotes.mjs` is gone.
- Both hooks start the watchdog and `await readHookInput(...)` (top-level await, `.mjs`).
- `hooks.test.mjs` (node:test, `pnpm test:hooks`, CI step after unit tests): payload parsing for both hosts, open stdin, terminal, bad JSON, and process-level runs of both hooks on temp git repos (deny/allow, remote-tracking refs, design notes, exempt types).

## Findings

- Fail-open on missing input is deliberate: without a payload there is no command to check. A real payload is still checked exactly as before (existing deny/allow cases covered by tests).
- The watchdog can only fire between synchronous steps; each git call is itself bounded at 5 s, so the worst case is a few git timeouts, not hours.
- Not verified: whether Antigravity enforces `timeout: 10` from `.agents/hooks.json`. The hooks no longer depend on it.

## Checks

`pnpm test:hooks` 15/15; `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm dup` pass; Prettier clean on changed files.
