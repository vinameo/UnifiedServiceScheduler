import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { notifications } from '@mantine/notifications'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import { notificationsCopy as copy } from './copy'

describe('NotificationsPage', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const failedMessageId = '0192d333-0000-7000-8000-000000000001'
  const appointmentId = '0192d222-0000-7000-8000-000000000002'

  let currentUser: Record<string, unknown> | null = null

  const adminUser = {
    id: 1,
    email: 'admin@saigonauto.test',
    name: 'Nguyễn Văn Quản Lý',
    role: 'ADMIN',
    dealership_id: dealershipId,
    dealership: {
      id: dealershipId,
      code: 'SGA-D7',
      name: 'Saigon Auto',
      branchName: 'District 7',
      timezone: 'Asia/Ho_Chi_Minh',
      phone: '028 3812 3456',
    },
  }

  const advisorUser = {
    id: 2,
    email: 'advisor@saigonauto.test',
    name: 'Trần Thị Cố Vấn',
    role: 'SERVICE_ADVISOR',
    dealership_id: dealershipId,
    dealership: adminUser.dealership,
  }

  const sampleSummary = {
    pending: 1,
    retrying: 1,
    failed: 2,
    sentToday: 214,
  }

  const sampleFailedMessage = {
    id: failedMessageId,
    eventId: '0192d444-0000-7000-8000-000000000001',
    eventType: 'AppointmentCancelled',
    channel: 'EMAIL',
    status: 'FAILED',
    displayStatus: 'Failed',
    attempts: 5,
    nextAttemptAt: null,
    lastError: 'SMTP timeout after 5 s',
    sentAt: null,
    createdAt: '2026-11-02T16:20:41+07:00',
    appointment: {
      id: appointmentId,
      reference: 'APT-20261102-0031',
      customerName: 'Nguyễn Văn Hùng',
    },
  }

  const samplePendingMessage = {
    id: '0192d333-0000-7000-8000-000000000002',
    eventId: '0192d444-0000-7000-8000-000000000002',
    eventType: 'AppointmentConfirmed',
    channel: 'SMS',
    status: 'PENDING',
    displayStatus: 'Pending',
    attempts: 0,
    nextAttemptAt: '2026-11-02T16:25:00+07:00',
    lastError: null,
    sentAt: null,
    createdAt: '2026-11-02T16:24:00+07:00',
    appointment: {
      id: '0192d222-0000-7000-8000-000000000003',
      reference: 'APT-20261102-0032',
      customerName: 'Lê Thị Mai',
    },
  }

  beforeEach(() => {
    notifications.clean()
    apiMock.install()
    apiMock.reset()
    currentUser = adminUser

    apiMock.get('/me', () => {
      if (!currentUser) {
        return apiMock.replyProblem(401, 'UNAUTHENTICATED', 'Please sign in.')
      }
      return [200, currentUser]
    })
  })

  afterEach(() => {
    notifications.clean()
    apiMock.restore()
  })

  it('TC-11-14 (AC-11.10): filters by Failed, clicking Resend sends POST with Idempotency-Key, invalidates queries and shows success notification', async () => {
    let summaryFetchCount = 0
    let listFetchCount = 0
    let retryCalls = 0
    let capturedIdempotencyKey: string | null = null

    apiMock.get('/outbox/summary', () => {
      summaryFetchCount++
      return [200, { data: sampleSummary }]
    })

    apiMock.get('/outbox', (config) => {
      listFetchCount++
      const params = config.params as Record<string, string> | undefined
      const status = params?.status

      if (status === 'FAILED') {
        return [
          200,
          {
            data: [sampleFailedMessage],
            meta: { nextCursor: null, limit: 50, total: 1 },
          },
        ]
      }

      return [
        200,
        {
          data: [sampleFailedMessage, samplePendingMessage],
          meta: { nextCursor: null, limit: 50, total: 2 },
        },
      ]
    })

    apiMock.post(new RegExp(`/outbox/${failedMessageId}/retry`), (config) => {
      retryCalls++
      const headers = config.headers as Record<string, unknown>
      capturedIdempotencyKey = (headers['Idempotency-Key'] ?? headers['idempotency-key']) as string
      return [
        200,
        {
          data: {
            ...sampleFailedMessage,
            status: 'PENDING',
            displayStatus: 'Pending',
            attempts: 0,
            lastError: null,
          },
        },
      ]
    })

    const user = userEvent.setup()
    const { router } = renderRoute('/staff/notifications')

    // 1. Initial render shows summary counts and rows
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 2, name: copy.title })).toBeInTheDocument()
      expect(screen.getByText(copy.subtitle)).toBeInTheDocument()
    })

    await waitFor(() => {
      const failedCard = screen.getByRole('button', { name: copy.stats.failed })
      expect(within(failedCard).getByText('2')).toBeInTheDocument()
    })

    // 2. Click "Failed, needs action" card
    const failedCard = screen.getByRole('button', { name: copy.stats.failed })
    await user.click(failedCard)

    // Verify filter changed to status=FAILED in URL
    await waitFor(() => {
      expect(router.state.location.search).toContain('status=FAILED')
    })

    await waitFor(() => {
      expect(screen.getByText('SMTP timeout after 5 s')).toBeInTheDocument()
    })

    // 3. Find and click "Resend" button
    const initialSummaryCount = summaryFetchCount
    const initialListCount = listFetchCount

    const resendButton = screen.getByRole('button', { name: copy.resend })
    expect(resendButton).toBeInTheDocument()
    await user.click(resendButton)

    // 4. Verify retry request sent with valid UUID Idempotency-Key
    await waitFor(() => {
      expect(retryCalls).toBe(1)
      expect(capturedIdempotencyKey).toBeTruthy()
      expect(capturedIdempotencyKey).toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
      )
    })

    // 5. Verify success toast notification
    await waitFor(() => {
      expect(screen.getByText(copy.resent)).toBeInTheDocument()
    })

    // 6. Verify summary and list refetched
    await waitFor(() => {
      expect(summaryFetchCount).toBeGreaterThan(initialSummaryCount)
      expect(listFetchCount).toBeGreaterThan(initialListCount)
    })
  })

  it('TC-11-14: handles 422 OUTBOX_NOT_RETRYABLE with Alert and refresh', async () => {
    let summaryFetchCount = 0
    let listFetchCount = 0
    let retryCalls = 0

    apiMock.get('/outbox/summary', () => {
      summaryFetchCount++
      return [200, { data: sampleSummary }]
    })

    apiMock.get('/outbox', () => {
      listFetchCount++
      return [
        200,
        {
          data: [sampleFailedMessage],
          meta: { nextCursor: null, limit: 50, total: 1 },
        },
      ]
    })

    apiMock.post(new RegExp(`/outbox/${failedMessageId}/retry`), () => {
      retryCalls++
      return apiMock.replyProblem(
        422,
        'OUTBOX_NOT_RETRYABLE',
        'Only failed messages can be sent again.',
      )
    })

    const user = userEvent.setup()
    renderRoute('/staff/notifications?status=FAILED')

    await waitFor(() => {
      expect(screen.getByRole('button', { name: copy.resend })).toBeInTheDocument()
    })

    const initialSummaryCount = summaryFetchCount
    const initialListCount = listFetchCount

    // Click Resend
    await user.click(screen.getByRole('button', { name: copy.resend }))

    // Expect Alert with README §5 copy "Only failed messages can be sent again."
    await waitFor(() => {
      expect(retryCalls).toBe(1)
      expect(screen.getByText(copy.outboxNotRetryable)).toBeInTheDocument()
    })

    // Expect refresh of queries
    await waitFor(() => {
      expect(summaryFetchCount).toBeGreaterThan(initialSummaryCount)
      expect(listFetchCount).toBeGreaterThan(initialListCount)
    })
  })

  it('renders all summary stat cards with counts and clicking sets status filter', async () => {
    apiMock.get('/outbox/summary', [200, { data: sampleSummary }])
    apiMock.get('/outbox', [
      200,
      {
        data: [],
        meta: { nextCursor: null, limit: 50, total: 0 },
      },
    ])

    const user = userEvent.setup()
    const { router } = renderRoute('/staff/notifications')

    await waitFor(() => {
      expect(screen.getByRole('button', { name: copy.stats.pending })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: copy.stats.retrying })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: copy.stats.failed })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: copy.stats.sentToday })).toBeInTheDocument()
    })

    const sentTodayCard = screen.getByRole('button', { name: copy.stats.sentToday })
    await waitFor(() => {
      expect(within(sentTodayCard).getByText('214')).toBeInTheDocument()
    })

    // Click Pending card
    await user.click(screen.getByRole('button', { name: copy.stats.pending }))
    await waitFor(() => {
      expect(router.state.location.search).toContain('status=PENDING')
    })

    // Click Retrying card
    await user.click(screen.getByRole('button', { name: copy.stats.retrying }))
    await waitFor(() => {
      expect(router.state.location.search).toContain('status=RETRYING')
    })

    // Click Sent today card
    await user.click(screen.getByRole('button', { name: copy.stats.sentToday }))
    await waitFor(() => {
      expect(router.state.location.search).toContain('status=SENT')
    })
  })

  it('renders row details (time, event, appointment link, channel, attempts, status badge, error note)', async () => {
    apiMock.get('/outbox/summary', [200, { data: sampleSummary }])
    apiMock.get('/outbox', [
      200,
      {
        data: [sampleFailedMessage, samplePendingMessage],
        meta: { nextCursor: null, limit: 50, total: 2 },
      },
    ])

    renderRoute('/staff/notifications')

    const table = await screen.findByRole('table')

    await waitFor(() => {
      // Time in Asia/Ho_Chi_Minh: 16:20:41
      expect(within(table).getByText('16:20:41')).toBeInTheDocument()
    })

    // Event labels in table
    expect(within(table).getByText(copy.event.AppointmentCancelled)).toBeInTheDocument()
    expect(within(table).getByText(copy.event.AppointmentConfirmed)).toBeInTheDocument()

    // Appointment link
    const aptLink = within(table).getByRole('link', {
      name: 'APT-20261102-0031 · Nguyễn Văn Hùng',
    })
    expect(aptLink).toHaveAttribute('href', `/staff/appointments/${appointmentId}`)

    // Channel labels in table
    expect(within(table).getByText(copy.channel.Email)).toBeInTheDocument()
    expect(within(table).getByText(copy.channel.SMS)).toBeInTheDocument()

    // Attempts
    expect(within(table).getByText('5/5')).toBeInTheDocument()
    expect(within(table).getByText('0/5')).toBeInTheDocument()

    // Status badges
    expect(within(table).getByText(copy.status.Failed)).toBeInTheDocument()
    expect(within(table).getByText(copy.status.Pending)).toBeInTheDocument()

    // Error note under Failed
    expect(within(table).getByText('SMTP timeout after 5 s')).toBeInTheDocument()

    // Resend button exists only for Failed, not for Pending
    const resendButtons = within(table).getAllByRole('button', { name: copy.resend })
    expect(resendButtons).toHaveLength(1)
  })

  it('displays empty state copy when no events match', async () => {
    apiMock.get('/outbox/summary', [200, { data: sampleSummary }])
    apiMock.get('/outbox', [
      200,
      {
        data: [],
        meta: { nextCursor: null, limit: 50, total: 0 },
      },
    ])

    renderRoute('/staff/notifications?status=SKIPPED')

    await waitFor(() => {
      expect(screen.getByText(copy.empty)).toBeInTheDocument()
    })
  })

  it('displays Forbidden when non-admin staff roles access /staff/notifications', async () => {
    currentUser = advisorUser

    renderRoute('/staff/notifications')

    await waitFor(() => {
      expect(screen.getByText('Forbidden')).toBeInTheDocument()
      expect(
        screen.getByText('You do not have permission to view this section.'),
      ).toBeInTheDocument()
      expect(screen.queryByText(copy.title)).not.toBeInTheDocument()
    })
  })

  it('redirects unauthenticated users to /staff/sign-in', async () => {
    currentUser = null

    const { router } = renderRoute('/staff/notifications')

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/staff/sign-in')
    })
  })
})
