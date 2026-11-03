import { render } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { AppProviders } from '../AppProviders'
import { routes } from '../router'

export function renderRoute(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] })
  return {
    ...render(
      <AppProviders>
        <RouterProvider router={router} />
      </AppProviders>,
    ),
    router,
  }
}
