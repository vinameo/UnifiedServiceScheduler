import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { AppointmentListItem } from '../../shared/api/appointments'

describe('MyBookingsPage', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'

  const mockCustomerUser = {
    id: 10,
    email: 'hung@example.test',
    name: 'Nguyễn Văn Hùng',
    role: 'CUSTOMER',
    dealership: null,
    customer: {
      id: 'cust-1',
      fullName: 'Nguyễn Văn Hùng',
      phone: '0903123218',
    },
  }

  const upcomingBooking: AppointmentListItem = {
    id: '0192d000-0000-7000-8000-000000000042',
    reference: 'APT-20261102-0042',
    status: 'CONFIRMED',
    start: '2026-11-02T10:00:00+07:00',
    end: '2026-11-02T11:00:00+07:00',
    customer: {
      id: 'cust-1',
      fullName: 'Nguyễn Văn Hùng',
    },
    vehicle: {
      make: 'Toyota',
      model: 'Vios',
      licencePlate: '51H-123.45',
    },
    serviceType: {
      id: 'st-1',
      name: 'Oil change',
      durationMinutes: 60,
    },
    technician: {
      id: 'tech-001',
      name: 'Trần Minh An',
    },
    bay: {
      id: 'bay-002',
      code: 'K2',
      name: 'Bay 2',
    },
    dealership: {
      id: dealershipId,
      name: 'Saigon Auto',
      branchName: 'District 7',
      timezone: 'Asia/Ho_Chi_Minh',
    },
    contactRequiredReason: null,
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

  it('TC-05-18: renders upcoming booking card, switches to past tab with empty state and URL sync', async () => {
    apiMock.get(new RegExp('/me/appointments'), (config) => {
      const params = (config.params ?? {}) as Record<string, unknown>
      if (params.scope === 'past') {
        return [
          200,
          {
            data: [],
            meta: {
              nextCursor: null,
              limit: 50,
              total: 0,
            },
          },
        ]
      }

      return [
        200,
        {
          data: [upcomingBooking],
          meta: {
            nextCursor: null,
            limit: 50,
            total: 1,
          },
        },
      ]
    })

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const { router } = renderRoute('/my-bookings')

    // Title and user email
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 2, name: 'My bookings' })).toBeInTheDocument()
      expect(screen.getByText('Signed in as hung@example.test')).toBeInTheDocument()
    })

    // Upcoming card content: "Mon 2 Nov · 10:00–11:00" and "Confirmed"
    await waitFor(() => {
      expect(screen.getByText('Mon 2 Nov · 10:00–11:00')).toBeInTheDocument()
      expect(screen.getByText('Confirmed')).toBeInTheDocument()
    })
    expect(screen.getByText('Oil change')).toBeInTheDocument()
    expect(screen.getByText('Toyota Vios · Saigon Auto District 7')).toBeInTheDocument()
    expect(screen.getByText('APT-20261102-0042')).toBeInTheDocument()

    const viewDetailsLink = screen.getByRole('link', { name: 'View details' })
    expect(viewDetailsLink).toHaveAttribute('href', `/my-bookings/${upcomingBooking.id}`)

    // Switch to Past tab
    const pastRadio = screen.getByRole('radio', { name: 'Past' })
    await user.click(pastRadio)

    // URL syncs with ?tab=past
    await waitFor(() => {
      expect(router.state.location.search).toContain('tab=past')
    })

    // Past tab shows empty state
    await waitFor(() => {
      expect(screen.getByText('No past bookings yet.')).toBeInTheDocument()
    })
  })

  it('renders empty upcoming state with New booking button', async () => {
    apiMock.get(new RegExp('/me/appointments'), [
      200,
      {
        data: [],
        meta: {
          nextCursor: null,
          limit: 50,
          total: 0,
        },
      },
    ])

    renderRoute('/my-bookings')

    await waitFor(() => {
      expect(screen.getByText('You have no upcoming bookings.')).toBeInTheDocument()
    })

    const newBookingButton = screen.getByRole('link', { name: 'New booking' })
    expect(newBookingButton).toHaveAttribute('href', '/book')
  })

  it('handles sign out button click', async () => {
    apiMock.get(new RegExp('/me/appointments'), [
      200,
      {
        data: [],
        meta: {
          nextCursor: null,
          limit: 50,
          total: 0,
        },
      },
    ])
    apiMock.post('/auth/sign-out', [200, { ok: true }])

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const { router } = renderRoute('/my-bookings')

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 2, name: 'My bookings' })).toBeInTheDocument()
    })

    const signOutButton = screen.getByRole('button', { name: 'Sign out' })
    await user.click(signOutButton)

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/sign-in')
    })
  })

  it('loads more bookings when nextCursor exists', async () => {
    const page2Booking: AppointmentListItem = {
      ...upcomingBooking,
      id: '0192d000-0000-7000-8000-000000000043',
      reference: 'APT-20261102-0043',
      start: '2026-11-02T14:00:00+07:00',
      end: '2026-11-02T15:00:00+07:00',
    }

    apiMock.get(new RegExp('/me/appointments'), (config) => {
      const params = (config.params ?? {}) as Record<string, unknown>
      if (params.cursor === 'cursor-2') {
        return [
          200,
          {
            data: [page2Booking],
            meta: {
              nextCursor: null,
              limit: 1,
              total: 2,
            },
          },
        ]
      }

      return [
        200,
        {
          data: [upcomingBooking],
          meta: {
            nextCursor: 'cursor-2',
            limit: 1,
            total: 2,
          },
        },
      ]
    })

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute('/my-bookings')

    await screen.findByText('APT-20261102-0042')

    const loadMoreButton = screen.getByRole('button', { name: 'Load more' })
    await user.click(loadMoreButton)

    await screen.findByText('APT-20261102-0043')
    expect(screen.getByText('APT-20261102-0042')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeInTheDocument()
  })
})
