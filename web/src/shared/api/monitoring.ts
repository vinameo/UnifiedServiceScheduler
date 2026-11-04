import { useQuery } from '@tanstack/react-query'
import { api } from './client'

export type MonitoringRange = '1h' | '24h' | '7d'

export interface ApiEventItem {
  id: number
  occurredAt: string
  endpoint: string
  code: string
  status: number | null
  correlationId: string
  details: string
}

export interface MonitoringHealth {
  app: {
    status: 'UP' | 'DOWN'
    bookingP95Ms: number
  }
  database: {
    status: 'UP' | 'DOWN'
    connectionsUsed: number
    connectionsMax: number
  }
  outbox: {
    pending: number
    oldestPendingAgeSeconds: number
  }
}

export interface MonitoringBookings {
  total: number
  confirmed: number
  noAvailability: number
  validationError: number
  error: number
  p95Ms: number
  allocationRetries: number
  idempotentReplays: number
  appointmentModified: number
}

export interface MonitoringSummary {
  range: MonitoringRange
  from: string
  to: string
  health: MonitoringHealth
  bookings: MonitoringBookings
  recentErrors: ApiEventItem[]
}

export function useMonitoringSummary(range: MonitoringRange) {
  return useQuery({
    queryKey: ['monitoring', 'summary', range],
    queryFn: async () => {
      const res = await api.get<{ data: MonitoringSummary }>('/monitoring/summary', {
        params: { range },
      })
      return res.data.data
    },
    refetchInterval: 30_000,
    retry: false,
  })
}

export function useMonitoringErrors(correlationId: string) {
  const trimmed = correlationId.trim()
  return useQuery({
    queryKey: ['monitoring', 'errors', trimmed],
    queryFn: async () => {
      const res = await api.get<{ data: ApiEventItem[]; meta?: unknown }>('/monitoring/errors', {
        params: { correlationId: trimmed },
      })
      return res.data.data
    },
    enabled: trimmed.length >= 8,
    retry: false,
  })
}
