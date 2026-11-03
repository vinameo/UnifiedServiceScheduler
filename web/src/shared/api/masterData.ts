import { useQuery } from '@tanstack/react-query'
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
