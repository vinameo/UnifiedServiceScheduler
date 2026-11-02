# Self-review: fix/change-password-modal-size

Diff reviewed: `git diff develop...HEAD` (1 file, `apps/web/src/features/admin/users/ChangePasswordModal.tsx`).

## Change
- In `ChangePasswordModal.tsx`, changed `<Modal>` prop `size="md"` to `size="lg"`.
- At `size="lg"` (~620px), both password descriptions ("At least 8 characters." and "Re-enter the password to confirm.") comfortably fit on a single line on desktop/tablet viewports, ensuring both the "New password" and "Confirm password" input boxes align horizontally.
- On narrow screens (`isNarrow = true`), `fullScreen` mode is preserved so mobile behavior remains unchanged.

## Verification
- Unit & component tests: `pnpm --filter @repo/web test src/features/admin/users/ChangePasswordModal.test.tsx src/features/admin/users/UsersRoute.test.tsx` passed (26/26 tests passed).
- Lint & typecheck: `pnpm lint` and `pnpm typecheck` passed cleanly across all workspaces.
- No backend/API changes involved.

## Findings
- None. Minimal and targeted UI fix.
