import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { api } from './client'
import { setAppointment, withIfMatch } from './appointmentQuery'
import type { Appointment } from './appointments'
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
