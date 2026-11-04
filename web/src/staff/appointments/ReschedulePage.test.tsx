import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { InternalAxiosRequestConfig } from 'axios'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { Appointment } from '../../shared/api/appointments'

describe('ReschedulePage (TC-09-16)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const appointmentId = '0192d222-0000-7000-8000-000000000002'

  const mockStaffUser = {
    id: 1,
    email: 'advisor@saigonauto.test',
    name: 'Trần Thị Cố Vấn',
    role: 'SERVICE_ADVISOR',
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

  const baseAppointment: Appointment = {
    id: appointmentId,
    reference: 'APT-20261102-0042',
    status: 'CONFIRMED',
    start: '2026-11-02T09:00:00+07:00',
    end: '2026-11-02T10:00:00+07:00',
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
    source: 'STAFF',
    createdAt: '2026-10-28T16:40:00+07:00',
    createdBy: {
      name: 'Minh Anh',
      role: 'SERVICE_ADVISOR',
    },
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockStaffUser])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-09-16 (component): picks 14:00, shows summary, sends If-Match & keepTechnician: true, navigates to details with notification', async () => {
    let capturedRequest: InternalAxiosRequestConfig | null = null

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      baseAppointment,
      { etag: '"1"' },
    ])
    apiMock.get(`/appointments/${appointmentId}/history`, [200, []])

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), [
      200,
      {
        data: {
          date: '2026-11-02',
          timezone: 'Asia/Ho_Chi_Minh',
          closed: false,
          opensAt: '08:00',
          closesAt: '17:00',
          stepMinutes: 15,
          durationMinutes: 60,
          freeCount: 2,
          slots: [
            {
              start: '2026-11-02T09:00:00+07:00',
              end: '2026-11-02T10:00:00+07:00',
              available: true,
              reason: null,
            },
            {
              start: '2026-11-02T14:00:00+07:00',
              end: '2026-11-02T15:00:00+07:00',
              available: true,
              reason: null,
            },
          ],
        },
      },
    ])

    apiMock.post(new RegExp(`/appointments/${appointmentId}/reschedule`), (config) => {
      capturedRequest = config
      return [
        200,
        {
          ...baseAppointment,
          start: '2026-11-02T14:00:00+07:00',
          end: '2026-11-02T15:00:00+07:00',
          version: 2,
        },
        { etag: '"2"' },
      ]
    })

    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderRoute(`/staff/appointments/${appointmentId}/reschedule`)

    // 1. Page renders title and subtitle
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 2, name: 'Reschedule appointment' })).toBeInTheDocument()
    })
    expect(
      screen.getByText(/Nguyễn Văn Hùng · Toyota Vios · Oil change \(60 min\)/),
    ).toBeInTheDocument()

    // 2. Current booking card
    expect(screen.getByText('Mon 2 Nov, 09:00–10:00')).toBeInTheDocument()
    expect(screen.getByText('Trần Minh An')).toBeInTheDocument()
    expect(screen.getByText('Bay 2')).toBeInTheDocument()

    // 3. Prefer keeping technician checkbox default checked
    const keepTechCheckbox = screen.getByLabelText('Prefer keeping technician Trần Minh An')
    expect(keepTechCheckbox).toBeChecked()

    // 4. Own slot shows as Current
    await waitFor(() => {
      expect(screen.getByText('Current')).toBeInTheDocument()
    })

    // 5. Pick 14:00
    const slot1400Btn = screen.getByRole('button', { name: '14:00' })
    await user.click(slot1400Btn)

    // 6. Summary line shows "Change to Mon 2 Nov, 14:00–15:00"
    expect(screen.getByText('Change to Mon 2 Nov, 14:00–15:00')).toBeInTheDocument()

    // 7. Click "Confirm reschedule"
    const confirmBtn = screen.getByRole('button', { name: 'Confirm reschedule' })
    expect(confirmBtn).not.toBeDisabled()
    await user.click(confirmBtn)

    // 8. Assert captured request headers and body
    await waitFor(() => {
      expect(capturedRequest).not.toBeNull()
    })

    const headers = capturedRequest!.headers
    expect(headers['If-Match'] || headers.get?.('If-Match')).toBe('"1"')

    const body = typeof capturedRequest!.data === 'string'
      ? JSON.parse(capturedRequest!.data)
      : capturedRequest!.data
    expect(body.newStart).toBe('2026-11-02T14:00:00+07:00')
    expect(body.keepTechnician).toBe(true)

    // 9. Navigates to details with "Appointment moved to Mon 2 Nov, 14:00–15:00"
    await waitFor(() => {
      expect(
        screen.getByText('Appointment moved to Mon 2 Nov, 14:00–15:00'),
      ).toBeInTheDocument()
    })
  })
})
