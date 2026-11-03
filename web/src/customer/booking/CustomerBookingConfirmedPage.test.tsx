import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { AppProviders } from '../../AppProviders'
import { routes } from '../../router'
import { apiMock } from '../../test/apiMock'
import type { Appointment } from '../../shared/api/appointments'

describe('CustomerBookingConfirmedPage (D-05.8)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const appointmentId = '0192d222-0000-7000-8000-000000000002'

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

  const mockAppointment: Appointment = {
    id: appointmentId,
    reference: 'APT-20261102-0042',
    status: 'CONFIRMED',
    start: '2026-11-02T10:00:00+07:00',
    end: '2026-11-02T11:00:00+07:00',
    expiresAt: null,
    version: 1,
    dealership: {
      id: dealershipId,
      name: 'Saigon Auto',
      branchName: 'District 7',
      timezone: 'Asia/Ho_Chi_Minh',
    },
    serviceType: {
      id: 'st-1',
      code: 'OIL_CHANGE',
      name: 'Oil change',
      durationMinutes: 60,
    },
    technician: {
      id: 'tech-1',
      name: 'Trần Minh An',
    },
    bay: {
      id: 'bay-1',
      code: 'K2',
      name: 'Bay 2',
      bayType: 'QUICK',
    },
    customer: {
      id: 'cust-1',
      fullName: 'Nguyễn Văn Hùng',
      phone: '0903123218',
    },
    vehicle: {
      id: 'veh-1',
      make: 'Toyota',
      model: 'Vios',
      year: 2021,
      licencePlate: '51H-123.45',
      vin: 'RL4BT3F3X0M001234',
    },
    reminderOptIn: false,
    source: 'CUSTOMER',
    createdAt: '2026-10-28T16:40:00+07:00',
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockCustomerUser])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('renders "View my bookings" button and navigates to /my-bookings', async () => {
    apiMock.get('/me/appointments?scope=upcoming', [
      200,
      {
        data: [mockAppointment],
        meta: { nextCursor: null, total: 1 },
      },
    ])

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })

    const router = createMemoryRouter(routes, {
      initialEntries: [
        {
          pathname: `/book/confirmed/${appointmentId}`,
          state: mockAppointment,
        },
      ],
    })

    render(
      <AppProviders>
        <RouterProvider router={router} />
      </AppProviders>,
    )

    await screen.findByText('Booking confirmed')
    const viewMyBookingsBtn = screen.getByRole('link', { name: /View my bookings/i })
    expect(viewMyBookingsBtn).toBeInTheDocument()
    expect(viewMyBookingsBtn).toHaveAttribute('href', '/my-bookings')

    await user.click(viewMyBookingsBtn)

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/my-bookings')
    })
  })

  it('renders "Book another service" button and navigates to /book', async () => {
    apiMock.get('/dealerships', [
      200,
      {
        data: [
          {
            id: dealershipId,
            name: 'Saigon Auto',
            branchName: 'District 7',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3812 3456',
            slotStepMinutes: 15,
          },
        ],
      },
    ])
    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      { data: [] },
    ])

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })

    const router = createMemoryRouter(routes, {
      initialEntries: [
        {
          pathname: `/book/confirmed/${appointmentId}`,
          state: mockAppointment,
        },
      ],
    })

    render(
      <AppProviders>
        <RouterProvider router={router} />
      </AppProviders>,
    )

    await screen.findByText('Booking confirmed')
    const bookAnotherBtn = screen.getByRole('link', { name: /Book another service/i })
    expect(bookAnotherBtn).toBeInTheDocument()
    expect(bookAnotherBtn).toHaveAttribute('href', '/book')

    await user.click(bookAnotherBtn)

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/book')
    })
  })
})
