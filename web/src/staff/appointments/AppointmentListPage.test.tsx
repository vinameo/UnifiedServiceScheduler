import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { AppointmentListItem } from '../../shared/api/appointments'

describe('AppointmentListPage', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'

  const mockAdvisorUser = {
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

  const mockForemanUser = {
    ...mockAdvisorUser,
    id: 2,
    role: 'WORKSHOP_FOREMAN',
    email: 'foreman@saigonauto.test',
    name: 'Lê Quản Đốc',
  }

  const mockTechnicians = [
    { id: 'tech-001', name: 'Trần Minh An', level: 2, active: true },
    { id: 'tech-002', name: 'Lê Văn Bình', level: 1, active: true },
  ]

  const mockBays = [
    { id: 'bay-001', code: 'K1', name: 'Bay 1', bayType: 'QUICK', active: true },
    { id: 'bay-002', code: 'K2', name: 'Bay 2', bayType: 'GENERAL', active: true },
  ]

  function createAppointmentItem(index: number, overrides?: Partial<AppointmentListItem>): AppointmentListItem {
    const pad = String(index).padStart(4, '0')
    return {
      id: `0192d000-0000-7000-8000-${pad.padStart(12, '0')}`,
      reference: `APT-20261102-${pad}`,
      status: 'CONFIRMED',
      start: '2026-11-02T09:00:00+07:00',
      end: '2026-11-02T10:00:00+07:00',
      customer: {
        id: `cust-${index}`,
        fullName: `Khách hàng ${index}`,
      },
      vehicle: {
        make: 'Toyota',
        model: 'Vios',
        licencePlate: `51H-${pad}`,
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
        id: 'bay-001',
        code: 'K1',
        name: 'Bay 1',
      },
      dealership: {
        id: dealershipId,
        name: 'Saigon Auto',
        branchName: 'District 7',
        timezone: 'Asia/Ho_Chi_Minh',
      },
      contactRequiredReason: null,
      ...overrides,
    }
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get('/me', [200, mockAdvisorUser])
    apiMock.get(`/dealerships/${dealershipId}/technicians`, [200, { data: mockTechnicians }])
    apiMock.get(`/dealerships/${dealershipId}/bays`, [200, { data: mockBays }])
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-05-14: filters by technician, updates URL, loads next page, and updates footer count', async () => {
    const page1Items = Array.from({ length: 50 }, (_, i) => createAppointmentItem(i + 1))
    const page2Items = Array.from({ length: 10 }, (_, i) => createAppointmentItem(i + 51))

    const capturedRequests: Array<{ url: string; params: Record<string, unknown> }> = []

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/appointments`), (config) => {
      const params = (config.params ?? {}) as Record<string, unknown>
      capturedRequests.push({ url: config.url ?? '', params })

      if (params.cursor === 'cursor-page-2') {
        return [
          200,
          {
            data: page2Items,
            meta: {
              nextCursor: null,
              limit: 50,
              total: 75,
            },
          },
        ]
      }

      return [
        200,
        {
          data: page1Items,
          meta: {
            nextCursor: 'cursor-page-2',
            limit: 50,
            total: 75,
          },
        },
      ]
    })

    const user = userEvent.setup()
    const { router } = renderRoute('/staff/appointments')

    // Initial render displays page 1
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 2, name: 'Appointments' })).toBeInTheDocument()
      expect(screen.getByText('Khách hàng 1')).toBeInTheDocument()
    })

    // URL automatically has default date in dealership timezone
    await waitFor(() => {
      expect(router.state.location.search).toContain('date=2026-11-02')
    })

    // Pick a technician (Trần Minh An -> tech-001)
    const technicianOption = await screen.findByRole('option', { name: 'Trần Minh An', hidden: true })
    await user.click(technicianOption)

    // URL now has technicianId
    await waitFor(() => {
      expect(router.state.location.search).toContain('technicianId=tech-001')
    })

    // The API request included technicianId
    await waitFor(() => {
      const techReq = capturedRequests.find((r) => r.params.technicianId === 'tech-001')
      expect(techReq).toBeDefined()
    })

    // Footer shows 50 of 75 appointments
    expect(
      screen.getByText('Showing 50 of 75 appointments, sorted by start time'),
    ).toBeInTheDocument()

    // Click "Load more"
    const loadMoreButton = screen.getByRole('button', { name: 'Load more' })
    await user.click(loadMoreButton)

    // Request for page 2 sent with cursor and technicianId
    await waitFor(() => {
      const p2Req = capturedRequests.find((r) => r.params.cursor === 'cursor-page-2')
      expect(p2Req).toBeDefined()
      expect(p2Req?.params.technicianId).toBe('tech-001')
    })

    // Page 2 items appended (Khách hàng 51 is visible)
    await screen.findByText('Khách hàng 51')
    expect(screen.getByText('Khách hàng 1')).toBeInTheDocument()

    // Footer now shows "Showing 60 of 75 appointments, sorted by start time"
    await waitFor(() => {
      expect(
        screen.getByText('Showing 60 of 75 appointments, sorted by start time'),
      ).toBeInTheDocument()
    })

    // Since page 2 has nextCursor: null, "Load more" button is now gone
    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeInTheDocument()
  })

  it('renders subtitle with dealership timezone offset and appointment table columns', async () => {
    const singleAppointment = createAppointmentItem(1, {
      start: '2026-11-02T09:00:00+07:00',
      end: '2026-11-02T10:00:00+07:00',
    })

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/appointments`), [
      200,
      {
        data: [singleAppointment],
        meta: {
          nextCursor: null,
          limit: 50,
          total: 1,
        },
      },
    ])

    renderRoute('/staff/appointments')

    // Subtitle formatted correctly
    await screen.findByText('Monday, 2 Nov 2026 · times in dealership time zone (GMT+7)')

    // Table headers
    expect(screen.getByRole('columnheader', { name: 'Time' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Customer · Vehicle' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Service' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Technician' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Bay' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Status' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Actions' })).toBeInTheDocument()

    // Row content
    const table = screen.getByRole('table')
    const withinTable = within(table)
    await withinTable.findByText('09:00–10:00')
    expect(withinTable.getByText('Khách hàng 1')).toBeInTheDocument()
    expect(withinTable.getByText('Toyota Vios · 51H-0001')).toBeInTheDocument()
    expect(withinTable.getByText('Oil change')).toBeInTheDocument()
    expect(withinTable.getByText('Trần Minh An')).toBeInTheDocument()
    expect(withinTable.getByText('Bay 1')).toBeInTheDocument()
    expect(withinTable.getByText('Confirmed')).toBeInTheDocument()

    // Actions link
    const viewLink = withinTable.getByRole('link', { name: 'View' })
    expect(viewLink).toHaveAttribute('href', `/staff/appointments/${singleAppointment.id}`)
  })

  it('renders empty state when there are no appointments on this day', async () => {
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/appointments`), [
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

    renderRoute('/staff/appointments')

    await screen.findByText('No appointments on this day.')
  })

  it('shows "New booking" button for SERVICE_ADVISOR', async () => {
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/appointments`), [
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

    // Advisor: page button present (plus sidebar navlink)
    renderRoute('/staff/appointments')
    await waitFor(() => {
      expect(screen.getAllByRole('link', { name: 'New booking' })).toHaveLength(2)
    })
  })

  it('hides "New booking" button for WORKSHOP_FOREMAN', async () => {
    apiMock.reset()
    apiMock.get('/me', [200, mockForemanUser])
    apiMock.get(`/dealerships/${dealershipId}/technicians`, [200, { data: mockTechnicians }])
    apiMock.get(`/dealerships/${dealershipId}/bays`, [200, { data: mockBays }])
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/appointments`), [
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

    renderRoute('/staff/appointments')
    await screen.findByRole('heading', { level: 2, name: 'Appointments' })

    // "New booking" should not be visible anywhere for foreman (neither sidebar nor page)
    expect(screen.queryByRole('link', { name: 'New booking' })).not.toBeInTheDocument()
  })

  it('filters by bay, status, and date updating URL and request params', async () => {
    const capturedRequests: Array<{ url: string; params: Record<string, unknown> }> = []

    apiMock.get(new RegExp(`/dealerships/${dealershipId}/appointments`), (config) => {
      const params = (config.params ?? {}) as Record<string, unknown>
      capturedRequests.push({ url: config.url ?? '', params })
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
    })

    const user = userEvent.setup()
    const { router, container } = renderRoute('/staff/appointments')

    await screen.findByRole('heading', { level: 2, name: 'Appointments' })

    // 1. Pick a bay (Bay 1 -> bay-001)
    const bayOption = await screen.findByRole('option', { name: 'Bay 1', hidden: true })
    await user.click(bayOption)
    await waitFor(() => {
      expect(router.state.location.search).toContain('bayId=bay-001')
      expect(capturedRequests.some((r) => r.params.bayId === 'bay-001')).toBe(true)
    })

    // 2. Pick a status (Confirmed -> CONFIRMED)
    const statusOption = await screen.findByRole('option', { name: 'Confirmed', hidden: true })
    await user.click(statusOption)
    await waitFor(() => {
      expect(router.state.location.search).toContain('status=CONFIRMED')
      expect(capturedRequests.some((r) => r.params.status === 'CONFIRMED')).toBe(true)
    })

    // 3. Change date
    const dateInput = container.querySelector('input[type="date"]') as HTMLInputElement
    expect(dateInput).toBeInTheDocument()
    fireEvent.change(dateInput, { target: { value: '2026-11-05' } })
    await waitFor(() => {
      expect(router.state.location.search).toContain('date=2026-11-05')
      expect(capturedRequests.some((r) => r.params.date === '2026-11-05')).toBe(true)
    })
  })
})
