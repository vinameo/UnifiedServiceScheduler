# Self-review: fix/migration-tests-and-extensions

Scope: `git diff develop...fix/migration-tests-and-extensions` (a4482a5). `apps/api/src/database/dataSourceOptions.ts`, `apps/api/test/e2eGlobalSetup.ts`, `apps/api/test/migrations.e2e-spec.ts`.

## Findings

None open.

## Checked

- **installExtensions: false.** Every migration uses `gen_random_uuid()`; no column default uses `uuid_generate_v4()`, and no other driver-installed extension is needed. All 413 API e2e tests pass (uuid primary keys insert through the database defaults). After the full e2e run, the test database holds only `plpgsql` and `unaccent`.
- **Leftover extension.** Dev and existing databases keep the `uuid-ossp` that the driver installed before; it is unused and harmless. e2e setup drops it from the reused test database so the snapshot can compare every extension. If a migration ever creates `uuid-ossp` on purpose, remove that drop; the snapshot test would fail visibly until then.
- **revertUntilLast().** It reverts until the named migration is the newest applied one, reading `typeorm_migrations` (SQL in e2e tests is allowed by CLAUDE.md §5). It is bounded by `MIGRATIONS.length` and throws when the target is not applied. All seven hard-coded revert sequences now use it, so a new migration no longer breaks older tests. The roundtrip test still reverts the newest `ROUNDTRIP_COUNT` on purpose.
- **Security / data:** no app behaviour change beyond the driver no longer running `CREATE EXTENSION` on connect.

## Checks

`pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm dup` and `pnpm --filter @repo/api test:e2e` pass (15 files, 413 tests; migrations e2e 13/13).
