import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { api } from './client'
import { setAppointment, withIfMatch } from './appointmentQuery'
import type { Appointment } from './appointments'
import { useMe } from '../auth/useMe'
import { appointmentCopy } from '../../staff/appointments/copy'

export interface ExtendAffectedItem {
  appointmentId: string
  reference: string
  outcome: 'REASSIGNED' | 'CONTACT_REQUIRED'
  technician?: { id: string; name: string } | null
  bay?: { id: string; name: string } | null
}

export interface ExtendResponseData {
  appointment: Appointment
  affected: ExtendAffectedItem[]
}

export interface ExtendPayload {
  minutes: number
  etag: string
  idempotencyKey: string
}

export interface AppointmentActionPayload {
  etag: string
  idempotencyKey: string
}

export async function postAppointmentAction(
  appointmentId: string,
  action: 'check-in' | 'start' | 'complete' | 'no-show',
  etag: string,
  idempotencyKey: string,
): Promise<{ data: Appointment; etag: string }> {
  const response = await api.post<Appointment>(
    `/appointments/${appointmentId}/${action}`,
    {},
    {
      headers: {
        'Idempotency-Key': idempotencyKey,
        ...withIfMatch(etag),
      },
    },
  )

  const rawEtag = response.headers['etag']
  const newEtag = typeof rawEtag === 'string' ? rawEtag : ''

  return {
    data: response.data,
    etag: newEtag,
  }
}

export function useAppointmentAction(
  appointmentId: string,
  action: 'check-in' | 'start' | 'complete' | 'no-show',
  options?: {
    onSuccess?: (result: { data: Appointment; etag: string }) => void
    onError?: (error: unknown) => void
  },
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ etag, idempotencyKey }: AppointmentActionPayload) =>
      postAppointmentAction(appointmentId, action, etag, idempotencyKey),
    onSuccess: (result) => {
      setAppointment(queryClient, result.data, result.etag)
      queryClient.invalidateQueries({ queryKey: ['dealershipAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['workshopBoard'] })
      queryClient.invalidateQueries({ queryKey: ['appointmentHistory', appointmentId] })
      queryClient.invalidateQueries({ queryKey: ['myAppointments'] })
      options?.onSuccess?.(result)
    },
    onError: options?.onError,
  })
}

export async function extendAppointment(
  appointmentId: string,
  minutes: number,
  etag: string,
  idempotencyKey: string,
): Promise<{ data: ExtendResponseData; etag: string }> {
  const response = await api.post<{ data?: ExtendResponseData } & ExtendResponseData>(
    `/appointments/${appointmentId}/extend`,
    { minutes },
    {
      headers: {
        'Idempotency-Key': idempotencyKey,
        ...withIfMatch(etag),
      },
    },
  )

  const rawEtag = response.headers['etag']
  const newEtag = typeof rawEtag === 'string' ? rawEtag : ''

  const responseData: ExtendResponseData =
    response.data && 'data' in response.data && response.data.data
      ? response.data.data
      : (response.data as ExtendResponseData)

  return {
    data: responseData,
    etag: newEtag,
  }
}

export function useExtend(
  appointmentId: string,
  options?: {
    onSuccess?: (result: { data: ExtendResponseData; etag: string }) => void
    onError?: (error: unknown) => void
  },
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ minutes, etag, idempotencyKey }: ExtendPayload) =>
      extendAppointment(appointmentId, minutes, etag, idempotencyKey),
    onSuccess: (result) => {
      if (result.data?.appointment) {
        setAppointment(queryClient, result.data.appointment, result.etag)
      }
      queryClient.invalidateQueries({ queryKey: ['dealershipAppointments'] })
      queryClient.invalidateQueries({ queryKey: ['workshopBoard'] })
      queryClient.invalidateQueries({ queryKey: ['appointmentHistory', appointmentId] })
      queryClient.invalidateQueries({ queryKey: ['myAppointments'] })

      // Show notifications for affected bookings
      if (result.data?.affected) {
        for (const item of result.data.affected) {
          if (item.outcome === 'REASSIGNED' && item.technician && item.bay) {
            notifications.show({
              message: appointmentCopy.actions.movedNext(
                item.reference,
                item.technician.name,
                item.bay.name,
              ),
              color: 'blue',
            })
          } else if (item.outcome === 'CONTACT_REQUIRED') {
            notifications.show({
              message: appointmentCopy.actions.flaggedNext(item.reference),
              color: 'yellow',
            })
          }
        }
      }

      options?.onSuccess?.(result)
    },
    onError: options?.onError,
  })
}

export interface WorkshopAlertNextBooking {
  appointmentId: string
  start: string
  status: string
}

export interface WorkshopAlert {
  type: 'OVERRUN'
  appointmentId: string
  version: number
  bayName: string
  minutesOver: number
  customerName?: string
  serviceName?: string
  technicianName?: string
  nextBooking?: WorkshopAlertNextBooking | null
}

export interface WorkshopBlock {
  appointmentId: string
  version: number
  reference: string
  status: string
  start: string
  end: string
  customerName: string
  serviceName: string
  technicianName: string
  overrunMinutes?: number
}

export interface WorkshopBay {
  id: string
  code: string
  name: string
  bayType: string
  blocks: WorkshopBlock[]
}

export interface WorkshopDisplaced {
  appointmentId: string
  reference: string
  start: string
  end: string
  reason: string
}

export interface WorkshopTechnician {
  id: string
  name: string
  level: number
  bookedMinutes: number
  status: 'WORKING' | 'OVERRUN'
}

export interface WorkshopBoardData {
  date: string
  now: string
  opensAt: string
  closesAt: string
  bays: WorkshopBay[]
  displaced: WorkshopDisplaced[]
  technicians: WorkshopTechnician[]
  alerts: WorkshopAlert[]
}

export async function getWorkshopBoard(dealershipId: string, date?: string): Promise<WorkshopBoardData> {
  const url = date
    ? `/dealerships/${dealershipId}/workshop-board?date=${date}`
    : `/dealerships/${dealershipId}/workshop-board`
  const response = await api.get<{ data: WorkshopBoardData }>(url)
  return response.data.data
}

export function useWorkshopBoard(date?: string) {
  const { data: me } = useMe()
  const dealershipId = me?.dealership?.id

  return useQuery({
    queryKey: ['workshopBoard', dealershipId, date],
    queryFn: () => {
      if (!dealershipId) throw new Error('No dealership')
      return getWorkshopBoard(dealershipId, date)
    },
    enabled: !!dealershipId,
    refetchInterval: 30_000,
  })
}

