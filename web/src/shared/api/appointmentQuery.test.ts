import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { QueryClient } from '@tanstack/react-query'
import { apiMock } from '../../test/apiMock'
import {
  appointmentQuery,
  setAppointment,
  withIfMatch,
  type AppointmentQueryResult,
} from './appointmentQuery'
import type { Appointment } from './appointments'

describe('appointmentQuery (TC-05-16)', () => {
  beforeEach(() => {
    apiMock.install()
    apiMock.reset()
  })

  afterEach(() => {
    apiMock.restore()
  })

  it('TC-05-16: fetches with ETag, updates via setAppointment, and generates withIfMatch header', async () => {
    const queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
      },
    })

    const appointmentId = '0192d111-0000-7000-8000-000000000001'
    const mockAppointment: Appointment = {
      id: appointmentId,
      reference: 'APT-20261102-0001',
      status: 'CONFIRMED',
      start: '2026-11-02T09:00:00+07:00',
      end: '2026-11-02T10:00:00+07:00',
      expiresAt: null,
      version: 3,
      dealership: {
        id: 'dealership-1',
        name: 'Saigon Auto',
        branchName: 'District 7',
        timezone: 'Asia/Ho_Chi_Minh',
      },
      serviceType: {
        id: 'service-1',
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
        code: 'K1',
        name: 'Bay 1',
        bayType: 'LIFT',
      },
      customer: {
        id: 'customer-1',
        fullName: 'Nguyễn Văn Hùng',
        phone: '0903123218',
      },
      vehicle: {
        id: 'vehicle-1',
        make: 'Toyota',
        model: 'Vios',
        licencePlate: '51H-123.45',
        year: 2021,
        vin: 'RL4BT3F3X0M001234',
      },
      reminderOptIn: false,
      source: 'STAFF',
      createdAt: '2026-10-28T16:40:00+07:00',
    }

    apiMock.get(`/appointments/${appointmentId}`, [
      200,
      mockAppointment,
      { etag: '"3"' },
    ])

    const fetched = await queryClient.fetchQuery(appointmentQuery(appointmentId))
    expect(fetched.etag).toBe('"3"')
    expect(fetched.appointment.id).toBe(appointmentId)

    const cacheAfterFetch = queryClient.getQueryData<AppointmentQueryResult>([
      'appointment',
      appointmentId,
    ])
    expect(cacheAfterFetch?.etag).toBe('"3"')

    const updatedAppointment: Appointment = {
      ...mockAppointment,
      version: 4,
    }
    setAppointment(queryClient, updatedAppointment, '"4"')

    const cacheAfterSet = queryClient.getQueryData<AppointmentQueryResult>([
      'appointment',
      appointmentId,
    ])
    expect(cacheAfterSet?.etag).toBe('"4"')
    expect(cacheAfterSet?.appointment.version).toBe(4)

    const ifMatchHeader = withIfMatch(cacheAfterSet?.etag ?? '')
    expect(ifMatchHeader).toEqual({ 'If-Match': '"4"' })
  })
})
