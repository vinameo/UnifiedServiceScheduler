import { useQuery } from '@tanstack/react-query'
import axios from 'axios'

export interface Health {
  status: 'ok' | 'fail'
  checks: Record<string, 'ok' | 'fail'>
}

export function useHealth() {
  return useQuery({
    queryKey: ['health', 'ready'],
    queryFn: async () => {
      // 503 still carries a body that says which check failed.
      const response = await axios.get<Health>('/health/ready', { validateStatus: () => true })
      return response.data
    },
    refetchInterval: 30_000,
  })
}
