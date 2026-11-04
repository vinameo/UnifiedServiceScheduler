import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import { formatAge } from './format'

describe('MonitoringPage', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'

  const sample24h = {
    range: '24h',
    from: '2026-11-01T17:00:00+07:00',
    to: '2026-11-02T17:00:00+07:00',
    health: {
      app: { status: 'UP', bookingP95Ms: 184 },
      database: { status: 'UP', connectionsUsed: 8, connectionsMax: 100 },
      outbox: { pending: 2, oldestPendingAgeSeconds: 420 },
    },
    bookings: {
      total: 1248,
      confirmed: 1102,
      noAvailability: 131,
      validationError: 0,
      error: 15,
      p95Ms: 184,
      allocationRetries: 23,
      idempotentReplays: 41,
      appointmentModified: 7,
    },
    recentErrors: [
      {
        id: 812,
        occurredAt: '2026-11-02T16:44:03+07:00',
        endpoint: 'POST /appointments',
        code: 'NO_AVAILABILITY',
        status: 409,
        correlationId: '7f3c9a1e-1111-2222-3333-444444444444',
        details: 'No Standard service bay free 10:00–12:00',
      },
      {
        id: 810,
        occurredAt: '2026-11-02T16:21:17+07:00',
        endpoint: 'POST /appointments',
        code: 'INTERNAL_ERROR',
        status: 500,
        correlationId: 'e90a55c2-5555-6666-7777-888888888888',
        details: 'Database connection timed out after 5 seconds',
      },
    ],
  }

  const sample7d = {
    ...sample24h,
    range: '7d',
    from: '2026-10-26T17:00:00+07:00',
    to: '2026-11-02T17:00:00+07:00',
  }

  let currentUser: Record<string, unknown> | null = null

  beforeEach(() => {
    apiMock.install()
    apiMock.reset()

    currentUser = {
      id: 1,
      email: 'admin@saigonauto.test',
      name: 'Nguyễn Văn Quản Lý',
      role: 'ADMIN',
      dealership: {
        id: dealershipId,
        code: 'SGA-D7',
        name: 'Saigon Auto',
        branchName: 'District 7',
        timezone: 'Asia/Ho_Chi_Minh',
        phone: '028 3812 3456',
      },
      customer: null,
    }

    apiMock.get('/me', () => {
      if (!currentUser) {
        return apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.')
      }
      return [200, currentUser]
    })

    // Mock clipboard writeText
    Object.defineProperty(navigator, 'clipboard', {
      value: {
        writeText: vi.fn().mockResolvedValue(undefined),
      },
      writable: true,
      configurable: true,
    })
  })

  afterEach(() => {
    apiMock.restore()
    vi.restoreAllMocks()
  })

  it('formatAge formats seconds into human-readable units', () => {
    expect(formatAge(0)).toBe('0 s')
    expect(formatAge(-5)).toBe('0 s')
    expect(formatAge(45)).toBe('45 s')
    expect(formatAge(420)).toBe('7 min')
    expect(formatAge(7200)).toBe('2 h')
    expect(formatAge(172800)).toBe('2 d')
  })

  it('TC-08-13 (AC-08.12): given Fake API summary 24h; when Render, click "7 days"; then Card "No availability (409)" "131" "10.5% · business outcome, not an outage"; URL ?range=7d, API called again with 7d', async () => {
    const user = userEvent.setup()
    const summaryCalls: string[] = []

    apiMock.get(/\/monitoring\/summary/, (config) => {
      const range = (config.params?.range as string | undefined) ?? '1h'
      summaryCalls.push(range)
      if (range === '7d') {
        return [200, { data: sample7d }]
      }
      return [200, { data: sample24h }]
    })

    const { router } = renderRoute('/staff/monitoring?range=24h')

    // Wait for the page to render with initial data
    await waitFor(() => {
      expect(screen.getByText('System monitoring')).toBeInTheDocument()
      expect(screen.getByText('No availability (409)')).toBeInTheDocument()
    })

    expect(summaryCalls).toContain('24h')

    // Click "7 days" range
    const sevenDaysOption = screen.getByText('7 days')
    await user.click(sevenDaysOption)

    // Verify Card "No availability (409)" displays "131" and "10.5% · business outcome, not an outage"
    await waitFor(() => {
      const noAvailTitle = screen.getByText('No availability (409)')
      const card = noAvailTitle.closest('div')!.parentElement!
      expect(within(card).getByText('131')).toBeInTheDocument()
      expect(
        within(card).getByText('10.5% · business outcome, not an outage'),
      ).toBeInTheDocument()
    })

    // Verify URL ?range=7d
    await waitFor(() => {
      expect(router.state.location.search).toBe('?range=7d')
    })

    // Verify API called again with 7d
    await waitFor(() => {
      expect(summaryCalls).toContain('7d')
    })
  })

  it('renders health cards with Application, Database, and Outbox pending', async () => {
    apiMock.get(/\/monitoring\/summary/, [200, { data: sample24h }])

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      expect(screen.getByText('Application')).toBeInTheDocument()
      expect(screen.getByText('Running · p95 184 ms (bookings)')).toBeInTheDocument()
      expect(screen.getByText('Database')).toBeInTheDocument()
      expect(screen.getByText('PostgreSQL · 8/100 connections')).toBeInTheDocument()
      expect(screen.getByText('UP')).toBeInTheDocument()
      expect(screen.getByText('Outbox pending')).toBeInTheDocument()
      expect(screen.getByText('2 events · oldest 7 min')).toBeInTheDocument()
      expect(screen.getByRole('link', { name: 'View' })).toHaveAttribute(
        'href',
        '/staff/notifications?status=PENDING',
      )
    })
  })

  it('renders all 7 booking figure cards with percentages and notes', async () => {
    apiMock.get(/\/monitoring\/summary/, [200, { data: sample24h }])

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      expect(screen.getByText('Bookings in the last 24 hours')).toBeInTheDocument()
      expect(screen.getByText('Booking requests')).toBeInTheDocument()
      expect(screen.getByText('1,248')).toBeInTheDocument()
      expect(screen.getByText('Total POST /appointments')).toBeInTheDocument()

      expect(screen.getByText('Confirmed')).toBeInTheDocument()
      expect(screen.getByText('1,102')).toBeInTheDocument()
      expect(screen.getByText('88.3% of requests')).toBeInTheDocument()

      expect(screen.getByText('System errors (5xx)')).toBeInTheDocument()
      expect(screen.getByText('15')).toBeInTheDocument()
      expect(screen.getByText('1.2% of requests')).toBeInTheDocument()

      expect(screen.getByText('p95 response time')).toBeInTheDocument()
      expect(screen.getByText('184 ms')).toBeInTheDocument()
      expect(screen.getByText('Target under 300 ms')).toBeInTheDocument()

      expect(screen.getByText('Allocation retries')).toBeInTheDocument()
      expect(screen.getByText('23')).toBeInTheDocument()
      expect(screen.getByText('Constraint conflicts, retried automatically')).toBeInTheDocument()

      expect(screen.getByText('Idempotent replays')).toBeInTheDocument()
      expect(screen.getByText('41')).toBeInTheDocument()
      expect(screen.getByText('Returned the original result, no duplicate')).toBeInTheDocument()
    })
  })

  it('highlights p95 in red when response time is >= 300 ms', async () => {
    const highLatencySummary = {
      ...sample24h,
      bookings: {
        ...sample24h.bookings,
        p95Ms: 350,
      },
    }
    apiMock.get(/\/monitoring\/summary/, [200, { data: highLatencySummary }])

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      const p95Text = screen.getByText('350 ms')
      expect(p95Text).toBeInTheDocument()
      expect(p95Text.getAttribute('style')).toMatch(/color: var\(--mantine-color-red/)
    })
  })

  it('renders recent errors table and copies correlationId on click', async () => {
    const user = userEvent.setup()
    apiMock.get(/\/monitoring\/summary/, [200, { data: sample24h }])

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      expect(screen.getByText('Recent errors')).toBeInTheDocument()
      expect(screen.getByText('16:44:03')).toBeInTheDocument()
      expect(screen.getByText('NO_AVAILABILITY')).toBeInTheDocument()
      expect(screen.getByText('409')).toBeInTheDocument()
      expect(screen.getByText('7f3c9a1e')).toBeInTheDocument()
      expect(
        screen.getByText('No Standard service bay free 10:00–12:00'),
      ).toBeInTheDocument()

      expect(screen.getByText('16:21:17')).toBeInTheDocument()
      expect(screen.getByText('INTERNAL_ERROR')).toBeInTheDocument()
      expect(screen.getByText('500')).toBeInTheDocument()
      expect(screen.getByText('e90a55c2')).toBeInTheDocument()
      expect(
        screen.getByText('Database connection timed out after 5 seconds'),
      ).toBeInTheDocument()
    })

    // Click correlationId copy button
    const copyBtn = screen.getByLabelText(/Copy 7f3c9a1e/)
    await user.click(copyBtn)

    expect(await navigator.clipboard.readText()).toBe(
      '7f3c9a1e-1111-2222-3333-444444444444',
    )
  })

  it('searches errors by correlationId when >= 8 characters are entered', async () => {
    const user = userEvent.setup()
    apiMock.get(/\/monitoring\/summary/, [200, { data: sample24h }])

    let searchedCorrelationId = ''
    apiMock.get(/\/monitoring\/errors/, (config) => {
      searchedCorrelationId = (config.params?.correlationId as string | undefined) ?? ''
      return [
        200,
        {
          data: [
            {
              id: 999,
              occurredAt: '2026-11-02T10:00:00+07:00',
              endpoint: 'POST /appointments',
              code: 'CUSTOM_ERROR',
              status: 400,
              correlationId: 'search12345',
              details: 'Searched error item details',
            },
          ],
        },
      ]
    })

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      expect(screen.getByText('Recent errors')).toBeInTheDocument()
    })

    const searchInput = screen.getByPlaceholderText('e.g. 7f3c9a1e')

    // Typing < 8 characters does not trigger search
    await user.type(searchInput, 'abc')
    expect(searchedCorrelationId).toBe('')

    // Typing up to 8+ characters triggers search
    await user.type(searchInput, 'defgh')

    await waitFor(() => {
      expect(searchedCorrelationId).toBe('abcdefgh')
      expect(screen.getByText('CUSTOM_ERROR')).toBeInTheDocument()
      expect(screen.getByText('Searched error item details')).toBeInTheDocument()
    })
  })

  it('displays empty state when searching yields no results', async () => {
    const user = userEvent.setup()
    apiMock.get(/\/monitoring\/summary/, [200, { data: sample24h }])
    apiMock.get(/\/monitoring\/errors/, [200, { data: [] }])

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      expect(screen.getByText('Recent errors')).toBeInTheDocument()
    })

    const searchInput = screen.getByPlaceholderText('e.g. 7f3c9a1e')
    await user.type(searchInput, 'nonexistent123')

    await waitFor(() => {
      expect(
        screen.getByText('No error with this correlation id.'),
      ).toBeInTheDocument()
    })
  })

  it('displays empty state when summary has no recent errors', async () => {
    const emptyErrorsSummary = {
      ...sample24h,
      recentErrors: [],
    }
    apiMock.get(/\/monitoring\/summary/, [200, { data: emptyErrorsSummary }])

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      expect(screen.getByText('No errors in this period.')).toBeInTheDocument()
    })
  })

  it('displays alert with Retry button when loading fails', async () => {
    const user = userEvent.setup()
    let fail = true
    apiMock.get(/\/monitoring\/summary/, () => {
      if (fail) {
        return [500, { message: 'Server error' }]
      }
      return [200, { data: sample24h }]
    })

    renderRoute('/staff/monitoring?range=24h')

    await waitFor(() => {
      expect(
        screen.getByText('Failed to load monitoring summary.'),
      ).toBeInTheDocument()
    })

    fail = false
    const retryBtn = screen.getByRole('button', { name: 'Retry' })
    await user.click(retryBtn)

    await waitFor(() => {
      expect(screen.getByText('Booking requests')).toBeInTheDocument()
    })
  })

  it('displays forbidden screen for non-admin staff users', async () => {
    currentUser = {
      id: 2,
      email: 'advisor@saigonauto.test',
      name: 'Nguyễn Văn Advisor',
      role: 'SERVICE_ADVISOR',
      dealership: {
        id: dealershipId,
        code: 'SGA-D7',
        name: 'Saigon Auto',
        branchName: 'District 7',
        timezone: 'Asia/Ho_Chi_Minh',
        phone: '028 3812 3456',
      },
      customer: null,
    }

    renderRoute('/staff/monitoring')

    await waitFor(() => {
      expect(screen.getByText('Forbidden')).toBeInTheDocument()
      expect(
        screen.getByText('You do not have permission to view this section.'),
      ).toBeInTheDocument()
      expect(screen.queryByText('System monitoring')).not.toBeInTheDocument()
    })
  })

  it('redirects unauthenticated users to staff sign-in', async () => {
    currentUser = null

    const { router } = renderRoute('/staff/monitoring')

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/staff/sign-in')
    })
  })
})
