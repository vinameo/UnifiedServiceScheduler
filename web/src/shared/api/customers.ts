import { useQuery } from '@tanstack/react-query'
import { api } from './client'

export interface CustomerSummary {
  id: string
  fullName: string
  phone: string
  email?: string
  vehicleCount?: number
}

export interface CustomerSearchResult {
  data: CustomerSummary[]
  meta: {
    total: number
  }
}

export function useCustomerSearch(query: string) {
  const trimmed = query.trim()
  const enabled = trimmed.length >= 3

  return useQuery({
    queryKey: ['customers', trimmed],
    queryFn: async () => {
      const response = await api.get<CustomerSearchResult>('/customers', {
        params: { query: trimmed },
      })
      return response.data
    },
    enabled,
  })
}

