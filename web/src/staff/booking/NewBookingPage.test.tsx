import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { CreateAppointmentPayload } from '../../shared/api/appointments'

describe('NewBookingPage (TC-02-25)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const customerId = '0192d222-0000-7000-8000-000000000002'
  const vehicleId = '0192d333-0000-7000-8000-000000000003'
  const serviceTypeId = '0192d444-0000-7000-8000-000000000004'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()
    apiMock.get(new RegExp(`/dealerships/${dealershipId}/availability`), (config) => {
      const url = new URL(config.url ?? '', 'http://localhost')
      const date = url.searchParams.get('date') ?? '2026-11-02'
      const slots: { start: string; end: string; available: boolean; reason: string | null }[] = []
      for (let h = 8; h <= 16; h++) {
        for (const m of [0, 15, 30, 45]) {
          if (h === 16 && m > 0) continue
          const hh = String(h).padStart(2, '0')
          const mm = String(m).padStart(2, '0')
          const endH = String(h + 1).padStart(2, '0')
          slots.push({
            start: `${date}T${hh}:${mm}:00+07:00`,
            end: `${date}T${endH}:${mm}:00+07:00`,
            available: true,
            reason: null,
          })
        }
      }
      return [
        200,
        {
          data: {
            date,
            timezone: 'Asia/Ho_Chi_Minh',
            closed: false,
            opensAt: '08:00',
            closesAt: '17:00',
            stepMinutes: 15,
            durationMinutes: 60,
            freeCount: slots.length,
            slots,
          },
        },
      ]
    })
  })

  afterEach(() => {
    vi.useRealTimers()
    sessionStorage.clear()
    apiMock.restore()
  })

  it('TC-02-25: advisor picks customer, vehicle, service, time and confirms appointment', async () => {
    let capturedRequest: CreateAppointmentPayload | null = null
    let capturedHeaders: Record<string, string | undefined> | null = null

    apiMock.get('/me', [
      200,
      {
        id: 2,
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
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [
            { weekday: 1, opensAt: '08:00', closesAt: '17:00' },
          ],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      {
        data: [
          {
            id: serviceTypeId,
            code: 'OIL_CHANGE',
            name: 'Oil change',
            description: 'Quick engine oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
        ],
      },
    ])

    apiMock.get('/customers', [
      200,
      {
        data: [
          {
            id: customerId,
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
            email: 'hung@example.test',
            vehicleCount: 1,
          },
        ],
        meta: { total: 1 },
      },
    ])

    apiMock.get(`/customers/${customerId}/vehicles`, [
      200,
      {
        data: [
          {
            id: vehicleId,
            licencePlate: '51H-123.45',
            make: 'Toyota',
            model: 'Vios',
            year: 2021,
          },
        ],
      },
    ])

    apiMock.post('/appointments', (config) => {
      capturedRequest = JSON.parse(config.data as string)
      capturedHeaders = config.headers
      return [
        201,
        {
          id: '0192d555-0000-7000-8000-000000000005',
          reference: 'APT-20261102-0042',
          status: 'CONFIRMED',
          start: '2026-11-02T09:00:00+07:00',
          end: '2026-11-02T10:00:00+07:00',
          version: 1,
          dealership: {
            id: dealershipId,
            name: 'Saigon Auto',
            branchName: 'District 7',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3812 3456',
          },
          serviceType: {
            id: serviceTypeId,
            code: 'OIL_CHANGE',
            name: 'Oil change',
            durationMinutes: 60,
          },
          technician: {
            id: 'tech-1',
            name: 'Nguyễn Văn An',
          },
          bay: {
            id: 'bay-1',
            code: 'K1',
            name: 'Quick service bay 1',
            bayType: 'QUICK_SERVICE',
          },
          customer: {
            id: customerId,
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
          },
          vehicle: {
            id: vehicleId,
            licencePlate: '51H-123.45',
            make: 'Toyota',
            model: 'Vios',
          },
          reminderOptIn: true,
          source: 'STAFF',
          createdAt: '2026-11-02T08:00:00+07:00',
        },
        { ETag: '"1"', Location: '/api/v1/appointments/0192d555-0000-7000-8000-000000000005' },
      ]
    })

    const user = userEvent.setup()
    renderRoute('/staff')

    // Wait for title
    await waitFor(() => {
      expect(screen.getByText('Book a service appointment')).toBeInTheDocument()
    })

    // 1. Search & select customer
    const customerInput = screen.getByPlaceholderText('Search customer by name, email or phone')
    await user.type(customerInput, 'Nguyễn')

    const customerOption = await screen.findByText('Nguyễn Văn Hùng · 0903 *** 218')
    await user.click(customerOption)

    // 2. Select vehicle
    const vehicleInput = await screen.findByPlaceholderText('Choose a vehicle')
    await user.click(vehicleInput)
    const vehicleOption = await screen.findByText('Toyota Vios · 51H-123.45')
    await user.click(vehicleOption)

    // 3. Pick service
    const serviceRadio = await screen.findByText('Oil change')
    await user.click(serviceRadio)

    // 4. Select time slot 09:00
    const timeButton = await screen.findByRole('button', { name: '09:00' })
    await user.click(timeButton)

    // 5. Confirm booking
    const confirmButton = screen.getByRole('button', { name: 'Confirm booking' })
    expect(confirmButton).not.toBeDisabled()
    await user.click(confirmButton)

    // Assert request payload and headers
    await waitFor(() => {
      expect(capturedRequest).not.toBeNull()
      expect(capturedHeaders).not.toBeNull()
    })
    expect(capturedRequest!.customerId).toBe(customerId)
    expect(capturedRequest!.vehicleId).toBe(vehicleId)
    expect(capturedRequest!.serviceTypeId).toBe(serviceTypeId)
    expect(capturedRequest!.desiredStart).toBe('2026-11-02T09:00:00+07:00')
    expect(capturedHeaders!['Idempotency-Key']).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    )

    // Assert confirmation screen rendered
    await screen.findByText('Appointment confirmed')
    expect(screen.getByText('APT-20261102-0042')).toBeInTheDocument()
    expect(screen.getByText('Monday, 2 Nov 2026, 09:00–10:00')).toBeInTheDocument()
  })

  it('TC-02-26: displays error card on OUTSIDE_OPENING_HOURS with latest start', async () => {
    const service20kId = '0192d444-0000-7000-8000-000000000020'

    apiMock.get('/me', [
      200,
      {
        id: 2,
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
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [{ weekday: 1, opensAt: '08:00', closesAt: '17:00' }],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      {
        data: [
          {
            id: serviceTypeId,
            code: 'OIL_CHANGE',
            name: 'Oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
          {
            id: service20kId,
            code: 'SVC_20000KM',
            name: '20,000 km service',
            description: 'Major maintenance service',
            durationMinutes: 120,
            requiredLevel: 2,
            requiredBayType: 'STANDARD',
            active: true,
          },
        ],
      },
    ])

    apiMock.get('/customers', [
      200,
      {
        data: [
          {
            id: customerId,
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
            email: 'hung@example.test',
            vehicleCount: 1,
          },
        ],
        meta: { total: 1 },
      },
    ])

    apiMock.get(`/customers/${customerId}/vehicles`, [
      200,
      {
        data: [
          {
            id: vehicleId,
            customer_id: customerId,
            make: 'Toyota',
            model: 'Vios',
            licencePlate: '51H-123.45',
          },
        ],
      },
    ])

    apiMock.post('/appointments', () => [
      422,
      {
        status: 422,
        code: 'OUTSIDE_OPENING_HOURS',
        title: 'Outside opening hours',
        closesAt: '17:00',
        latestStart: '15:00',
      },
    ])

    const user = userEvent.setup()
    renderRoute('/staff')

    await waitFor(() => {
      expect(screen.getByText('Book a service appointment')).toBeInTheDocument()
    })

    // 1. Search & select customer
    const customerInput = screen.getByPlaceholderText('Search customer by name, email or phone')
    await user.type(customerInput, 'Nguyễn')
    const customerOption = await screen.findByText('Nguyễn Văn Hùng · 0903 *** 218')
    await user.click(customerOption)

    // 2. Select vehicle
    const vehicleInput = await screen.findByPlaceholderText('Choose a vehicle')
    await user.click(vehicleInput)
    const vehicleOption = await screen.findByText('Toyota Vios · 51H-123.45')
    await user.click(vehicleOption)

    // 3. Pick 60-min service first so 16:00 is visible (16:00 + 60 = 17:00 closing)
    const oilRadio = await screen.findByText('Oil change')
    await user.click(oilRadio)

    // 4. Select time slot 16:00
    const timeButton = await screen.findByRole('button', { name: '16:00' })
    await user.click(timeButton)

    // 5. Switch to 20,000 km service (120 min duration would end at 18:00, after 17:00 closing)
    const serviceRadio = await screen.findByText('20,000 km service')
    await user.click(serviceRadio)

    // 6. Confirm booking
    const confirmButton = screen.getByRole('button', { name: 'Confirm booking' })
    await user.click(confirmButton)

    // 7. Assert error card shows up
    expect(await screen.findByText('The service would end after closing time')).toBeInTheDocument()
    expect(screen.getByText(/The latest start that day is 15:00/)).toBeInTheDocument()
  })

  it('displays field-level error under vehicle on VEHICLE_NOT_OWNED_BY_CUSTOMER', async () => {
    apiMock.get('/me', [
      200,
      {
        id: 2,
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
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [{ weekday: 1, opensAt: '08:00', closesAt: '17:00' }],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      {
        data: [
          {
            id: serviceTypeId,
            code: 'OIL_CHANGE',
            name: 'Oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
        ],
      },
    ])

    apiMock.get('/customers', [
      200,
      {
        data: [
          {
            id: customerId,
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
            email: 'hung@example.test',
            vehicleCount: 1,
          },
        ],
        meta: { total: 1 },
      },
    ])

    apiMock.get(`/customers/${customerId}/vehicles`, [
      200,
      {
        data: [
          {
            id: vehicleId,
            customer_id: customerId,
            make: 'Toyota',
            model: 'Vios',
            licencePlate: '51H-123.45',
          },
        ],
      },
    ])

    apiMock.post('/appointments', () => [
      422,
      {
        status: 422,
        code: 'VEHICLE_NOT_OWNED_BY_CUSTOMER',
        title: 'This vehicle is not registered to the selected customer.',
      },
    ])

    const user = userEvent.setup()
    renderRoute('/staff')

    await waitFor(() => {
      expect(screen.getByText('Book a service appointment')).toBeInTheDocument()
    })

    const customerInput = screen.getByPlaceholderText('Search customer by name, email or phone')
    await user.type(customerInput, 'Nguyễn')
    const customerOption = await screen.findByText('Nguyễn Văn Hùng · 0903 *** 218')
    await user.click(customerOption)

    const vehicleInput = await screen.findByPlaceholderText('Choose a vehicle')
    await user.click(vehicleInput)
    const vehicleOption = await screen.findByText('Toyota Vios · 51H-123.45')
    await user.click(vehicleOption)

    const serviceRadio = await screen.findByText('Oil change')
    await user.click(serviceRadio)

    const timeButton = await screen.findByRole('button', { name: '09:00' })
    await user.click(timeButton)

    const confirmButton = screen.getByRole('button', { name: 'Confirm booking' })
    await user.click(confirmButton)

    // Assert field-level error under Vehicle
    expect(
      await screen.findByText(
        'This vehicle does not belong to Nguyễn Văn Hùng. Choose another vehicle or change the customer.',
      ),
    ).toBeInTheDocument()
  })

  it('TC-02-37: date picker minimum is today in the dealership timezone', async () => {
    vi.setSystemTime(new Date('2026-11-02T18:30:00Z')) // 01:30 on Nov 3 in Asia/Ho_Chi_Minh

    apiMock.get('/me', [
      200,
      {
        id: 2,
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
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [{ weekday: 2, opensAt: '08:00', closesAt: '17:00' }],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      { data: [] },
    ])

    const { container } = renderRoute('/staff')

    await waitFor(() => {
      expect(screen.getByText('Book a service appointment')).toBeInTheDocument()
    })

    const dateInput = container.querySelector('input[type="date"]') as HTMLInputElement
    expect(dateInput).not.toBeNull()
    expect(dateInput.getAttribute('min')).toBe('2026-11-03')
  })

  it('TC-06-16: given 409 with 3 suggestions, tapping suggestion selects time, clears error, and subsequent confirm succeeds with fresh Idempotency-Key', async () => {
    let postCallCount = 0
    let firstHeaders: Record<string, string | undefined> | null = null
    let secondHeaders: Record<string, string | undefined> | null = null
    let firstPayload: CreateAppointmentPayload | null = null
    let secondPayload: CreateAppointmentPayload | null = null

    apiMock.get('/me', [
      200,
      {
        id: 2,
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
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}`, [
      200,
      {
        data: {
          id: dealershipId,
          code: 'SGA-D7',
          name: 'Saigon Auto',
          branchName: 'District 7',
          timezone: 'Asia/Ho_Chi_Minh',
          phone: '028 3812 3456',
          slotStepMinutes: 15,
          openingHours: [{ weekday: 1, opensAt: '08:00', closesAt: '17:00' }],
        },
      },
    ])

    apiMock.get(`/dealerships/${dealershipId}/service-types`, [
      200,
      {
        data: [
          {
            id: serviceTypeId,
            code: 'OIL_CHANGE',
            name: 'Oil change',
            description: 'Quick engine oil change',
            durationMinutes: 60,
            requiredLevel: 1,
            requiredBayType: 'QUICK_SERVICE',
            active: true,
          },
        ],
      },
    ])

    apiMock.get('/customers', [
      200,
      {
        data: [
          {
            id: customerId,
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
            email: 'hung@example.test',
            vehicleCount: 1,
          },
        ],
        meta: { total: 1 },
      },
    ])

    apiMock.get(`/customers/${customerId}/vehicles`, [
      200,
      {
        data: [
          {
            id: vehicleId,
            licencePlate: '51H-123.45',
            make: 'Toyota',
            model: 'Vios',
            year: 2021,
          },
        ],
      },
    ])

    apiMock.post('/appointments', (config) => {
      postCallCount++
      const payload = JSON.parse(config.data as string)
      if (postCallCount === 1) {
        firstHeaders = config.headers
        firstPayload = payload
        return [
          409,
          {
            status: 409,
            code: 'NO_AVAILABILITY',
            reason: 'NO_BAY_AVAILABLE',
            title: 'No availability',
            detail: 'The 09:00 slot was just taken.',
            alternatives: [
              { start: '2026-11-02T10:00:00+07:00', end: '2026-11-02T11:00:00+07:00' },
              { start: '2026-11-02T11:15:00+07:00', end: '2026-11-02T12:15:00+07:00' },
              { start: '2026-11-03T08:00:00+07:00', end: '2026-11-03T09:00:00+07:00' },
            ],
          },
        ]
      }
      secondHeaders = config.headers
      secondPayload = payload
      return [
        201,
        {
          id: '0192d555-0000-7000-8000-000000000005',
          reference: 'APT-20261102-0042',
          status: 'CONFIRMED',
          start: '2026-11-02T10:00:00+07:00',
          end: '2026-11-02T11:00:00+07:00',
          version: 1,
          dealership: {
            id: dealershipId,
            name: 'Saigon Auto',
            branchName: 'District 7',
            timezone: 'Asia/Ho_Chi_Minh',
            phone: '028 3812 3456',
          },
          technician: {
            id: 'tech-1',
            name: 'Nguyễn Văn An',
          },
          bay: {
            id: 'bay-1',
            code: 'K1',
            name: 'Quick service bay 1',
            bayType: 'QUICK_SERVICE',
          },
          customer: {
            id: customerId,
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
          },
          vehicle: {
            id: vehicleId,
            licencePlate: '51H-123.45',
            make: 'Toyota',
            model: 'Vios',
          },
          reminderOptIn: true,
          source: 'STAFF',
          createdAt: '2026-11-02T08:00:00+07:00',
        },
      ]
    })

    const user = userEvent.setup()
    renderRoute('/staff')

    await waitFor(() => {
      expect(screen.getByText('Book a service appointment')).toBeInTheDocument()
    })

    const customerInput = screen.getByPlaceholderText('Search customer by name, email or phone')
    await user.type(customerInput, 'Nguyễn')
    const customerOption = await screen.findByText('Nguyễn Văn Hùng · 0903 *** 218')
    await user.click(customerOption)

    const vehicleInput = await screen.findByPlaceholderText('Choose a vehicle')
    await user.click(vehicleInput)
    const vehicleOption = await screen.findByText('Toyota Vios · 51H-123.45')
    await user.click(vehicleOption)

    const serviceRadio = await screen.findByText('Oil change')
    await user.click(serviceRadio)

    const time0900 = await screen.findByRole('button', { name: '09:00' })
    await user.click(time0900)

    const confirmButton = screen.getByRole('button', { name: 'Confirm booking' })
    await user.click(confirmButton)

    // Error card appears with 3 suggestion chips
    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByText('Nearest available slots')).toBeInTheDocument()
    const chip1000 = screen.getByRole('button', { name: '10:00 today' })
    expect(chip1000).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '11:15 today' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '08:00 Tue 3 Nov' })).toBeInTheDocument()

    // Tap "10:00 today"
    await user.click(chip1000)

    // The card disappears
    expect(screen.queryByRole('alert')).toBeNull()

    // 10:00 is selected
    expect(screen.getByText(/10:00–11:00/)).toBeInTheDocument()

    // Second confirm
    await user.click(screen.getByRole('button', { name: 'Confirm booking' }))

    // Navigates to confirmed
    await screen.findByText('Appointment confirmed')

    // Verify first and second request payloads and distinct Idempotency-Keys
    expect(postCallCount).toBe(2)
    expect((firstPayload as CreateAppointmentPayload | null)?.desiredStart).toBe('2026-11-02T09:00:00+07:00')
    expect((secondPayload as CreateAppointmentPayload | null)?.desiredStart).toBe('2026-11-02T10:00:00+07:00')

    const firstKey = firstHeaders?.['Idempotency-Key'] ?? firstHeaders?.['idempotency-key']
    const secondKey = secondHeaders?.['Idempotency-Key'] ?? secondHeaders?.['idempotency-key']
    expect(firstKey).toBeDefined()
    expect(secondKey).toBeDefined()
    expect(secondKey).not.toEqual(firstKey)
  })
})
