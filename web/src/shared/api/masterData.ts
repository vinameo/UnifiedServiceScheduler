import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'

export interface DealershipOpeningHour {
  weekday: number
  opensAt: string
  closesAt: string
}

export interface Dealership {
  id: string
  code: string
  name: string
  branchName?: string | null
  timezone: string
  phone: string
  slotStepMinutes: number
  openingHours?: DealershipOpeningHour[]
}

export interface ServiceType {
  id: string
  code: string
  name: string
  description?: string | null
  durationMinutes: number
  requiredLevel: number
  requiredBayType: string
  active: boolean
}

export interface Bay {
  id: string
  code: string
  name: string
  bayType: string
  active: boolean
  supportedServiceTypeIds?: string[]
}

export interface Technician {
  id: string
  name: string
  level: number
  active: boolean
}

export interface TechnicianAppointmentPreview {
  id: string
  reference: string
  start: string
  end: string
  customerName: string
}

export interface TechnicianHasBookingsProblem {
  code: 'TECHNICIAN_HAS_BOOKINGS'
  status: 409
  count: number
  appointments: TechnicianAppointmentPreview[]
  detail?: string
  title?: string
}

export function useDealerships() {
  return useQuery({
    queryKey: ['dealerships'],
    queryFn: async () => {
      const response = await api.get<{ data: Dealership[] }>('/dealerships')
      return response.data.data
    },
  })
}

export function useDealership(id: string | undefined) {
  return useQuery({
    queryKey: ['dealership', id],
    queryFn: async () => {
      if (!id) return null
      const response = await api.get<{ data: Dealership }>(`/dealerships/${id}`)
      return response.data.data
    },
    enabled: Boolean(id),
  })
}

export function useServiceTypes(dealershipId: string | undefined) {
  return useQuery({
    queryKey: ['service-types', dealershipId],
    queryFn: async () => {
      if (!dealershipId) return []
      const response = await api.get<{ data: ServiceType[] }>(`/dealerships/${dealershipId}/service-types`)
      return response.data.data
    },
    enabled: Boolean(dealershipId),
  })
}

export function useBays(dealershipId: string | undefined) {
  return useQuery({
    queryKey: ['bays', dealershipId],
    queryFn: async () => {
      if (!dealershipId) return []
      const response = await api.get<{ data: Bay[] }>(`/dealerships/${dealershipId}/bays`)
      return response.data.data
    },
    enabled: Boolean(dealershipId),
  })
}

export function useTechnicians(dealershipId: string | undefined) {
  return useQuery({
    queryKey: ['technicians', dealershipId],
    queryFn: async () => {
      if (!dealershipId) return []
      const response = await api.get<{ data: Technician[] }>(`/dealerships/${dealershipId}/technicians`)
      return response.data.data
    },
    enabled: Boolean(dealershipId),
  })
}

export async function setTechnicianStatus(
  dealershipId: string,
  technicianId: string,
  active: boolean,
): Promise<Technician> {
  const response = await api.put<{ data: Technician }>(
    `/dealerships/${dealershipId}/technicians/${technicianId}/status`,
    { active },
  )
  return response.data.data
}

export function useSetTechnicianActive(dealershipId: string | undefined) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      technicianId,
      active,
    }: {
      technicianId: string
      active: boolean
    }) => {
      if (!dealershipId) {
        throw new Error('Dealership ID is required')
      }
      return setTechnicianStatus(dealershipId, technicianId, active)
    },
    onSuccess: () => {
      if (dealershipId) {
        queryClient.invalidateQueries({ queryKey: ['technicians', dealershipId] })
      }
    },
  })
}

