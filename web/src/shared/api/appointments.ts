import { useMutation } from '@tanstack/react-query'
import { api } from './client'

export interface CreateAppointmentPayload {
  dealershipId?: string
  customerId?: string
  vehicleId: string
  serviceTypeId: string
  desiredStart: string
  reminderOptIn?: boolean
  mode?: string
}

export interface AppointmentDealership {
  id: string
  name: string
  branchName?: string
  timezone: string
  phone?: string
}

export interface AppointmentServiceType {
  id: string
  code: string
  name: string
  durationMinutes: number
}

export interface AppointmentTechnician {
  id: string
  name: string
}

export interface AppointmentBay {
  id: string
  code: string
  name: string
  bayType: string
}

export interface AppointmentCustomer {
  id: string
  fullName: string
  phone?: string
}

export interface AppointmentVehicle {
  id: string
  licencePlate: string
  make: string
  model: string
  year?: number
  vin?: string
}

export interface Appointment {
  id: string
  reference: string
  status: string
  start: string
  end: string
  expiresAt: string | null
  version: number
  dealership: AppointmentDealership
  serviceType: AppointmentServiceType
  technician: AppointmentTechnician | null
  bay: AppointmentBay | null
  customer: AppointmentCustomer
  vehicle: AppointmentVehicle | null
  reminderOptIn: boolean
  source: string
  createdAt: string
}

export async function createAppointment(
  payload: CreateAppointmentPayload,
  idempotencyKey: string,
): Promise<{ data: Appointment; etag: string }> {
  const response = await api.post<Appointment>('/appointments', payload, {
    headers: {
      'Idempotency-Key': idempotencyKey,
    },
  })

  return {
    data: response.data,
    etag: response.headers['etag'] ?? '',
  }
}

export function useCreateAppointment(options?: {
  onSuccess?: (result: { data: Appointment; etag: string }) => void
  onError?: (error: unknown) => void
}) {
  return useMutation({
    mutationFn: ({
      payload,
      idempotencyKey,
    }: {
      payload: CreateAppointmentPayload
      idempotencyKey: string
    }) => createAppointment(payload, idempotencyKey),
    onSuccess: options?.onSuccess,
    onError: options?.onError,
  })
}

