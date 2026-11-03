import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AppProviders } from '../../AppProviders'
import { apiMock } from '../../test/apiMock'
import { idempotentPost } from '../idempotency/idempotentPost'
import { useIdempotencyKey } from '../idempotency/useIdempotencyKey'
import { AppointmentModifiedAlert } from './AppointmentModifiedAlert'
import type { AppointmentDiffInput } from './diffAppointment'

describe('AppointmentModifiedAlert (TC-04-38, TC-04-40, WEB-04.5)', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('TC-04-38: renders Status: Confirmed → Cancelled, no time/technician rows, button calls onReview, no request sent', async () => {
    const user = userEvent.setup()
    const onReview = vi.fn()

    const before = {
      status: 'CONFIRMED',
      start: '09:00',
      technician: 'An',
    }
    const latest = {
      status: 'CANCELLED',
      start: '09:00',
      technician: 'An',
      version: 4,
    }

    render(
      <AppProviders>
        <AppointmentModifiedAlert
          before={before}
          latest={latest}
          etag='"4"'
          onReview={onReview}
        />
      </AppProviders>,
    )

    // Title and body
    expect(
      screen.getByText('This appointment was changed by someone else'),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Review the latest details and try again.'),
    ).toBeInTheDocument()

    // Status diff is shown
    expect(screen.getByText('Status: Confirmed → Cancelled')).toBeInTheDocument()

    // Time and technician did not change, so no rows for them
    expect(screen.queryByText(/Time:/)).not.toBeInTheDocument()
    expect(screen.queryByText(/Technician:/)).not.toBeInTheDocument()

    // Click "Review and retry"
    const button = screen.getByRole('button', { name: 'Review and retry' })
    await user.click(button)

    // onReview called with latest and etag
    expect(onReview).toHaveBeenCalledTimes(1)
    expect(onReview).toHaveBeenCalledWith(latest, '"4"')
  })

  it('TC-04-40: first cancel 412, review and retry updates ETag and generates DIFFERENT Idempotency-Key, then 200 shown', async () => {
    const user = userEvent.setup()
    const capturedRequests: Array<{ ifMatch?: string; idempotencyKey?: string }> = []
    let callCount = 0

    apiMock.post('/appointments/apt-1/cancel', (config) => {
      callCount++
      const ifMatch = config.headers?.['If-Match'] as string | undefined
      const idempotencyKey = config.headers?.['Idempotency-Key'] as string | undefined
      capturedRequests.push({ ifMatch, idempotencyKey })

      if (callCount === 1) {
        return apiMock.replyProblem(
          412,
          'APPOINTMENT_MODIFIED',
          'This appointment was modified by another request.',
          {
            appointment: {
              id: 'apt-1',
              status: 'CANCELLED',
              start: '09:00',
              technician: 'An',
              version: 4,
            },
          },
          { ETag: '"4"' },
        )
      }

      return [200, { id: 'apt-1', status: 'CANCELLED', version: 5 }]
    })

    function CancelFlowComponent() {
      const [appointment, setAppointment] = useState<AppointmentDiffInput>({
        id: 'apt-1',
        status: 'CONFIRMED',
        start: '09:00',
        technician: 'An',
        version: 3,
      })
      const [etag, setEtag] = useState<string>('"3"')
      const [modifiedData, setModifiedData] = useState<{
        before: AppointmentDiffInput
        latest: AppointmentDiffInput
        etag: string
      } | null>(null)
      const [cancelled, setCancelled] = useState(false)

      const { key, settle } = useIdempotencyKey({
        id: appointment.id,
        etag,
        action: 'cancel',
      })

      const handleCancel = async () => {
        const res = await idempotentPost(
          '/appointments/apt-1/cancel',
          { reason: 'Customer requested' },
          key,
          { ifMatch: etag },
        )

        if (res.kind === 'problem' && res.status === 412) {
          settle(res.problem)
          const latestAppt = (res.problem.appointment as AppointmentDiffInput) ?? {
            id: 'apt-1',
            status: 'CANCELLED',
            version: 4,
          }
          setModifiedData({
            before: appointment,
            latest: latestAppt,
            etag: '"4"',
          })
        } else if (res.kind === 'ok') {
          settle(res.status)
          setCancelled(true)
        }
      }

      const handleReview = (latest: AppointmentDiffInput, newEtag: string) => {
        setAppointment(latest)
        setEtag(newEtag)
        setModifiedData(null)
      }

      return (
        <div>
          {!cancelled && (
            <button type="button" onClick={handleCancel}>
              Submit cancel
            </button>
          )}

          {modifiedData && (
            <AppointmentModifiedAlert
              before={modifiedData.before}
              latest={modifiedData.latest}
              etag={modifiedData.etag}
              onReview={handleReview}
            />
          )}

          {cancelled && <div>Appointment successfully cancelled!</div>}
        </div>
      )
    }

    render(
      <AppProviders>
        <CancelFlowComponent />
      </AppProviders>,
    )

    // 1. Submit cancel (sends If-Match "3")
    await user.click(screen.getByRole('button', { name: 'Submit cancel' }))

    // 2. Alert is shown with "This appointment was changed by someone else"
    expect(
      screen.getByText('This appointment was changed by someone else'),
    ).toBeInTheDocument()
    expect(screen.getByText('Status: Confirmed → Cancelled')).toBeInTheDocument()

    // 3. Tap "Review and retry"
    await user.click(screen.getByRole('button', { name: 'Review and retry' }))

    // 4. Alert is dismissed
    expect(
      screen.queryByText('This appointment was changed by someone else'),
    ).not.toBeInTheDocument()

    // 5. Submit cancel again
    await user.click(screen.getByRole('button', { name: 'Submit cancel' }))

    // 6. Second call succeeds and 200 message shown
    expect(
      await screen.findByText('Appointment successfully cancelled!'),
    ).toBeInTheDocument()

    // 7. Verify the two requests sent
    expect(capturedRequests).toHaveLength(2)

    // First request: If-Match "3"
    expect(capturedRequests[0].ifMatch).toBe('"3"')
    const key1 = capturedRequests[0].idempotencyKey
    expect(key1).toBeDefined()

    // Second request: If-Match "4" and DIFFERENT Idempotency-Key
    expect(capturedRequests[1].ifMatch).toBe('"4"')
    const key2 = capturedRequests[1].idempotencyKey
    expect(key2).toBeDefined()
    expect(key2).not.toBe(key1)
  })
})
