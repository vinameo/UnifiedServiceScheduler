import { type QueryClient, useQuery } from '@tanstack/react-query'
import { api } from './client'

export interface AvailabilitySlot {
  start: string
  end: string
  available: boolean
  reason: string | null
}

export interface DayAvailability {
  date: string
  timezone: string
  closed: boolean
  opensAt: string | null
  closesAt: string | null
  stepMinutes: number
  durationMinutes: number
  freeCount: number
  slots: AvailabilitySlot[]
}

export function reasonTooltip(reason: string | null | undefined): string | undefined {
  if (!reason) return undefined
  switch (reason) {
    case 'PAST':
      return 'This time has passed'
    case 'NO_TECHNICIAN_AVAILABLE':
      return 'No qualified technician free'
    case 'NO_BAY_AVAILABLE':
      return 'No bay free'
    case 'NO_QUALIFIED_TECHNICIAN':
      return 'No technician for this service'
    default:
      return reason
  }
}

export async function getAvailability(params: {
  dealershipId: string
  serviceTypeId: string
  date: string
  excludeAppointmentId?: string | null
}): Promise<DayAvailability> {
  const searchParams = new URLSearchParams()
  searchParams.set('serviceTypeId', params.serviceTypeId)
  searchParams.set('date', params.date)
  if (params.excludeAppointmentId) {
    searchParams.set('excludeAppointmentId', params.excludeAppointmentId)
  }

  const response = await api.get<{ data: DayAvailability }>(
    `/dealerships/${params.dealershipId}/availability?${searchParams.toString()}`,
  )
  return response.data.data
}

export function invalidateAvailability(queryClient: QueryClient): void {
  queryClient.invalidateQueries({ queryKey: ['availability'] })
}

export function useAvailability(params: {
  dealershipId?: string | null
  serviceTypeId?: string | null
  date?: string | null
  excludeAppointmentId?: string | null
}) {
  const { dealershipId, serviceTypeId, date, excludeAppointmentId } = params

  return useQuery({
    queryKey: ['availability', dealershipId, serviceTypeId, date, excludeAppointmentId ?? null],
    queryFn: () =>
      getAvailability({
        dealershipId: dealershipId!,
        serviceTypeId: serviceTypeId!,
        date: date!,
        excludeAppointmentId,
      }),
    enabled: Boolean(dealershipId && serviceTypeId && date),
    staleTime: 15_000,
  })
}
