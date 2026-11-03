import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { AppProviders } from '../../AppProviders'
import { routes } from '../../router'
import { apiMock } from '../../test/apiMock'

describe('CustomerBookingLayout (D-05.8)', () => {
  const mockCustomerUser = {
    id: 10,
    email: 'hung@example.test',
    name: 'Nguyễn Văn Hùng',
    role: 'CUSTOMER',
    dealership_id: null,
    customer: {
      id: 'cust-1',
      fullName: 'Nguyễn Văn Hùng',
      phone: '0903123218',
    },
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockCustomerUser])
    apiMock.get('/dealerships', [
      200,
      {
        data: [
          {
            id: 'dealership-1',
            code: 'SGA-D7',
            name: 'Saigon Auto',
            branchName: 'District 7',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3812 3456',
            slotStepMinutes: 15,
          },
        ],
      },
    ])
    apiMock.get('/dealerships/dealership-1/service-types', [
      200,
      { data: [] },
    ])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('renders header with "My bookings" link navigating to /my-bookings', async () => {
    apiMock.get('/me/appointments?scope=upcoming', [
      200,
      {
        data: [],
        meta: { nextCursor: null, total: 0 },
      },
    ])

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const router = createMemoryRouter(routes, {
      initialEntries: ['/book'],
    })

    render(
      <AppProviders>
        <RouterProvider router={router} />
      </AppProviders>,
    )

    await screen.findByText('Book a service')
    const myBookingsLink = screen.getByRole('link', { name: 'My bookings' })
    expect(myBookingsLink).toBeInTheDocument()
    expect(myBookingsLink).toHaveAttribute('href', '/my-bookings')

    await user.click(myBookingsLink)

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/my-bookings')
    })
  })
})
