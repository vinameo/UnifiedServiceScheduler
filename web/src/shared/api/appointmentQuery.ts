import { queryOptions, useQuery, type QueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api } from './client'
import type { Appointment } from './appointments'

export interface AppointmentQueryResult {
  appointment: Appointment
  etag: string
}

export function appointmentQuery(id: string) {
  return queryOptions<AppointmentQueryResult>({
    queryKey: ['appointment', id],
    queryFn: async () => {
      const response = await api.get<Appointment>(`/appointments/${id}`)
      const rawEtag = response.headers['etag']
      const etag = typeof rawEtag === 'string' ? rawEtag : ''
      return {
        appointment: response.data,
        etag,
      }
    },
    retry: (failureCount, error) => {
      if (
        isAxiosError(error) &&
        error.response?.status &&
        error.response.status >= 400 &&
        error.response.status < 500
      ) {
        return false
      }
      return failureCount < 1
    },
  })
}

export function useAppointment(id: string) {
  return useQuery(appointmentQuery(id))
}

export function setAppointment(
  queryClient: QueryClient,
  appointment: Appointment,
  etag: string,
): void {
  queryClient.setQueryData<AppointmentQueryResult>(['appointment', appointment.id], {
    appointment,
    etag,
  })
}

export function withIfMatch(etag: string): { 'If-Match': string } {
  return {
    'If-Match': etag,
  }
}
