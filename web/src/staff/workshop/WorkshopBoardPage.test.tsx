import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'

describe('WorkshopBoardPage (TC-12-28, AC-12.15, D-12.15)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const appointmentId = '0192d222-0000-7000-8000-000000000002'

  const mockForemanUser = {
    id: 2,
    email: 'foreman@saigonauto.test',
    name: 'Nguyễn Văn Đốc',
    role: 'WORKSHOP_FOREMAN',
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

  const mockBoardData = {
    data: {
      date: '2026-11-02',
      now: '2026-11-02T10:50:00+07:00',
      opensAt: '08:00',
      closesAt: '17:00',
      bays: [],
      displaced: [],
      technicians: [],
      alerts: [
        {
          type: 'OVERRUN',
          appointmentId,
          version: 3,
          bayName: 'Bay 3',
          minutesOver: 20,
          customerName: 'Phạm Quốc Bảo',
          serviceName: '20,000 km service',
          technicianName: 'Võ Thị Chi',
          nextBooking: {
            appointmentId: '0192d333-0000-7000-8000-000000000003',
            start: '2026-11-02T14:00:00+07:00',
            status: 'CANCELLED',
          },
        },
      ],
    },
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T03:50:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockForemanUser])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-12-28: given board with alert for X v3, extend 412 shows AppointmentModifiedAlert with Status: In progress -> Completed and refetches board', async () => {
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })

    let boardFetchCount = 0
    apiMock.get(
      new RegExp(`/dealerships/${dealershipId}/workshop-board`),
      () => {
        boardFetchCount++
        return [200, mockBoardData]
      },
    )

    let capturedIfMatch: string | null = null
    apiMock.post(
      new RegExp(`/appointments/${appointmentId}/extend`),
      (config) => {
        const rawHeader = config.headers?.['If-Match'] ?? config.headers?.['if-match']
        capturedIfMatch = typeof rawHeader === 'string' ? rawHeader : null

        return [
          412,
          {
            code: 'APPOINTMENT_MODIFIED',
            title: 'This appointment was modified by another request.',
            appointment: {
              id: appointmentId,
              version: 4,
              status: 'COMPLETED',
              start: '2026-11-02T09:00:00+07:00',
              end: '2026-11-02T10:00:00+07:00',
            },
          },
          { etag: '"4"' },
        ]
      },
    )

    renderRoute('/staff/workshop')

    // Expect alert card to be displayed
    await waitFor(() => {
      expect(screen.getByText('Bay 3 is running 20 minutes over')).toBeInTheDocument()
    })

    expect(boardFetchCount).toBe(1)

    // Tap "Extend 30 min"
    const extendButton = screen.getByRole('button', { name: 'Extend 30 min' })
    expect(extendButton).toBeInTheDocument()
    await user.click(extendButton)

    // Assert: request carried If-Match: "3"
    await waitFor(() => {
      expect(capturedIfMatch).toBe('"3"')
    })

    // Assert: 412 alert shows "Status: In progress → Completed"
    await waitFor(() => {
      expect(screen.getByText('Status: In progress → Completed')).toBeInTheDocument()
    })

    // Assert: the board query refetches
    await waitFor(() => {
      expect(boardFetchCount).toBeGreaterThanOrEqual(2)
    })
  })
})
