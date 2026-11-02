import { useQuery } from '@tanstack/react-query'
import { api } from '../api/client'
import { toProblem } from '../api/problem'

export interface Dealership {
  id: string
  code: string
  name: string
  branchName: string | null
  timezone: string
  phone: string
}

export interface CustomerProfile {
  id: string
  fullName: string
  phone: string
}

export interface Me {
  id: number
  email: string
  name: string
  role: 'CUSTOMER' | 'SERVICE_ADVISOR' | 'WORKSHOP_FOREMAN' | 'ADMIN'
  dealership: Dealership | null
  customer: CustomerProfile | null
}

export function useMe() {
  return useQuery<Me | null>({
    queryKey: ['me'],
    queryFn: async () => {
      try {
        const res = await api.get<Me>('/api/v1/me')
        return res.data
      } catch (err) {
        const problem = toProblem(err)
        if (problem?.status === 401) {
          return null
        }
        throw err
      }
    },
    staleTime: 5 * 60 * 1000,
    retry: false,
  })
}
