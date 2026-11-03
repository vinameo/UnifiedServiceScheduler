import { useMutation, useQuery } from '@tanstack/react-query'
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

export interface AppointmentCreatedBy {
  name: string
  role: string
}

export interface AppointmentCancellation {
  reason?: string | null
  note?: string | null
  at: string
  byRole: string
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
  createdBy?: AppointmentCreatedBy | null
  cancellation?: AppointmentCancellation | null
  contactRequiredReason?: string | null
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

export interface AppointmentEventActor {
  name: string
  role: string
}

export interface AppointmentEvent {
  id: string
  type: string
  fromStatus: string | null
  toStatus: string | null
  version: number
  actor: AppointmentEventActor | null
  data: Record<string, unknown>
  occurredAt: string
}

export async function getAppointmentHistory(appointmentId: string): Promise<AppointmentEvent[]> {
  const response = await api.get<AppointmentEvent[]>(`/appointments/${appointmentId}/history`)
  return response.data
}

export function useAppointmentHistory(appointmentId: string) {
  return useQuery({
    queryKey: ['appointmentHistory', appointmentId],
    queryFn: () => getAppointmentHistory(appointmentId),
    enabled: Boolean(appointmentId),
  })
}

