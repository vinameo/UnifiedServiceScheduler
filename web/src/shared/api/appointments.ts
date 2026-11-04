import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api } from './client'
import { useMe } from '../auth/useMe'
import { setAppointment, withIfMatch } from './appointmentQuery'

export interface CreateAppointmentPayload {
  dealershipId?: string
  customerId?: string
  vehicleId?: string | null
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

export interface AppointmentListItemCustomer {
  id: string
  fullName: string
}

export interface AppointmentListItemVehicle {
  make: string
  model: string
  licencePlate: string
}

export interface AppointmentListItemServiceType {
  id: string
  name: string
  durationMinutes: number
}

export interface AppointmentListItemTechnician {
  id: string
  name: string
}

export interface AppointmentListItemBay {
  id: string
  code: string
  name: string
}

export interface AppointmentListItemDealership {
  id: string
  name: string
  branchName?: string | null
  timezone: string
}

export interface AppointmentListItem {
  id: string
  reference: string
  status: string
  start: string
  end: string
  customer: AppointmentListItemCustomer
  vehicle: AppointmentListItemVehicle | null
  serviceType: AppointmentListItemServiceType
  technician: AppointmentListItemTechnician | null
  bay: AppointmentListItemBay | null
  dealership: AppointmentListItemDealership
  contactRequiredReason?: string | null
}

export interface PaginatedAppointments {
  data: AppointmentListItem[]
  meta: {
    nextCursor: string | null
    limit: number
    total: number
  }
}

export interface DealershipAppointmentFilters {
  dealershipId?: string
  date?: string
  contactRequired?: boolean
  technicianId?: string
  bayId?: string
  status?: string
  limit?: number
}

export async function getDealershipAppointments(
  dealershipId: string,
  filters: Omit<DealershipAppointmentFilters, 'dealershipId'>,
  cursor?: string | null,
): Promise<PaginatedAppointments> {
  const params: Record<string, unknown> = {}
  if (filters.date) {
    params.date = filters.date
  }
  if (filters.contactRequired) {
    params.contactRequired = filters.contactRequired
  }
  if (filters.technicianId) {
    params.technicianId = filters.technicianId
  }
  if (filters.bayId) {
    params.bayId = filters.bayId
  }
  if (filters.status) {
    params.status = filters.status
  }
  if (filters.limit) {
    params.limit = filters.limit
  }
  if (cursor) {
    params.cursor = cursor
  }

  const response = await api.get<PaginatedAppointments>(
    `/dealerships/${dealershipId}/appointments`,
    { params },
  )
  return response.data
}

export function useDealershipAppointments(filters: DealershipAppointmentFilters) {
  const { data: me } = useMe()
  const dealershipId = filters.dealershipId || me?.dealership?.id

  return useInfiniteQuery({
    queryKey: ['dealershipAppointments', dealershipId, filters],
    initialPageParam: null as string | null,
    queryFn: ({ pageParam }) =>
      getDealershipAppointments(dealershipId!, filters, pageParam),
    getNextPageParam: (lastPage) => lastPage.meta.nextCursor ?? undefined,
    refetchInterval: 60 * 1000,
    enabled: Boolean(dealershipId && (filters.date || filters.contactRequired)),
  })
}

export type AppointmentScope = 'upcoming' | 'past'

export async function getMyAppointments(
  scope: AppointmentScope,
  cursor?: string | null,
  limit?: number,
): Promise<PaginatedAppointments> {
  const params: Record<string, unknown> = {
    scope,
  }
  if (cursor) {
    params.cursor = cursor
  }
  if (limit) {
    params.limit = limit
  }

  const response = await api.get<PaginatedAppointments>('/me/appointments', { params })
  return response.data
}

export function useMyAppointments(scope: AppointmentScope) {
  return useInfiniteQuery({
    queryKey: ['myAppointments', scope],
    initialPageParam: null as string | null,
    queryFn: ({ pageParam }) => getMyAppointments(scope, pageParam),
    getNextPageParam: (lastPage) => lastPage.meta.nextCursor ?? undefined,
  })
}

export interface CancelAppointmentPayload {
  reason?: string | null
  note?: string | null
}

export function useCancelAppointment(appointmentId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      reason,
      note,
      idempotencyKey,
      etag,
    }: CancelAppointmentPayload & { idempotencyKey: string; etag: string }) => {
      const response = await api.post<Appointment>(
        `/appointments/${appointmentId}/cancel`,
        { reason, note },
        {
          headers: {
            'Idempotency-Key': idempotencyKey,
            ...withIfMatch(etag),
          },
        },
      )
      const rawEtag = response.headers['etag']
      const newEtag = typeof rawEtag === 'string' ? rawEtag : ''
      return { appointment: response.data, etag: newEtag }
    },
    onSuccess: ({ appointment, etag }) => {
      setAppointment(queryClient, appointment, etag)
      queryClient.invalidateQueries({ queryKey: ['dealershipAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['myAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['availability'] })
      queryClient.invalidateQueries({ queryKey: ['appointmentHistory', appointmentId] })
    },
  })
}

export interface RescheduleAppointmentPayload {
  newStart: string
  keepTechnician?: boolean
}

export function useRescheduleAppointment(appointmentId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      newStart,
      keepTechnician,
      idempotencyKey,
      etag,
    }: RescheduleAppointmentPayload & { idempotencyKey: string; etag: string }) => {
      const response = await api.post<Appointment>(
        `/appointments/${appointmentId}/reschedule`,
        { newStart, keepTechnician },
        {
          headers: {
            'Idempotency-Key': idempotencyKey,
            ...withIfMatch(etag),
          },
        },
      )
      const rawEtag = response.headers['etag']
      const newEtag = typeof rawEtag === 'string' ? rawEtag : ''
      return { appointment: response.data, etag: newEtag }
    },
    onSuccess: ({ appointment, etag }) => {
      setAppointment(queryClient, appointment, etag)
      queryClient.invalidateQueries({ queryKey: ['dealershipAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['myAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['availability'] })
      queryClient.invalidateQueries({ queryKey: ['appointmentHistory', appointmentId] })
    },
  })
}

export interface CreateHoldPayload {
  dealershipId: string
  serviceTypeId: string
  desiredStart: string
}

export async function createHold(
  payload: CreateHoldPayload,
  idempotencyKey: string,
): Promise<{ data: Appointment; etag: string }> {
  const response = await api.post<Appointment>(
    '/appointments',
    {
      dealershipId: payload.dealershipId,
      serviceTypeId: payload.serviceTypeId,
      desiredStart: payload.desiredStart,
      mode: 'HOLD',
    },
    {
      headers: {
        'Idempotency-Key': idempotencyKey,
      },
    },
  )

  const rawEtag = response.headers['etag']
  const etag = typeof rawEtag === 'string' ? rawEtag : ''

  return {
    data: response.data,
    etag,
  }
}

export function useCreateHold(options?: {
  onSuccess?: (result: { data: Appointment; etag: string }) => void
  onError?: (error: unknown) => void
}) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  return useMutation({
    mutationFn: ({
      payload,
      idempotencyKey,
    }: {
      payload: CreateHoldPayload
      idempotencyKey: string
    }) => createHold(payload, idempotencyKey),
    onSuccess: (result) => {
      setAppointment(queryClient, result.data, result.etag)
      options?.onSuccess?.(result)
      navigate(`/book/hold/${result.data.id}`)
    },
    onError: options?.onError,
  })
}

export interface ConfirmHoldPayload {
  vehicleId: string
  reminderOptIn?: boolean
}

export async function confirmHold(
  id: string,
  payload: ConfirmHoldPayload,
  idempotencyKey: string,
  etag: string,
): Promise<{ data: Appointment; etag: string }> {
  const response = await api.post<Appointment>(
    `/appointments/${id}/confirm`,
    payload,
    {
      headers: {
        'Idempotency-Key': idempotencyKey,
        ...withIfMatch(etag),
      },
    },
  )

  const rawEtag = response.headers['etag']
  const newEtag = typeof rawEtag === 'string' ? rawEtag : ''
  return { data: response.data, etag: newEtag }
}

export function useConfirmHold(
  appointmentId: string,
  options?: {
    onSuccess?: (result: { data: Appointment; etag: string }) => void
    onError?: (error: unknown) => void
  },
) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  return useMutation({
    mutationFn: ({
      payload,
      idempotencyKey,
      etag,
    }: {
      payload: ConfirmHoldPayload
      idempotencyKey: string
      etag: string
    }) => confirmHold(appointmentId, payload, idempotencyKey, etag),
    onSuccess: (result) => {
      setAppointment(queryClient, result.data, result.etag)
      queryClient.invalidateQueries({ queryKey: ['dealershipAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['myAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['availability'] })
      queryClient.invalidateQueries({ queryKey: ['appointmentHistory', appointmentId] })
      options?.onSuccess?.(result)
      navigate(`/book/confirmed/${result.data.id}`, { state: result.data })
    },
    onError: options?.onError,
  })
}



