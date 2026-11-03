import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { useState } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AppProviders } from '../../AppProviders'
import { apiMock } from '../../test/apiMock'
import { IDEMPOTENCY_COPY, shortenKey } from './copy'
import { IdempotentRetryPanel } from './IdempotentRetryPanel'
import { idempotentPost } from './idempotentPost'
import { useIdempotencyKey } from './useIdempotencyKey'

describe('IdempotentRetryPanel (TC-04-36, TC-04-39, WEB-04.4)', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('shortens idempotency keys correctly', () => {
    expect(shortenKey('12345678')).toBe('12345678')
    expect(shortenKey('c0a80101-1234-4567-89ab-0123456789ab')).toBe('c0a8…89ab')
  })

  it('renders staff variant with shortened key', () => {
    const onRetry = vi.fn()
    render(
      <AppProviders>
        <IdempotentRetryPanel
          idempotencyKey="c0a80101-1234-4567-89ab-0123456789ab"
          reason="no-response"
          variant="staff"
          onRetry={onRetry}
        />
      </AppProviders>,
    )

    expect(screen.getByText(IDEMPOTENCY_COPY.retryTitle)).toBeInTheDocument()
    expect(
      screen.getByText(
        'It is safe to retry. The request reuses key c0a8…89ab, so if the first attempt succeeded the system returns that same appointment instead of creating a second one.',
      ),
    ).toBeInTheDocument()
  })

  it('TC-04-36: first call times out, panel shown, tap Retry sends the same key and succeeds 201', async () => {
    const user = userEvent.setup()
    const capturedKeys: string[] = []
    let callCount = 0

    apiMock.post('/appointments', (config) => {
      callCount++
      const key = config.headers?.['Idempotency-Key'] as string
      capturedKeys.push(key)

      if (callCount === 1) {
        // Simulate network timeout without response
        throw new AxiosError('timeout of 15000ms exceeded', AxiosError.ECONNABORTED, config)
      }

      return [201, { id: 'apt-1', status: 'CONFIRMED' }]
    })

    function BookingTestForm() {
      const { key, settle } = useIdempotencyKey({ dealershipId: 'd1' })
      const [panel, setPanel] = useState<boolean>(false)
      const [success, setSuccess] = useState<boolean>(false)

      const handleSend = async () => {
        const res = await idempotentPost('/appointments', { service: 'oil' }, key)
        if (res.kind === 'no-response') {
          setPanel(true)
        } else if (res.kind === 'ok') {
          settle(res.status)
          setPanel(false)
          setSuccess(true)
        }
      }

      return (
        <div>
          <button type="button" onClick={handleSend}>
            Submit booking
          </button>
          {panel && (
            <IdempotentRetryPanel
              idempotencyKey={key}
              reason="no-response"
              variant="customer"
              onRetry={handleSend}
            />
          )}
          {success && <div>Booking confirmed!</div>}
        </div>
      )
    }

    render(
      <AppProviders>
        <BookingTestForm />
      </AppProviders>,
    )

    // 1. Submit initial request
    await user.click(screen.getByRole('button', { name: 'Submit booking' }))

    // 2. See the panel with "No response from the server yet"
    expect(screen.getByText('No response from the server yet')).toBeInTheDocument()
    expect(
      screen.getByText("No response yet. It's safe to try again: you won't be booked twice."),
    ).toBeInTheDocument()

    // 3. Tap Retry
    await user.click(screen.getByRole('button', { name: 'Retry' }))

    // 4. Booking succeeds
    expect(await screen.findByText('Booking confirmed!')).toBeInTheDocument()

    // 5. The second call sent the EXACT same Idempotency-Key
    expect(capturedKeys).toHaveLength(2)
    expect(capturedKeys[0]).toBeDefined()
    expect(capturedKeys[1]).toBe(capturedKeys[0])
  })

  it('TC-04-39: 409 REQUEST_IN_PROGRESS shows inProgress copy and Retry reuses the same key', async () => {
    const user = userEvent.setup()
    const capturedKeys: string[] = []
    let callCount = 0

    apiMock.post('/appointments', (config) => {
      callCount++
      const key = config.headers?.['Idempotency-Key'] as string
      capturedKeys.push(key)

      if (callCount === 1) {
        return apiMock.replyProblem(
          409,
          'REQUEST_IN_PROGRESS',
          'A request with this key is already running.',
        )
      }

      return [200, { id: 'apt-1', status: 'CONFIRMED' }]
    })

    function BookingTestForm() {
      const { key, settle } = useIdempotencyKey({ dealershipId: 'd1' })
      const [reason, setReason] = useState<'in-progress' | null>(null)
      const [success, setSuccess] = useState<boolean>(false)

      const handleSend = async () => {
        const res = await idempotentPost('/appointments', { service: 'oil' }, key)
        if (res.kind === 'problem' && res.problem.code === 'REQUEST_IN_PROGRESS') {
          settle(res.problem)
          setReason('in-progress')
        } else if (res.kind === 'ok') {
          settle(res.status)
          setReason(null)
          setSuccess(true)
        }
      }

      return (
        <div>
          <button type="button" onClick={handleSend}>
            Submit action
          </button>
          {reason && (
            <IdempotentRetryPanel
              idempotencyKey={key}
              reason={reason}
              variant="customer"
              onRetry={handleSend}
            />
          )}
          {success && <div>Action completed!</div>}
        </div>
      )
    }

    render(
      <AppProviders>
        <BookingTestForm />
      </AppProviders>,
    )

    // 1. Submit action -> 409 REQUEST_IN_PROGRESS
    await user.click(screen.getByRole('button', { name: 'Submit action' }))

    // 2. inProgress.body copy is displayed
    expect(
      screen.getByText('Your request is still being processed. Wait a moment, then check again.'),
    ).toBeInTheDocument()

    // 3. Tap "Retry"
    await user.click(screen.getByRole('button', { name: 'Retry' }))

    // 4. Action completes
    expect(await screen.findByText('Action completed!')).toBeInTheDocument()

    // 5. The second call reused the same key
    expect(capturedKeys).toHaveLength(2)
    expect(capturedKeys[1]).toBe(capturedKeys[0])
  })

  it('D-05.8: renders "Check appointment list" for staff linking to /staff/appointments', () => {
    render(
      <AppProviders>
        <IdempotentRetryPanel
          idempotencyKey="c0a80101-1234-4567-89ab-0123456789ab"
          reason="no-response"
          variant="staff"
          onRetry={vi.fn()}
        />
      </AppProviders>,
    )

    const link = screen.getByRole('link', { name: 'Check appointment list' })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/staff/appointments')
  })

  it('D-05.8: renders "Check appointment list" for staff with date query parameter', () => {
    render(
      <AppProviders>
        <IdempotentRetryPanel
          idempotencyKey="c0a80101-1234-4567-89ab-0123456789ab"
          reason="no-response"
          variant="staff"
          date="2026-11-02"
          onRetry={vi.fn()}
        />
      </AppProviders>,
    )

    const link = screen.getByRole('link', { name: 'Check appointment list' })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/staff/appointments?date=2026-11-02')
  })

  it('D-05.8: renders "Check appointment list" for customer linking to /my-bookings', () => {
    render(
      <AppProviders>
        <IdempotentRetryPanel
          idempotencyKey="c0a80101-1234-4567-89ab-0123456789ab"
          reason="no-response"
          variant="customer"
          onRetry={vi.fn()}
        />
      </AppProviders>,
    )

    const link = screen.getByRole('link', { name: 'Check appointment list' })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/my-bookings')
  })

  it('D-05.8: custom secondaryAction overrides the default Check appointment list button', () => {
    render(
      <AppProviders>
        <IdempotentRetryPanel
          idempotencyKey="c0a80101-1234-4567-89ab-0123456789ab"
          reason="no-response"
          variant="staff"
          onRetry={vi.fn()}
          secondaryAction={<button type="button">Custom secondary action</button>}
        />
      </AppProviders>,
    )

    expect(screen.queryByRole('link', { name: 'Check appointment list' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Custom secondary action' })).toBeInTheDocument()
  })
})
