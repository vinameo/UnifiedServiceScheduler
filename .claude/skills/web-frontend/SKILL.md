---
name: web-frontend
description: Conventions for the Unified Service Scheduler web app (web/): Mantine 9, React Router, TanStack Query, axios against the Laravel API, Vitest + React Testing Library. Use when building or changing a page, component, hook, route, query or web test.
---

# Web frontend: Mantine 9 + React Router

`CLAUDE.md` "Frontend conventions" is the rule set; this skill is how those rules land in `web/src`. Exact versions are in `web/package.json`; read the installed types under `web/node_modules/@mantine/core` and `web/node_modules/react-router` when an API is in doubt.

## What is installed, and what is not

- Mantine: `@mantine/core`, `@mantine/hooks`, `@mantine/notifications` only. There is no `@mantine/form`, `@mantine/dates` or `@mantine/modals`, and `docs/features/README.md` D-G.11 allows a new package only when a spec names it in a `D` row. Forms are controlled `useState` with Mantine inputs and `error` props; date and time choices use `Select`, `SegmentedControl` or the `SlotGrid` seam; dialogs are `Modal` with `useDisclosure`; toasts are `notifications.show`.
- Mantine since v7 styles with CSS variables, style props and CSS modules. Examples from older versions (`sx`, `createStyles`, `@emotion/*`) do not compile here.
- Routing is the `react-router` package (data router, `createBrowserRouter`). Import from `'react-router'`; `react-router-dom` is not installed.

## Layout

```
src/main.tsx          Mantine CSS imports, <AppProviders>, <RouterProvider>
src/AppProviders.tsx  MantineProvider(theme), Notifications, QueryClientProvider (queries retry 1, mutations never retry)
src/router.tsx        `routes` array (exported for tests) + `router`
src/customer/         customer app, built for 390 px first
src/staff/            staff web, 1440 px, inside StaffLayout (AppShell)
src/shared/           theme.ts, api/ (client, problem, errorCopy), seams used by both areas
src/test/             render.tsx (renderRoute), setup.ts (jsdom stubs)
```

Each area keeps its query hooks next to its screens (`useXxx()` in an `api.ts` or `queries.ts`), its copy in `copy.ts`, and imports cross-area pieces only from `src/shared/`.

## Routing

- Add routes to the `routes` array in `src/router.tsx`, nested under the area layout so they render in its `<Outlet />`. Tests mount the same array, so a route that exists only in `router` is untested.
- Navigation links: Mantine `NavLink` with `component={RouterNavLink}` (see `StaffLayout.tsx`); in-page links: Mantine `Anchor` with `component={Link}`.
- Protected routes are wrapped in the `RequireRole` seam (F00) with the roles of the permission matrix (README §4); a `401` sends the user to that area's sign-in screen.
- Flows that lose their state on an error go back by route: `410 HOLD_EXPIRED` navigates to "Choose date and time".

## Styling

- Everything comes from `src/shared/theme.ts` (primary blue shade 8, Inter, radius `md`). Use theme tokens and style props (`c="dimmed"`, `color="red"`, `p="lg"`, `gap="sm"`); colours are theme names, never hex.
- Layout with `Stack`, `Group`, `SimpleGrid`, `AppShell`; responsive values as objects (`cols={{ base: 1, sm: 2 }}`).
- When a style prop is not enough, a `*.module.css` file next to the component, using Mantine CSS variables (`var(--mantine-spacing-md)`, `var(--mantine-color-blue-8)`) and the breakpoint variables from `web/postcss.config.cjs` (`@media (max-width: $mantine-breakpoint-sm)`).
- Match `docs/designs/` (Admin, Customer) for layout and copy.

## Server state and the API

- All calls go through `api` in `src/shared/api/client.ts` (base `/api/v1`, Sanctum cookies, XSRF header). Call `ensureCsrfCookie()` before sign-in and sign-up.
- Reads are `useQuery` with array keys that start with the resource (`['appointments', id]`); writes are `useMutation` that invalidate or set the affected keys on success.
- Writes use the shared seams, never their own copies (README §6.2): `useIdempotencyKey(actionInputs)` for every writing `POST` (one key per attempt, D-G.15), `appointmentQuery` keeping the `ETag` beside the appointment and `withIfMatch()` on every change, `SubmitButton`, `IdempotentRetryPanel`, `AppointmentModifiedAlert`.
- Errors: `toProblem(error)` from `src/shared/api/problem.ts`, then branch on `problem.code` and take the sentence from `errorCopy.ts`. `title` and `detail` are for logs, never for the screen. Field errors from `400 VALIDATION_ERROR` go to the matching input's `error` prop.
- Times: `formatInZone(iso, dealership.timezone)` for every business time; the browser's zone is never used.

## Copy and accessibility

- Every visible string comes from the area's `copy.ts` or `errorCopy.ts`, copied from the spec's copy table.
- Give every input a `label`, every page one heading, every icon-only button an `aria-label`. Tests find elements by role and name, so an unlabelled control is untestable.

## Tests

- Vitest + React Testing Library, globals on, jsdom; `src/test/setup.ts` stubs `matchMedia` and `ResizeObserver` for Mantine.
- Render screens with `renderRoute(path)` from `src/test/render.tsx` (memory router over the real `routes` and real `AppProviders`). Mock HTTP with `vi.mock('axios', …)` as in `router.test.tsx`; no new mocking package.
- Query by role and accessible name, assert on the copy from `copy.ts`, drive input with `@testing-library/user-event`, and await async UI with `findBy…`.
- Name each test after its `TC-` row ("Where" column).
- Narrow run: `cd web && npx vitest run <file>`; before a commit: `npm run lint && npm run test` (`lint` is ESLint plus `tsc -b`, TypeScript `strict`).
