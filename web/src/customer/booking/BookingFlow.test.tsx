import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoute } from '../../test/render'
import { apiMock } from '../../test/apiMock'
import type { CreateVehiclePayload } from '../../shared/api/vehicles'

describe('BookingFlow (TC-02-27, TC-02-32)', () => {
  const dealershipId = '0192d111-0000-7000-8000-000000000001'
  const customerId = '0192d222-0000-7000-8000-000000000002'
  const serviceId = '0192d333-0000-7000-8000-000000000003'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-02T08:00:00Z'))
    sessionStorage.clear()
    apiMock.install()
    apiMock.reset()

    apiMock.get('/me', [
      200,
      {
        id: 10,
        email: 'hung@example.test',
        name: 'Nguyễn Văn Hùng',
        role: 'CUSTOMER',
        dealership_id: null,
        customer: {
          id: customerId,
          fullName: 'Nguyễn Văn Hùng',
          phone: '0903123218',
        },
      },
    ])

    apiMock.get('/dealerships', [
      200,
      {
        data: [
          {
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
        ],
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
            id: serviceId,
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

  it('TC-02-27: customer goes through 3 steps, adds a vehicle, confirms', async () => {
    const newVehicleId = '0192d444-0000-7000-8000-000000000004'
    const newAppointmentId = '0192d555-0000-7000-8000-000000000005'
    let addedVehiclePayload: CreateVehiclePayload | null = null
    let holdPayload: Record<string, unknown> | null = null
    let confirmPayload: Record<string, unknown> | null = null

    apiMock.get('/me/vehicles', [
      200,
      { data: [] }, // Initially no vehicles
    ])

    apiMock.post('/me/vehicles', (config) => {
      addedVehiclePayload = JSON.parse(config.data as string)
      return [
        201,
        {
          id: newVehicleId,
          licencePlate: addedVehiclePayload!.licencePlate,
          make: addedVehiclePayload!.make,
          model: addedVehiclePayload!.model,
          year: addedVehiclePayload!.year,
          vin: null,
        },
      ]
    })

    const holdAppointment = {
      id: newAppointmentId,
      reference: 'APT-20261102-0088',
      status: 'HELD',
      start: '2026-11-02T09:00:00+07:00',
      end: '2026-11-02T10:00:00+07:00',
      expiresAt: '2026-11-02T08:05:00Z',
      version: 1,
      dealership: {
        id: dealershipId,
        name: 'Saigon Auto',
        branchName: 'District 7',
        timezone: 'Asia/Ho_Chi_Minh',
        phone: '028 3812 3456',
      },
      serviceType: {
        id: serviceId,
        name: 'Oil change',
        durationMinutes: 60,
      },
      vehicle: null,
      customer: {
        id: customerId,
        fullName: 'Nguyễn Văn Hùng',
        phone: '0903123218',
      },
      technician: {
        id: 'tech-1',
        name: 'Nguyễn Văn An',
      },
      bay: {
        id: 'bay-1',
        name: 'Bay 1',
      },
      reminderOptIn: true,
      source: 'CUSTOMER',
      createdAt: '2026-11-02T08:00:00+07:00',
    }

    apiMock.get(`/appointments/${newAppointmentId}`, [
      200,
      holdAppointment,
      { etag: '"1"' },
    ])

    apiMock.post('/appointments', (config) => {
      holdPayload = JSON.parse(config.data as string)
      return [
        201,
        holdAppointment,
        { etag: '"1"', date: 'Mon, 02 Nov 2026 08:00:00 GMT' },
      ]
    })

    apiMock.post(`/appointments/${newAppointmentId}/confirm`, (config) => {
      confirmPayload = JSON.parse(config.data as string)
      return [
        200,
        {
          ...holdAppointment,
          status: 'CONFIRMED',
          expiresAt: null,
          version: 2,
          vehicle: {
            id: newVehicleId,
            licencePlate: '51G-999.99',
            make: 'Honda',
            model: 'City',
          },
        },
        { etag: '"2"' },
      ]
    })

    const user = userEvent.setup()
    renderRoute('/book')

    // STEP 1: Choose a service
    await waitFor(() => {
      expect(screen.getByText('Step 1 of 3 · Choose a service')).toBeInTheDocument()
    })
    const serviceRadio = await screen.findByText('Oil change')
    await user.click(serviceRadio)

    const step1Continue = screen.getByRole('button', { name: 'Choose date and time' })
    await user.click(step1Continue)

    // STEP 2: Choose date and time
    await waitFor(() => {
      expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
    })
    const slot0900 = await screen.findByRole('button', { name: '09:00' })
    await user.click(slot0900)

    const step2Continue = screen.getByRole('button', { name: /Hold and continue/i })
    await user.click(step2Continue)

    // STEP 3: Contact details & add vehicle (Hold page)
    await waitFor(() => {
      expect(screen.getByText('Step 3 of 3 · Contact details')).toBeInTheDocument()
      expect(screen.getByText('Slot held for you')).toBeInTheDocument()
    })

    // Fill in new vehicle form
    const plateInput = screen.getByLabelText(/Licence plate/i)
    const makeInput = screen.getByLabelText(/Make/i)
    const modelInput = screen.getByLabelText(/Model/i)

    await user.type(plateInput, '51G-999.99')
    await user.type(makeInput, 'Honda')
    await user.type(modelInput, 'City')

    // Confirm appointment
    const confirmBtn = screen.getByRole('button', { name: 'Confirm appointment' })
    await user.click(confirmBtn)

    // Assert API calls
    await waitFor(() => {
      expect(addedVehiclePayload).not.toBeNull()
      expect(holdPayload).not.toBeNull()
      expect(confirmPayload).not.toBeNull()
    })
    expect(addedVehiclePayload!.licencePlate).toBe('51G-999.99')
    expect(addedVehiclePayload!.make).toBe('Honda')
    expect(addedVehiclePayload!.model).toBe('City')

    expect(holdPayload!.dealershipId).toBe(dealershipId)
    expect(holdPayload!.serviceTypeId).toBe(serviceId)
    expect(holdPayload!.desiredStart).toBe('2026-11-02T09:00:00+07:00')
    expect(holdPayload!.mode).toBe('HOLD')

    expect(confirmPayload!.vehicleId).toBe(newVehicleId)

    // Assert confirmed screen
    await waitFor(() => {
      expect(screen.getByText('Booking confirmed')).toBeInTheDocument()
    })
    expect(screen.getByText(/0903 \*\*\* 218/)).toBeInTheDocument()
    expect(screen.getByText('APT-20261102-0088')).toBeInTheDocument()
  })

  it('TC-02-32: draft survives unmount; on 409 back to /book/time with Alert and unselected time', async () => {
    const existingVehicleId = '0192d444-0000-7000-8000-000000000009'

    apiMock.get('/me/vehicles', [
      200,
      {
        data: [
          {
            id: existingVehicleId,
            licencePlate: '51H-123.45',
            make: 'Toyota',
            model: 'Vios',
            year: 2021,
          },
        ],
      },
    ])

    apiMock.post('/appointments', [
      409,
      {
        type: 'https://problems.keyloop.test/no-availability',
        status: 409,
        code: 'NO_AVAILABILITY',
        reason: 'NO_BAY_AVAILABLE',
        title: 'No availability found for this slot.',
        detail: 'No bay of type QUICK_SERVICE is available at the requested time.',
      },
    ])

    // Seed draft at step 2 in sessionStorage
    sessionStorage.setItem(
      'customer_booking_draft',
      JSON.stringify({
        dealershipId,
        serviceTypeId: serviceId,
        date: '2026-11-02',
        start: '2026-11-02T09:00:00+07:00',
        vehicleId: existingVehicleId,
        reminderOptIn: true,
      })
    )

    // Mount at /book/time
    const { unmount } = renderRoute('/book/time')

    await waitFor(() => {
      expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
      expect(screen.getByText(/Your choice Mon 2 Nov, 09:00/)).toBeInTheDocument()
    })

    // Unmount and remount (simulating F5)
    unmount()
    renderRoute('/book/time')

    await waitFor(() => {
      expect(screen.getByText('Step 2 of 3 · Choose date and time')).toBeInTheDocument()
      expect(screen.getByText(/Your choice Mon 2 Nov, 09:00/)).toBeInTheDocument()
    })

    // Click Hold and continue
    const user = userEvent.setup()
    const holdBtn = screen.getByRole('button', { name: /Hold and continue/i })
    await user.click(holdBtn)

    // On 409 NO_AVAILABILITY: Alert shown and time unselected
    await waitFor(() => {
      expect(screen.getByText(/slot was just taken/i)).toBeInTheDocument()
    })

    // Hold and continue button must be disabled (old time unselected)
    expect(screen.getByRole('button', { name: /Hold and continue/i })).toBeDisabled()
  })
})

