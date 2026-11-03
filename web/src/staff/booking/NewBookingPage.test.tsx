import { describe, it, expect, beforeEach, afterEach } from 'vitest'
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
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
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
})
